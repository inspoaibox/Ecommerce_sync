<?php
/**
 * 检查numberOfDrawers和numberOfShelves的映射类型
 */

require_once(__DIR__ . '/../../../wp-load.php');

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 检查正则表达式字段的映射类型\n";
echo str_repeat("=", 60) . "\n\n";

// 获取产品的分类映射
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

if (!$mapped_category) {
    echo "❌ 未找到分类映射\n";
    exit;
}

$attribute_rules = !empty($mapped_category['walmart_attributes'])
    ? json_decode($mapped_category['walmart_attributes'], true)
    : null;

if (empty($attribute_rules)) {
    echo "❌ 未找到属性规则\n";
    exit;
}

// 查找这两个字段
$fields_to_check = ['numberOfDrawers', 'numberOfShelves'];

foreach ($fields_to_check as $field) {
    echo "字段: {$field}\n";
    echo str_repeat("-", 60) . "\n";

    $found = false;
    foreach ($attribute_rules['name'] as $index => $name) {
        if ($name === $field) {
            $found = true;
            $type = $attribute_rules['type'][$index] ?? 'N/A';
            $source = $attribute_rules['source'][$index] ?? 'N/A';
            $format = $attribute_rules['format'][$index] ?? 'N/A';

            echo "  索引: {$index}\n";
            echo "  类型: {$type}\n";
            echo "  来源: {$source}\n";
            echo "  格式: {$format}\n";

            // 检查是否是正则表达式
            if (is_string($source) && preg_match('/^\/.*\/$/', $source)) {
                echo "  ⚠️  来源包含正则表达式！\n";
            }

            break;
        }
    }

    if (!$found) {
        echo "  ❓ 未找到该字段\n";
    }

    echo "\n";
}
