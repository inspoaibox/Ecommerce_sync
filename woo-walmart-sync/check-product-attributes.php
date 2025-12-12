<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

$product = wc_get_product(25921);
echo "=== 产品属性详情 ===\n";

$attributes = $product->get_attributes();
foreach ($attributes as $attr_name => $attribute) {
    echo "属性名: {$attr_name}\n";
    
    if ($attribute->is_taxonomy()) {
        $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
        if (!is_wp_error($terms) && !empty($terms)) {
            echo "  值: " . implode(', ', wp_list_pluck($terms, 'name')) . "\n";
        }
    } else {
        $options = $attribute->get_options();
        echo "  值: " . implode(', ', $options) . "\n";
    }
    
    if (strpos(strtolower($attr_name), 'seat') !== false) {
        echo "  ❌ 找到seat相关属性！\n";
    }
}

echo "\n=== 直接测试get_attribute ===\n";
echo "seat_depth: '" . $product->get_attribute('seat_depth') . "'\n";
echo "Seat_Depth: '" . $product->get_attribute('Seat_Depth') . "'\n";
echo "Seat Depth: '" . $product->get_attribute('Seat Depth') . "'\n";

?>
