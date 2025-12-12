<?php
/**
 * 测试API规范查找功能
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试API规范查找功能 ===\n\n";

// 1. 检查Walmart_Spec_Service是否可用
if (!class_exists('Walmart_Spec_Service')) {
    echo "❌ Walmart_Spec_Service 类不存在\n";
    exit;
}

$spec_service = new Walmart_Spec_Service();
echo "✅ Walmart_Spec_Service 实例化成功\n\n";

// 2. 查找可用的产品类型ID
global $wpdb;
$product_types = $wpdb->get_results("SELECT DISTINCT product_type_id FROM {$wpdb->prefix}walmart_product_attributes LIMIT 10");

echo "2. 可用的产品类型ID:\n";
foreach ($product_types as $type) {
    echo "  - '{$type->product_type_id}'\n";
}

// 3. 测试不同的产品类型ID格式
$test_product_types = [
    'Luggage & Luggage Sets',
    'Luggage',
    'luggage',
    'LUGGAGE'
];

$test_fields = [
    'luggage_lock_type',
    'luggageStyle', 
    'season',
    'netContent'
];

echo "\n3. 测试字段查找:\n\n";

foreach ($test_product_types as $product_type) {
    echo "产品类型: '$product_type'\n";
    
    foreach ($test_fields as $field) {
        $spec = $spec_service->get_field_spec($product_type, $field);
        
        if ($spec) {
            echo "  ✅ $field: type={$spec['type']}, required=" . ($spec['required'] ? 'Yes' : 'No') . "\n";
        } else {
            echo "  ❌ $field: 未找到\n";
        }
    }
    echo "\n";
}

// 4. 测试字段值验证
echo "4. 测试字段值验证:\n\n";

$correct_product_type = null;
foreach ($test_product_types as $product_type) {
    $spec = $spec_service->get_field_spec($product_type, 'luggage_lock_type');
    if ($spec) {
        $correct_product_type = $product_type;
        break;
    }
}

if ($correct_product_type) {
    echo "使用产品类型: '$correct_product_type'\n\n";
    
    $test_cases = [
        ['field' => 'luggage_lock_type', 'value' => 'TSA Lock'],
        ['field' => 'luggage_lock_type', 'value' => 'Key Locks'],
        ['field' => 'luggageStyle', 'value' => 'Hardside'],
        ['field' => 'season', 'value' => 'All-Season'],
        ['field' => 'netContent', 'value' => '1']
    ];
    
    foreach ($test_cases as $test) {
        echo "测试: {$test['field']} = '{$test['value']}'\n";
        
        $validation_result = $spec_service->validate_field_value($correct_product_type, $test['field'], $test['value']);
        
        echo "  有效: " . ($validation_result['valid'] ? '✅ Yes' : '❌ No') . "\n";
        echo "  消息: {$validation_result['message']}\n";
        echo "  纠正值: " . json_encode($validation_result['corrected_value'], JSON_UNESCAPED_UNICODE) . "\n";
        echo "\n";
    }
} else {
    echo "❌ 没有找到有效的产品类型\n";
}

// 5. 检查当前映射中使用的产品类型ID
echo "5. 检查当前映射中使用的产品类型ID:\n\n";

$map_table = $wpdb->prefix . 'walmart_category_map';
$mappings = $wpdb->get_results("SELECT id, wc_category_name, walmart_category_path FROM $map_table ORDER BY id DESC LIMIT 5");

foreach ($mappings as $mapping) {
    echo "映射ID {$mapping->id}: WC分类='{$mapping->wc_category_name}', 沃尔玛分类='{$mapping->walmart_category_path}'\n";
    
    // 测试这个分类是否能找到API规范
    $spec = $spec_service->get_field_spec($mapping->walmart_category_path, 'luggage_lock_type');
    if ($spec) {
        echo "  ✅ 可以找到API规范\n";
    } else {
        echo "  ❌ 无法找到API规范\n";
    }
}

echo "\n=== 测试完成 ===\n";
