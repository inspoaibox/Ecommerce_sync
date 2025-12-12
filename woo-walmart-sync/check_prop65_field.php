<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查 isProp65WarningRequired 字段 ===\n\n";

global $wpdb;

// 1. 检查API规范中的字段信息
$spec = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}walmart_product_attributes WHERE product_type_id = %s AND attribute_name = %s",
    'Luggage & Luggage Sets', 'isProp65WarningRequired'
));

if ($spec) {
    echo "✅ 找到API规范:\n";
    echo "  - attribute_name: {$spec->attribute_name}\n";
    echo "  - attribute_type: {$spec->attribute_type}\n";
    echo "  - default_type: {$spec->default_type}\n";
    echo "  - is_required: " . ($spec->is_required ? 'Yes' : 'No') . "\n";
    echo "  - allowed_values: {$spec->allowed_values}\n";
    echo "  - validation_rules: {$spec->validation_rules}\n";
    echo "  - description: {$spec->description}\n";
    echo "  - attribute_group: {$spec->attribute_group}\n";
} else {
    echo "❌ 未找到API规范\n";
    exit;
}

// 2. 检查分类映射中的配置
echo "\n2. 检查分类映射中的配置:\n\n";

$mapping = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d ORDER BY id DESC LIMIT 1",
    100
));

if ($mapping) {
    $attribute_rules = json_decode($mapping->walmart_attributes, true);
    $field_index = array_search('isProp65WarningRequired', $attribute_rules['name']);
    
    if ($field_index !== false) {
        echo "✅ 找到映射配置:\n";
        echo "  - 映射类型: " . ($attribute_rules['type'][$field_index] ?? 'N/A') . "\n";
        echo "  - 映射源: '" . ($attribute_rules['source'][$field_index] ?? 'N/A') . "'\n";
        echo "  - 格式设置: " . ($attribute_rules['format'][$field_index] ?? 'N/A') . "\n";
        
        $map_source = $attribute_rules['source'][$field_index] ?? '';
        echo "  - 源值内容: '$map_source'\n";
        
        if (empty($map_source)) {
            echo "  ⚠️ 映射源为空，这就是为什么返回null的原因\n";
        }
    } else {
        echo "❌ 未在映射配置中找到\n";
    }
} else {
    echo "❌ 未找到分类映射\n";
}

// 3. 测试API规范转换
echo "\n3. 测试API规范转换:\n\n";

if (class_exists('Walmart_Spec_Service')) {
    $spec_service = new Walmart_Spec_Service();
    
    // 测试不同的输入值
    $test_values = ['', null, 'Yes', 'No', 'invalid'];
    
    foreach ($test_values as $test_value) {
        echo "输入值: " . (is_null($test_value) ? 'null' : "'$test_value'") . "\n";
        
        $validation_result = $spec_service->validate_field_value('Luggage & Luggage Sets', 'isProp65WarningRequired', $test_value);
        
        echo "  - 有效: " . ($validation_result['valid'] ? 'Yes' : 'No') . "\n";
        echo "  - 消息: {$validation_result['message']}\n";
        echo "  - 纠正值: " . json_encode($validation_result['corrected_value'], JSON_UNESCAPED_UNICODE) . "\n";
        echo "\n";
    }
} else {
    echo "❌ Walmart_Spec_Service 不可用\n";
}

// 4. 检查允许值
echo "4. 分析允许值:\n\n";

if (!empty($spec->allowed_values)) {
    $allowed = json_decode($spec->allowed_values, true);
    if (is_array($allowed)) {
        echo "允许的值:\n";
        foreach ($allowed as $value) {
            echo "  - '$value'\n";
        }
        echo "\n默认值应该是: '{$allowed[0]}'\n";
    } else {
        echo "允许值格式: {$spec->allowed_values}\n";
    }
} else {
    echo "❌ 没有允许值列表\n";
}

echo "\n=== 检查完成 ===\n";
