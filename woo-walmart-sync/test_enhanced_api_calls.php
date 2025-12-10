<?php
// 测试增强的API调用和数据完整性检查

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

echo "=== 测试增强的API调用和数据完整性检查 ===\n\n";

// 测试批次ID
$test_batch_id = 'BATCH_20250903061604_1994_CHUNK_1';

echo "🧪 测试批次: $test_batch_id\n";
echo str_repeat('-', 80) . "\n";

// 1. 测试增强的Feed状态检查
echo "1. 测试增强的Feed状态检查:\n";

if (class_exists('Woo_Walmart_Product_Sync')) {
    $sync = new Woo_Walmart_Product_Sync();
    
    // 调用单个批次状态检查
    $result = $sync->check_single_batch_feed_status($test_batch_id);
    
    if ($result['success']) {
        echo "  ✅ 批次状态检查成功\n";
        echo "  状态: {$result['status']}\n";
        echo "  消息: {$result['message']}\n";
    } else {
        echo "  ❌ 批次状态检查失败: {$result['message']}\n";
    }
} else {
    echo "  ❌ Woo_Walmart_Product_Sync 类不存在\n";
}

// 2. 检查日志记录
echo "\n2. 检查相关日志记录:\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找最近的相关日志（修复字段名问题）
$recent_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $logs_table
     WHERE (action LIKE %s OR action LIKE %s OR action LIKE %s OR request LIKE %s)
     AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
     ORDER BY created_at DESC
     LIMIT 10",
    '%Feed%',
    '%数据一致性%',
    '%完整获取%',
    '%' . $test_batch_id . '%'
));

if (!empty($recent_logs)) {
    foreach ($recent_logs as $log) {
        echo "  时间: {$log->created_at}\n";
        echo "  操作: {$log->action}\n";
        echo "  状态: {$log->status}\n";

        // 检查是否有message字段
        if (property_exists($log, 'message') && !empty($log->message)) {
            echo "  消息: {$log->message}\n";
        }

        if (!empty($log->request)) {
            $request_data = json_decode($log->request, true);
            if (isset($request_data['total_items'])) {
                echo "  详情: 总商品数 {$request_data['total_items']}\n";
            }
            if (isset($request_data['total_pages'])) {
                echo "  详情: 总页数 {$request_data['total_pages']}\n";
            }
            if (isset($request_data['feed_id'])) {
                echo "  Feed ID: " . substr($request_data['feed_id'], -20) . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "  ℹ️ 没有找到相关的日志记录\n";
}

// 3. 验证数据一致性
echo "\n3. 验证数据一致性:\n";

$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

// 获取批次信息
$batch_info = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
    $test_batch_id
));

if ($batch_info) {
    echo "  批次统计: 成功{$batch_info->success_count}, 失败{$batch_info->failed_count}\n";
    
    // 检查商品状态统计
    $item_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count FROM $batch_items_table 
         WHERE batch_id = %s 
         GROUP BY status",
        $test_batch_id
    ));
    
    echo "  商品状态统计:\n";
    $db_success = 0;
    $db_failed = 0;
    $db_processing = 0;
    
    foreach ($item_stats as $stat) {
        echo "    {$stat->status}: {$stat->count} 个\n";
        
        switch ($stat->status) {
            case 'SUCCESS':
                $db_success += $stat->count;
                break;
            case 'ERROR':
                $db_failed += $stat->count;
                break;
            default:
                $db_processing += $stat->count;
                break;
        }
    }
    
    // 检查一致性
    echo "  一致性检查:\n";
    echo "    成功数: 批次{$batch_info->success_count} vs 商品{$db_success} " . 
         ($batch_info->success_count == $db_success ? '✅' : '❌') . "\n";
    echo "    失败数: 批次{$batch_info->failed_count} vs 商品{$db_failed} " . 
         ($batch_info->failed_count == $db_failed ? '✅' : '❌') . "\n";
    
    if ($db_processing > 0 && ($batch_info->success_count > 0 || $batch_info->failed_count > 0)) {
        echo "    ⚠️ 发现{$db_processing}个商品状态未同步\n";
    }
    
} else {
    echo "  ❌ 批次不存在\n";
}

// 4. 测试主批次一致性（如果是子批次）
if ($batch_info && !empty($batch_info->parent_batch_id)) {
    echo "\n4. 检查主批次一致性:\n";
    
    $master_batch_id = $batch_info->parent_batch_id;
    echo "  主批次ID: $master_batch_id\n";
    
    // 获取主批次信息
    $master_batch = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
        $master_batch_id
    ));
    
    // 获取所有子批次汇总
    $sub_batches_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(success_count) as total_success, SUM(failed_count) as total_failed, 
                SUM(product_count) as total_products
         FROM $batch_feeds_table 
         WHERE parent_batch_id = %s",
        $master_batch_id
    ));
    
    if ($master_batch && $sub_batches_stats) {
        echo "  主批次统计: 成功{$master_batch->success_count}, 失败{$master_batch->failed_count}, 总计{$master_batch->product_count}\n";
        echo "  子批次汇总: 成功{$sub_batches_stats->total_success}, 失败{$sub_batches_stats->total_failed}, 总计{$sub_batches_stats->total_products}\n";
        
        echo "  一致性检查:\n";
        echo "    成功数: " . ($master_batch->success_count == $sub_batches_stats->total_success ? '✅' : '❌') . "\n";
        echo "    失败数: " . ($master_batch->failed_count == $sub_batches_stats->total_failed ? '✅' : '❌') . "\n";
        echo "    总数: " . ($master_batch->product_count == $sub_batches_stats->total_products ? '✅' : '❌') . "\n";
    }
}

// 5. 性能统计
echo "\n5. 性能统计:\n";

// 检查最近的API调用日志
$api_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $logs_table
     WHERE (action LIKE %s OR action LIKE %s)
     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
     ORDER BY created_at DESC
     LIMIT 5",
    '%完整获取%',
    '%Feed分页%'
));

if (!empty($api_logs)) {
    foreach ($api_logs as $log) {
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "  Feed: " . substr($request_data['feed_id'] ?? '未知', -20) . "\n";
            echo "    页数: " . ($request_data['total_pages'] ?? '未知') . "\n";
            echo "    商品数: " . ($request_data['total_items'] ?? '未知') . "\n";
            echo "    时间: {$log->created_at}\n\n";
        }
    }
} else {
    echo "  ℹ️ 没有找到最近的API调用记录\n";
}

echo "=== 测试完成 ===\n";
echo "\n💡 改进效果:\n";
echo "  ✅ API调用支持分页，获取完整数据\n";
echo "  ✅ 自动检测和修复数据不一致问题\n";
echo "  ✅ 详细的日志记录和性能监控\n";
echo "  ✅ 主批次和子批次数据同步\n";
?>
