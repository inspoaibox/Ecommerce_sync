<?php
/**
 * 检查 seat_material 字段的规范和实际数据类型
 */

require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 检查 seat_material 字段问题 ===\n\n";

global $wpdb;

// 1. 检查数据库中的 seat_material 规范
echo "1. 检查数据库中的 seat_material 规范:\n";
$specs = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}walmart_product_attributes 
    WHERE attribute_name = %s
", 'seat_material'));

if ($specs) {
    echo "✅ 找到 " . count($specs) . " 条 seat_material 规范:\n";
    foreach ($specs as $spec) {
        echo "  产品类型: {$spec->product_type_id}\n";
        echo "  默认类型: {$spec->default_type}\n";
        echo "  属性类型: {$spec->attribute_type}\n";
        echo "  允许值: {$spec->allowed_values}\n";
        echo "  是否必填: " . ($spec->is_required ? '是' : '否') . "\n";
        echo "  ---\n";
    }
} else {
    echo "❌ 未找到 seat_material 规范\n";
}

// 2. 检查 v5_common_attributes 中的定义
echo "\n2. 检查 v5_common_attributes 中的定义:\n";
$plugin_file = WOO_WALMART_SYNC_PATH . 'woo-walmart-sync.php';
$plugin_content = file_get_contents($plugin_file);

// 查找 seat_material 的定义
if (preg_match("/'attributeName'\s*=>\s*'seat_material'.*?(?='attributeName'|\$v5_core_attributes)/s", $plugin_content, $matches)) {
    echo "✅ 找到 seat_material 定义:\n";
    echo substr($matches[0], 0, 500) . "...\n";
    
    // 提取 defaultType
    if (preg_match("/'defaultType'\s*=>\s*'([^']+)'/", $matches[0], $type_match)) {
        echo "\n  defaultType: {$type_match[1]}\n";
    }
} else {
    echo "❌ 未找到 seat_material 定义\n";
}

// 3. 测试 extract_seat_material 方法
echo "\n3. 测试 extract_seat_material 方法:\n";

// 获取一个测试产品
$products = wc_get_products(['limit' => 1, 'status' => 'publish']);
if (!empty($products)) {
    $product = $products[0];
    echo "测试产品: {$product->get_name()} (ID: {$product->get_id()})\n";
    
    require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    
    // 使用反射调用私有方法
    $reflection = new ReflectionClass($mapper);
    $method = $reflection->getMethod('extract_seat_material');
    $method->setAccessible(true);
    
    $result = $method->invoke($mapper, $product);
    
    echo "返回值类型: " . gettype($result) . "\n";
    echo "返回值内容: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    
    if (is_array($result)) {
        echo "✅ 返回值是数组\n";
    } else {
        echo "❌ 返回值不是数组\n";
    }
}

// 4. 测试 spec_service 的类型转换
echo "\n4. 测试 spec_service 的类型转换:\n";

if ($specs) {
    require_once WOO_WALMART_SYNC_PATH . 'includes/class-walmart-spec-service.php';
    $spec_service = new Walmart_Spec_Service();
    
    $test_spec = $specs[0];
    echo "使用产品类型: {$test_spec->product_type_id}\n";
    
    // 测试数组输入
    $test_array = ['Leather', 'Fabric'];
    echo "\n测试输入 (数组): " . json_encode($test_array) . "\n";
    
    $validation_result = $spec_service->validate_field_value($test_spec->product_type_id, 'seat_material', $test_array);
    
    echo "验证结果:\n";
    echo "  valid: " . ($validation_result['valid'] ? 'true' : 'false') . "\n";
    echo "  message: {$validation_result['message']}\n";
    echo "  corrected_value 类型: " . gettype($validation_result['corrected_value']) . "\n";
    echo "  corrected_value 内容: " . json_encode($validation_result['corrected_value'], JSON_UNESCAPED_UNICODE) . "\n";
    
    if (is_array($validation_result['corrected_value'])) {
        echo "  ✅ corrected_value 是数组\n";
    } else {
        echo "  ❌ corrected_value 不是数组\n";
    }
}

echo "\n=== 检查完成 ===\n";
?>

