<?php
/**
 * 完整测试 seat_material 字段的数据流程
 */

require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 完整测试 seat_material 字段数据流程 ===\n\n";

// 获取一个椅子类产品
$products = wc_get_products([
    'limit' => 1,
    'status' => 'publish',
    'category' => ['chairs', 'dining-chairs', 'chair']
]);

if (empty($products)) {
    // 如果没有椅子，随便找一个产品
    $products = wc_get_products(['limit' => 1, 'status' => 'publish']);
}

if (empty($products)) {
    die("❌ 没有找到测试产品\n");
}

$product = $products[0];
echo "测试产品: {$product->get_name()} (ID: {$product->get_id()})\n\n";

// 模拟分类映射
global $wpdb;
$mapping = $wpdb->get_row("
    SELECT * FROM {$wpdb->prefix}walmart_category_map 
    WHERE walmart_attributes LIKE '%seat_material%'
    LIMIT 1
");

if (!$mapping) {
    echo "⚠️ 未找到包含 seat_material 的分类映射，使用默认配置\n";
    $walmart_category = 'Dining Chairs';
} else {
    echo "使用分类映射: {$mapping->wc_category_name} -> {$mapping->walmart_category_path}\n";
    $walmart_category = $mapping->walmart_category_path;
    
    // 检查映射配置
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        foreach ($attributes['name'] as $index => $field_name) {
            if (strtolower($field_name) === 'seat_material') {
                $type = $attributes['type'][$index] ?? '';
                $source = $attributes['source'][$index] ?? '';
                $format = $attributes['format'][$index] ?? '';
                
                echo "  seat_material 配置:\n";
                echo "    类型: {$type}\n";
                echo "    来源: {$source}\n";
                echo "    格式: {$format}\n";
                break;
            }
        }
    }
}

echo "\n";

// 1. 测试 generate_special_attribute_value
echo "1. 测试 generate_special_attribute_value:\n";

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

$reflection = new ReflectionClass($mapper);

// 设置 current_product_type_id
$property = $reflection->getProperty('current_product_type_id');
$property->setAccessible(true);
$property->setValue($mapper, $walmart_category);

// 设置 spec_service
require_once WOO_WALMART_SYNC_PATH . 'includes/class-walmart-spec-service.php';
$spec_service = new Walmart_Spec_Service();
$spec_property = $reflection->getProperty('spec_service');
$spec_property->setAccessible(true);
$spec_property->setValue($mapper, $spec_service);

$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

$generated_value = $method->invoke($mapper, 'seat_material', $product, 1);

echo "  生成值类型: " . gettype($generated_value) . "\n";
echo "  生成值内容: " . json_encode($generated_value, JSON_UNESCAPED_UNICODE) . "\n";

if (is_array($generated_value)) {
    echo "  ✅ 生成值是数组\n";
} else {
    echo "  ❌ 生成值不是数组: " . gettype($generated_value) . "\n";
}

// 2. 测试 convert_field_data_type
echo "\n2. 测试 convert_field_data_type:\n";

$convert_method = $reflection->getMethod('convert_field_data_type');
$convert_method->setAccessible(true);

$converted_value = $convert_method->invoke($mapper, 'seat_material', $generated_value, null);

echo "  转换后类型: " . gettype($converted_value) . "\n";
echo "  转换后内容: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n";

if (is_array($converted_value)) {
    echo "  ✅ 转换后是数组\n";
} else {
    echo "  ❌ 转换后不是数组: " . gettype($converted_value) . "\n";
}

// 3. 测试完整的字段映射流程
echo "\n3. 测试完整的字段映射流程:\n";

// 模拟 map_product_to_walmart_format 中的处理
$item_data = [];
$walmart_category_name = $walmart_category;

// 模拟字段映射
$field_name = 'seat_material';
$map_type = 'auto_generate';
$map_source = '';
$format = 'auto';

echo "  字段名: {$field_name}\n";
echo "  映射类型: {$map_type}\n";
echo "  格式: {$format}\n\n";

// 生成值
$value = $method->invoke($mapper, $field_name, $product, 1);
echo "  步骤1 - 生成值:\n";
echo "    类型: " . gettype($value) . "\n";
echo "    内容: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";

// 转换类型
$value = $convert_method->invoke($mapper, $field_name, $value, $format);
echo "  步骤2 - 类型转换:\n";
echo "    类型: " . gettype($value) . "\n";
echo "    内容: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";

// 检查最终结果
if (is_array($value)) {
    echo "  ✅ 最终值是数组 - 符合 API 要求\n";
} else {
    echo "  ❌ 最终值不是数组 - 不符合 API 要求\n";
    echo "  实际类型: " . gettype($value) . "\n";
}

// 4. 检查 JSON 编码后的格式
echo "\n4. 检查 JSON 编码后的格式:\n";

$json_encoded = json_encode(['seat_material' => $value], JSON_UNESCAPED_UNICODE);
echo "  JSON: {$json_encoded}\n";

$decoded = json_decode($json_encoded, true);
if (is_array($decoded['seat_material'])) {
    echo "  ✅ JSON 解码后仍是数组\n";
} else {
    echo "  ❌ JSON 解码后不是数组\n";
}

echo "\n=== 测试完成 ===\n";
?>

