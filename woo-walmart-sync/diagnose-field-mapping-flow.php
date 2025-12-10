<?php
/**
 * 深度诊断字段映射流程
 * 检查为什么 sofa_and_loveseat_design 字段配置后没有生效
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

echo "=== 深度诊断字段映射流程 ===\n\n";

if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

global $wpdb;

// ============================================
// 检查1: 查看分类映射的完整配置
// ============================================
echo "【检查1: 分类映射的完整配置】\n";
echo str_repeat("-", 80) . "\n";

$mapping = $wpdb->get_row("
    SELECT *
    FROM {$wpdb->prefix}walmart_category_map
    WHERE id = 144
");

if (!$mapping) {
    echo "❌ 找不到分类映射 ID 144\n\n";
    exit;
}

echo "分类 ID: {$mapping->id}\n";
echo "本地分类: {$mapping->wc_category_name}\n";
echo "Walmart分类: {$mapping->walmart_category_path}\n\n";

echo "walmart_attributes 字段内容:\n";
$attributes = json_decode($mapping->walmart_attributes, true);

if (!is_array($attributes)) {
    echo "❌ walmart_attributes 不是有效的 JSON 数组\n";
    echo "原始内容: {$mapping->walmart_attributes}\n\n";
    exit;
}

echo "总共配置了 " . count($attributes) . " 个字段\n\n";

// 查找 sofa_and_loveseat_design 字段
$found_field = null;
foreach ($attributes as $index => $attr) {
    if (isset($attr['name']) && $attr['name'] === 'sofa_and_loveseat_design') {
        $found_field = $attr;
        echo "✅ 找到 sofa_and_loveseat_design 字段配置\n";
        echo "索引位置: {$index}\n";
        echo "完整配置:\n";
        echo json_encode($attr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        break;
    }
}

if (!$found_field) {
    echo "❌ 未找到 sofa_and_loveseat_design 字段配置\n";
    echo "这就是问题所在！\n\n";
    
    // 显示前10个字段
    echo "当前配置的字段（前10个）:\n";
    foreach (array_slice($attributes, 0, 10) as $attr) {
        echo "  - " . ($attr['name'] ?? '(无名称)') . "\n";
    }
    echo "\n";
}

// ============================================
// 检查2: 验证字段配置的关键属性
// ============================================
if ($found_field) {
    echo "【检查2: 验证字段配置的关键属性】\n";
    echo str_repeat("-", 80) . "\n";
    
    $required_keys = ['name', 'type', 'source'];
    $missing_keys = [];
    
    foreach ($required_keys as $key) {
        if (!isset($found_field[$key])) {
            $missing_keys[] = $key;
        }
    }
    
    if (!empty($missing_keys)) {
        echo "❌ 字段配置缺少必要的键: " . implode(', ', $missing_keys) . "\n\n";
    } else {
        echo "✅ 字段配置包含所有必要的键\n\n";
        
        echo "字段名称 (name): {$found_field['name']}\n";
        echo "映射类型 (type): {$found_field['type']}\n";
        echo "来源 (source): " . ($found_field['source'] ?: '(空)') . "\n\n";
        
        // 检查类型是否正确
        if ($found_field['type'] !== 'auto_generate') {
            echo "⚠️ 警告：映射类型不是 'auto_generate'，而是 '{$found_field['type']}'\n";
            echo "   这可能导致字段不会被自动生成\n\n";
        } else {
            echo "✅ 映射类型正确：auto_generate\n\n";
        }
    }
}

// ============================================
// 检查3: 模拟产品映射流程
// ============================================
echo "【检查3: 模拟产品映射流程】\n";
echo str_repeat("-", 80) . "\n";

// 查找一个使用此分类的产品
$product_id = $wpdb->get_var($wpdb->prepare("
    SELECT object_id
    FROM {$wpdb->prefix}term_relationships
    WHERE term_taxonomy_id = %d
    LIMIT 1
", $mapping->local_category_id));

if (!$product_id) {
    echo "⚠️ 没有找到使用此分类的产品，创建测试产品\n";
    $product = new WC_Product_Simple();
    $product->set_name('Test Sofa for Diagnosis');
    $product->set_description('Modern sofa with comfortable seating');
} else {
    echo "找到产品 ID: {$product_id}\n";
    $product = wc_get_product($product_id);
    echo "产品名称: {$product->get_name()}\n";
}

echo "\n";

// 测试字段映射流程
$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// 步骤1: 测试 generate_special_attribute_value
echo "步骤1: 测试 generate_special_attribute_value\n";
$method1 = $reflection->getMethod('generate_special_attribute_value');
$method1->setAccessible(true);

try {
    $value1 = $method1->invoke($mapper, 'sofa_and_loveseat_design', $product, 1);
    echo "  返回值: " . json_encode($value1, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  类型: " . gettype($value1) . "\n";
    
    if (empty($value1)) {
        echo "  ❌ 返回值为空！\n";
    } else {
        echo "  ✅ 返回值正常\n";
    }
} catch (Exception $e) {
    echo "  ❌ 调用失败: {$e->getMessage()}\n";
}

echo "\n";

// 步骤2: 测试 convert_field_data_type
echo "步骤2: 测试 convert_field_data_type\n";
$method2 = $reflection->getMethod('convert_field_data_type');
$method2->setAccessible(true);

try {
    $value2 = $method2->invoke($mapper, 'sofa_and_loveseat_design', $value1 ?? null, null);
    echo "  输入: " . json_encode($value1 ?? null, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  输出: " . json_encode($value2, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  类型: " . gettype($value2) . "\n";
    
    if (empty($value2)) {
        echo "  ❌ 转换后为空！\n";
    } else {
        echo "  ✅ 转换正常\n";
    }
} catch (Exception $e) {
    echo "  ❌ 调用失败: {$e->getMessage()}\n";
}

echo "\n";

// 步骤3: 测试完整的映射流程
echo "步骤3: 测试完整的映射流程\n";
$method3 = $reflection->getMethod('map_product_to_walmart_format');
$method3->setAccessible(true);

try {
    $walmart_data = $method3->invoke($mapper, $product, 1);
    
    if (isset($walmart_data['sofa_and_loveseat_design'])) {
        echo "  ✅ sofa_and_loveseat_design 字段存在于映射数据中\n";
        echo "  值: " . json_encode($walmart_data['sofa_and_loveseat_design'], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "  ❌ sofa_and_loveseat_design 字段不存在于映射数据中\n";
        echo "  这就是问题所在！\n\n";
        
        echo "  可能的原因：\n";
        echo "  1. 字段在映射过程中被过滤掉了\n";
        echo "  2. 字段配置的 type 不是 'auto_generate'\n";
        echo "  3. 字段生成返回了 null\n";
        echo "  4. 产品的分类没有正确关联到分类映射\n";
    }
} catch (Exception $e) {
    echo "  ❌ 映射失败: {$e->getMessage()}\n";
}

echo "\n";

// ============================================
// 检查4: 检查产品的分类关联
// ============================================
echo "【检查4: 检查产品的分类关联】\n";
echo str_repeat("-", 80) . "\n";

if (isset($product_id)) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    echo "产品的分类 ID: " . implode(', ', $product_categories) . "\n";
    echo "映射的分类 ID: {$mapping->local_category_id}\n";
    
    if (in_array($mapping->local_category_id, $product_categories)) {
        echo "✅ 产品属于此分类\n";
    } else {
        echo "❌ 产品不属于此分类\n";
        echo "   这可能导致字段不会被加载\n";
    }
} else {
    echo "⚠️ 使用测试产品，跳过分类关联检查\n";
}

echo "\n";

// ============================================
// 检查5: 检查字段过滤逻辑
// ============================================
echo "【检查5: 检查字段过滤逻辑】\n";
echo str_repeat("-", 80) . "\n";

echo "检查 map_product_to_walmart_format 方法中的字段过滤逻辑...\n\n";

$mapper_file = WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
$mapper_content = file_get_contents($mapper_file);

// 查找可能过滤掉字段的代码
$filter_patterns = [
    '/if\s*\(\s*empty\s*\(\s*\$value\s*\)\s*\)\s*\{[^}]*continue/',
    '/if\s*\(\s*is_null\s*\(\s*\$value\s*\)\s*\)\s*\{[^}]*continue/',
    '/if\s*\(\s*!\s*\$value\s*\)\s*\{[^}]*continue/',
];

$found_filters = [];
foreach ($filter_patterns as $pattern) {
    if (preg_match_all($pattern, $mapper_content, $matches)) {
        $found_filters = array_merge($found_filters, $matches[0]);
    }
}

if (!empty($found_filters)) {
    echo "找到 " . count($found_filters) . " 个可能的过滤逻辑:\n";
    foreach ($found_filters as $filter) {
        echo "  - " . substr($filter, 0, 80) . "...\n";
    }
    echo "\n";
    echo "⚠️ 这些过滤逻辑可能会过滤掉空值或 null 值\n";
    echo "   但 sofa_and_loveseat_design 应该有默认值，不应该被过滤\n";
} else {
    echo "✅ 没有找到明显的字段过滤逻辑\n";
}

echo "\n";

// ============================================
// 总结和建议
// ============================================
echo str_repeat("=", 80) . "\n";
echo "【诊断总结和建议】\n";
echo str_repeat("=", 80) . "\n\n";

if (!$found_field) {
    echo "🔴 **问题确认**：字段未在分类映射中配置\n\n";
    echo "解决方案：\n";
    echo "1. 在分类映射页面点击「重置属性」\n";
    echo "2. 确认 sofa_and_loveseat_design 出现在字段列表中\n";
    echo "3. 保存配置\n\n";
} elseif (isset($found_field['type']) && $found_field['type'] !== 'auto_generate') {
    echo "🔴 **问题确认**：字段的映射类型不正确\n\n";
    echo "当前类型: {$found_field['type']}\n";
    echo "应该是: auto_generate\n\n";
    echo "解决方案：\n";
    echo "1. 在分类映射页面找到 sofa_and_loveseat_design 字段\n";
    echo "2. 将映射类型改为「自动生成」\n";
    echo "3. 保存配置\n\n";
} elseif (isset($walmart_data) && !isset($walmart_data['sofa_and_loveseat_design'])) {
    echo "🔴 **问题确认**：字段在映射过程中被过滤掉了\n\n";
    echo "可能的原因：\n";
    echo "1. 产品的分类没有正确关联到分类映射\n";
    echo "2. 字段生成返回了 null 或空值\n";
    echo "3. 映射逻辑中有过滤条件\n\n";
    echo "建议：\n";
    echo "1. 检查产品是否属于正确的分类\n";
    echo "2. 查看同步日志中的详细错误信息\n";
    echo "3. 检查 map_product_to_walmart_format 方法的过滤逻辑\n\n";
} else {
    echo "✅ **字段配置正常**\n\n";
    echo "如果同步还是失败，请检查：\n";
    echo "1. 同步日志中的实际请求数据\n";
    echo "2. Walmart API 的响应信息\n";
    echo "3. 是否有其他字段也缺失\n\n";
}

echo "诊断完成！\n";
?>

