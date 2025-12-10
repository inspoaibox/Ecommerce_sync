<?php
// 检查队列管理页面批次状态问题

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

echo "=== 队列状态诊断 ===\n\n";

// 首先显示所有活跃批次的概览
echo "📋 所有活跃批次概览:\n";
$all_batches = $wpdb->get_results(
    "SELECT batch_id, status, batch_type, product_count, success_count, failed_count, created_at, updated_at
     FROM $batch_feeds_table
     WHERE status IN ('PENDING', 'SUBMITTED', 'PROCESSING', 'PARTIAL_SUBMITTED')
     ORDER BY created_at DESC
     LIMIT 20"
);

if (empty($all_batches)) {
    echo "  ⚠️ 没有找到活跃的批次\n";
} else {
    foreach ($all_batches as $batch) {
        $age = time() - strtotime($batch->created_at);
        $age_str = $age > 3600 ? round($age/3600, 1) . '小时' : round($age/60) . '分钟';
        echo "  {$batch->batch_id} | {$batch->status} | {$batch->batch_type} | {$batch->product_count}商品 | 成功:{$batch->success_count} 失败:{$batch->failed_count} | {$age_str}前\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n\n";

// 从页面显示的批次ID列表（简写形式）
$display_batch_ids = [
    '505_1167',
    '352_3074',
    '244_3053',
    '603_3351',
    '850_1683',
    '312_7247',
    '753_9188',
    '407_2594',
    '636_9643'
];

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

// 首先获取所有活跃的批次，然后匹配简写ID
echo "🔍 查找匹配的完整批次ID...\n";
$all_active_batches = $wpdb->get_results(
    "SELECT batch_id, status, created_at FROM $batch_feeds_table
     WHERE status IN ('PENDING', 'SUBMITTED', 'PROCESSING', 'PARTIAL_SUBMITTED')
     ORDER BY created_at DESC"
);

$matched_batches = [];
foreach ($display_batch_ids as $display_id) {
    foreach ($all_active_batches as $batch) {
        if (strpos($batch->batch_id, $display_id) !== false) {
            $matched_batches[$display_id] = $batch->batch_id;
            echo "  ✅ $display_id -> {$batch->batch_id}\n";
            break;
        }
    }
    if (!isset($matched_batches[$display_id])) {
        echo "  ❌ $display_id -> 未找到匹配的完整批次ID\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n\n";

foreach ($display_batch_ids as $display_id) {
    if (!isset($matched_batches[$display_id])) {
        echo "⚠️ 跳过未匹配的批次: $display_id\n\n";
        continue;
    }

    $batch_id = $matched_batches[$display_id];
    echo "🔍 检查批次: $display_id (完整ID: $batch_id)\n";
    echo str_repeat('-', 60) . "\n";

    // 1. 检查批次基本信息
    $batch_info = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
        $batch_id
    ));
    
    if (!$batch_info) {
        echo "❌ 批次不存在于数据库中\n\n";
        continue;
    }
    
    echo "批次基本信息:\n";
    echo "  状态: {$batch_info->status}\n";
    echo "  同步方式: {$batch_info->sync_method}\n";
    echo "  批次类型: {$batch_info->batch_type}\n";
    echo "  商品数量: {$batch_info->product_count}\n";
    echo "  Feed ID: " . ($batch_info->feed_id ?: '无') . "\n";
    echo "  提交时间: " . ($batch_info->submitted_at ?: '未提交') . "\n";
    echo "  完成时间: " . ($batch_info->completed_at ?: '未完成') . "\n";
    echo "  成功数量: {$batch_info->success_count}\n";
    echo "  失败数量: {$batch_info->failed_count}\n";
    echo "  创建时间: {$batch_info->created_at}\n";
    echo "  更新时间: {$batch_info->updated_at}\n";
    
    // 2. 检查子批次（如果是主批次）
    if ($batch_info->batch_type === 'master') {
        echo "\n子批次信息:\n";
        $sub_batches = $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id, status, feed_id, success_count, failed_count, submitted_at, completed_at 
             FROM $batch_feeds_table 
             WHERE parent_batch_id = %s 
             ORDER BY chunk_index ASC",
            $batch_id
        ));
        
        if (empty($sub_batches)) {
            echo "  ❌ 没有找到子批次\n";
        } else {
            foreach ($sub_batches as $i => $sub_batch) {
                echo "  子批次 " . ($i + 1) . ":\n";
                echo "    ID: {$sub_batch->batch_id}\n";
                echo "    状态: {$sub_batch->status}\n";
                echo "    Feed ID: " . ($sub_batch->feed_id ?: '无') . "\n";
                echo "    成功/失败: {$sub_batch->success_count}/{$sub_batch->failed_count}\n";
                echo "    提交时间: " . ($sub_batch->submitted_at ?: '未提交') . "\n";
                echo "    完成时间: " . ($sub_batch->completed_at ?: '未完成') . "\n";
            }
        }
    }
    
    // 3. 检查批次商品详情
    echo "\n批次商品状态统计:\n";
    $item_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count 
         FROM $batch_items_table 
         WHERE batch_id = %s 
         GROUP BY status",
        $batch_id
    ));
    
    if (empty($item_stats)) {
        echo "  ❌ 没有找到批次商品记录\n";
    } else {
        foreach ($item_stats as $stat) {
            echo "  {$stat->status}: {$stat->count} 个商品\n";
        }
    }
    
    // 4. 检查Feed状态（如果有Feed ID）
    if (!empty($batch_info->feed_id)) {
        echo "\nFeed状态检查:\n";
        echo "  Feed ID: {$batch_info->feed_id}\n";
        
        // 检查是否有API响应数据
        if (!empty($batch_info->api_response)) {
            $api_response = json_decode($batch_info->api_response, true);
            if ($api_response) {
                echo "  API响应状态: " . ($api_response['feedStatus'] ?? '未知') . "\n";
                if (isset($api_response['itemsReceived'])) {
                    echo "  接收商品数: {$api_response['itemsReceived']}\n";
                }
                if (isset($api_response['itemsSucceeded'])) {
                    echo "  成功商品数: {$api_response['itemsSucceeded']}\n";
                }
                if (isset($api_response['itemsFailed'])) {
                    echo "  失败商品数: {$api_response['itemsFailed']}\n";
                }
                if (isset($api_response['itemsProcessing'])) {
                    echo "  处理中商品数: {$api_response['itemsProcessing']}\n";
                }
            } else {
                echo "  ❌ API响应数据格式错误\n";
            }
        } else {
            echo "  ⚠️ 没有API响应数据\n";
        }
    }
    
    // 5. 检查最近的相关日志
    echo "\n最近相关日志:\n";
    $logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
    $recent_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT created_at, action, status, response 
         FROM $logs_table 
         WHERE request LIKE %s OR response LIKE %s 
         ORDER BY created_at DESC 
         LIMIT 3",
        '%' . $batch_id . '%',
        '%' . $batch_id . '%'
    ));
    
    if (empty($recent_logs)) {
        echo "  ⚠️ 没有找到相关日志\n";
    } else {
        foreach ($recent_logs as $log) {
            echo "  [{$log->created_at}] {$log->action} - {$log->status}\n";
            if (strlen($log->response) > 100) {
                echo "    响应: " . substr($log->response, 0, 100) . "...\n";
            } else {
                echo "    响应: {$log->response}\n";
            }
        }
    }
    
    // 6. 分析可能的问题
    echo "\n问题分析:\n";
    $issues = [];
    
    if ($batch_info->status === 'SUBMITTED' && empty($batch_info->feed_id)) {
        $issues[] = "批次状态为SUBMITTED但没有Feed ID";
    }
    
    if ($batch_info->status === 'SUBMITTED' && empty($batch_info->api_response)) {
        $issues[] = "批次已提交但没有API响应数据";
    }
    
    if ($batch_info->batch_type === 'master' && empty($sub_batches)) {
        $issues[] = "主批次没有子批次";
    }
    
    if (empty($item_stats)) {
        $issues[] = "批次没有商品记录";
    }
    
    $time_diff = time() - strtotime($batch_info->created_at);
    if ($time_diff > 3600 && $batch_info->status === 'PENDING') { // 超过1小时还是PENDING
        $issues[] = "批次创建超过1小时仍为PENDING状态";
    }
    
    if ($time_diff > 7200 && in_array($batch_info->status, ['SUBMITTED', 'PROCESSING'])) { // 超过2小时还在处理
        $issues[] = "批次处理时间过长（超过2小时）";
    }
    
    if (empty($issues)) {
        echo "  ✅ 未发现明显问题\n";
    } else {
        foreach ($issues as $issue) {
            echo "  ⚠️ $issue\n";
        }
    }
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}

// 检查定时任务状态
echo "=== 定时任务检查 ===\n";
$next_feed_check = wp_next_scheduled('walmart_check_feed_status_hook');
if ($next_feed_check) {
    echo "下次Feed状态检查: " . date('Y-m-d H:i:s', $next_feed_check) . "\n";
} else {
    echo "❌ Feed状态检查定时任务未设置\n";
}

// 检查Action Scheduler（如果存在）
if (function_exists('as_next_scheduled_action')) {
    $next_action = as_next_scheduled_action('walmart_check_feed_status_hook');
    if ($next_action) {
        echo "Action Scheduler下次执行: " . date('Y-m-d H:i:s', $next_action) . "\n";
    }
}

echo "\n=== 完成 ===\n";
?>
