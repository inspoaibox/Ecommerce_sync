<?php
/**
 * 加拿大市场字段格式转换测试脚本
 *
 * 功能：测试字段值从美国格式自动转换为加拿大多语言格式
 *
 * 使用方法：
 * 1. 确保主市场设置为 WALMART_CA
 * 2. 访问 http://your-site/wp-content/plugins/woo-walmart-sync/test-ca-market-conversion.php
 * 3. 查看输出的字段转换结果
 */

// 加载WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// 加载必需的类
require_once(__DIR__ . '/includes/class-product-mapper.php');

echo "<h1>🇨🇦 加拿大市场字段格式转换测试</h1>";
echo "<style>
    body { font-family: 'Courier New', monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
    h3 { color: #666; }
    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #0066cc; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .info { color: #17a2b8; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #0066cc; color: white; }
    tr:nth-child(even) { background: #f8f9fa; }
</style>";

// ========================================
// 1. 环境检查
// ========================================
echo "<div class='section'>";
echo "<h2>📋 环境检查</h2>";

$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);

echo "<table>";
echo "<tr><th>配置项</th><th>当前值</th><th>状态</th></tr>";
echo "<tr><td>Business Unit</td><td>{$business_unit}</td><td>" .
     ($market_code === 'CA' ? "<span class='success'>✓ 已设置为加拿大</span>" : "<span class='warning'>⚠ 当前为{$market_code}市场</span>") .
     "</td></tr>";
echo "<tr><td>Market Code</td><td>{$market_code}</td><td></td></tr>";

// 检查spec文件
$spec_file = __DIR__ . '/api/CA_MP_ITEM_INTL_SPEC.json';
$spec_exists = file_exists($spec_file);
echo "<tr><td>CA Spec File</td><td>" . basename($spec_file) . "</td><td>" .
     ($spec_exists ? "<span class='success'>✓ 存在</span>" : "<span class='error'>✗ 缺失</span>") .
     "</td></tr>";

echo "</table>";
echo "</div>";

// ========================================
// 2. 获取测试产品
// ========================================
echo "<div class='section'>";
echo "<h2>🛋️ 测试产品</h2>";

// 获取第一个已发布的产品
$args = [
    'post_type' => 'product',
    'posts_per_page' => 1,
    'post_status' => 'publish',
    'orderby' => 'ID',
    'order' => 'DESC'
];

$products = get_posts($args);

if (empty($products)) {
    echo "<p class='error'>✗ 未找到可用的测试产品</p>";
    exit;
}

$product_id = $products[0]->ID;
$product = wc_get_product($product_id);

echo "<table>";
echo "<tr><th>属性</th><th>值</th></tr>";
echo "<tr><td>Product ID</td><td>{$product_id}</td></tr>";
echo "<tr><td>Product Name</td><td>{$product->get_name()}</td></tr>";
echo "<tr><td>SKU</td><td>{$product->get_sku()}</td></tr>";
echo "<tr><td>Price</td><td>\${$product->get_price()}</td></tr>";
echo "</table>";
echo "</div>";

// ========================================
// 3. 获取分类映射
// ========================================
echo "<div class='section'>";
echo "<h2>🗂️ 分类映射</h2>";

global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$product_cat_ids = $product->get_category_ids();

if (empty($product_cat_ids)) {
    echo "<p class='error'>✗ 产品未分配任何分类</p>";
    exit;
}

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
    echo "<p class='error'>✗ 未找到分类映射</p>";
    exit;
}

echo "<table>";
echo "<tr><th>配置项</th><th>值</th></tr>";
echo "<tr><td>WC Category ID</td><td>{$mapped_category['wc_category_id']}</td></tr>";
echo "<tr><td>Walmart Category</td><td>{$mapped_category['walmart_category_name']}</td></tr>";
echo "<tr><td>Walmart Category ID</td><td>{$mapped_category['walmart_category_id']}</td></tr>";
echo "</table>";

// 获取属性映射规则
$attr_table = $wpdb->prefix . 'walmart_attributes';
$attribute_rules = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$attr_table} WHERE wc_category_id = %d ORDER BY display_order",
    $mapped_category['wc_category_id']
), ARRAY_A);

// 转换为mapper期望的格式
$rules = ['name' => [], 'type' => [], 'source' => [], 'format' => []];
foreach ($attribute_rules as $rule) {
    $rules['name'][] = $rule['walmart_attribute_name'];
    $rules['type'][] = $rule['defaultType'];
    $rules['source'][] = $rule['wc_attribute_label'];
    $rules['format'][] = $rule['format'] ?? '';
}

echo "<p><span class='info'>ℹ</span> 已加载 " . count($attribute_rules) . " 条属性映射规则</p>";
echo "</div>";

// ========================================
// 4. 执行映射（美国市场）
// ========================================
echo "<div class='section'>";
echo "<h2>🇺🇸 美国市场映射（对照组）</h2>";

$mapper_us = new Woo_Walmart_Product_Mapper();
$walmart_data_us = $mapper_us->map(
    $product,
    $mapped_category['walmart_category_name'],
    '123456789012', // 测试UPC
    $rules,
    1,
    'US' // 明确指定美国市场
);

echo "<h3>Visible字段示例（前5个）:</h3>";
$visible_us = $walmart_data_us['MPItem'][0]['Visible'][$mapped_category['walmart_category_name']] ?? [];
$sample_us = array_slice($visible_us, 0, 5, true);

echo "<table>";
echo "<tr><th>字段名</th><th>值类型</th><th>值</th></tr>";
foreach ($sample_us as $field => $value) {
    $type = gettype($value);
    $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $value;
    echo "<tr><td><strong>{$field}</strong></td><td>{$type}</td><td><pre>{$display}</pre></td></tr>";
}
echo "</table>";
echo "</div>";

// ========================================
// 5. 执行映射（加拿大市场）
// ========================================
echo "<div class='section'>";
echo "<h2>🇨🇦 加拿大市场映射（转换组）</h2>";

$mapper_ca = new Woo_Walmart_Product_Mapper();
$walmart_data_ca = $mapper_ca->map(
    $product,
    $mapped_category['walmart_category_name'],
    '123456789012', // 测试UPC
    $rules,
    1,
    'CA' // 加拿大市场
);

echo "<h3>Visible字段示例（前5个）:</h3>";
$visible_ca = $walmart_data_ca['MPItem'][0]['Visible'][$mapped_category['walmart_category_name']] ?? [];
$sample_ca = array_slice($visible_ca, 0, 5, true);

echo "<table>";
echo "<tr><th>字段名</th><th>值类型</th><th>值</th></tr>";
foreach ($sample_ca as $field => $value) {
    $type = gettype($value);
    $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $value;
    echo "<tr><td><strong>{$field}</strong></td><td>{$type}</td><td><pre>{$display}</pre></td></tr>";
}
echo "</table>";
echo "</div>";

// ========================================
// 6. 对比分析
// ========================================
echo "<div class='section'>";
echo "<h2>🔍 字段格式对比分析</h2>";

echo "<table>";
echo "<tr><th>字段名</th><th>US格式</th><th>CA格式</th><th>转换状态</th></tr>";

// 对比前10个字段
$fields_to_compare = array_slice(array_keys($visible_us), 0, 10);

foreach ($fields_to_compare as $field) {
    $us_value = $visible_us[$field] ?? null;
    $ca_value = $visible_ca[$field] ?? null;

    $us_display = is_array($us_value) ? json_encode($us_value, JSON_UNESCAPED_UNICODE) : $us_value;
    $ca_display = is_array($ca_value) ? json_encode($ca_value, JSON_UNESCAPED_UNICODE) : $ca_value;

    // 检测是否转换为多语言格式
    $is_multilingual = false;
    if (is_array($ca_value)) {
        if (isset($ca_value['en'])) {
            $is_multilingual = true;
            $status = "<span class='success'>✓ 转换为多语言对象</span>";
        } elseif (!empty($ca_value) && is_array($ca_value[0]) && isset($ca_value[0]['en'])) {
            $is_multilingual = true;
            $status = "<span class='success'>✓ 转换为多语言数组</span>";
        } else {
            $status = "<span class='info'>- 保持原格式</span>";
        }
    } else {
        $status = "<span class='info'>- 保持原格式</span>";
    }

    echo "<tr>";
    echo "<td><strong>{$field}</strong></td>";
    echo "<td>" . (strlen($us_display) > 50 ? substr($us_display, 0, 50) . "..." : $us_display) . "</td>";
    echo "<td>" . (strlen($ca_display) > 50 ? substr($ca_display, 0, 50) . "..." : $ca_display) . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// ========================================
// 7. Feed格式验证
// ========================================
echo "<div class='section'>";
echo "<h2>📦 Feed格式验证</h2>";

echo "<h3>美国市场 Feed Header:</h3>";
echo "<pre>" . json_encode($walmart_data_us['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>加拿大市场 Feed Header:</h3>";
echo "<pre>" . json_encode($walmart_data_ca['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

// 验证关键差异
$us_header = $walmart_data_us['MPItemFeedHeader'];
$ca_header = $walmart_data_ca['MPItemFeedHeader'];

echo "<h3>关键差异:</h3>";
echo "<table>";
echo "<tr><th>配置项</th><th>US值</th><th>CA值</th><th>验证</th></tr>";

$checks = [
    ['key' => 'version', 'us_expected' => '5.0', 'ca_expected' => '3.16'],
    ['key' => 'mart', 'us_expected' => null, 'ca_expected' => 'WALMART_CA'],
    ['key' => 'businessUnit', 'us_expected' => 'WALMART_US', 'ca_expected' => null]
];

foreach ($checks as $check) {
    $key = $check['key'];
    $us_value = $us_header[$key] ?? 'N/A';
    $ca_value = $ca_header[$key] ?? 'N/A';

    // 对于version字段，只检查开头
    if ($key === 'version') {
        $us_ok = strpos($us_value, $check['us_expected']) === 0;
        $ca_ok = $ca_value === $check['ca_expected'];
    } else {
        $us_ok = $check['us_expected'] === null || $us_value === $check['us_expected'];
        $ca_ok = $check['ca_expected'] === null || $ca_value === $check['ca_expected'];
    }

    $status = ($us_ok && $ca_ok) ? "<span class='success'>✓ 正确</span>" : "<span class='error'>✗ 异常</span>";

    echo "<tr>";
    echo "<td><strong>{$key}</strong></td>";
    echo "<td>{$us_value}</td>";
    echo "<td>{$ca_value}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// ========================================
// 8. 测试总结
// ========================================
echo "<div class='section'>";
echo "<h2>✅ 测试总结</h2>";

// 统计多语言字段数量
$multilingual_count = 0;
foreach ($visible_ca as $field => $value) {
    if (is_array($value)) {
        if (isset($value['en']) || (!empty($value) && is_array($value[0]) && isset($value[0]['en']))) {
            $multilingual_count++;
        }
    }
}

echo "<ul style='font-size: 16px; line-height: 2;'>";
echo "<li><span class='success'>✓</span> 美国市场字段总数: <strong>" . count($visible_us) . "</strong></li>";
echo "<li><span class='success'>✓</span> 加拿大市场字段总数: <strong>" . count($visible_ca) . "</strong></li>";
echo "<li><span class='success'>✓</span> 已转换为多语言格式: <strong>{$multilingual_count}</strong> 个字段</li>";
echo "<li><span class='success'>✓</span> Feed Header格式: <strong>" . ($ca_header['version'] === '3.16' ? '正确' : '异常') . "</strong></li>";
echo "<li><span class='success'>✓</span> 市场代码传递: <strong>" . ($market_code === 'CA' ? '正确' : '异常') . "</strong></li>";
echo "</ul>";

if ($multilingual_count > 0) {
    echo "<p style='font-size: 18px; padding: 20px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;'>";
    echo "<strong>🎉 转换功能正常工作！</strong><br>";
    echo "已成功将 {$multilingual_count} 个字段转换为加拿大市场多语言格式。";
    echo "</p>";
} else {
    echo "<p style='font-size: 18px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;'>";
    echo "<strong>⚠️ 注意</strong><br>";
    echo "未检测到多语言字段转换。这可能是因为当前分类的字段不需要多语言格式，或者spec文件中没有多语言字段定义。";
    echo "</p>";
}

echo "</div>";

echo "<div class='section' style='text-align: center; color: #666;'>";
echo "<p>测试完成时间: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>🔧 Generated by CA Market Conversion Test Script</p>";
echo "</div>";
