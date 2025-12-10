<?php
// 手动触发批次状态更新并调试更新过程

// 尝试加载WordPress
$wp_load_paths = [
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '../wp-load.php'
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!function_exists('get_option')) {
    die('请通过WordPress环境访问此脚本');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== 手动触发批次状态更新 ===\n\n";

// 目标批次ID
$target_batch_ids = [
    'BATCH_20250903061604_1994_CHUNK_1',
    'BATCH_20250903061604_1994_CHUNK_2'
];

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

foreach ($target_batch_ids as $batch_id) {
    echo "🔄 处理批次: $batch_id\n";
    echo str_repeat('-', 80) . "\n";
    
    // 1. 获取批次信息
    $batch_info = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
        $batch_id
    ));
    
    if (!$batch_info) {
        echo "❌ 批次不存在\n\n";
        continue;
    }
    
    echo "批次信息:\n";
    echo "  Feed ID: {$batch_info->feed_id}\n";
    echo "  状态: {$batch_info->status}\n";
    echo "  成功/失败: {$batch_info->success_count}/{$batch_info->failed_count}\n";
    
    if (empty($batch_info->feed_id)) {
        echo "❌ 没有Feed ID，跳过\n\n";
        continue;
    }
    
    // 2. 手动调用API获取Feed状态
    echo "\n📡 调用API获取Feed状态...\n";
    $api_auth = new Woo_Walmart_API_Key_Auth();
    $endpoint = "/v3/feeds/{$batch_info->feed_id}?includeDetails=true";
    $result = $api_auth->make_request($endpoint);
    
    if (is_wp_error($result)) {
        echo "❌ API调用失败: " . $result->get_error_message() . "\n\n";
        continue;
    }
    
    if (empty($result)) {
        echo "❌ API返回空结果\n\n";
        continue;
    }
    
    echo "✅ API调用成功\n";
    echo "Feed状态: {$result['feedStatus']}\n";
    echo "商品统计: 接收{$result['itemsReceived']} | 成功{$result['itemsSucceeded']} | 失败{$result['itemsFailed']} | 处理中{$result['itemsProcessing']}\n";
    
    // 3. 检查商品详情
    if (!isset($result['itemDetails']['itemIngestionStatus'])) {
        echo "❌ 没有商品详情数据\n\n";
        continue;
    }
    
    $item_details = $result['itemDetails']['itemIngestionStatus'];
    echo "商品详情数量: " . count($item_details) . "\n";
    
    // 4. 获取批次商品列表
    $batch_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $batch_items_table WHERE batch_id = %s",
        $batch_id
    ));
    
    echo "数据库商品数量: " . count($batch_items) . "\n";
    
    // 5. 手动更新商品状态
    if (isset($_GET['update']) && $_GET['update'] === '1') {
        echo "\n🔄 开始更新商品状态...\n";
        
        // 创建SKU到详情的映射
        $item_details_map = [];
        foreach ($item_details as $detail) {
            if (isset($detail['sku'])) {
                $item_details_map[$detail['sku']] = $detail;
            }
        }
        
        $updated_count = 0;
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($batch_items as $item) {
            $sku = $item->sku;
            
            if (!isset($item_details_map[$sku])) {
                echo "  ⚠️ SKU $sku 在API响应中未找到\n";
                continue;
            }
            
            $detail = $item_details_map[$sku];
            $ingestion_status = $detail['ingestionStatus'] ?? 'UNKNOWN';
            
            // 映射状态
            $item_status = 'INPROGRESS';
            $error_message = null;
            
            switch ($ingestion_status) {
                case 'SUCCESS':
                    $item_status = 'SUCCESS';
                    $success_count++;
                    break;
                case 'DATA_ERROR':
                case 'SYSTEM_ERROR':
                case 'TIMEOUT_ERROR':
                case 'ERROR':
                    $item_status = 'ERROR';
                    $failed_count++;
                    $error_message = isset($detail['ingestionErrors']) ? wp_json_encode($detail['ingestionErrors']) : '处理失败';
                    break;
                case 'INPROGRESS':
                    $item_status = 'INPROGRESS';
                    break;
            }
            
            // 更新数据库
            $update_result = $wpdb->update(
                $batch_items_table,
                [
                    'status' => $item_status,
                    'error_message' => $error_message,
                    'processed_at' => current_time('mysql')
                ],
                [
                    'batch_id' => $batch_id,
                    'sku' => $sku
                ]
            );
            
            if ($update_result !== false) {
                $updated_count++;
                echo "  ✅ SKU $sku: $ingestion_status → $item_status\n";
            } else {
                echo "  ❌ SKU $sku: 更新失败\n";
            }
        }
        
        echo "\n更新统计:\n";
        echo "  总更新: $updated_count 个商品\n";
        echo "  成功: $success_count 个\n";
        echo "  失败: $failed_count 个\n";
        echo "  处理中: " . ($updated_count - $success_count - $failed_count) . " 个\n";
        
        // 更新批次统计
        $batch_update_result = $wpdb->update(
            $batch_feeds_table,
            [
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'updated_at' => current_time('mysql')
            ],
            ['batch_id' => $batch_id]
        );
        
        if ($batch_update_result !== false) {
            echo "  ✅ 批次统计已更新\n";
        } else {
            echo "  ❌ 批次统计更新失败\n";
        }
        
    } else {
        echo "\n💡 添加 ?update=1 参数来执行状态更新\n";
    }
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}

echo "=== 完成 ===\n";
?>
