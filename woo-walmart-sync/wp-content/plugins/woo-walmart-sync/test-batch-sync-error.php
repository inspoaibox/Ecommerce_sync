<?php
/**
 * 测试批量同步错误处理
 */

require_once dirname(__FILE__) . '/../../../wp-load.php';

echo "=== 测试批量同步错误处理 ===\n\n";

// 1. 获取测试产品
global $wpdb;
$test_products = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    LIMIT 3
");

if (empty($test_products)) {
    echo "❌ 没有找到测试产品\n";
    exit;
}

$product_ids = array_column($test_products, 'ID');
echo "测试产品ID: " . implode(', ', $product_ids) . "\n\n";

// 2. 模拟AJAX请求
$_POST = [
    'action' => 'walmart_batch_sync_products',
    'product_ids' => $product_ids,
    'force_sync' => 0,
    'skip_validation' => 0,
    'nonce' => wp_create_nonce('sku_batch_sync_nonce')
];

// 模拟登录用户
wp_set_current_user(1);

echo "2. 开始批量同步测试...\n";

// 捕获输出
ob_start();

try {
    handle_walmart_batch_sync_products();
    echo "✅ AJAX函数调用完成\n";
} catch (Exception $e) {
    echo "❌ 异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$output = ob_get_clean();

if (!empty($output)) {
    echo "\n输出内容:\n";
    echo $output . "\n";
}

// 3. 查看最近的日志
echo "\n3. 查看最近的批量同步日志:\n";

$logs = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs 
    WHERE action LIKE '%批量%' OR action LIKE '%Feed%'
    ORDER BY created_at DESC 
    LIMIT 5
");

foreach ($logs as $log) {
    echo "\n时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "级别: {$log->level}\n";
    echo "消息: {$log->message}\n";
    
    if (!empty($log->context)) {
        $context = json_decode($log->context, true);
        if ($context) {
            echo "上下文: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    if (!empty($log->details)) {
        echo "详情: " . substr($log->details, 0, 500) . "\n";
    }
    
    echo "---\n";
}

echo "\n=== 测试完成 ===\n";

