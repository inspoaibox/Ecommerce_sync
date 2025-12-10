<?php
// 修复定时任务注册问题

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

echo "=== 修复定时任务注册 ===\n\n";

// 1. 检查当前状态
echo "1. 修复前状态检查:\n";
$next_walmart_check = wp_next_scheduled('walmart_check_feed_status_hook');
if ($next_walmart_check) {
    echo "  ✅ walmart_check_feed_status_hook 已注册 (下次: " . date('Y-m-d H:i:s', $next_walmart_check) . ")\n";
} else {
    echo "  ❌ walmart_check_feed_status_hook 未注册\n";
}

$feed_check_counter = get_option('walmart_feed_check_counter', 0);
echo "  Feed检查计数器: $feed_check_counter\n";

// 2. 清除可能存在的旧定时任务
echo "\n2. 清除旧定时任务:\n";
$cleared = wp_clear_scheduled_hook('walmart_check_feed_status_hook');
echo "  清除结果: " . ($cleared ? "成功清除 $cleared 个任务" : "没有找到要清除的任务") . "\n";

// 3. 重新注册定时任务
echo "\n3. 重新注册定时任务:\n";
$scheduled = wp_schedule_event(time(), 'every_five_minutes', 'walmart_check_feed_status_hook');
if ($scheduled === false) {
    echo "  ❌ 注册失败\n";
} else {
    echo "  ✅ 注册成功\n";
}

// 4. 验证注册结果
echo "\n4. 验证注册结果:\n";
$next_walmart_check_after = wp_next_scheduled('walmart_check_feed_status_hook');
if ($next_walmart_check_after) {
    echo "  ✅ walmart_check_feed_status_hook 已注册\n";
    echo "  下次执行时间: " . date('Y-m-d H:i:s', $next_walmart_check_after) . "\n";
    echo "  距离现在: " . ($next_walmart_check_after - time()) . " 秒\n";
} else {
    echo "  ❌ 注册验证失败\n";
}

// 5. 可选：重置计数器以立即触发Feed检查
if (isset($_GET['reset_counter']) && $_GET['reset_counter'] === '1') {
    echo "\n5. 重置计数器:\n";
    update_option('walmart_feed_check_counter', 9); // 设置为9，下次执行就会检查Feed
    echo "  ✅ 计数器已重置为9，下次定时任务执行时将检查Feed状态\n";
} else {
    echo "\n5. 计数器重置:\n";
    echo "  💡 添加 ?reset_counter=1 参数来重置计数器，立即触发Feed检查\n";
}

// 6. 手动触发一次定时任务测试
if (isset($_GET['test_run']) && $_GET['test_run'] === '1') {
    echo "\n6. 手动触发测试:\n";
    echo "  🔄 正在手动触发定时任务...\n";
    
    $before_counter = get_option('walmart_feed_check_counter', 0);
    
    // 手动触发
    do_action('walmart_check_feed_status_hook');
    
    $after_counter = get_option('walmart_feed_check_counter', 0);
    
    echo "  触发前计数器: $before_counter\n";
    echo "  触发后计数器: $after_counter\n";
    
    if ($after_counter > $before_counter) {
        echo "  ✅ 定时任务执行成功\n";
        
        if ($after_counter >= 10) {
            echo "  ✅ 已触发Feed状态检查\n";
        } else {
            echo "  ⚠️ 计数器未达到10，Feed状态检查未触发\n";
        }
    } else {
        echo "  ❌ 定时任务可能没有执行\n";
    }
} else {
    echo "\n6. 手动测试:\n";
    echo "  💡 添加 ?test_run=1 参数来手动触发测试\n";
}

// 7. 显示所有Walmart定时任务
echo "\n7. 当前所有Walmart定时任务:\n";
$cron_jobs = _get_cron_array();
$walmart_jobs = [];

foreach ($cron_jobs as $timestamp => $jobs) {
    foreach ($jobs as $hook => $job_array) {
        if (strpos($hook, 'walmart') !== false) {
            $walmart_jobs[] = [
                'hook' => $hook,
                'timestamp' => $timestamp,
                'time_str' => date('Y-m-d H:i:s', $timestamp)
            ];
        }
    }
}

if (!empty($walmart_jobs)) {
    foreach ($walmart_jobs as $job) {
        echo "  ✅ {$job['hook']} -> {$job['time_str']}\n";
    }
} else {
    echo "  ❌ 没有找到Walmart相关的定时任务\n";
}

echo "\n=== 修复完成 ===\n";
echo "建议操作:\n";
echo "1. 访问 ?reset_counter=1 重置计数器\n";
echo "2. 访问 ?test_run=1 手动测试\n";
echo "3. 等待5分钟观察定时任务是否正常执行\n";
echo "4. 检查队列管理页面的批次状态是否开始更新\n";
?>
