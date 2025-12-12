<?php
// 检查fulfillmentCenterID的实际来源

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

echo "=== fulfillmentCenterID来源追踪 ===\n\n";

$test_skus = ['B2726S00512', 'LT000682AAK'];

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
    
    echo "1. 产品属性中的fulfillmentCenterID:\n";
    $attr_value = $product->get_attribute('fulfillmentCenterID');
    echo "   get_attribute结果: " . ($attr_value ?: '空') . "\n";
    
    echo "2. 产品元数据中的fulfillmentCenterID:\n";
    $meta_value = get_post_meta($product_id, 'fulfillmentCenterID', true);
    echo "   get_post_meta结果: " . ($meta_value ?: '空') . "\n";
    
    echo "3. 所有相关元数据:\n";
    $all_meta = get_post_meta($product_id);
    foreach ($all_meta as $key => $values) {
        if (stripos($key, 'fulfillment') !== false) {
            echo "   $key: " . implode(', ', $values) . "\n";
        }
    }
    
    echo "4. 配置选项测试:\n";
    echo "   woo_walmart_fulfillment_center_id: " . get_option('woo_walmart_fulfillment_center_id', '空') . "\n";
    echo "   woo_walmart_US_fulfillment_center_id: " . get_option('woo_walmart_US_fulfillment_center_id', '空') . "\n";
    
    echo "5. handle_auto_generate_field测试:\n";
    if (function_exists('handle_auto_generate_field')) {
        $result = handle_auto_generate_field($product, 'fulfillmentCenterID');
        echo "   结果: " . ($result ?: '空') . "\n";
        
        // 测试其他字段是否正常
        $test_fields = ['fulfillmentLagTime', 'releaseDate', 'startDate'];
        foreach ($test_fields as $field) {
            $test_result = handle_auto_generate_field($product, $field);
            echo "   $field: " . ($test_result ?: '空') . "\n";
        }
    }
    
    echo "6. 检查是否有其他函数处理fulfillmentCenterID:\n";
    // 检查是否有专门的函数
    if (function_exists('get_product_fulfillment_center_id')) {
        $func_result = get_product_fulfillment_center_id($product);
        echo "   get_product_fulfillment_center_id: " . ($func_result ?: '空') . "\n";
    } else {
        echo "   get_product_fulfillment_center_id函数不存在\n";
    }
    
    echo "\n" . str_repeat('-', 80) . "\n\n";
}

// 检查通用属性配置
echo "=== 通用属性配置检查 ===\n";
if (function_exists('get_v5_common_attributes')) {
    $common_attrs = get_v5_common_attributes();
    foreach ($common_attrs as $attr) {
        if ($attr['attributeName'] === 'fulfillmentCenterID') {
            echo "找到fulfillmentCenterID配置:\n";
            print_r($attr);
            break;
        }
    }
} else {
    echo "get_v5_common_attributes函数不存在\n";
}

echo "\n=== 完成 ===\n";
?>
