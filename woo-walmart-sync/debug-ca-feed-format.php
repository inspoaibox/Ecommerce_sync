<?php
/**
 * 加拿大市场Feed格式诊断脚本
 * 检查实际生成的Feed数据是否符合CA规范
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 加拿大市场Feed格式诊断</h1>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
    h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #0066cc; white-space: pre-wrap; word-wrap: break-word; }
    .error { color: #dc3545; font-weight: bold; }
    .success { color: #28a745; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: top; }
    th { background: #0066cc; color: white; }
    tr:nth-child(even) { background: #f8f9fa; }
</style>";

// 获取最近同步的产品
$args = [
    'post_type' => 'product',
    'posts_per_page' => 1,
    'post_status' => 'publish',
    'orderby' => 'ID',
    'order' => 'DESC'
];

$products = get_posts($args);

if (empty($products)) {
    echo "<p class='error'>未找到产品</p>";
    exit;
}

$product_id = $products[0]->ID;
$product = wc_get_product($product_id);

echo "<div class='section'>";
echo "<h2>📦 测试产品</h2>";
echo "<p><strong>ID:</strong> {$product_id}</p>";
echo "<p><strong>Name:</strong> {$product->get_name()}</p>";
echo "<p><strong>SKU:</strong> {$product->get_sku()}</p>";
echo "</div>";

// 获取分类映射
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$product_cat_ids = $product->get_category_ids();

if (empty($product_cat_ids)) {
    echo "<p class='error'>产品未分配分类</p>";
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
    echo "<p class='error'>未找到分类映射</p>";
    exit;
}

// 🔧 修复：从 walmart_attributes 列加载属性（JSON格式）
$attribute_rules = !empty($mapped_category['walmart_attributes'])
    ? json_decode($mapped_category['walmart_attributes'], true)
    : null;

if (empty($attribute_rules) || !isset($attribute_rules['name'])) {
    echo "<p class='error'>未找到属性映射规则</p>";
    $attribute_rules = ['name' => [], 'type' => [], 'source' => [], 'format' => []];
}

// 获取正确的分类名称
$walmart_category_id = $mapped_category['walmart_category_path'];
$walmart_category_name = '';

// 🔧 从 CA spec 文件中查找分类名称
$spec_file = __DIR__ . '/api/CA_MP_ITEM_INTL_SPEC.json';
if (file_exists($spec_file)) {
    $spec = json_decode(file_get_contents($spec_file), true);

    if ($spec && isset($spec['definitions'])) {
        foreach ($spec['definitions'] as $def_name => $definition) {
            if (isset($definition['properties']['Visible']['properties'])) {
                $visible_props = $definition['properties']['Visible']['properties'];

                // 尝试直接匹配
                if (isset($visible_props[$walmart_category_id])) {
                    $walmart_category_name = $walmart_category_id;
                    break;
                }

                // 如果是 CA_XXXX 格式，尝试转换
                if (strpos($walmart_category_id, 'CA_') === 0) {
                    $clean_name = str_replace('CA_', '', $walmart_category_id);

                    // 尝试大写
                    if (isset($visible_props[$clean_name])) {
                        $walmart_category_name = $clean_name;
                        break;
                    }

                    // 尝试首字母大写
                    $ucfirst_name = ucfirst(strtolower($clean_name));
                    if (isset($visible_props[$ucfirst_name])) {
                        $walmart_category_name = $ucfirst_name;
                        break;
                    }
                }
            }
        }
    }
}

if (empty($walmart_category_name)) {
    $walmart_category_name = $walmart_category_id;
}

echo "<div class='section'>";
echo "<h2>🗂️ 分类映射</h2>";
echo "<p><strong>Walmart Category ID:</strong> {$walmart_category_id}</p>";
echo "<p><strong>Walmart Category Name:</strong> {$walmart_category_name}</p>";
echo "<p><strong>Attributes Count:</strong> " . count($attribute_rules['name']) . "</p>";
echo "</div>";

// 生成Feed数据（加拿大市场）
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);

echo "<div class='section'>";
echo "<h2>🌍 市场设置</h2>";
echo "<p><strong>Business Unit:</strong> {$business_unit}</p>";
echo "<p><strong>Market Code:</strong> {$market_code}</p>";

if ($market_code !== 'CA') {
    echo "<p class='warning'>⚠️ 警告：当前市场不是加拿大(CA)，是 {$market_code}</p>";
}
echo "</div>";

// 映射产品
$mapper = new Woo_Walmart_Product_Mapper();
$walmart_data = $mapper->map(
    $product,
    $walmart_category_name,  // 使用转换后的分类名称
    '123456789012',
    $attribute_rules,
    1,
    $market_code
);

echo "<div class='section'>";
echo "<h2>📄 完整Feed数据</h2>";
echo "<pre>" . json_encode($walmart_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";
echo "</div>";

// 检查Feed Header
echo "<div class='section'>";
echo "<h2>✅ Feed Header 验证</h2>";

$header = $walmart_data['MPItemFeedHeader'] ?? [];

$checks = [
    ['field' => 'version', 'expected' => '3.16', 'actual' => $header['version'] ?? 'N/A'],
    ['field' => 'mart', 'expected' => 'WALMART_CA', 'actual' => $header['mart'] ?? 'N/A'],
    ['field' => 'sellingChannel', 'expected' => 'marketplace', 'actual' => $header['sellingChannel'] ?? 'N/A'],
    ['field' => 'processMode', 'expected' => 'REPLACE', 'actual' => $header['processMode'] ?? 'N/A'],
    ['field' => 'subset', 'expected' => 'EXTERNAL', 'actual' => $header['subset'] ?? 'N/A'],
];

echo "<table>";
echo "<tr><th>字段</th><th>期望值</th><th>实际值</th><th>状态</th></tr>";

foreach ($checks as $check) {
    $match = ($check['actual'] === $check['expected']);
    $status = $match ? "<span class='success'>✓ 正确</span>" : "<span class='error'>✗ 错误</span>";

    echo "<tr>";
    echo "<td><strong>{$check['field']}</strong></td>";
    echo "<td>{$check['expected']}</td>";
    echo "<td>{$check['actual']}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

// 检查是否有businessUnit字段（CA不应该有）
if (isset($header['businessUnit'])) {
    echo "<p class='error'>✗ 错误：加拿大Feed不应包含 businessUnit 字段</p>";
}

// 检查是否有locale字段（CA不应该有）
if (isset($header['locale'])) {
    echo "<p class='error'>✗ 错误：加拿大Feed Header不应包含 locale 字段（应该在各字段内）</p>";
}

echo "</div>";

// 检查Orderable字段
echo "<div class='section'>";
echo "<h2>📋 Orderable 字段</h2>";

$orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];

echo "<table>";
echo "<tr><th>字段名</th><th>值类型</th><th>值</th></tr>";

foreach ($orderable as $field => $value) {
    $type = gettype($value);
    $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;

    echo "<tr>";
    echo "<td><strong>{$field}</strong></td>";
    echo "<td>{$type}</td>";
    echo "<td>" . htmlspecialchars(mb_substr($display, 0, 100)) . (mb_strlen($display) > 100 ? '...' : '') . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// 检查Visible字段和多语言转换
echo "<div class='section'>";
echo "<h2>👁️ Visible 字段分析</h2>";

$visible = $walmart_data['MPItem'][0]['Visible'] ?? [];

// 🔧 修复：CA市场直接在Visible下，不需要获取分类层级
// 检查是否有直接字段（CA格式）或分类层级（US格式）
if (isset($visible['productName'])) {
    // CA格式：直接字段
    $category_fields = $visible;
    echo "<p class='success'>✓ 检测到加拿大格式（直接字段）</p>";
} else {
    // US格式：分类层级
    $category_fields = reset($visible) ?? [];
    echo "<p class='info'>检测到美国格式（分类层级）</p>";
}

$multilingual_count = 0;
$non_multilingual_count = 0;
$multilingual_fields = [];

echo "<table>";
echo "<tr><th>字段名</th><th>值类型</th><th>是否多语言</th><th>值示例</th></tr>";

foreach ($category_fields as $field => $value) {
    $type = gettype($value);
    $is_multilingual = false;

    // 检测多语言格式
    if (is_array($value)) {
        if (isset($value['en'])) {
            $is_multilingual = true;
            $multilingual_count++;
            $multilingual_fields[] = $field;
        } elseif (!empty($value) && is_array($value[0]) && isset($value[0]['en'])) {
            $is_multilingual = true;
            $multilingual_count++;
            $multilingual_fields[] = $field;
        } else {
            $non_multilingual_count++;
        }
    } else {
        $non_multilingual_count++;
    }

    $status = $is_multilingual ? "<span class='success'>✓ 多语言</span>" : "<span>- 普通</span>";
    $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;

    echo "<tr>";
    echo "<td><strong>{$field}</strong></td>";
    echo "<td>{$type}</td>";
    echo "<td>{$status}</td>";
    echo "<td>" . htmlspecialchars(mb_substr($display, 0, 80)) . (mb_strlen($display) > 80 ? '...' : '') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>统计</h3>";
echo "<ul>";
echo "<li>多语言字段数量: <strong class='success'>{$multilingual_count}</strong></li>";
echo "<li>普通字段数量: <strong>{$non_multilingual_count}</strong></li>";
echo "<li>总字段数: <strong>" . count($category_fields) . "</strong></li>";
echo "</ul>";

if ($multilingual_count > 0) {
    echo "<h3>已转换的多语言字段列表：</h3>";
    echo "<ul>";
    foreach ($multilingual_fields as $field) {
        echo "<li><strong>{$field}</strong></li>";
    }
    echo "</ul>";
}

echo "</div>";

// JSON格式验证
echo "<div class='section'>";
echo "<h2>🔍 JSON格式验证</h2>";

$json_string = json_encode($walmart_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$json_error = json_last_error();

if ($json_error === JSON_ERROR_NONE) {
    echo "<p class='success'>✓ JSON格式正确</p>";
    echo "<p><strong>JSON大小:</strong> " . number_format(strlen($json_string)) . " bytes</p>";
} else {
    echo "<p class='error'>✗ JSON格式错误: " . json_last_error_msg() . "</p>";
}

echo "</div>";

// 常见问题检查
echo "<div class='section'>";
echo "<h2>⚠️ 常见问题检查</h2>";

$issues = [];

// 检查1: 必需字段
if (!isset($orderable['sku'])) {
    $issues[] = "缺少必需字段: Orderable.sku";
}

if (!isset($orderable['productIdentifiers'])) {
    $issues[] = "缺少必需字段: Orderable.productIdentifiers";
}

// 检查2: productName
if (!isset($category_fields['productName'])) {
    $issues[] = "缺少必需字段: Visible.productName";
}

// 检查3: 图片URL格式
if (isset($category_fields['mainImageUrl'])) {
    $main_image = $category_fields['mainImageUrl'];
    if (!filter_var($main_image, FILTER_VALIDATE_URL)) {
        $issues[] = "mainImageUrl 格式无效: {$main_image}";
    }
    if (strpos($main_image, '?') !== false) {
        $issues[] = "mainImageUrl 包含查询参数（可能导致问题）: {$main_image}";
    }
}

// 检查4: 空数组
foreach ($category_fields as $field => $value) {
    if (is_array($value) && empty($value)) {
        $issues[] = "字段 {$field} 是空数组（可能导致错误）";
    }
}

// 检查5: null值
foreach ($category_fields as $field => $value) {
    if (is_null($value)) {
        $issues[] = "字段 {$field} 是 null（不应该发送null字段）";
    }
}

if (empty($issues)) {
    echo "<p class='success'>✓ 未发现明显问题</p>";
} else {
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li class='error'>{$issue}</li>";
    }
    echo "</ul>";
}

echo "</div>";

// 与spec文件对比
echo "<div class='section'>";
echo "<h2>📖 Spec文件对比</h2>";

$spec_file = __DIR__ . '/api/CA_MP_ITEM_INTL_SPEC.json';

if (file_exists($spec_file)) {
    echo "<p class='success'>✓ CA_MP_ITEM_INTL_SPEC.json 存在</p>";

    $spec = json_decode(file_get_contents($spec_file), true);

    if ($spec) {
        // 查找当前分类的spec定义
        echo "<p><strong>查找分类定义:</strong> {$walmart_category_name}</p>";

        // 遍历definitions寻找匹配的分类
        $found_category = false;
        if (isset($spec['definitions'])) {
            foreach ($spec['definitions'] as $def_name => $definition) {
                if (isset($definition['properties']['Visible']['properties'][$walmart_category_name])) {
                    $found_category = true;
                    $cat_spec = $definition['properties']['Visible']['properties'][$walmart_category_name];

                    echo "<p class='success'>✓ 找到分类定义: {$def_name}</p>";

                    // 统计多语言字段定义
                    $spec_multilingual = 0;
                    $spec_fields = $cat_spec['properties'] ?? [];

                    foreach ($spec_fields as $field_name => $field_spec) {
                        if (isset($field_spec['type'])) {
                            if ($field_spec['type'] === 'object' && isset($field_spec['properties']['en'])) {
                                $spec_multilingual++;
                            } elseif ($field_spec['type'] === 'array' &&
                                      isset($field_spec['items']['type']) &&
                                      $field_spec['items']['type'] === 'object' &&
                                      isset($field_spec['items']['properties']['en'])) {
                                $spec_multilingual++;
                            }
                        }
                    }

                    echo "<p><strong>Spec定义的多语言字段数:</strong> {$spec_multilingual}</p>";
                    echo "<p><strong>实际转换的多语言字段数:</strong> {$multilingual_count}</p>";

                    if ($spec_multilingual > 0 && $multilingual_count === 0) {
                        echo "<p class='error'>⚠️ 警告：Spec要求多语言字段，但未检测到转换</p>";
                    }

                    break;
                }
            }

            if (!$found_category) {
                echo "<p class='warning'>⚠️ 未在Spec中找到分类 {$walmart_category_name}（分类ID: {$walmart_category_id}）</p>";
            }
        }
    }
} else {
    echo "<p class='error'>✗ CA_MP_ITEM_INTL_SPEC.json 不存在</p>";
}

echo "</div>";

echo "<div class='section' style='text-align: center; color: #666;'>";
echo "<p>诊断完成时间: " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";
