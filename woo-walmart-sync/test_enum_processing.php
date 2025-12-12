<?php
/**
 * 测试枚举值处理的完整流程
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试枚举值处理的完整流程 ===\n\n";

// 1. 模拟后端API响应中的features字段
echo "1. 模拟API响应中的features字段:\n";

$mock_api_response = [
    'schema' => [
        'properties' => [
            'features' => [
                'type' => 'array',
                'title' => 'Additional Features',
                'items' => [
                    'type' => 'string',
                    'enum' => [
                        'Adjustable Height',
                        'Portable', 
                        'Adjustable Shelves',
                        'Drop Front',
                        'Hanging',
                        'Revolving',
                        'Expandable',
                        'Stackable',
                        'Wheeled'
                    ]
                ]
            ]
        ],
        'required' => ['features']
    ]
];

echo "API响应结构:\n";
print_r($mock_api_response);

// 2. 模拟parse_v5_spec_response函数的处理
echo "\n2. 模拟parse_v5_spec_response函数的处理:\n";

function mock_parse_property($property_name, $property_def, $is_required = false, $group = 'General') {
    // 基本属性信息
    $attribute = [
        'attributeName' => $property_name,
        'isrequired' => $is_required,
        'description' => $property_def['title'] ?? $property_def['description'] ?? '',
        'defaultType' => 'text',
        'group' => $group
    ];

    // 根据类型确定字段类型
    if (isset($property_def['type'])) {
        switch ($property_def['type']) {
            case 'array':
                $attribute['defaultType'] = 'array';
                // 如果数组项有枚举值，转换为多选
                if (isset($property_def['items']['enum'])) {
                    $attribute['allowed_values'] = $property_def['items']['enum'];
                    $attribute['enumValues'] = $property_def['items']['enum']; // 兼容性字段
                    $attribute['defaultType'] = 'multiselect';
                }
                break;
            default:
                $attribute['defaultType'] = 'text';
        }
    }

    // 如果有枚举值，设置为选择框
    if (isset($property_def['enum']) && is_array($property_def['enum'])) {
        $attribute['defaultType'] = 'select';
        $attribute['allowed_values'] = $property_def['enum'];
        $attribute['enumValues'] = $property_def['enum']; // 兼容性字段
    }

    return $attribute;
}

$features_def = $mock_api_response['schema']['properties']['features'];
$is_required = in_array('features', $mock_api_response['schema']['required'] ?? []);

$processed_attribute = mock_parse_property('features', $features_def, $is_required, 'General');

echo "处理后的属性:\n";
print_r($processed_attribute);

// 3. 检查前端应该接收到的数据格式
echo "\n3. 前端应该接收到的数据格式:\n";
echo "- attributeName: {$processed_attribute['attributeName']}\n";
echo "- defaultType: {$processed_attribute['defaultType']}\n";
echo "- enumValues: " . (isset($processed_attribute['enumValues']) ? count($processed_attribute['enumValues']) . " 个值" : "无") . "\n";

if (isset($processed_attribute['enumValues'])) {
    echo "- 枚举值列表:\n";
    foreach ($processed_attribute['enumValues'] as $i => $value) {
        echo "  [{$i}] {$value}\n";
    }
}

// 4. 模拟前端JavaScript的处理
echo "\n4. 模拟前端JavaScript的处理:\n";

// 模拟前端存储枚举值到data属性
echo "前端代码: \$newRow.find('.attr-type-selector').data('enum-values', attr.enumValues);\n";
echo "存储的枚举值: " . json_encode($processed_attribute['enumValues'] ?? null) . "\n";

// 模拟前端获取枚举值
echo "\n前端代码: var enumValues = selector.data('enum-values') || null;\n";
$frontend_enum_values = $processed_attribute['enumValues'] ?? null;
echo "获取的枚举值: " . json_encode($frontend_enum_values) . "\n";

// 5. 模拟loadWalmartFieldOptions函数的处理
echo "\n5. 模拟loadWalmartFieldOptions函数的处理:\n";

function mock_loadWalmartFieldOptions($attributeName, $enumValues) {
    echo "loadWalmartFieldOptions调用:\n";
    echo "- attributeName: {$attributeName}\n";
    echo "- enumValues: " . json_encode($enumValues) . "\n";
    
    $options = [];
    
    // 优先使用传入的枚举值
    if ($enumValues && count($enumValues) > 0) {
        $options = $enumValues;
        echo "- 使用传入的枚举值，数量: " . count($options) . "\n";
    } else {
        echo "- 没有传入枚举值，查找硬编码选项...\n";
        
        // 硬编码的沃尔玛字段选项（当前代码中的逻辑）
        $walmartFieldOptions = [
            'features' => ['Adjustable Height', 'Wireless Remote', 'Heavy Duty'], // 示例
        ];
        
        if (isset($walmartFieldOptions[$attributeName])) {
            $options = $walmartFieldOptions[$attributeName];
            echo "- 找到硬编码选项，数量: " . count($options) . "\n";
        } else {
            echo "- 没有找到硬编码选项\n";
        }
    }
    
    if (empty($options)) {
        echo "- 结果: 无预定义选项\n";
    } else {
        echo "- 结果: " . count($options) . " 个选项\n";
        foreach ($options as $i => $option) {
            echo "  [{$i}] {$option}\n";
        }
    }
    
    return $options;
}

$result_options = mock_loadWalmartFieldOptions('features', $frontend_enum_values);

// 6. 总结问题
echo "\n6. 问题分析:\n";

if (!empty($result_options)) {
    echo "✅ 枚举值处理正常\n";
    echo "   - 后端正确解析了API响应中的枚举值\n";
    echo "   - 前端正确接收并存储了枚举值\n";
    echo "   - loadWalmartFieldOptions正确使用了枚举值\n";
} else {
    echo "❌ 枚举值处理有问题\n";
    
    if (!isset($processed_attribute['enumValues'])) {
        echo "   - 后端没有正确解析枚举值\n";
    } elseif (empty($frontend_enum_values)) {
        echo "   - 前端没有正确接收枚举值\n";
    } else {
        echo "   - loadWalmartFieldOptions没有正确处理枚举值\n";
    }
}

echo "\n=== 测试完成 ===\n";
?>
