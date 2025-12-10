<?php
// 检查API响应的完整性

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

echo "=== API响应完整性检查 ===\n\n";

// 目标子批次
$sub_batches = [
    'BATCH_20250903061604_1994_CHUNK_1',
    'BATCH_20250903061604_1994_CHUNK_2'
];

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

foreach ($sub_batches as $batch_id) {
    echo "🔍 检查子批次: $batch_id\n";
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
    
    echo "Feed ID: {$batch_info->feed_id}\n";
    echo "商品数量: {$batch_info->product_count}\n";
    echo "API统计: 成功{$batch_info->success_count}, 失败{$batch_info->failed_count}\n";
    
    if (empty($batch_info->api_response)) {
        echo "❌ 无API响应数据\n\n";
        continue;
    }
    
    // 2. 解析API响应
    $api_response = json_decode($batch_info->api_response, true);
    if (!$api_response) {
        echo "❌ API响应解析失败\n\n";
        continue;
    }
    
    // 3. 检查API响应结构
    echo "\nAPI响应结构:\n";
    echo "  feedStatus: " . ($api_response['feedStatus'] ?? '未知') . "\n";
    echo "  itemsReceived: " . ($api_response['itemsReceived'] ?? 0) . "\n";
    echo "  itemsSucceeded: " . ($api_response['itemsSucceeded'] ?? 0) . "\n";
    echo "  itemsFailed: " . ($api_response['itemsFailed'] ?? 0) . "\n";
    echo "  itemsProcessing: " . ($api_response['itemsProcessing'] ?? 0) . "\n";
    
    // 4. 检查商品详情
    if (isset($api_response['itemDetails']['itemIngestionStatus'])) {
        $item_details = $api_response['itemDetails']['itemIngestionStatus'];
        echo "  itemDetails数量: " . count($item_details) . "\n";
        
        // 统计状态
        $status_counts = [];
        foreach ($item_details as $item) {
            $status = $item['ingestionStatus'] ?? '未知';
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
        }
        
        echo "  详细状态统计:\n";
        foreach ($status_counts as $status => $count) {
            echo "    $status: $count 个\n";
        }
        
        // 5. 检查是否有分页信息
        echo "\n分页信息检查:\n";
        $pagination_fields = ['offset', 'limit', 'totalCount', 'hasMore', 'nextOffset'];
        $has_pagination = false;
        
        foreach ($pagination_fields as $field) {
            if (isset($api_response[$field])) {
                echo "  $field: " . $api_response[$field] . "\n";
                $has_pagination = true;
            }
        }
        
        if (!$has_pagination) {
            echo "  ❌ 未发现分页信息\n";
        }
        
        // 6. 数据完整性分析
        echo "\n数据完整性分析:\n";
        $expected_items = $batch_info->product_count;
        $api_total_items = ($api_response['itemsReceived'] ?? 0);
        $detail_items = count($item_details);
        
        echo "  预期商品数: $expected_items\n";
        echo "  API接收数: $api_total_items\n";
        echo "  详情数量: $detail_items\n";
        
        if ($detail_items < $api_total_items) {
            echo "  ⚠️ 商品详情不完整！详情数量($detail_items) < API接收数($api_total_items)\n";
            echo "  可能原因: API响应被截断或需要分页获取\n";
        } elseif ($detail_items == $api_total_items) {
            echo "  ✅ 商品详情完整\n";
        } else {
            echo "  ❓ 商品详情数量异常\n";
        }
        
    } else {
        echo "  ❌ 无商品详情数据\n";
    }
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}

// 7. 重新获取完整的API响应
if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
    echo "🔄 重新获取完整的API响应...\n\n";
    
    foreach ($sub_batches as $batch_id) {
        echo "刷新批次: $batch_id\n";
        
        $batch_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
            $batch_id
        ));
        
        if (!$batch_info || empty($batch_info->feed_id)) {
            echo "  ❌ 批次无Feed ID\n";
            continue;
        }
        
        // 调用API获取完整的Feed状态
        $api_auth = new Woo_Walmart_API_Key_Auth();
        $endpoint = "/v3/feeds/{$batch_info->feed_id}?includeDetails=true&limit=1000&offset=0";
        $result = $api_auth->make_request($endpoint);
        
        if (is_wp_error($result)) {
            echo "  ❌ API调用失败: " . $result->get_error_message() . "\n";
            continue;
        }
        
        if (empty($result)) {
            echo "  ❌ API返回空结果\n";
            continue;
        }
        
        // 更新API响应到数据库
        $update_result = $wpdb->update(
            $batch_feeds_table,
            [
                'api_response' => wp_json_encode($result),
                'success_count' => $result['itemsSucceeded'] ?? 0,
                'failed_count' => $result['itemsFailed'] ?? 0,
                'updated_at' => current_time('mysql')
            ],
            ['batch_id' => $batch_id]
        );
        
        if ($update_result !== false) {
            $item_count = isset($result['itemDetails']['itemIngestionStatus']) ? count($result['itemDetails']['itemIngestionStatus']) : 0;
            echo "  ✅ API响应已更新，商品详情数量: $item_count\n";
        } else {
            echo "  ❌ API响应更新失败\n";
        }
    }
    
    echo "\n💡 请重新运行 fix_item_status_sync.php 来更新商品状态\n";
} else {
    echo "💡 添加 ?refresh=1 参数来重新获取完整的API响应\n";
}

echo "\n=== 检查完成 ===\n";
?>
