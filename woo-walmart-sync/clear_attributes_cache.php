<?php
/**
 * 清理属性缓存脚本
 * 用途：清除所有分类的属性缓存，强制重新从API获取
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 输出管理
$output_file = 'cache-clear-results.txt';
$output = '';

function log_output($message) {
    global $output;
    $output .= $message . "\n";
    echo $message . "\n";
}

log_output("=== 清理属性缓存脚本 ===");
log_output("执行时间: " . date('Y-m-d H:i:s'));

// WordPress环境加载 - 自动检测路径
$wp_path = '';

// 方法1: 从当前路径向上查找WordPress根目录
$current_dir = __DIR__;
$max_levels = 5; // 最多向上查找5级目录

for ($i = 0; $i < $max_levels; $i++) {
    $test_path = $current_dir . str_repeat('/..', $i);
    if (file_exists($test_path . '/wp-config.php')) {
        $wp_path = realpath($test_path);
        break;
    }
}

// 方法2: 如果自动检测失败，使用手动路径
if (empty($wp_path) || !file_exists($wp_path . '/wp-config.php')) {
    // 根据您的环境，WordPress根目录应该是：
    $wp_path = '/home/aokede.com/public_html';

    // 验证路径是否正确
    if (!file_exists($wp_path . '/wp-config.php')) {
        log_output("❌ WordPress路径不正确，请手动设置正确的路径");
        log_output("当前尝试的路径: {$wp_path}");
        log_output("请将脚本中的wp_path变量设置为正确的WordPress根目录路径");
        file_put_contents($output_file, $output);
        exit;
    }
}

log_output("WordPress路径: {$wp_path}");

require_once $wp_path . '/wp-config.php';
require_once $wp_path . '/wp-load.php';

log_output("✅ WordPress加载成功");

// 1. 清除Transient缓存
log_output("\n1. 清除Transient缓存:");

global $wpdb;

// 查找所有walmart_attributes_*的transient
$transients = $wpdb->get_results("
    SELECT option_name, option_value 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_walmart_attributes_%' 
    OR option_name LIKE '_transient_timeout_walmart_attributes_%'
");

$cleared_count = 0;
foreach ($transients as $transient) {
    $transient_key = str_replace(['_transient_', '_transient_timeout_'], '', $transient->option_name);
    
    if (strpos($transient_key, 'walmart_attributes_') === 0) {
        $category_id = str_replace('walmart_attributes_', '', $transient_key);
        
        // 删除transient
        delete_transient($transient_key);
        log_output("清除缓存: {$category_id}");
        $cleared_count++;
    }
}

log_output("✅ 清除了 {$cleared_count} 个Transient缓存");

// 2. 清除Object缓存
log_output("\n2. 清除Object缓存:");

// 清除walmart_sync缓存组
wp_cache_flush_group('walmart_sync');
log_output("✅ 清除了walmart_sync缓存组");

// 3. 清除所有相关缓存
log_output("\n3. 清除所有相关缓存:");

// 清除所有WordPress缓存
wp_cache_flush();
log_output("✅ 清除了所有WordPress缓存");

// 4. 验证清理结果
log_output("\n4. 验证清理结果:");

$remaining_transients = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_walmart_attributes_%'
");

log_output("剩余Transient缓存: {$remaining_transients} 个");

if ($remaining_transients == 0) {
    log_output("✅ 所有缓存已清理完成");
} else {
    log_output("⚠️ 仍有缓存残留，可能需要手动清理");
}

// 5. 提供测试建议
log_output("\n5. 测试建议:");
log_output("请执行以下步骤验证清理效果：");
log_output("1. 进入分类映射页面");
log_output("2. 选择一个之前有100个字段的分类");
log_output("3. 点击'重置属性'按钮");
log_output("4. 检查字段数量是否恢复正常（约50个）");

// 保存结果
log_output("\n=== 清理完成 ===");
file_put_contents($output_file, $output);
log_output("清理结果已保存到: {$output_file}");
?>
