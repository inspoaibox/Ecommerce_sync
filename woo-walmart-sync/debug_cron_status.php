<?php
// 检查定时任务的具体状态和执行情况

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

echo "=== WordPress定时任务诊断 ===\n\n";

// 1. 检查WP-Cron是否被禁用
echo "1. WP-Cron系统状态:\n";
if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
    echo "  ❌ WP-Cron被禁用 (DISABLE_WP_CRON = true)\n";
} else {
    echo "  ✅ WP-Cron已启用\n";
}

// 2. 检查自定义cron间隔是否注册
echo "\n2. 自定义Cron间隔:\n";
$schedules = wp_get_schedules();
if (isset($schedules['every_five_minutes'])) {
    echo "  ✅ every_five_minutes 间隔已注册 (间隔: {$schedules['every_five_minutes']['interval']}秒)\n";
} else {
    echo "  ❌ every_five_minutes 间隔未注册\n";
}

// 3. 检查Walmart定时任务注册状态
echo "\n3. Walmart定时任务状态:\n";
$next_walmart_check = wp_next_scheduled('walmart_check_feed_status_hook');
if ($next_walmart_check) {
    echo "  ✅ walmart_check_feed_status_hook 已注册\n";
    echo "  下次执行时间: " . date('Y-m-d H:i:s', $next_walmart_check) . "\n";
    echo "  距离现在: " . ($next_walmart_check - time()) . " 秒\n";
} else {
    echo "  ❌ walmart_check_feed_status_hook 未注册\n";
}

$next_daily_stats = wp_next_scheduled('walmart_daily_stats_update');
if ($next_daily_stats) {
    echo "  ✅ walmart_daily_stats_update 已注册\n";
    echo "  下次执行时间: " . date('Y-m-d H:i:s', $next_daily_stats) . "\n";
} else {
    echo "  ❌ walmart_daily_stats_update 未注册\n";
}

// 4. 检查所有定时任务
echo "\n4. 所有已注册的定时任务:\n";
$cron_jobs = _get_cron_array();
$walmart_jobs = [];
$total_jobs = 0;

foreach ($cron_jobs as $timestamp => $jobs) {
    foreach ($jobs as $hook => $job_array) {
        $total_jobs++;
        if (strpos($hook, 'walmart') !== false) {
            $walmart_jobs[] = [
                'hook' => $hook,
                'timestamp' => $timestamp,
                'time_str' => date('Y-m-d H:i:s', $timestamp),
                'args' => $job_array
            ];
        }
    }
}

echo "  总定时任务数: $total_jobs\n";
echo "  Walmart相关任务数: " . count($walmart_jobs) . "\n";

if (!empty($walmart_jobs)) {
    foreach ($walmart_jobs as $job) {
        echo "    - {$job['hook']} -> {$job['time_str']}\n";
    }
} else {
    echo "  ❌ 没有找到Walmart相关的定时任务\n";
}

// 5. 检查定时任务执行计数器
echo "\n5. 定时任务执行状态:\n";
$feed_check_counter = get_option('walmart_feed_check_counter', 0);
echo "  Feed检查计数器: $feed_check_counter (每10次执行一次Feed状态检查)\n";

// 6. 检查同步队列
echo "\n6. 同步队列状态:\n";
$sync_queue = get_option('walmart_sync_queue', []);
echo "  队列中商品数: " . count($sync_queue) . "\n";
if (!empty($sync_queue)) {
    echo "  队列前5个商品ID: " . implode(', ', array_slice($sync_queue, 0, 5)) . "\n";
}

// 7. 手动触发定时任务测试
echo "\n7. 手动触发测试:\n";
if (isset($_GET['test_cron']) && $_GET['test_cron'] === '1') {
    echo "  🔄 正在手动触发定时任务...\n";
    
    // 记录触发前的状态
    $before_counter = get_option('walmart_feed_check_counter', 0);
    
    // 手动触发
    do_action('walmart_check_feed_status_hook');
    
    // 记录触发后的状态
    $after_counter = get_option('walmart_feed_check_counter', 0);
    
    echo "  触发前计数器: $before_counter\n";
    echo "  触发后计数器: $after_counter\n";
    
    if ($after_counter > $before_counter) {
        echo "  ✅ 定时任务执行成功\n";
    } else {
        echo "  ❌ 定时任务可能没有执行\n";
    }
} else {
    echo "  💡 添加 ?test_cron=1 参数来手动触发测试\n";
}

// 8. 检查最近的相关日志
echo "\n8. 最近的定时任务日志:\n";
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$recent_logs = $wpdb->get_results(
    "SELECT created_at, action, status, response 
     FROM $logs_table 
     WHERE action LIKE '%队列%' OR action LIKE '%Feed%' OR action LIKE '%定时%'
     ORDER BY created_at DESC 
     LIMIT 10"
);

if (empty($recent_logs)) {
    echo "  ⚠️ 没有找到相关的定时任务日志\n";
} else {
    foreach ($recent_logs as $log) {
        echo "  [{$log->created_at}] {$log->action} - {$log->status}\n";
    }
}

// 9. 系统环境检查
echo "\n9. 系统环境:\n";
echo "  当前时间: " . date('Y-m-d H:i:s') . "\n";
echo "  WordPress时间: " . current_time('mysql') . "\n";
echo "  时区: " . date_default_timezone_get() . "\n";
echo "  PHP版本: " . PHP_VERSION . "\n";

echo "\n=== 诊断完成 ===\n";
echo "如果定时任务未注册，请尝试重新激活插件\n";
echo "如果定时任务已注册但不执行，可能是WP-Cron或服务器配置问题\n";
?>
