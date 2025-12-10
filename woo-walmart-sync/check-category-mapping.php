<?php
/**
 * 检查分类映射
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查分类映射 ===\n\n";

global $wpdb;
$map_table = $wpdb->prefix . 'woo_walmart_category_mapping';

// 查看所有映射
$all_mappings = $wpdb->get_results("SELECT * FROM $map_table");

echo "所有分类映射:\n";
foreach ($all_mappings as $mapping) {
    echo "ID: {$mapping->id}\n";
    echo "WC分类: {$mapping->wc_category_name}\n";
    echo "沃尔玛分类: {$mapping->walmart_category_path}\n";
    echo "---\n";
}

// 检查产品的分类
$product_id = 13917;
$product = wc_get_product($product_id);

if ($product) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
    echo "\n产品分类:\n";
    foreach ($product_categories as $cat_name) {
        echo "- {$cat_name}\n";
    }
    
    // 查找匹配的映射
    if (!empty($product_categories)) {
        $first_category = $product_categories[0];
        $mapping = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $map_table 
            WHERE wc_category_name = %s 
            LIMIT 1
        ", $first_category));
        
        if ($mapping) {
            echo "\n✅ 找到匹配的映射:\n";
            echo "WC分类: {$mapping->wc_category_name}\n";
            echo "沃尔玛分类: {$mapping->walmart_category_path}\n";
        } else {
            echo "\n❌ 没有找到匹配的映射\n";
        }
    } else {
        echo "\n产品没有分类，查找默认映射...\n";
        
        $default_mapping = $wpdb->get_row("
            SELECT * FROM $map_table 
            ORDER BY id ASC 
            LIMIT 1
        ");
        
        if ($default_mapping) {
            echo "使用默认映射: {$default_mapping->wc_category_name} -> {$default_mapping->walmart_category_path}\n";
        }
    }
}

?>
