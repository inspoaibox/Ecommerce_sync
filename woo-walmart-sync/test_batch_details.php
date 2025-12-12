<?php
// 测试批次详情查询修复效果

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

echo "=== 测试批次详情查询修复效果 ===\n\n";

// 目标批次ID
$batch_id = 'BATCH_20250903061604_1994';

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

// 1. 检查批次统计信息
echo "1. 批次统计信息:\n";
$batch_info = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
    $batch_id
));

if ($batch_info) {
    echo "  批次ID: {$batch_info->batch_id}\n";
    echo "  总商品数: {$batch_info->product_count}\n";
    echo "  成功数: {$batch_info->success_count}\n";
    echo "  失败数: {$batch_info->failed_count}\n";
    echo "  处理中: " . ($batch_info->product_count - $batch_info->success_count - $batch_info->failed_count) . "\n";
    echo "  状态: {$batch_info->status}\n";
} else {
    echo "  ❌ 批次不存在\n";
    exit;
}

// 2. 检查数据库中的商品状态
echo "\n2. 数据库商品状态统计:\n";
$db_status_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT status, COUNT(*) as count FROM $batch_items_table 
     WHERE batch_id = %s 
     GROUP BY status 
     ORDER BY count DESC",
    $batch_id
));

foreach ($db_status_stats as $stat) {
    echo "  {$stat->status}: {$stat->count} 个商品\n";
}

// 3. 测试新的批次详情查询逻辑
echo "\n3. 测试批次详情查询:\n";

$test_types = ['success', 'failed', 'processing'];

foreach ($test_types as $type) {
    echo "\n测试类型: $type\n";
    echo str_repeat('-', 40) . "\n";
    
    // 模拟AJAX请求的逻辑
    $_POST = [
        'batch_id' => $batch_id,
        'type' => $type,
        'nonce' => wp_create_nonce('batch_details_nonce')
    ];
    
    // 调用批次详情查询逻辑
    ob_start();
    
    try {
        // 复制handle_get_batch_details函数的核心逻辑
        $status_conditions = [];
        if ($type === 'success') {
            $status_conditions = ['SUCCESS'];
        } elseif ($type === 'failed') {
            $status_conditions = ['ERROR'];
        } elseif ($type === 'processing') {
            $status_conditions = ['INPROGRESS', 'PENDING', 'PROCESSING'];
        }

        // 查询数据库
        $placeholders = implode(',', array_fill(0, count($status_conditions), '%s'));
        $query_params = array_merge([$batch_id], $status_conditions);
        
        if ($type === 'success') {
            $skus = $wpdb->get_col($wpdb->prepare(
                "SELECT sku FROM $batch_items_table
                 WHERE batch_id = %s AND status IN ($placeholders)
                 ORDER BY id ASC",
                ...$query_params
            ));
            $items = array_map(function($sku) {
                return ['sku' => $sku];
            }, $skus);
        } else {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT sku, error_message FROM $batch_items_table
                 WHERE batch_id = %s AND status IN ($placeholders)
                 ORDER BY id ASC",
                ...$query_params
            ), ARRAY_A);
        }

        echo "数据库查询结果: " . count($items) . " 个商品\n";

        // 检查是否需要从API响应获取数据
        $batch_data = $wpdb->get_row($wpdb->prepare(
            "SELECT api_response, success_count, failed_count, status FROM $batch_feeds_table WHERE batch_id = %s",
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
                echo "原因: 数据不一致（所有商品都是PENDING但有统计数据）\n";
            }
        }
        
        if ($should_use_api_response && $batch_data) {
            echo "尝试从API响应获取数据...\n";
            
            if (!empty($batch_data->api_response)) {
                $api_response = json_decode($batch_data->api_response, true);
                $api_items = extract_items_from_api_response($api_response, $type);
                echo "主批次API响应: " . count($api_items) . " 个商品\n";
                $items = $api_items;
            } else {
                // 检查子批次
                $sub_batches = $wpdb->get_results($wpdb->prepare(
                    "SELECT batch_id, api_response FROM $batch_feeds_table
                     WHERE (parent_batch_id = %s OR batch_id LIKE %s)
                     AND api_response IS NOT NULL AND api_response != ''
                     ORDER BY batch_id",
                    $batch_id,
                    $batch_id . '%'
                ));
                
                if (!empty($sub_batches)) {
                    echo "从子批次获取数据...\n";
                    $items = [];
                    foreach ($sub_batches as $sub_batch) {
                        $sub_api_response = json_decode($sub_batch->api_response, true);
                        if ($sub_api_response) {
                            $sub_items = extract_items_from_api_response($sub_api_response, $type);
                            $items = array_merge($items, $sub_items);
                            echo "  子批次 {$sub_batch->batch_id}: " . count($sub_items) . " 个商品\n";
                        }
                    }
                }
            }
        }
        
        echo "最终结果: " . count($items) . " 个商品\n";
        
        if (!empty($items)) {
            echo "前5个商品:\n";
            $sample_items = array_slice($items, 0, 5);
            foreach ($sample_items as $i => $item) {
                echo "  " . ($i + 1) . ". SKU: {$item['sku']}";
                if (isset($item['error_message']) && !empty($item['error_message'])) {
                    echo " | 错误: " . substr($item['error_message'], 0, 50) . "...";
                }
                echo "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 测试失败: " . $e->getMessage() . "\n";
    }
    
    ob_end_clean();
}

echo "\n=== 测试完成 ===\n";
?>
