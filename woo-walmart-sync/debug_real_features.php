<?php
/**
 * 调试实际的features字段数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试实际的features字段数据 ===\n\n";

global $wpdb;
$attributes_table = $wpdb->prefix . 'walmart_product_attributes';

// 1. 查询features字段的所有记录
echo "1. 查询features字段的所有记录:\n";

$features_records = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$attributes_table} WHERE attribute_name = %s ORDER BY id DESC LIMIT 5",
    'features'
));

if ($features_records) {
    foreach ($features_records as $i => $record) {
        echo "\n记录 #" . ($i + 1) . ":\n";
        echo "  - ID: {$record->id}\n";
        echo "  - 属性名: {$record->attribute_name}\n";
        echo "  - 属性类型: {$record->attribute_type}\n";
        echo "  - 允许值: {$record->allowed_values}\n";
        echo "  - 验证规则: {$record->validation_rules}\n";
        echo "  - 分组: {$record->attribute_group}\n";
        echo "  - 必填: {$record->is_required}\n";
        echo "  - 描述: {$record->description}\n";
        
        // 解析allowed_values
        if (!empty($record->allowed_values)) {
            echo "  - 解析allowed_values:\n";
            
            // 处理单位信息（格式：value1|value2||UNITS:unit1|unit2||DEFAULT_UNIT:ft）
            $allowed_values_parts = explode('||', $record->allowed_values);
            $enum_values = explode('|', $allowed_values_parts[0]);
            
            // 检查是否只有单位信息
            if (count($enum_values) === 1 && strpos($enum_values[0], 'UNITS:') === 0) {
                echo "    只有单位信息，不设置枚举值\n";
            } else {
                echo "    有效的枚举值，数量: " . count($enum_values) . "\n";
                foreach ($enum_values as $j => $value) {
                    echo "      [{$j}] {$value}\n";
                }
            }
        }
    }
} else {
    echo "❌ 没有找到features字段的记录\n";
}

// 2. 模拟V5.0属性加载的AJAX调用
echo "\n\n2. 模拟V5.0属性加载的AJAX调用:\n";

// 模拟get_walmart_category_attributes函数的逻辑
function simulate_get_walmart_category_attributes($category_name) {
    global $wpdb;
    $attributes_table = $wpdb->prefix . 'walmart_product_attributes';
    
    echo "模拟获取分类属性: {$category_name}\n";
    
    // 查询该分类的所有属性
    $attributes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$attributes_table} WHERE category_name = %s OR category_name IS NULL ORDER BY attribute_name",
        $category_name
    ));
    
    $result = [];
    foreach ($attributes as $attr) {
        $attribute_data = [
            'attributeName' => $attr->attribute_name,
            'isrequired' => (bool) $attr->is_required,
            'description' => $attr->description,
            'defaultType' => $attr->attribute_type,
            'group' => $attr->attribute_group
        ];
        
        // 处理枚举值
        if (!empty($attr->allowed_values)) {
            $allowed_values_parts = explode('||', $attr->allowed_values);
            $enum_values = explode('|', $allowed_values_parts[0]);
            
            // 检查是否只有单位信息
            if (!(count($enum_values) === 1 && strpos($enum_values[0], 'UNITS:') === 0)) {
                $attribute_data['enumValues'] = $enum_values;
                $attribute_data['allowed_values'] = $enum_values;
                
                // 根据类型设置defaultType
                if ($attr->attribute_type === 'multiselect') {
                    $attribute_data['defaultType'] = 'multiselect';
                } else {
                    $attribute_data['defaultType'] = 'walmart_field';
                }
            }
        }
        
        $result[] = $attribute_data;
    }
    
    return $result;
}

$test_categories = ['Furniture', 'furniture_other', 'home_other'];

foreach ($test_categories as $category) {
    echo "\n测试分类: {$category}\n";
    $attributes = simulate_get_walmart_category_attributes($category);
    
    $features_found = false;
    foreach ($attributes as $attr) {
        if ($attr['attributeName'] === 'features') {
            $features_found = true;
            echo "✅ 找到features字段:\n";
            echo "  - defaultType: {$attr['defaultType']}\n";
            echo "  - enumValues: " . (isset($attr['enumValues']) ? count($attr['enumValues']) . " 个值" : "无") . "\n";
            
            if (isset($attr['enumValues'])) {
                echo "  - 枚举值:\n";
                foreach (array_slice($attr['enumValues'], 0, 5) as $i => $value) {
                    echo "    [{$i}] {$value}\n";
                }
                if (count($attr['enumValues']) > 5) {
                    echo "    ... 还有 " . (count($attr['enumValues']) - 5) . " 个\n";
                }
            }
            break;
        }
    }
    
    if (!$features_found) {
        echo "❌ 没有找到features字段\n";
    }
}

// 3. 检查前端映射类型的匹配
echo "\n\n3. 检查前端映射类型的匹配:\n";

echo "当用户选择'沃尔玛字段'时，前端会:\n";
echo "1. 获取存储的枚举值: selector.data('enum-values')\n";
echo "2. 调用loadWalmartFieldOptions(walmart_field_select, attributeName, currentValue, enumValues)\n";
echo "3. loadWalmartFieldOptions函数会优先使用传入的enumValues\n";

echo "\n可能的问题:\n";
echo "- 如果defaultType是'multiselect'，用户需要选择'沃尔玛字段'才能看到枚举值\n";
echo "- 如果枚举值没有正确存储到data('enum-values')中\n";
echo "- 如果loadWalmartFieldOptions函数没有正确处理传入的枚举值\n";

echo "\n=== 调试完成 ===\n";
?>
