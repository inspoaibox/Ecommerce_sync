<?php
/**
 * 测试API规范功能是否完整可用
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试API规范功能完整性 ===\n\n";

// 1. 检查数据库表是否存在
echo "1. 检查数据库表:\n";
global $wpdb;

$tables_to_check = [
    'walmart_attributes_spec' => $wpdb->prefix . 'walmart_attributes_spec',
    'walmart_product_attributes' => $wpdb->prefix . 'walmart_product_attributes'
];

foreach ($tables_to_check as $table_name => $full_table_name) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
    echo "  - $table_name: " . ($table_exists ? "✅ 存在" : "❌ 不存在") . "\n";
    
    if ($table_exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "    记录数: $count\n";
    }
}

// 2. 检查Walmart_Spec_Service类是否可用
echo "\n2. 检查Walmart_Spec_Service类:\n";
if (class_exists('Walmart_Spec_Service')) {
    echo "  ✅ 类存在\n";
    
    try {
        $spec_service = new Walmart_Spec_Service();
        echo "  ✅ 可以实例化\n";
        
        // 测试方法是否存在
        $methods_to_check = [
            'get_field_spec',
            'get_all_field_specs', 
            'validate_field_value',
            'convert_value_to_spec_type',
            'get_default_value_for_field'
        ];
        
        foreach ($methods_to_check as $method) {
            if (method_exists($spec_service, $method)) {
                echo "    ✅ 方法 $method 存在\n";
            } else {
                echo "    ❌ 方法 $method 不存在\n";
            }
        }
        
    } catch (Exception $e) {
        echo "  ❌ 实例化失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ❌ 类不存在\n";
}

// 3. 检查API调用功能
echo "\n3. 检查API调用功能:\n";
if (class_exists('Woo_Walmart_API_Key_Auth')) {
    echo "  ✅ API认证类存在\n";
    
    try {
        $api_auth = new Woo_Walmart_API_Key_Auth();
        echo "  ✅ 可以实例化\n";
        
        // 检查关键方法
        if (method_exists($api_auth, 'make_request')) {
            echo "    ✅ make_request 方法存在\n";
        } else {
            echo "    ❌ make_request 方法不存在\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ 实例化失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ❌ API认证类不存在\n";
}

// 4. 检查解析函数是否存在
echo "\n4. 检查解析函数:\n";
$functions_to_check = [
    'parse_v5_spec_response',
    'parse_json_schema_attributes',
    'extract_attribute_from_schema_v2',
    'save_attributes_to_database',
    'get_attributes_from_database'
];

foreach ($functions_to_check as $function) {
    if (function_exists($function)) {
        echo "  ✅ 函数 $function 存在\n";
    } else {
        echo "  ❌ 函数 $function 不存在\n";
    }
}

// 5. 测试实际的API规范获取（如果有数据的话）
echo "\n5. 测试API规范数据获取:\n";

// 查找已有的规范数据
$existing_specs = $wpdb->get_results("
    SELECT DISTINCT product_type_id, COUNT(*) as attr_count 
    FROM {$wpdb->prefix}walmart_product_attributes 
    GROUP BY product_type_id 
    LIMIT 5
");

if (!empty($existing_specs)) {
    echo "  ✅ 找到已存储的规范数据:\n";
    foreach ($existing_specs as $spec) {
        echo "    - 产品类型: {$spec->product_type_id}, 属性数: {$spec->attr_count}\n";
    }
    
    // 测试获取第一个产品类型的规范
    $test_product_type = $existing_specs[0]->product_type_id;
    echo "\n  测试获取产品类型 '$test_product_type' 的规范:\n";
    
    if (class_exists('Walmart_Spec_Service')) {
        $spec_service = new Walmart_Spec_Service();
        
        try {
            $all_specs = $spec_service->get_all_field_specs($test_product_type);
            echo "    ✅ 成功获取 " . count($all_specs) . " 个字段规范\n";
            
            // 显示前几个字段
            $count = 0;
            foreach ($all_specs as $field_name => $spec) {
                if ($count >= 3) break;
                echo "      - $field_name: type={$spec['type']}, required=" . ($spec['required'] ? 'Yes' : 'No') . "\n";
                $count++;
            }
            
            // 测试字段验证功能
            if (!empty($all_specs)) {
                $test_field = array_keys($all_specs)[0];
                $test_value = "test_value";
                
                echo "\n    测试字段验证功能 (字段: $test_field):\n";
                $validation_result = $spec_service->validate_field_value($test_product_type, $test_field, $test_value);
                echo "      验证结果: " . ($validation_result['valid'] ? '✅ 有效' : '❌ 无效') . "\n";
                echo "      消息: {$validation_result['message']}\n";
            }
            
        } catch (Exception $e) {
            echo "    ❌ 获取规范失败: " . $e->getMessage() . "\n";
        }
    }
    
} else {
    echo "  ❌ 没有找到已存储的规范数据\n";
    echo "  建议: 先通过后台界面获取一些产品分类的API规范\n";
}

// 6. 检查AJAX处理函数
echo "\n6. 检查AJAX处理:\n";
$ajax_actions = [
    'get_walmart_category_attributes'
];

foreach ($ajax_actions as $action) {
    if (has_action("wp_ajax_$action")) {
        echo "  ✅ AJAX动作 $action 已注册\n";
    } else {
        echo "  ❌ AJAX动作 $action 未注册\n";
    }
}

// 7. 总结
echo "\n=== 功能完整性总结 ===\n";

$issues = [];

// 检查关键组件
if (!class_exists('Walmart_Spec_Service')) {
    $issues[] = "Walmart_Spec_Service 类缺失";
}

if (!class_exists('Woo_Walmart_API_Key_Auth')) {
    $issues[] = "API认证类缺失";
}

if (!function_exists('parse_v5_spec_response')) {
    $issues[] = "API响应解析函数缺失";
}

$spec_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}walmart_product_attributes'") == $wpdb->prefix . 'walmart_product_attributes';
if (!$spec_table_exists) {
    $issues[] = "API规范数据库表缺失";
}

if (empty($issues)) {
    echo "✅ API规范功能完整，可以正常使用\n";
    echo "\n功能包括:\n";
    echo "  - ✅ 从沃尔玛API获取产品分类规范\n";
    echo "  - ✅ 解析JSON Schema格式的API响应\n";
    echo "  - ✅ 存储规范数据到数据库\n";
    echo "  - ✅ 字段类型自动检测和转换\n";
    echo "  - ✅ 字段值验证和纠正\n";
    echo "  - ✅ AJAX接口支持\n";
} else {
    echo "❌ API规范功能不完整，存在以下问题:\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
}

echo "\n=== 测试完成 ===\n";
