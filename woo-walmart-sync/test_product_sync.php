<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试商品同步功能 ===\n\n";

// 获取分类100和101下的商品进行测试
$products_cat_100 = get_posts([
    'post_type' => 'product',
    'posts_per_page' => 1,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => 100
        ]
    ]
]);

$products_cat_101 = get_posts([
    'post_type' => 'product', 
    'posts_per_page' => 1,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'term_id', 
            'terms' => 101
        ]
    ]
]);

$test_products = [];
if (!empty($products_cat_100)) {
    $test_products[] = ['id' => $products_cat_100[0]->ID, 'cat' => 100];
}
if (!empty($products_cat_101)) {
    $test_products[] = ['id' => $products_cat_101[0]->ID, 'cat' => 101];
}

if (empty($test_products)) {
    echo "❌ 没有找到测试商品\n";
    exit;
}

// 加载同步类
require_once 'includes/class-product-sync.php';
$sync_service = new Woo_Walmart_Product_Sync();

foreach ($test_products as $test_info) {
    $product = wc_get_product($test_info['id']);
    if (!$product) {
        continue;
    }
    
    echo "=== 测试商品 (分类{$test_info['cat']}) ===\n";
    echo "商品ID: {$test_info['id']}\n";
    echo "商品名: {$product->get_name()}\n";
    echo "SKU: {$product->get_sku()}\n";
    
    $product_cat_ids = wp_get_post_terms($test_info['id'], 'product_cat', ['fields' => 'ids']);
    echo "商品分类IDs: " . implode(', ', $product_cat_ids) . "\n";
    
    // 清理之前的日志
    global $wpdb;
    $logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $logs_table WHERE product_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        $test_info['id']
    ));
    
    echo "\n开始同步...\n";
    
    try {
        // 执行同步
        $result = $sync_service->initiate_sync($test_info['id']);
        
        if ($result['success']) {
            echo "✅ 同步成功: {$result['message']}\n";
        } else {
            echo "❌ 同步失败: {$result['message']}\n";
        }
        
        // 显示详细日志
        echo "\n--- 同步日志 ---\n";
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table WHERE product_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY created_at DESC LIMIT 10",
            $test_info['id']
        ));
        
        foreach ($logs as $log) {
            echo "[{$log->level}] {$log->action}: {$log->message}\n";
            if (!empty($log->request) && strlen($log->request) < 500) {
                $request_data = json_decode($log->request, true);
                if ($request_data) {
                    echo "  请求数据: " . json_encode($request_data, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 同步异常: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
}

echo "测试完成！\n";
?>
