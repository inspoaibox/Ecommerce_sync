<?php
/**
 * CA Feed 必需字段验证
 * 检查是否缺少加拿大市场的关键必需字段
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 CA Feed 必需字段验证</h1>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
    h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #0066cc; white-space: pre-wrap; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: top; }
    th { background: #0066cc; color: white; }
    .missing { background: #f8d7da; }
    .present { background: #d4edda; }
</style>";

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

echo "<div class='section'>";
echo "<h2>📦 测试产品</h2>";
echo "<p><strong>SKU:</strong> {$product->get_sku()}</p>";
echo "</div>";

// 检查Feed Header必需字段
echo "<div class='section'>";
echo "<h2>📋 Feed Header 必需字段</h2>";

$header = $walmart_data['MPItemFeedHeader'] ?? [];

$required_header_fields = [
    'version' => '3.16',
    'mart' => 'WALMART_CA',
    'sellingChannel' => 'marketplace',
    'processMode' => 'REPLACE',
    'subset' => 'EXTERNAL'
];

echo "<table>";
echo "<tr><th>字段</th><th>必需值</th><th>实际值</th><th>状态</th></tr>";

foreach ($required_header_fields as $field => $required_value) {
    $actual_value = $header[$field] ?? null;
    $match = ($actual_value === $required_value);
    $row_class = $match ? 'present' : 'missing';
    $status = $match ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>";

    echo "<tr class='{$row_class}'>";
    echo "<td><strong>{$field}</strong></td>";
    echo "<td>{$required_value}</td>";
    echo "<td>" . ($actual_value ?? '<em>缺失</em>') . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// 检查Orderable必需字段
echo "<div class='section'>";
echo "<h2>📦 Orderable 必需字段</h2>";

$orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];

$required_orderable = [
    'sku' => '必需 - 产品SKU',
    'productIdentifiers' => '必需 - UPC等标识符',
    'price' => '必需 - 价格'
];

echo "<table>";
echo "<tr><th>字段</th><th>说明</th><th>当前值</th><th>状态</th></tr>";

foreach ($required_orderable as $field => $description) {
    $present = isset($orderable[$field]);
    $value = $present ? $orderable[$field] : null;
    $row_class = $present ? 'present' : 'missing';
    $status = $present ? "<span class='success'>✓ 存在</span>" : "<span class='error'>✗ 缺失</span>";

    $display_value = '';
    if ($present) {
        if (is_array($value)) {
            $display_value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $display_value = $value;
        }
    }

    echo "<tr class='{$row_class}'>";
    echo "<td><strong>{$field}</strong></td>";
    echo "<td>{$description}</td>";
    echo "<td>" . ($present ? htmlspecialchars(substr($display_value, 0, 100)) : '<em>缺失</em>') . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// 检查Visible必需字段
echo "<div class='section'>";
echo "<h2>👁️ Visible 必需字段</h2>";

$visible = $walmart_data['MPItem'][0]['Visible'] ?? [];

// CA市场最基本的必需字段
$required_visible = [
    'productName' => '必需 - 产品名称',
    'mainImageUrl' => '必需 - 主图URL',
    'brand' => '必需 - 品牌',
    'shortDescription' => '推荐 - 简短描述'
];

echo "<table>";
echo "<tr><th>字段</th><th>说明</th><th>当前值</th><th>状态</th></tr>";

foreach ($required_visible as $field => $description) {
    $present = isset($visible[$field]);
    $value = $present ? $visible[$field] : null;
    $row_class = $present ? 'present' : 'missing';
    $status = $present ? "<span class='success'>✓ 存在</span>" : "<span class='error'>✗ 缺失</span>";

    $display_value = '';
    if ($present) {
        if (is_array($value)) {
            $display_value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $display_value = $value;
        }
    }

    echo "<tr class='{$row_class}'>";
    echo "<td><strong>{$field}</strong></td>";
    echo "<td>{$description}</td>";
    echo "<td>" . ($present ? htmlspecialchars(substr($display_value, 0, 100)) : '<em>缺失</em>') . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// 检查可能导致NullPointerException的问题
echo "<div class='section'>";
echo "<h2>⚠️ 潜在问题检查</h2>";

$issues = [];

// 检查1: 空数组
foreach ($visible as $field => $value) {
    if (is_array($value) && empty($value)) {
        $issues[] = "字段 <strong>{$field}</strong> 是空数组";
    }
}

// 检查2: null值
foreach ($visible as $field => $value) {
    if (is_null($value)) {
        $issues[] = "字段 <strong>{$field}</strong> 是 null";
    }
}

// 检查3: 无效的正则表达式值
if (isset($visible['numberOfDrawers']) && preg_match('/^\/.*\/$/', $visible['numberOfDrawers'])) {
    $issues[] = "字段 <strong>numberOfDrawers</strong> 包含正则表达式格式: {$visible['numberOfDrawers']}";
}
if (isset($visible['numberOfShelves']) && preg_match('/^\/.*\/$/', $visible['numberOfShelves'])) {
    $issues[] = "字段 <strong>numberOfShelves</strong> 包含正则表达式格式: {$visible['numberOfShelves']}";
}

// 检查4: 图片URL格式
if (isset($visible['mainImageUrl'])) {
    $url = $visible['mainImageUrl'];
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $issues[] = "字段 <strong>mainImageUrl</strong> 不是有效的URL: {$url}";
    }
    if (strpos($url, '?') !== false) {
        $issues[] = "字段 <strong>mainImageUrl</strong> 包含查询参数（可能导致问题）";
    }
}

// 检查5: UPC格式
if (isset($orderable['productIdentifiers']['productId'])) {
    $upc = $orderable['productIdentifiers']['productId'];
    if (!is_numeric($upc) || strlen($upc) != 12) {
        $issues[] = "UPC格式可能不正确: {$upc}（应该是12位数字）";
    }
}

if (empty($issues)) {
    echo "<p class='success'>✓ 未发现明显问题</p>";
} else {
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li class='warning'>{$issue}</li>";
    }
    echo "</ul>";
}

echo "</div>";

// 显示完整JSON供参考
echo "<div class='section'>";
echo "<h2>📄 完整Feed数据</h2>";
echo "<pre>" . json_encode($walmart_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

echo "<div class='section' style='text-align: center; color: #666;'>";
echo "<p>验证时间: " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";
