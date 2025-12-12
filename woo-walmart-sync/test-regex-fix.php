<?php
/**
 * 测试正则表达式字段是否被过滤
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

header('Content-Type: text/plain; charset=utf-8');

echo "🧪 测试正则表达式字段过滤\n";
echo str_repeat("=", 60) . "\n\n";

// 生成测试Feed
$product_id = 47;
$product = wc_get_product($product_id);

global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$product_cat_ids = $product->get_category_ids();

$mapped_category = null;
foreach ($product_cat_ids as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$map_table} WHERE wc_category_id = %d",
        $cat_id
    ), ARRAY_A);
    if ($mapping) {
        $mapped_category = $mapping;
        break;
    }
}

$attribute_rules = !empty($mapped_category['walmart_attributes'])
    ? json_decode($mapped_category['walmart_attributes'], true)
    : null;

$mapper = new Woo_Walmart_Product_Mapper();
$walmart_data = $mapper->map(
    $product,
    $mapped_category['walmart_category_path'],
    '763437444167',
    $attribute_rules,
    1,
    'CA'
);

$visible = $walmart_data['MPItem'][0]['Visible'] ?? [];

// 检查关键字段
$fields_to_check = ['numberOfDrawers', 'numberOfShelves'];

echo "检查结果:\n";
echo str_repeat("-", 60) . "\n";

foreach ($fields_to_check as $field) {
    if (isset($visible[$field])) {
        $value = $visible[$field];
        $is_regex = is_string($value) && preg_match('/^\/.*\/$/', $value);

        echo "❌ 字段 {$field}: 仍然存在\n";
        echo "   值: {$value}\n";
        echo "   是正则: " . ($is_regex ? "是" : "否") . "\n";
    } else {
        echo "✅ 字段 {$field}: 已过滤（不存在）\n";
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";

// 输出所有Visible字段供检查
echo "\n所有Visible字段:\n";
echo str_repeat("-", 60) . "\n";
foreach ($visible as $key => $value) {
    $display_value = is_array($value) ? json_encode($value) : $value;
    if (strlen($display_value) > 80) {
        $display_value = substr($display_value, 0, 80) . '...';
    }
    echo "{$key}: {$display_value}\n";
}
