<?php
// 测试修复后的fulfillmentCenterID处理

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

echo "=== 修复后的fulfillmentCenterID测试 ===\n\n";

$test_skus = ['B2726S00512', 'LT000682AAK', 'B2741S00266'];

foreach ($test_skus as $sku) {
    echo "📦 测试SKU: $sku\n";
    
    global $wpdb;
    $product_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
        $sku
    ));
    
    if (!$product_id) {
        echo "❌ 产品未找到\n\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    
    echo "修复前后对比:\n";
    
    // 测试修复后的结果
    if (function_exists('handle_auto_generate_field')) {
        $result = handle_auto_generate_field($product, 'fulfillmentCenterID');
        echo "✅ 修复后结果: " . ($result ?: '仍为空') . "\n";
    } else {
        echo "❌ handle_auto_generate_field函数不存在\n";
    }
    
    // 显示配置信息
    echo "配置信息:\n";
    echo "  业务单元: " . get_option('woo_walmart_business_unit', '未设置') . "\n";
    echo "  通用履行中心ID: " . get_option('woo_walmart_fulfillment_center_id', '未设置') . "\n";
    echo "  US履行中心ID: " . get_option('woo_walmart_US_fulfillment_center_id', '未设置') . "\n";
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
}

echo "=== 完成 ===\n";
?>
