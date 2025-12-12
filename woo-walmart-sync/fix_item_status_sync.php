<?php
// 修复商品状态同步问题

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

echo "=== 修复商品状态同步 ===\n\n";

// 目标子批次
$sub_batches = [
    'BATCH_20250903061604_1994_CHUNK_1',
    'BATCH_20250903061604_1994_CHUNK_2'
];

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

foreach ($sub_batches as $batch_id) {
    echo "🔄 处理子批次: $batch_id\n";
    echo str_repeat('-', 80) . "\n";
    
    // 1. 获取批次信息
    $batch_info = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
        $batch_id
    ));
    
    if (!$batch_info || empty($batch_info->api_response)) {
        echo "❌ 批次不存在或无API响应数据\n\n";
        continue;
    }
    
    // 2. 解析API响应
    $api_response = json_decode($batch_info->api_response, true);
    if (!$api_response || !isset($api_response['itemDetails']['itemIngestionStatus'])) {
        echo "❌ API响应数据格式错误\n\n";
        continue;
    }
    
    $item_details = $api_response['itemDetails']['itemIngestionStatus'];
    echo "API响应包含 " . count($item_details) . " 个商品详情\n";
    
    // 3. 获取批次商品列表
    $batch_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $batch_items_table WHERE batch_id = %s",
        $batch_id
    ));
    
    echo "数据库包含 " . count($batch_items) . " 个商品记录\n";
    
    // 4. 创建SKU到详情的映射
    $item_details_map = [];
    foreach ($item_details as $detail) {
        if (isset($detail['sku'])) {
            $item_details_map[$detail['sku']] = $detail;
        }
    }
    
    // 5. 更新商品状态
    $updated_count = 0;
    $success_count = 0;
    $failed_count = 0;
    $processing_count = 0;
    
    foreach ($batch_items as $item) {
        $sku = $item->sku;
        
        if (!isset($item_details_map[$sku])) {
            echo "  ⚠️ SKU $sku 在API响应中未找到\n";
            continue;
        }
        
        $detail = $item_details_map[$sku];
        $ingestion_status = $detail['ingestionStatus'] ?? 'UNKNOWN';
        
        // 映射状态（根据沃尔玛官方API文档）
        $item_status = 'INPROGRESS';
        $error_message = null;
        $wpid = null;
        
        // 提取WPID
        if (isset($detail['wpid']) && !empty($detail['wpid'])) {
            $wpid = $detail['wpid'];
        }
        
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
                $processing_count++;
                break;
            default:
                echo "  ⚠️ SKU $sku 未知状态: $ingestion_status\n";
                $processing_count++;
                break;
        }
        
        // 更新数据库
        $update_data = [
            'status' => $item_status,
            'error_message' => $error_message,
            'processed_at' => current_time('mysql')
        ];
        
        $update_result = $wpdb->update(
            $batch_items_table,
            $update_data,
            [
                'batch_id' => $batch_id,
                'sku' => $sku
            ]
        );
        
        if ($update_result !== false) {
            $updated_count++;
            if ($updated_count <= 5) { // 只显示前5个
                echo "  ✅ SKU $sku: $ingestion_status → $item_status\n";
            }
        } else {
            echo "  ❌ SKU $sku: 更新失败\n";
        }
        
        // 更新WPID到feeds表
        if ($wpid && $item_status === 'SUCCESS') {
            $feeds_table = $wpdb->prefix . 'walmart_feeds';
            $wpdb->update(
                $feeds_table,
                ['wpid' => $wpid],
                ['sku' => $sku]
            );
        }
    }
    
    echo "\n更新统计:\n";
    echo "  总更新: $updated_count 个商品\n";
    echo "  成功: $success_count 个\n";
    echo "  失败: $failed_count 个\n";
    echo "  处理中: $processing_count 个\n";
    
    // 6. 更新批次统计（确保与API数据一致）
    $api_success = $api_response['itemsSucceeded'] ?? 0;
    $api_failed = $api_response['itemsFailed'] ?? 0;
    
    $batch_update_result = $wpdb->update(
        $batch_feeds_table,
        [
            'success_count' => $api_success,
            'failed_count' => $api_failed,
            'updated_at' => current_time('mysql')
        ],
        ['batch_id' => $batch_id]
    );
    
    if ($batch_update_result !== false) {
        echo "  ✅ 批次统计已更新 (API数据: 成功$api_success, 失败$api_failed)\n";
    } else {
        echo "  ❌ 批次统计更新失败\n";
    }
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}

// 7. 更新主批次状态
echo "🔄 更新主批次状态...\n";

$main_batch_id = 'BATCH_20250903061604_1994';

// 重新计算主批次统计
$sub_batch_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT SUM(success_count) as total_success, SUM(failed_count) as total_failed, SUM(product_count) as total_products
     FROM $batch_feeds_table 
     WHERE parent_batch_id = %s",
    $main_batch_id
));

if ($sub_batch_stats && $sub_batch_stats[0]) {
    $total_success = $sub_batch_stats[0]->total_success;
    $total_failed = $sub_batch_stats[0]->total_failed;
    $total_products = $sub_batch_stats[0]->total_products;
    
    $main_update_result = $wpdb->update(
        $batch_feeds_table,
        [
            'success_count' => $total_success,
            'failed_count' => $total_failed,
            'updated_at' => current_time('mysql')
        ],
        ['batch_id' => $main_batch_id]
    );
    
    if ($main_update_result !== false) {
        echo "✅ 主批次统计已更新: 成功$total_success, 失败$total_failed\n";
    } else {
        echo "❌ 主批次统计更新失败\n";
    }
}

echo "\n=== 修复完成 ===\n";
echo "💡 请刷新队列管理页面查看修复结果\n";
?>
