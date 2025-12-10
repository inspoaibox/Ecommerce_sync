<?php
/**
 * 测试 Visible 结构修复
 * 直接调用 mapper->map() 方法验证 CA 市场是否正确生成无分类层级的结构
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Visible 结构修复验证</h1>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
    h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #0066cc; white-space: pre-wrap; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #0066cc; color: white; }
</style>";

// ========================================
// 1. 清除 opcache（如果启用）
// ========================================
echo "<div class='section'>";
echo "<h2>🗑️ 缓存清理</h2>";

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p class='success'>✓ OPcache 已清除</p>";
} else {
    echo "<p class='warning'>⚠ OPcache 未启用或不可用</p>";
}

echo "</div>";

// ========================================
// 2. 获取测试产品
// ========================================
echo "<div class='section'>";
echo "<h2>📦 测试产品</h2>";

$args = [
    'post_type' => 'product',
    'posts_per_page' => 1,
    'post_status' => 'publish',
    'orderby' => 'ID',
    'order' => 'DESC'
];

$products = get_posts($args);

if (empty($products)) {
    echo "<p class='error'>✗ 未找到产品</p>";
    exit;
}

$product_id = $products[0]->ID;
$product = wc_get_product($product_id);

echo "<p><strong>Product ID:</strong> {$product_id}</p>";
echo "<p><strong>Name:</strong> {$product->get_name()}</p>";
echo "<p><strong>SKU:</strong> {$product->get_sku()}</p>";
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
    echo "<p class='error'>✗ 产品未分配分类</p>";
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

$walmart_category_path = $mapped_category['walmart_category_path'];
echo "<p><strong>Walmart Category Path:</strong> {$walmart_category_path}</p>";

// 🔧 修复：从 walmart_attributes 列加载属性（JSON格式）
$attribute_rules = !empty($mapped_category['walmart_attributes'])
    ? json_decode($mapped_category['walmart_attributes'], true)
    : null;

if (empty($attribute_rules) || !isset($attribute_rules['name'])) {
    echo "<p class='warning'>⚠ 未找到属性映射规则</p>";
    $attribute_rules = ['name' => [], 'type' => [], 'source' => [], 'format' => []];
}

echo "<p><strong>Attribute Rules:</strong> " . count($attribute_rules['name']) . " 条</p>";
echo "</div>";

// ========================================
// 4. 测试美国市场映射
// ========================================
echo "<div class='section'>";
echo "<h2>🇺🇸 美国市场映射（对照组）</h2>";

$mapper_us = new Woo_Walmart_Product_Mapper();
$walmart_data_us = $mapper_us->map(
    $product,
    $walmart_category_path,  // CA_FURNITURE
    '123456789012',
    $attribute_rules,
    1,
    'US'  // 明确指定美国市场
);

echo "<h3>Visible 结构:</h3>";
echo "<pre>";
echo json_encode($walmart_data_us['MPItem'][0]['Visible'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "</pre>";

// 检查结构
$has_category_wrapper_us = isset($walmart_data_us['MPItem'][0]['Visible'][$walmart_category_path]);
echo "<p><strong>是否有分类层级:</strong> " . ($has_category_wrapper_us ? "<span class='success'>✓ 有</span>" : "<span class='error'>✗ 无</span>") . "</p>";

echo "</div>";

// ========================================
// 5. 测试加拿大市场映射
// ========================================
echo "<div class='section'>";
echo "<h2>🇨🇦 加拿大市场映射（测试组）</h2>";

$mapper_ca = new Woo_Walmart_Product_Mapper();
$walmart_data_ca = $mapper_ca->map(
    $product,
    $walmart_category_path,  // CA_FURNITURE
    '123456789012',
    $attribute_rules,
    1,
    'CA'  // 明确指定加拿大市场
);

echo "<h3>Visible 结构:</h3>";
echo "<pre>";
echo json_encode($walmart_data_ca['MPItem'][0]['Visible'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "</pre>";

// 检查结构
$visible_keys = array_keys($walmart_data_ca['MPItem'][0]['Visible']);
$has_category_wrapper_ca = isset($walmart_data_ca['MPItem'][0]['Visible'][$walmart_category_path]);
$has_direct_fields_ca = isset($walmart_data_ca['MPItem'][0]['Visible']['productName']) ||
                        isset($walmart_data_ca['MPItem'][0]['Visible']['mainImageUrl']);

echo "<p><strong>Visible 顶层键:</strong> " . implode(', ', $visible_keys) . "</p>";
echo "<p><strong>是否有分类层级:</strong> " . ($has_category_wrapper_ca ? "<span class='error'>✗ 有（错误）</span>" : "<span class='success'>✓ 无（正确）</span>") . "</p>";
echo "<p><strong>是否直接包含字段:</strong> " . ($has_direct_fields_ca ? "<span class='success'>✓ 是（正确）</span>" : "<span class='error'>✗ 否（错误）</span>") . "</p>";

echo "</div>";

// ========================================
// 6. Feed Header 对比
// ========================================
echo "<div class='section'>";
echo "<h2>📋 Feed Header 对比</h2>";

echo "<h3>美国 Header:</h3>";
echo "<pre>" . json_encode($walmart_data_us['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>加拿大 Header:</h3>";
echo "<pre>" . json_encode($walmart_data_ca['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

echo "</div>";

// ========================================
// 7. 验证结果
// ========================================
echo "<div class='section'>";
echo "<h2>✅ 验证结果</h2>";

$all_checks_passed = true;

echo "<table>";
echo "<tr><th>检查项</th><th>期望值</th><th>实际值</th><th>状态</th></tr>";

// 检查1: CA market 无分类层级
$check1 = !$has_category_wrapper_ca;
echo "<tr>";
echo "<td>CA市场无分类层级</td>";
echo "<td>无</td>";
echo "<td>" . ($has_category_wrapper_ca ? '有' : '无') . "</td>";
echo "<td>" . ($check1 ? "<span class='success'>✓ 通过</span>" : "<span class='error'>✗ 失败</span>") . "</td>";
echo "</tr>";
if (!$check1) $all_checks_passed = false;

// 检查2: CA market 直接包含字段
$check2 = $has_direct_fields_ca;
echo "<tr>";
echo "<td>CA市场直接包含字段</td>";
echo "<td>是</td>";
echo "<td>" . ($has_direct_fields_ca ? '是' : '否') . "</td>";
echo "<td>" . ($check2 ? "<span class='success'>✓ 通过</span>" : "<span class='error'>✗ 失败</span>") . "</td>";
echo "</tr>";
if (!$check2) $all_checks_passed = false;

// 检查3: US market 有分类层级
$check3 = $has_category_wrapper_us;
echo "<tr>";
echo "<td>US市场有分类层级</td>";
echo "<td>有</td>";
echo "<td>" . ($has_category_wrapper_us ? '有' : '无') . "</td>";
echo "<td>" . ($check3 ? "<span class='success'>✓ 通过</span>" : "<span class='error'>✗ 失败</span>") . "</td>";
echo "</tr>";
if (!$check3) $all_checks_passed = false;

// 检查4: CA Header version
$ca_version_check = ($walmart_data_ca['MPItemFeedHeader']['version'] === '3.16');
echo "<tr>";
echo "<td>CA Header version</td>";
echo "<td>3.16</td>";
echo "<td>{$walmart_data_ca['MPItemFeedHeader']['version']}</td>";
echo "<td>" . ($ca_version_check ? "<span class='success'>✓ 通过</span>" : "<span class='error'>✗ 失败</span>") . "</td>";
echo "</tr>";
if (!$ca_version_check) $all_checks_passed = false;

// 检查5: CA Header mart
$ca_mart_check = ($walmart_data_ca['MPItemFeedHeader']['mart'] === 'WALMART_CA');
echo "<tr>";
echo "<td>CA Header mart</td>";
echo "<td>WALMART_CA</td>";
echo "<td>{$walmart_data_ca['MPItemFeedHeader']['mart']}</td>";
echo "<td>" . ($ca_mart_check ? "<span class='success'>✓ 通过</span>" : "<span class='error'>✗ 失败</span>") . "</td>";
echo "</tr>";
if (!$ca_mart_check) $all_checks_passed = false;

echo "</table>";

if ($all_checks_passed) {
    echo "<p style='font-size: 20px; padding: 20px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;'>";
    echo "<strong>🎉 所有检查通过！</strong><br>";
    echo "Visible 结构修复已生效，CA 市场现在使用正确的无分类层级格式。";
    echo "</p>";
} else {
    echo "<p style='font-size: 20px; padding: 20px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;'>";
    echo "<strong>❌ 检查未通过</strong><br>";
    echo "请检查代码修改是否正确保存，或尝试重启 PHP-FPM。";
    echo "</p>";
}

echo "</div>";

echo "<div class='section' style='text-align: center; color: #666;'>";
echo "<p>测试时间: " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";
