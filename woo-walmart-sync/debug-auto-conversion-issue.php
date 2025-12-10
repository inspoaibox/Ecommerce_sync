<?php
/**
 * 调试自动转换问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试自动转换问题 ===\n\n";

// 1. 检查spec_service是否正确初始化
echo "=== 检查spec_service初始化 ===\n";

require_once 'includes/class-product-mapper.php';
require_once 'includes/class-walmart-spec-service.php';

$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射检查私有属性
$reflection = new ReflectionClass($mapper);

$spec_service_property = $reflection->getProperty('spec_service');
$spec_service_property->setAccessible(true);
$spec_service = $spec_service_property->getValue($mapper);

if ($spec_service) {
    echo "✅ spec_service已初始化\n";
    echo "类型: " . get_class($spec_service) . "\n";
} else {
    echo "❌ spec_service未初始化\n";
}

$product_type_property = $reflection->getProperty('current_product_type_id');
$product_type_property->setAccessible(true);
$current_product_type_id = $product_type_property->getValue($mapper);

echo "当前产品类型ID: " . ($current_product_type_id ?: '未设置') . "\n";

// 2. 测试字段规范获取
echo "\n=== 测试字段规范获取 ===\n";

if ($spec_service) {
    // 设置一个测试的产品类型
    $test_product_type = 'Desk Chairs';
    $product_type_property->setValue($mapper, $test_product_type);
    
    echo "设置产品类型: {$test_product_type}\n";
    
    // 测试获取seat_depth规范
    $seat_depth_spec = $spec_service->get_field_spec($test_product_type, 'seat_depth');
    
    if ($seat_depth_spec) {
        echo "✅ 找到seat_depth规范:\n";
        echo "  类型: " . ($seat_depth_spec['type'] ?? '未知') . "\n";
        echo "  必填: " . ($seat_depth_spec['required'] ? '是' : '否') . "\n";
        echo "  允许值: " . json_encode($seat_depth_spec['allowed_values'] ?? []) . "\n";
    } else {
        echo "❌ 未找到seat_depth规范\n";
    }
    
    // 测试获取arm_height规范
    $arm_height_spec = $spec_service->get_field_spec($test_product_type, 'arm_height');
    
    if ($arm_height_spec) {
        echo "✅ 找到arm_height规范:\n";
        echo "  类型: " . ($arm_height_spec['type'] ?? '未知') . "\n";
        echo "  必填: " . ($arm_height_spec['required'] ? '是' : '否') . "\n";
        echo "  允许值: " . json_encode($arm_height_spec['allowed_values'] ?? []) . "\n";
    } else {
        echo "❌ 未找到arm_height规范\n";
    }
} else {
    echo "❌ 无法测试，spec_service未初始化\n";
}

// 3. 测试convert_field_data_type方法
echo "\n=== 测试convert_field_data_type方法 ===\n";

$convert_method = $reflection->getMethod('convert_field_data_type');
$convert_method->setAccessible(true);

// 设置产品类型
$product_type_property->setValue($mapper, 'Desk Chairs');

$test_cases = [
    ['field' => 'seat_depth', 'value' => '18'],
    ['field' => 'seat_depth', 'value' => '18 in'],
    ['field' => 'arm_height', 'value' => '26'],
    ['field' => 'arm_height', 'value' => '26 in']
];

foreach ($test_cases as $test) {
    echo "测试字段: {$test['field']}\n";
    echo "  输入值: '{$test['value']}'\n";
    
    try {
        $result = $convert_method->invoke($mapper, $test['field'], $test['value'], 'auto');
        echo "  输出值: " . json_encode($result) . "\n";
        echo "  输出类型: " . gettype($result) . "\n";
        
        if (is_array($result) && isset($result['measure']) && isset($result['unit'])) {
            echo "  ✅ 转换成功！生成了measurement_object格式\n";
        } else {
            echo "  ❌ 转换失败！仍然是原始格式\n";
        }
    } catch (Exception $e) {
        echo "  ❌ 转换异常: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// 4. 测试validate_field_value方法
echo "=== 测试validate_field_value方法 ===\n";

if ($spec_service) {
    $validation_tests = [
        ['field' => 'seat_depth', 'value' => '18'],
        ['field' => 'arm_height', 'value' => '26']
    ];
    
    foreach ($validation_tests as $test) {
        echo "验证字段: {$test['field']}\n";
        echo "  输入值: '{$test['value']}'\n";
        
        try {
            $validation_result = $spec_service->validate_field_value('Desk Chairs', $test['field'], $test['value']);
            
            echo "  验证结果: " . json_encode($validation_result) . "\n";
            
            if (isset($validation_result['corrected_value'])) {
                $corrected = $validation_result['corrected_value'];
                echo "  修正值: " . json_encode($corrected) . "\n";
                echo "  修正类型: " . gettype($corrected) . "\n";
                
                if (is_array($corrected) && isset($corrected['measure']) && isset($corrected['unit'])) {
                    echo "  ✅ 验证成功！生成了measurement_object格式\n";
                } else {
                    echo "  ❌ 验证失败！没有生成正确格式\n";
                }
            }
        } catch (Exception $e) {
            echo "  ❌ 验证异常: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
}

// 5. 检查数据库中的字段规范
echo "=== 检查数据库中的字段规范 ===\n";

global $wpdb;
$attr_table = $wpdb->prefix . 'walmart_product_attributes';

$fields_to_check = ['seat_depth', 'arm_height'];

foreach ($fields_to_check as $field) {
    $spec = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$attr_table} 
        WHERE attribute_name = %s 
        AND product_type_id = %s
    ", $field, 'Desk Chairs'));
    
    if ($spec) {
        echo "字段: {$field}\n";
        echo "  产品类型: {$spec->product_type_id}\n";
        echo "  属性类型: {$spec->attribute_type}\n";
        echo "  是否必填: " . ($spec->is_required ? '是' : '否') . "\n";
        echo "  描述: {$spec->description}\n";
        
        if (isset($spec->default_type)) {
            echo "  默认类型: {$spec->default_type}\n";
        }
        if (isset($spec->allowed_values)) {
            echo "  允许值: {$spec->allowed_values}\n";
        }
    } else {
        echo "❌ 未找到字段 {$field} 的规范\n";
    }
    echo "\n";
}

// 6. 总结问题
echo "=== 问题总结 ===\n";

if (!$spec_service) {
    echo "❌ 主要问题：spec_service未初始化\n";
    echo "解决方案：检查Walmart_Spec_Service类的初始化\n";
} else if (!$current_product_type_id) {
    echo "❌ 主要问题：current_product_type_id未设置\n";
    echo "解决方案：确保在映射过程中正确设置产品类型ID\n";
} else {
    echo "✅ spec_service和产品类型ID都正常\n";
    echo "需要进一步检查字段规范获取和转换逻辑\n";
}

?>
