<?php
require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

$product = wc_get_product(47);
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$product_cat_ids = $product->get_category_ids();
$mapped_category = null;
foreach ($product_cat_ids as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$map_table} WHERE wc_category_id = %d", $cat_id), ARRAY_A);
    if ($mapping) { $mapped_category = $mapping; break; }
}
$attribute_rules = !empty($mapped_category['walmart_attributes']) ? json_decode($mapped_category['walmart_attributes'], true) : null;
$mapper = new Woo_Walmart_Product_Mapper();
$data = $mapper->map($product, $mapped_category['walmart_category_path'], '763437444167', $attribute_rules, 1, 'CA');

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
