<?php
/**
 * 分析CA格式问题
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 分析加拿大Feed格式问题\n";
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

$visible = $walmart_data['MPItem'][0]['Visible'] ?? [];
$orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];

// 检查可能有问题的字段格式
echo "📋 检查可能导致NullPointerException的字段\n";
echo str_repeat("-", 70) . "\n\n";

// 1. 检查所有字符串字段是否包含特殊字符
echo "1️⃣ 检查特殊字符/转义问题:\n";
$problem_fields = [];
foreach ($visible as $field => $value) {
    if (is_string($value)) {
        // 检查是否包含可能导致JSON解析问题的字符
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $problem_fields[] = "$field: 包含控制字符";
        }
        if (strpos($value, '\\') !== false && strpos($value, '\\n') === false) {
            $problem_fields[] = "$field: 包含反斜杠";
        }
        // 检查是否是正则表达式格式
        if (preg_match('/^\/.*\/$/', $value)) {
            $problem_fields[] = "$field: 正则表达式格式 → $value";
        }
    }
}

if (empty($problem_fields)) {
    echo "   ✅ 未发现特殊字符问题\n";
} else {
    foreach ($problem_fields as $p) {
        echo "   ⚠️ $p\n";
    }
}

// 2. 检查measurement对象格式
echo "\n2️⃣ 检查measurement对象格式:\n";
$measurement_fields = ['assembledProductHeight', 'assembledProductLength', 'assembledProductWidth',
                       'assembledProductWeight', 'seatHeight', 'seatBackHeight', 'tableHeight'];

foreach ($measurement_fields as $field) {
    if (isset($visible[$field])) {
        $val = $visible[$field];
        if (is_array($val)) {
            $has_measure = isset($val['measure']);
            $has_unit = isset($val['unit']);
            $measure_type = gettype($val['measure'] ?? null);

            echo "   $field: measure=" . ($has_measure ? $val['measure'] : 'N/A') .
                 " ($measure_type), unit=" . ($val['unit'] ?? 'N/A');

            if (!$has_measure || !$has_unit) {
                echo " ⚠️ 缺少字段";
            } elseif ($val['measure'] === 0 || $val['measure'] === 1) {
                echo " ⚠️ 值可能太小";
            }
            echo "\n";
        } else {
            echo "   $field: 不是对象格式 ⚠️\n";
        }
    }
}

// 3. 检查数组字段是否为空
echo "\n3️⃣ 检查空数组字段:\n";
$array_fields = [];
foreach ($visible as $field => $value) {
    if (is_array($value) && empty($value)) {
        $array_fields[] = $field;
    }
}

if (empty($array_fields)) {
    echo "   ✅ 没有空数组字段\n";
} else {
    foreach ($array_fields as $f) {
        echo "   ⚠️ $f: 空数组\n";
    }
}

// 4. 检查Orderable中的stateRestrictions格式
echo "\n4️⃣ 检查Orderable字段:\n";
if (isset($orderable['stateRestrictions'])) {
    echo "   stateRestrictions: " . json_encode($orderable['stateRestrictions']) . "\n";
    // 检查格式是否正确
    if (!is_array($orderable['stateRestrictions'])) {
        echo "   ⚠️ stateRestrictions应该是数组\n";
    }
}

if (isset($orderable['ShippingWeight'])) {
    echo "   ShippingWeight: " . $orderable['ShippingWeight'] . " (类型: " . gettype($orderable['ShippingWeight']) . ")\n";
}

if (isset($orderable['MustShipAlone'])) {
    echo "   MustShipAlone: " . $orderable['MustShipAlone'] . "\n";
}

// 5. 检查加拿大特有的多语言字段需求
echo "\n5️⃣ 检查应该是多语言格式的字段:\n";
$multilingual_fields = ['shortDescription', 'longDescription', 'keyFeatures', 'productName'];

foreach ($multilingual_fields as $field) {
    if (isset($visible[$field])) {
        $val = $visible[$field];
        $is_multilingual = false;

        if (is_array($val) && isset($val['en'])) {
            $is_multilingual = true;
        } elseif (is_array($val) && isset($val[0]) && is_array($val[0]) && isset($val[0]['en'])) {
            $is_multilingual = true;
        }

        if ($is_multilingual) {
            echo "   ✅ $field: 多语言格式\n";
        } else {
            $type = is_array($val) ? 'array' : 'string';
            $preview = is_array($val) ? '[...]' : substr($val, 0, 50) . '...';
            echo "   ⚠️ $field: 非多语言格式 ($type) → $preview\n";
        }
    }
}

// 6. 列出所有使用的字段
echo "\n6️⃣ 所有Visible字段列表:\n";
foreach ($visible as $field => $value) {
    $type = gettype($value);
    if (is_array($value)) {
        if (isset($value['measure'])) {
            $type = 'measurement';
        } elseif (isset($value['en'])) {
            $type = 'multilingual';
        } elseif (isset($value[0])) {
            $type = 'array[' . count($value) . ']';
        }
    }
    echo "   - $field ($type)\n";
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "提示: PGW可能指向特定字段的格式问题\n";
