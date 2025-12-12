<?php
/**
 * 检查完整的CA Feed结构
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 CA Feed完整结构检查\n";
echo str_repeat("=", 70) . "\n\n";

// 生成Feed
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

// 检查Header
echo "📋 Header:\n";
echo str_repeat("-", 70) . "\n";
$header = $walmart_data['MPItemFeedHeader'];
foreach ($header as $key => $value) {
    if (is_array($value)) {
        echo "  $key: " . json_encode($value) . "\n";
    } else {
        echo "  $key: $value\n";
    }
}

// 检查Orderable
echo "\n📦 Orderable:\n";
echo str_repeat("-", 70) . "\n";
$orderable = $walmart_data['MPItem'][0]['Orderable'];
foreach ($orderable as $key => $value) {
    $type = gettype($value);
    if (is_array($value)) {
        if (isset($value['en'])) {
            echo "  ✅ $key: {\"en\": \"...\"} (多语言对象)\n";
        } elseif (isset($value[0]['en'])) {
            echo "  ✅ $key: [{\"en\": \"...\"}, ...] (多语言数组, " . count($value) . "项)\n";
        } elseif (isset($value['unit']) && isset($value['measure'])) {
            echo "  ✅ $key: {measure: {$value['measure']}, unit: \"{$value['unit']}\"}\n";
        } else {
            echo "  $key: " . json_encode($value) . "\n";
        }
    } else {
        echo "  $key: $value ($type)\n";
    }
}

// 检查Visible
echo "\n👁️ Visible:\n";
echo str_repeat("-", 70) . "\n";
$visible = $walmart_data['MPItem'][0]['Visible'];
$count = 0;
foreach ($visible as $key => $value) {
    $count++;
    if ($count > 30) {
        echo "  ... 还有 " . (count($visible) - 30) . " 个字段\n";
        break;
    }
    $type = gettype($value);
    if (is_array($value)) {
        if (isset($value['unit']) && isset($value['measure'])) {
            echo "  $key: measurement\n";
        } else {
            echo "  $key: array[" . count($value) . "]\n";
        }
    } else {
        $preview = is_string($value) && strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value;
        echo "  $key: $preview\n";
    }
}

// 关键检查点
echo "\n\n📊 关键检查点:\n";
echo str_repeat("=", 70) . "\n";

// 1. locale字段
$has_locale = isset($header['locale']);
echo "1. Header有locale字段: " . ($has_locale ? "✅ " . json_encode($header['locale']) : "❌ 缺失") . "\n";

// 2. ShippingWeight格式
$sw = $orderable['ShippingWeight'] ?? null;
$sw_ok = is_array($sw) && isset($sw['unit']) && isset($sw['measure']);
echo "2. ShippingWeight是对象格式: " . ($sw_ok ? "✅" : "❌ (当前: " . json_encode($sw) . ")") . "\n";

// 3. productName多语言
$pn = $orderable['productName'] ?? null;
$pn_ok = is_array($pn) && isset($pn['en']);
echo "3. productName多语言格式: " . ($pn_ok ? "✅" : "❌") . "\n";

// 4. brand多语言
$br = $orderable['brand'] ?? null;
$br_ok = is_array($br) && isset($br['en']);
echo "4. brand多语言格式: " . ($br_ok ? "✅" : "❌") . "\n";

// 5. shortDescription多语言
$sd = $orderable['shortDescription'] ?? null;
$sd_ok = is_array($sd) && isset($sd['en']);
echo "5. shortDescription多语言格式: " . ($sd_ok ? "✅" : "❌ (在Orderable中: " . (isset($orderable['shortDescription']) ? "有" : "无") . ")") . "\n";

// 6. keyFeatures多语言数组
$kf = $orderable['keyFeatures'] ?? null;
$kf_ok = is_array($kf) && isset($kf[0]['en']);
echo "6. keyFeatures多语言数组格式: " . ($kf_ok ? "✅ (" . count($kf) . "项)" : "❌") . "\n";

// 7. Visible无分类wrapper
$visible_direct = !isset($visible['CA_FURNITURE']) && !isset($visible['Furniture']);
echo "7. Visible无分类wrapper: " . ($visible_direct ? "✅" : "❌") . "\n";

echo "\n";
