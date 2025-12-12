<?php
/**
 * 诊断属性数据源脚本
 * 用途：找出100个字段的具体来源
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 输出管理
$output_file = 'diagnose-results.txt';
$output = '';

function log_output($message) {
    global $output;
    $output .= $message . "\n";
    echo $message . "\n";
}

log_output("=== 诊断属性数据源脚本 ===");
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
    // 根据错误信息，您的WordPress根目录应该是：
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

// 1. 检查特定分类的缓存数据
log_output("\n1. 检查特定分类的缓存数据:");

$test_category = 'Bed Frames'; // 替换为您遇到问题的分类
$transient_key = 'walmart_attributes_' . $test_category;

$cached_data = get_transient($transient_key);
if ($cached_data !== false) {
    log_output("✅ 发现缓存数据: {$transient_key}");
    log_output("缓存字段数量: " . count($cached_data));
    log_output("前10个字段:");
    
    $count = 0;
    foreach ($cached_data as $attr) {
        if ($count >= 10) break;
        $attr_name = is_array($attr) ? ($attr['attributeName'] ?? 'Unknown') : 'Unknown';
        log_output("  - {$attr_name}");
        $count++;
    }
    
    // 显示缓存过期时间
    $timeout_key = '_transient_timeout_' . $transient_key;
    $timeout = get_option($timeout_key);
    if ($timeout) {
        $expire_time = date('Y-m-d H:i:s', $timeout);
        log_output("缓存过期时间: {$expire_time}");
    }
} else {
    log_output("❌ 未发现缓存数据: {$transient_key}");
}

// 2. 检查数据库中的属性数据
log_output("\n2. 检查数据库中的属性数据:");

// 检查可能的存储位置
$possible_tables = [
    $wpdb->prefix . 'walmart_attributes',
    $wpdb->prefix . 'walmart_category_attributes',
    $wpdb->prefix . 'walmart_specs'
];

foreach ($possible_tables as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($table_exists) {
        log_output("✅ 检查表: {$table}");
        
        // 查找与测试分类相关的数据
        $records = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table} 
            WHERE category_id = %s 
            OR category_name = %s 
            OR data LIKE %s
            LIMIT 5
        ", $test_category, $test_category, '%' . $test_category . '%'));
        
        if ($records) {
            log_output("  发现 " . count($records) . " 条相关记录");
            foreach ($records as $record) {
                log_output("  记录ID: " . ($record->id ?? 'N/A'));
                if (isset($record->data)) {
                    $data = json_decode($record->data, true);
                    if (is_array($data)) {
                        log_output("    数据字段数: " . count($data));
                    }
                }
            }
        } else {
            log_output("  未发现相关记录");
        }
    }
}

// 3. 检查Options表中的相关数据
log_output("\n3. 检查Options表中的相关数据:");

$walmart_options = $wpdb->get_results($wpdb->prepare("
    SELECT option_name, LENGTH(option_value) as value_length
    FROM {$wpdb->options} 
    WHERE (option_name LIKE %s OR option_name LIKE %s)
    AND option_value LIKE %s
    ORDER BY value_length DESC
    LIMIT 10
", '%walmart%', '%attribute%', '%' . $test_category . '%'));

if ($walmart_options) {
    log_output("发现相关选项:");
    foreach ($walmart_options as $option) {
        log_output("  - {$option->option_name} (长度: {$option->value_length})");
        
        // 如果数据不太大，显示内容摘要
        if ($option->value_length < 5000) {
            $value = get_option($option->option_name);
            if (is_string($value) && strpos($value, '{') === 0) {
                $data = json_decode($value, true);
                if (is_array($data)) {
                    log_output("    JSON数据，元素数: " . count($data));
                }
            }
        }
    }
} else {
    log_output("未发现相关选项");
}

// 4. 模拟get_attributes_from_database函数调用
log_output("\n4. 模拟get_attributes_from_database函数调用:");

// 检查是否存在这个函数
if (function_exists('get_attributes_from_database')) {
    log_output("✅ 发现get_attributes_from_database函数");
    
    try {
        $db_attributes = get_attributes_from_database($test_category);
        if (!empty($db_attributes)) {
            log_output("✅ 从数据库获取到属性数据");
            log_output("数据库字段数量: " . count($db_attributes));
            log_output("这就是100个字段的来源！");
            
            // 显示前10个字段
            log_output("前10个字段:");
            $count = 0;
            foreach ($db_attributes as $attr) {
                if ($count >= 10) break;
                $attr_name = is_array($attr) ? ($attr['attributeName'] ?? 'Unknown') : 'Unknown';
                log_output("  - {$attr_name}");
                $count++;
            }
        } else {
            log_output("❌ 数据库中无属性数据");
        }
    } catch (Exception $e) {
        log_output("❌ 调用失败: " . $e->getMessage());
    }
} else {
    log_output("❌ 未发现get_attributes_from_database函数");
    log_output("请检查函数是否在其他文件中定义");
}

// 5. 检查所有Transient缓存
log_output("\n5. 检查所有相关Transient缓存:");

$all_transients = $wpdb->get_results("
    SELECT option_name, LENGTH(option_value) as value_length
    FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_walmart_attributes_%'
    ORDER BY value_length DESC
");

if ($all_transients) {
    log_output("发现 " . count($all_transients) . " 个属性缓存:");
    foreach ($all_transients as $transient) {
        $category = str_replace('_transient_walmart_attributes_', '', $transient->option_name);
        log_output("  - 分类: {$category} (数据长度: {$transient->value_length})");
    }
} else {
    log_output("未发现属性缓存");
}

// 6. 提供清理建议
log_output("\n6. 清理建议:");

if ($cached_data !== false) {
    log_output("🎯 发现问题：缓存中存在历史数据");
    log_output("建议执行：delete_transient('{$transient_key}');");
}

if (function_exists('get_attributes_from_database')) {
    log_output("🎯 发现问题：数据库函数返回历史数据");
    log_output("建议：清理数据库中的属性数据或修改函数逻辑");
}

log_output("\n推荐清理步骤：");
log_output("1. 运行 clear_attributes_cache.php 清理缓存");
log_output("2. 如果问题仍存在，运行 deep_clean_attributes.php");
log_output("3. 重新测试重置属性功能");

// 保存结果
log_output("\n=== 诊断完成 ===");
file_put_contents($output_file, $output);
log_output("诊断结果已保存到: {$output_file}");
?>
