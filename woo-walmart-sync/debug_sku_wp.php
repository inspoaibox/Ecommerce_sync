<?php
// 通过WordPress管理后台运行的脚本
// 访问: /wp-content/plugins/woo-walmart-sync/debug_sku_wp.php

// 检查是否在WordPress环境中
if (!defined('ABSPATH')) {
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
}

if (!function_exists('get_option')) {
    die('请通过WordPress环境访问此脚本');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== SKU对比分析 ===\n\n";

$success_skus = ['LT000682AAK', 'B2741S00491', 'N7090004012A'];
$failed_skus = ['B2726S00512', 'B2741S00266'];

function analyze_product_wp($sku, $status) {
    echo "📦 SKU: $sku ($status)\n";
    
    // 通过SKU获取产品
    global $wpdb;
    $product_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
        $sku
    ));
    
    if (!$product_id) {
        echo "❌ 产品未找到\n\n";
        return;
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        echo "❌ 无法加载产品对象\n\n";
        return;
    }
    
    echo "产品ID: $product_id\n";
    echo "产品名称: " . $product->get_name() . "\n";
    echo "产品类型: " . $product->get_type() . "\n";
    echo "产品状态: " . $product->get_status() . "\n";
    
    // 获取产品分类
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
    echo "WC分类: " . implode(', ', $categories) . "\n";
    
    // 获取沃尔玛分类映射
    $category_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (!empty($category_ids)) {
        $walmart_mapping = $wpdb->get_var($wpdb->prepare(
            "SELECT walmart_category_path FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
            $category_ids[0]
        ));
        echo "沃尔玛分类: " . ($walmart_mapping ?: '未映射') . "\n";
    }
    
    // 产品物理属性
    echo "重量: " . ($product->get_weight() ?: '未设置') . "\n";
    echo "尺寸: " . $product->get_length() . " x " . $product->get_width() . " x " . $product->get_height() . "\n";
    
    // 库存信息
    echo "库存管理: " . ($product->get_manage_stock() ? 'Yes' : 'No') . "\n";
    echo "库存状态: " . $product->get_stock_status() . "\n";
    echo "库存数量: " . $product->get_stock_quantity() . "\n";
    
    // 检查关键属性
    $key_attributes = [
        'electronicsIndicator',
        'batteryTechnologyType', 
        'chemicalAerosolPesticide',
        'MustShipAlone',
        'ShippingWeight'
    ];
    
    echo "关键属性:\n";
    foreach ($key_attributes as $attr) {
        $value = $product->get_attribute($attr) ?: get_post_meta($product_id, $attr, true);
        if ($value) {
            echo "  $attr: $value\n";
        }
    }
    
    // 检查fulfillmentCenterID的处理
    echo "fulfillmentCenterID处理测试:\n";
    if (function_exists('handle_auto_generate_field')) {
        $fulfillment_id = handle_auto_generate_field($product, 'fulfillmentCenterID');
        echo "  自动生成结果: " . ($fulfillment_id ?: '空值') . "\n";
    } else {
        echo "  handle_auto_generate_field函数不存在\n";
    }
    
    // 检查配置值
    $us_center_id = get_option('woo_walmart_US_fulfillment_center_id', '');
    $general_center_id = get_option('woo_walmart_fulfillment_center_id', '');
    echo "  US履行中心ID: " . ($us_center_id ?: '未设置') . "\n";
    echo "  通用履行中心ID: " . ($general_center_id ?: '未设置') . "\n";
    
    echo "\n" . str_repeat('-', 80) . "\n\n";
}

echo "🟢 成功的产品:\n";
foreach ($success_skus as $sku) {
    analyze_product_wp($sku, '成功');
}

echo "🔴 失败的产品:\n";
foreach ($failed_skus as $sku) {
    analyze_product_wp($sku, '失败');
}

// 检查系统配置
echo "=== 系统配置检查 ===\n";
echo "默认市场: " . get_option('woo_walmart_business_unit', '未设置') . "\n";
echo "API版本: " . get_option('woo_walmart_api_version', '未设置') . "\n";
echo "通用履行中心ID: " . get_option('woo_walmart_fulfillment_center_id', '未设置') . "\n";
echo "US履行中心ID: " . get_option('woo_walmart_US_fulfillment_center_id', '未设置') . "\n";
echo "CA履行中心ID: " . get_option('woo_walmart_CA_fulfillment_center_id', '未设置') . "\n";
echo "MX履行中心ID: " . get_option('woo_walmart_MX_fulfillment_center_id', '未设置') . "\n";
echo "CL履行中心ID: " . get_option('woo_walmart_CL_fulfillment_center_id', '未设置') . "\n";

echo "\n=== 完成 ===\n";
?>
