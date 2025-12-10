<?php
/**
 * 完整测试三个字段：sofa_and_loveseat_design, sizeDescriptor, sofa_bed_size
 * 测试从数据库读取配置 → 字段生成 → 类型转换 → 最终映射
 */

// 自动检测 WordPress 根目录
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("错误：无法找到 WordPress。\n");
}

if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

echo "=== 完整测试三个字段 ===\n\n";

global $wpdb;

// 测试的三个字段
$test_fields = [
    'sofa_and_loveseat_design',
    'sizeDescriptor',
    'sofa_bed_size'
];

// ============================================
// 步骤1: 检查字段是否在 v5_common_attributes 中定义
// ============================================
echo "【步骤1: 检查字段定义】\n";
echo str_repeat("-", 80) . "\n";

$main_file = WOO_WALMART_SYNC_PATH . 'woo-walmart-sync.php';
$content = file_get_contents($main_file);

foreach ($test_fields as $field) {
    if (strpos($content, "'attributeName' => '{$field}'") !== false) {
        echo "✅ {$field} - 已在 v5_common_attributes 中定义\n";
    } else {
        echo "❌ {$field} - 未在 v5_common_attributes 中定义\n";
    }
}

echo "\n";

// ============================================
// 步骤2: 检查字段是否在分类映射中配置
// ============================================
echo "【步骤2: 检查分类映射配置】\n";
echo str_repeat("-", 80) . "\n";

// 查找沙发分类映射
$mapping = $wpdb->get_row("
    SELECT *
    FROM {$wpdb->prefix}walmart_category_map
    WHERE walmart_category_path LIKE '%Sofa%' OR walmart_category_path LIKE '%Couch%'
    LIMIT 1
");

if (!$mapping) {
    echo "❌ 找不到沙发相关的分类映射\n";
    echo "请先创建分类映射\n\n";
    exit;
}

echo "分类映射 ID: {$mapping->id}\n";
echo "Walmart分类: {$mapping->walmart_category_path}\n\n";

$attributes = json_decode($mapping->walmart_attributes, true);

if (!is_array($attributes)) {
    echo "❌ walmart_attributes 不是有效的 JSON\n\n";
    exit;
}

echo "总字段数: " . count($attributes['name'] ?? []) . "\n\n";

$field_configs = [];
foreach ($test_fields as $field) {
    $found = false;
    if (isset($attributes['name'])) {
        $index = array_search($field, $attributes['name']);
        if ($index !== false) {
            $found = true;
            $field_configs[$field] = [
                'index' => $index,
                'type' => $attributes['type'][$index] ?? '(未知)',
                'source' => $attributes['source'][$index] ?? '(空)',
            ];
            echo "✅ {$field}\n";
            echo "   索引: {$index}\n";
            echo "   类型: {$field_configs[$field]['type']}\n";
            echo "   来源: {$field_configs[$field]['source']}\n";
        }
    }
    
    if (!$found) {
        echo "❌ {$field} - 未在分类映射中配置\n";
    }
    echo "\n";
}

// ============================================
// 步骤3: 测试字段生成方法
// ============================================
echo "【步骤3: 测试字段生成方法】\n";
echo str_repeat("-", 80) . "\n";

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// 创建测试产品
$test_products = [
    [
        'name' => 'Modern Mid-Century Sofa',
        'description' => 'Comfortable queen size sleeper sofa with modern design',
    ],
    [
        'name' => 'Compact Tuxedo Loveseat',
        'description' => 'Small space-saving loveseat with tuxedo arms',
    ],
    [
        'name' => 'Large King Size Sofa Bed',
        'description' => 'Oversized convertible sofa bed, king size mattress',
    ],
];

$method_generate = $reflection->getMethod('generate_special_attribute_value');
$method_generate->setAccessible(true);

foreach ($test_products as $idx => $test_data) {
    echo "测试产品 " . ($idx + 1) . ": {$test_data['name']}\n";
    echo str_repeat("-", 40) . "\n";
    
    $product = new WC_Product_Simple();
    $product->set_name($test_data['name']);
    $product->set_description($test_data['description']);
    
    foreach ($test_fields as $field) {
        try {
            $value = $method_generate->invoke($mapper, $field, $product, 1);
            echo "  {$field}:\n";
            echo "    返回值: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            echo "    类型: " . gettype($value) . "\n";
            
            if (is_null($value)) {
                echo "    ⚠️ 返回 null\n";
            } elseif (is_array($value) && empty($value)) {
                echo "    ⚠️ 返回空数组\n";
            } else {
                echo "    ✅ 正常\n";
            }
        } catch (Exception $e) {
            echo "  {$field}: ❌ 错误 - {$e->getMessage()}\n";
        }
    }
    echo "\n";
}

// ============================================
// 步骤4: 测试类型转换
// ============================================
echo "【步骤4: 测试类型转换】\n";
echo str_repeat("-", 80) . "\n";

$method_convert = $reflection->getMethod('convert_field_data_type');
$method_convert->setAccessible(true);

$test_values = [
    'sofa_and_loveseat_design' => [
        ['Mid-Century Modern'],
        ['Tuxedo', 'Club'],
        [],
        null,
    ],
    'sizeDescriptor' => [
        'Regular',
        'Compact',
        '',
        null,
    ],
    'sofa_bed_size' => [
        'Queen',
        'King',
        null,
        '',
    ],
];

foreach ($test_values as $field => $values) {
    echo "{$field}:\n";
    foreach ($values as $value) {
        $input_display = json_encode($value, JSON_UNESCAPED_UNICODE);
        try {
            $converted = $method_convert->invoke($mapper, $field, $value, null);
            $output_display = json_encode($converted, JSON_UNESCAPED_UNICODE);
            echo "  输入: {$input_display} → 输出: {$output_display}\n";
            
            if (is_null($converted)) {
                echo "    ⚠️ 转换后为 null\n";
            } elseif (is_array($converted) && empty($converted)) {
                echo "    ⚠️ 转换后为空数组\n";
            }
        } catch (Exception $e) {
            echo "  输入: {$input_display} → ❌ 错误: {$e->getMessage()}\n";
        }
    }
    echo "\n";
}

// ============================================
// 步骤5: 测试完整映射流程
// ============================================
echo "【步骤5: 测试完整映射流程】\n";
echo str_repeat("-", 80) . "\n";

// 查找使用此分类的产品
$category_id = $mapping->wc_category_id ?? $mapping->local_category_id ?? null;

if ($category_id) {
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT object_id
        FROM {$wpdb->prefix}term_relationships
        WHERE term_taxonomy_id = %d
        LIMIT 1
    ", $category_id));
} else {
    $product_id = null;
}

if ($product_id) {
    $product = wc_get_product($product_id);
    echo "使用真实产品: {$product->get_name()} (ID: {$product_id})\n\n";
} else {
    $product = new WC_Product_Simple();
    $product->set_name('Test Modern Sofa');
    $product->set_description('Comfortable mid-century modern sofa with queen size sleeper');
    echo "使用测试产品\n\n";
}

try {
    // 使用公共的 map 方法
    $walmart_category = $mapping->walmart_category_path;
    $walmart_data = $mapper->map(
        $product,
        $walmart_category,
        '123456789012',
        $attributes,
        1
    );

    $visible_data = $walmart_data['MPItem'][0]['Visible'][$walmart_category] ?? [];
    
    echo "检查三个字段是否在最终映射数据中:\n";
    foreach ($test_fields as $field) {
        if (isset($visible_data[$field])) {
            $value = $visible_data[$field];
            $display = json_encode($value, JSON_UNESCAPED_UNICODE);
            echo "✅ {$field}: {$display}\n";
        } else {
            echo "❌ {$field}: 不存在\n";
        }
    }
    echo "\n";
    
    // 显示所有字段
    echo "Visible 部分的所有字段（前 30 个）:\n";
    $field_names = array_keys($visible_data);
    foreach (array_slice($field_names, 0, 30) as $name) {
        echo "  - {$name}\n";
    }
    if (count($field_names) > 30) {
        echo "  ... 还有 " . (count($field_names) - 30) . " 个字段\n";
    }
    
} catch (Exception $e) {
    echo "❌ 映射失败: {$e->getMessage()}\n";
    echo "堆栈跟踪:\n{$e->getTraceAsString()}\n";
}

echo "\n";

// ============================================
// 总结
// ============================================
echo str_repeat("=", 80) . "\n";
echo "【测试总结】\n";
echo str_repeat("=", 80) . "\n\n";

$all_passed = true;

foreach ($test_fields as $field) {
    echo "{$field}:\n";
    
    // 检查1: 字段定义
    $has_definition = strpos($content, "'attributeName' => '{$field}'") !== false;
    echo "  字段定义: " . ($has_definition ? '✅' : '❌') . "\n";
    if (!$has_definition) $all_passed = false;
    
    // 检查2: 分类映射配置
    $has_config = isset($field_configs[$field]);
    echo "  分类映射: " . ($has_config ? '✅' : '❌') . "\n";
    if (!$has_config) $all_passed = false;
    
    // 检查3: 字段生成
    echo "  字段生成: 见上方测试结果\n";
    
    // 检查4: 最终映射
    $in_final_data = isset($visible_data[$field]);
    echo "  最终映射: " . ($in_final_data ? '✅' : '❌') . "\n";
    if (!$in_final_data) $all_passed = false;
    
    echo "\n";
}

if ($all_passed) {
    echo "🎉 所有检查通过！三个字段都能正常工作。\n";
} else {
    echo "⚠️ 部分检查失败，请根据上方详细信息排查问题。\n";
}

echo "\n测试完成！\n";
?>

