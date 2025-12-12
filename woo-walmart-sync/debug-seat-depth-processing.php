<?php
/**
 * 调试seat_depth字段的完整处理过程
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试seat_depth字段的完整处理过程 ===\n\n";

// 1. 获取问题产品
$problem_sku = 'W2791P306821';
global $wpdb;

$product_id = $wpdb->get_var($wpdb->prepare("
    SELECT post_id FROM {$wpdb->prefix}postmeta 
    WHERE meta_key = '_sku' AND meta_value = %s
", $problem_sku));

if (!$product_id) {
    echo "❌ 未找到产品\n";
    exit;
}

$product = wc_get_product($product_id);
echo "产品ID: {$product_id}\n";
echo "产品名称: {$product->get_name()}\n";
echo "SKU: {$product->get_sku()}\n\n";

// 2. 模拟分类映射配置
$mock_attribute_rules = [
    'name' => ['seat_depth'],
    'type' => ['attributes_field'],
    'source' => ['1'], // 备用默认值
    'attributes_key' => ['seat_depth'] // 用户指定的属性名
];

echo "=== 模拟分类映射配置 ===\n";
echo "字段名: seat_depth\n";
echo "类型: attributes_field\n";
echo "属性名: seat_depth\n";
echo "备用默认值: 1\n\n";

// 3. 测试get_product_attribute_value函数
echo "=== 测试get_product_attribute_value函数 ===\n";
$attr_value = get_product_attribute_value($product, 'seat_depth', '');
echo "get_product_attribute_value结果: '" . $attr_value . "' (类型: " . gettype($attr_value) . ")\n";

if (empty($attr_value)) {
    echo "✅ 属性值为空，将使用备用默认值\n";
} else {
    echo "❌ 意外：找到了属性值\n";
}

// 4. 模拟get_attributes_field_value方法
echo "\n=== 模拟get_attributes_field_value方法 ===\n";

require_once plugin_dir_path(__FILE__) . 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('get_attributes_field_value');
$method->setAccessible(true);

$attributes_field_value = $method->invoke($mapper, 'seat_depth', $product, $mock_attribute_rules, 0);
echo "get_attributes_field_value结果: '" . $attributes_field_value . "' (类型: " . gettype($attributes_field_value) . ")\n";

// 5. 测试数据类型转换
echo "\n=== 测试数据类型转换 ===\n";

// 加载规范服务
require_once plugin_dir_path(__FILE__) . 'includes/class-walmart-spec-service.php';
$spec_service = new Walmart_Spec_Service();

echo "转换前的值: '" . $attributes_field_value . "' (类型: " . gettype($attributes_field_value) . ")\n";

// 测试规范服务转换
$validation_result = $spec_service->validate_field_value('Desk Chairs', 'seat_depth', $attributes_field_value);
echo "规范服务验证结果:\n";
echo "  valid: " . ($validation_result['valid'] ? 'true' : 'false') . "\n";
echo "  message: {$validation_result['message']}\n";
echo "  corrected_value: " . json_encode($validation_result['corrected_value']) . " (类型: " . gettype($validation_result['corrected_value']) . ")\n";

// 6. 测试产品映射器的convert_field_data_type方法
echo "\n=== 测试convert_field_data_type方法 ===\n";

$convert_method = $reflection->getMethod('convert_field_data_type');
$convert_method->setAccessible(true);

// 设置spec_service属性
$spec_service_property = $reflection->getProperty('spec_service');
$spec_service_property->setAccessible(true);
$spec_service_property->setValue($mapper, $spec_service);

$product_type_property = $reflection->getProperty('current_product_type_id');
$product_type_property->setAccessible(true);
$product_type_property->setValue($mapper, 'Desk Chairs');

$converted_value = $convert_method->invoke($mapper, 'seat_depth', $attributes_field_value, null);
echo "convert_field_data_type结果: " . json_encode($converted_value) . " (类型: " . gettype($converted_value) . ")\n";

// 7. 检查最终的API数据结构
echo "\n=== 检查最终的API数据结构 ===\n";

if (is_array($converted_value) && isset($converted_value['measure']) && isset($converted_value['unit'])) {
    echo "✅ 转换结果是正确的JSONObject格式\n";
    echo "  measure: {$converted_value['measure']} (类型: " . gettype($converted_value['measure']) . ")\n";
    echo "  unit: {$converted_value['unit']} (类型: " . gettype($converted_value['unit']) . ")\n";
    
    // 测试JSON序列化
    $json_encoded = json_encode($converted_value);
    echo "  JSON序列化: {$json_encoded}\n";
    
    // 测试是否会被意外转换为字符串
    $string_cast = (string)$converted_value;
    echo "  字符串转换: '{$string_cast}'\n";
    
    if ($string_cast === 'Array') {
        echo "  ❌ 问题：数组被转换为'Array'字符串！\n";
    }
    
} else {
    echo "❌ 转换结果不是正确的JSONObject格式\n";
    echo "  实际类型: " . gettype($converted_value) . "\n";
    echo "  实际值: " . var_export($converted_value, true) . "\n";
}

// 8. 完整的映射测试
echo "\n=== 完整的映射测试 ===\n";

try {
    // 模拟完整的映射过程
    $walmart_data = $mapper->map($product, 'Desk Chairs', '123456789012', $mock_attribute_rules, 1);
    
    if (isset($walmart_data['MPItem'][0]['Visible']['Desk Chairs']['seat_depth'])) {
        $final_seat_depth = $walmart_data['MPItem'][0]['Visible']['Desk Chairs']['seat_depth'];
        echo "最终API数据中的seat_depth: " . json_encode($final_seat_depth) . "\n";
        echo "类型: " . gettype($final_seat_depth) . "\n";
        
        if (is_array($final_seat_depth) && isset($final_seat_depth['measure']) && isset($final_seat_depth['unit'])) {
            echo "✅ 最终结果是正确的JSONObject格式\n";
        } else {
            echo "❌ 最终结果不是JSONObject格式\n";
            echo "这就是导致Walmart API错误的原因！\n";
        }
    } else {
        echo "❌ 最终API数据中没有seat_depth字段\n";
    }
    
} catch (Exception $e) {
    echo "❌ 映射过程出错: " . $e->getMessage() . "\n";
}

echo "\n=== 结论 ===\n";
echo "如果最终结果是正确的JSONObject格式，那么问题可能在于：\n";
echo "1. 旧的缓存数据\n";
echo "2. 不同的分类映射配置\n";
echo "3. API请求过程中的序列化问题\n";
echo "4. 错误信息来自历史同步记录\n";

?>
