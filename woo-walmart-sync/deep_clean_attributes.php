<?php
/**
 * 深度清理属性数据脚本
 * 用途：清理数据库中所有相关的属性数据
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 输出管理
$output_file = 'deep-clean-results.txt';
$output = '';

function log_output($message) {
    global $output;
    $output .= $message . "\n";
    echo $message . "\n";
}

log_output("=== 深度清理属性数据脚本 ===");
log_output("执行时间: " . date('Y-m-d H:i:s'));
log_output("⚠️ 警告：此脚本将清理所有属性相关数据，请确保已备份数据库");

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

global $wpdb;

// 1. 分析现有数据
log_output("\n1. 分析现有数据:");

// 检查可能的属性存储表
$tables_to_check = [
    $wpdb->prefix . 'walmart_attributes',
    $wpdb->prefix . 'walmart_category_attributes', 
    $wpdb->prefix . 'walmart_specs',
    $wpdb->prefix . 'options' // 检查options表中的相关数据
];

foreach ($tables_to_check as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($table_exists) {
        log_output("✅ 发现表: {$table}");
        
        if ($table === $wpdb->prefix . 'options') {
            // 检查options表中的walmart相关数据
            $walmart_options = $wpdb->get_results("
                SELECT option_name, LENGTH(option_value) as value_length
                FROM {$table} 
                WHERE option_name LIKE '%walmart%' 
                AND (option_name LIKE '%attribute%' OR option_name LIKE '%spec%')
                ORDER BY option_name
            ");
            
            log_output("  - 找到 " . count($walmart_options) . " 个相关选项:");
            foreach ($walmart_options as $option) {
                log_output("    * {$option->option_name} (长度: {$option->value_length})");
            }
        } else {
            // 检查专用表的记录数
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            log_output("  - 记录数: {$count}");
        }
    } else {
        log_output("❌ 表不存在: {$table}");
    }
}

// 2. 清理Transient缓存
log_output("\n2. 清理Transient缓存:");

$transient_patterns = [
    'walmart_attributes_%',
    'walmart_spec_%', 
    'walmart_category_%'
];

$total_cleared = 0;
foreach ($transient_patterns as $pattern) {
    $transients = $wpdb->get_results($wpdb->prepare("
        SELECT option_name 
        FROM {$wpdb->options} 
        WHERE option_name LIKE %s 
        OR option_name LIKE %s
    ", '_transient_' . $pattern, '_transient_timeout_' . $pattern));
    
    foreach ($transients as $transient) {
        $wpdb->delete($wpdb->options, ['option_name' => $transient->option_name]);
        $total_cleared++;
    }
}

log_output("✅ 清理了 {$total_cleared} 个Transient缓存");

// 3. 清理专用属性表（如果存在）
log_output("\n3. 清理专用属性表:");

$attribute_tables = [
    $wpdb->prefix . 'walmart_attributes',
    $wpdb->prefix . 'walmart_category_attributes',
    $wpdb->prefix . 'walmart_specs'
];

foreach ($attribute_tables as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($table_exists) {
        // 备份数据（可选）
        $backup_table = $table . '_backup_' . date('Ymd_His');
        $wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$table}");
        log_output("✅ 备份表 {$table} 到 {$backup_table}");
        
        // 清空表
        $deleted = $wpdb->query("DELETE FROM {$table}");
        log_output("✅ 清理表 {$table}，删除了 {$deleted} 条记录");
    }
}

// 4. 清理Options表中的相关数据
log_output("\n4. 清理Options表中的相关数据:");

$option_patterns = [
    'walmart_attributes_%',
    'walmart_spec_%',
    'walmart_category_spec_%'
];

$options_cleared = 0;
foreach ($option_patterns as $pattern) {
    $deleted = $wpdb->query($wpdb->prepare("
        DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE %s
    ", $pattern));
    $options_cleared += $deleted;
}

log_output("✅ 清理了 {$options_cleared} 个选项");

// 5. 强制清除所有缓存
log_output("\n5. 强制清除所有缓存:");

wp_cache_flush();
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('walmart_sync');
}

log_output("✅ 清除了所有缓存");

// 6. 验证清理结果
log_output("\n6. 验证清理结果:");

$remaining_transients = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_%walmart%'
");

$remaining_options = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '%walmart%attribute%'
    OR option_name LIKE '%walmart%spec%'
");

log_output("剩余Transient缓存: {$remaining_transients} 个");
log_output("剩余相关选项: {$remaining_options} 个");

if ($remaining_transients == 0 && $remaining_options == 0) {
    log_output("✅ 深度清理完成，所有相关数据已清除");
} else {
    log_output("⚠️ 仍有数据残留，可能需要手动检查");
}

// 7. 重要提醒
log_output("\n7. 重要提醒:");
log_output("清理完成后，请执行以下操作：");
log_output("1. 进入分类映射页面");
log_output("2. 选择任意分类，点击'重置属性'");
log_output("3. 系统将重新从Walmart API获取最新规范");
log_output("4. 验证字段数量是否正常（应该只有该分类对应的字段）");
log_output("5. 如果问题仍然存在，请检查代码中的get_attributes_from_database函数");

// 保存结果
log_output("\n=== 深度清理完成 ===");
file_put_contents($output_file, $output);
log_output("清理结果已保存到: {$output_file}");
?>
