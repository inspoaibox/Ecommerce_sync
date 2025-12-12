<?php
// 调试批次详情查询问题

require_once '../../../wp-load.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== 调试批次详情查询 ===\n\n";

// 测试批次ID
$batch_id = 'BATCH_20250903061604_1994';

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

echo "🔍 查询批次: $batch_id\n";
echo str_repeat('-', 80) . "\n";

// 1. 检查批次信息
$batch_info = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
    $batch_id
));

if ($batch_info) {
    echo "批次信息:\n";
    echo "  类型: {$batch_info->batch_type}\n";
    echo "  状态: {$batch_info->status}\n";
    echo "  成功: {$batch_info->success_count}\n";
    echo "  失败: {$batch_info->failed_count}\n";
    echo "  总数: {$batch_info->product_count}\n";
    echo "  有API响应: " . (!empty($batch_info->api_response) ? '是' : '否') . "\n";
} else {
    echo "❌ 批次不存在\n";
    exit;
}

// 2. 检查子批次
echo "\n子批次信息:\n";
$sub_batches = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, success_count, failed_count, product_count, 
            CASE WHEN api_response IS NOT NULL AND api_response != '' THEN '是' ELSE '否' END as has_api_response
     FROM $batch_feeds_table 
     WHERE parent_batch_id = %s OR batch_id LIKE %s
     ORDER BY batch_id",
    $batch_id,
    $batch_id . '%'
));

foreach ($sub_batches as $sub) {
    echo "  {$sub->batch_id}: 成功{$sub->success_count}, 失败{$sub->failed_count}, API响应:{$sub->has_api_response}\n";
}

// 3. 模拟批次详情查询的逻辑
echo "\n模拟批次详情查询:\n";

$test_types = ['success', 'failed', 'processing'];

foreach ($test_types as $type) {
    echo "\n测试类型: $type\n";
    echo str_repeat('-', 40) . "\n";
    
    // 步骤1: 查询数据库
    $status_conditions = [];
    if ($type === 'success') {
        $status_conditions = ['SUCCESS'];
    } elseif ($type === 'failed') {
        $status_conditions = ['ERROR'];
    } elseif ($type === 'processing') {
        $status_conditions = ['INPROGRESS', 'PENDING', 'PROCESSING'];
    }

    $placeholders = implode(',', array_fill(0, count($status_conditions), '%s'));
    $query_params = array_merge([$batch_id], $status_conditions);
    
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT sku, status, error_message FROM $batch_items_table
         WHERE batch_id = %s AND status IN ($placeholders)
         ORDER BY id ASC",
        ...$query_params
    ), ARRAY_A);
    
    echo "数据库查询结果: " . count($items) . " 个商品\n";
    
    // 步骤2: 检查是否需要从API响应获取
    $batch_data = $wpdb->get_row($wpdb->prepare(
        "SELECT api_response, success_count, failed_count FROM $batch_feeds_table WHERE batch_id = %s",
        $batch_id
    ));
    
    $should_use_api_response = false;
    
    if (empty($items)) {
        $should_use_api_response = true;
        echo "原因: 数据库无结果\n";
    } else {
        // 检查数据一致性
        $all_pending = true;
        foreach ($items as $item) {
            if (isset($item['status']) && $item['status'] !== 'PENDING') {
                $all_pending = false;
                break;
            }
        }
        
        if ($all_pending && $batch_data && ($batch_data->success_count > 0 || $batch_data->failed_count > 0)) {
            $should_use_api_response = true;
            echo "原因: 数据不一致\n";
        }
    }
    
    // 步骤3: 如果需要从API响应获取
    if ($should_use_api_response) {
        echo "尝试从API响应获取数据...\n";
        
        if ($batch_data && !empty($batch_data->api_response)) {
            echo "主批次有API响应\n";
            $api_response = json_decode($batch_data->api_response, true);
            // 这里应该调用 extract_items_from_api_response
            echo "API响应商品数: " . (isset($api_response['itemDetails']['itemIngestionStatus']) ? count($api_response['itemDetails']['itemIngestionStatus']) : 0) . "\n";
        } else {
            echo "主批次无API响应，检查子批次...\n";
            
            // 查询有API响应的子批次
            $sub_batches_with_api = $wpdb->get_results($wpdb->prepare(
                "SELECT batch_id, api_response FROM $batch_feeds_table
                 WHERE (parent_batch_id = %s OR batch_id LIKE %s)
                 AND api_response IS NOT NULL AND api_response != ''
                 ORDER BY batch_id",
                $batch_id,
                $batch_id . '%'
            ));
            
            echo "找到 " . count($sub_batches_with_api) . " 个有API响应的子批次\n";
            
            $total_api_items = 0;
            foreach ($sub_batches_with_api as $sub_batch) {
                $sub_api_response = json_decode($sub_batch->api_response, true);
                if ($sub_api_response && isset($sub_api_response['itemDetails']['itemIngestionStatus'])) {
                    $sub_items = $sub_api_response['itemDetails']['itemIngestionStatus'];
                    echo "  子批次 {$sub_batch->batch_id}: " . count($sub_items) . " 个商品\n";
                    $total_api_items += count($sub_items);
                }
            }
            echo "API响应总商品数: $total_api_items\n";
        }
    }
    
    echo "最终应该显示: " . ($should_use_api_response ? "API响应数据" : count($items) . "个数据库商品") . "\n";
}

echo "\n=== 调试完成 ===\n";
?>
