<?php
/**
 * 调试AJAX 500错误脚本
 * 用于诊断批量同步时的500错误问题
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../wp-config.php';
}

echo "=== AJAX 500错误诊断 ===\n\n";

// 1. 检查PHP错误日志
echo "1. 检查PHP错误日志:\n";
$error_log_locations = [
    ini_get('error_log'),
    '/var/log/php_errors.log',
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log',
    ABSPATH . 'wp-content/debug.log'
];

foreach ($error_log_locations as $log_file) {
    if ($log_file && file_exists($log_file) && is_readable($log_file)) {
        echo "  找到错误日志: {$log_file}\n";
        
        // 读取最近的错误（最后50行）
        $lines = file($log_file);
        $recent_lines = array_slice($lines, -50);
        
        echo "  最近的错误:\n";
        foreach ($recent_lines as $line) {
            if (strpos($line, 'admin-ajax.php') !== false || 
                strpos($line, 'walmart') !== false ||
                strpos($line, 'Fatal error') !== false ||
                strpos($line, 'PHP Fatal error') !== false) {
                echo "    " . trim($line) . "\n";
            }
        }
        break;
    }
}

// 2. 检查WordPress调试设置
echo "\n2. 检查WordPress调试设置:\n";
echo "  WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? '✅ 启用' : '❌ 禁用') . "\n";
echo "  WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? '✅ 启用' : '❌ 禁用') . "\n";
echo "  WP_DEBUG_DISPLAY: " . (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? '✅ 启用' : '❌ 禁用') . "\n";

// 3. 检查PHP配置
echo "\n3. 检查PHP配置:\n";
echo "  内存限制: " . ini_get('memory_limit') . "\n";
echo "  执行时间限制: " . ini_get('max_execution_time') . "秒\n";
echo "  POST最大大小: " . ini_get('post_max_size') . "\n";
echo "  上传最大大小: " . ini_get('upload_max_filesize') . "\n";
echo "  错误报告级别: " . ini_get('error_reporting') . "\n";

// 4. 检查AJAX处理函数是否存在
echo "\n4. 检查AJAX处理函数:\n";
if (function_exists('handle_walmart_batch_sync_products')) {
    echo "  ✅ handle_walmart_batch_sync_products 函数存在\n";
} else {
    echo "  ❌ handle_walmart_batch_sync_products 函数不存在\n";
}

if (function_exists('handle_convert_skus_to_product_ids')) {
    echo "  ✅ handle_convert_skus_to_product_ids 函数存在\n";
} else {
    echo "  ❌ handle_convert_skus_to_product_ids 函数不存在\n";
}

// 5. 检查AJAX钩子注册
echo "\n5. 检查AJAX钩子注册:\n";
if (has_action('wp_ajax_walmart_batch_sync_products')) {
    echo "  ✅ wp_ajax_walmart_batch_sync_products 已注册\n";
} else {
    echo "  ❌ wp_ajax_walmart_batch_sync_products 未注册\n";
}

if (has_action('wp_ajax_convert_skus_to_product_ids')) {
    echo "  ✅ wp_ajax_convert_skus_to_product_ids 已注册\n";
} else {
    echo "  ❌ wp_ajax_convert_skus_to_product_ids 未注册\n";
}

// 6. 检查数据库表
echo "\n6. 检查数据库表:\n";
global $wpdb;

$tables_to_check = [
    'walmart_batch_feeds',
    'walmart_batch_items', 
    'walmart_upc_pool',
    'walmart_category_map'
];

foreach ($tables_to_check as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'");
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_table_name}");
        echo "  ✅ {$table}: 存在 ({$count} 条记录)\n";
    } else {
        echo "  ❌ {$table}: 不存在\n";
    }
}

// 7. 模拟AJAX请求测试
echo "\n7. 模拟AJAX请求测试:\n";

// 设置模拟的POST数据
$_POST = [
    'action' => 'walmart_batch_sync_products',
    'product_ids' => [1, 2, 3], // 测试用的产品ID
    'force_sync' => 0,
    'skip_validation' => 0,
    'nonce' => wp_create_nonce('sku_batch_sync_nonce')
];

// 开启输出缓冲
ob_start();

try {
    echo "  开始模拟AJAX调用...\n";
    
    // 直接调用函数
    handle_walmart_batch_sync_products();
    
    echo "  ✅ AJAX函数调用完成，无致命错误\n";
    
} catch (Exception $e) {
    echo "  ❌ AJAX函数调用异常: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "  ❌ AJAX函数调用错误: " . $e->getMessage() . "\n";
}

// 获取输出内容
$output = ob_get_clean();

if (!empty($output)) {
    echo "  输出内容:\n";
    echo "    " . str_replace("\n", "\n    ", trim($output)) . "\n";
}

// 8. 检查最近的批次记录
echo "\n8. 检查最近的批次记录:\n";
$recent_batches = $wpdb->get_results("
    SELECT batch_id, product_count, status, feed_id, created_at 
    FROM {$wpdb->prefix}walmart_batch_feeds 
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($recent_batches) {
    echo "  最近的批次:\n";
    foreach ($recent_batches as $batch) {
        echo "    批次ID: {$batch->batch_id}\n";
        echo "    产品数量: {$batch->product_count}\n";
        echo "    状态: {$batch->status}\n";
        echo "    Feed ID: " . ($batch->feed_id ?: '无') . "\n";
        echo "    创建时间: {$batch->created_at}\n";
        echo "    ---\n";
    }
} else {
    echo "  ❌ 没有找到批次记录\n";
}

echo "\n=== 诊断完成 ===\n";
echo "请将以上信息发送给开发者进行进一步分析。\n";
