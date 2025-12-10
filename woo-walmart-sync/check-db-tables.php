<?php
/**
 * 检查数据库表和分类映射
 */

require_once(__DIR__ . '/../../../wp-load.php');

header('Content-Type: text/html; charset=utf-8');
echo "<h1>数据库表检查</h1>";
echo "<pre>";

global $wpdb;

echo "=== Walmart相关表 ===\n";
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}walmart%'", ARRAY_N);
foreach ($tables as $table) {
    echo $table[0] . "\n";
}

echo "\n=== walmart_category_map 表结构 ===\n";
$map_table = $wpdb->prefix . 'walmart_category_map';
$columns = $wpdb->get_results("DESCRIBE {$map_table}");
if ($columns) {
    foreach ($columns as $col) {
        echo "{$col->Field} - {$col->Type}\n";
    }
} else {
    echo "表不存在或查询失败\n";
}

echo "\n=== walmart_category_map 数据 ===\n";
$maps = $wpdb->get_results("SELECT * FROM {$map_table} LIMIT 5", ARRAY_A);
if ($maps) {
    print_r($maps);
} else {
    echo "无数据或表不存在\n";
}

echo "\n=== 产品47的分类映射 ===\n";
$product_id = 47;
$product = wc_get_product($product_id);
$cat_ids = $product->get_category_ids();
echo "产品分类ID: " . implode(', ', $cat_ids) . "\n\n";

foreach ($cat_ids as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$map_table} WHERE wc_category_id = %d",
        $cat_id
    ), ARRAY_A);

    echo "分类ID {$cat_id} 的映射:\n";
    print_r($mapping);
    echo "\n";
}

echo "</pre>";
