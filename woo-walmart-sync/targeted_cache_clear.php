<?php
/**
 * 针对性缓存清理脚本
 * 基于诊断结果，清理发现的问题缓存
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 针对性缓存清理脚本 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n";

// 自动检测WordPress路径
$wp_path = '';
$current_dir = __DIR__;
for ($i = 0; $i < 5; $i++) {
    $test_path = $current_dir . str_repeat('/..', $i);
    if (file_exists($test_path . '/wp-config.php')) {
        $wp_path = realpath($test_path);
        break;
    }
}

if (empty($wp_path)) {
    echo "❌ 无法检测WordPress路径\n";
    exit;
}

require_once $wp_path . '/wp-config.php';
require_once $wp_path . '/wp-load.php';

echo "✅ WordPress加载成功\n\n";

global $wpdb;

// 1. 清理发现的问题缓存
echo "1. 清理发现的问题缓存:\n";

$problem_categories = [
    'Television Stands',
    'Benches', 
    'Accent Cabinets'
];

$cleared_count = 0;
foreach ($problem_categories as $category) {
    $transient_key = 'walmart_attributes_' . $category;
    
    // 检查缓存是否存在
    $cached_data = get_transient($transient_key);
    if ($cached_data !== false) {
        $field_count = count($cached_data);
        echo "发现缓存: {$category} - {$field_count} 个字段\n";
        
        // 删除缓存
        delete_transient($transient_key);
        echo "✅ 已清理: {$category}\n";
        $cleared_count++;
    } else {
        echo "❌ 缓存不存在: {$category}\n";
    }
}

echo "总计清理了 {$cleared_count} 个问题缓存\n\n";

// 2. 清理所有walmart属性缓存
echo "2. 清理所有walmart属性缓存:\n";

$all_transients = $wpdb->get_results("
    SELECT option_name 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_walmart_attributes_%'
");

$total_cleared = 0;
foreach ($all_transients as $transient) {
    $key = str_replace('_transient_', '', $transient->option_name);
    delete_transient($key);
    $total_cleared++;
    
    $category = str_replace('walmart_attributes_', '', $key);
    echo "清理: {$category}\n";
}

echo "✅ 总计清理了 {$total_cleared} 个属性缓存\n\n";

// 3. 清理timeout缓存
echo "3. 清理timeout缓存:\n";

$timeout_deleted = $wpdb->query("
    DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_timeout_walmart_attributes_%'
");

echo "✅ 清理了 {$timeout_deleted} 个timeout缓存\n\n";

// 4. 强制刷新所有缓存
echo "4. 强制刷新所有缓存:\n";

wp_cache_flush();
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('walmart_sync');
}

echo "✅ 已刷新所有WordPress缓存\n\n";

// 5. 验证清理结果
echo "5. 验证清理结果:\n";

$remaining = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_walmart_attributes_%'
");

echo "剩余属性缓存: {$remaining} 个\n";

if ($remaining == 0) {
    echo "✅ 所有属性缓存已清理完成\n";
} else {
    echo "⚠️ 仍有缓存残留\n";
    
    // 显示剩余的缓存
    $remaining_caches = $wpdb->get_results("
        SELECT option_name 
        FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_walmart_attributes_%'
        LIMIT 5
    ");
    
    echo "剩余缓存:\n";
    foreach ($remaining_caches as $cache) {
        $category = str_replace('_transient_walmart_attributes_', '', $cache->option_name);
        echo "  - {$category}\n";
    }
}

echo "\n=== 清理完成 ===\n";
echo "请现在测试重置属性功能:\n";
echo "1. 进入分类映射页面\n";
echo "2. 选择任意分类（如Television Stands、Benches等）\n";
echo "3. 点击'重置属性'按钮\n";
echo "4. 检查字段数量是否恢复正常（应该只有该分类对应的字段数量）\n";
?>
