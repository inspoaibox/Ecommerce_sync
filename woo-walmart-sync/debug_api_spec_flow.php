<?php
/**
 * 调试API规范的完整流程
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试API规范的完整流程 ===\n\n";

global $wpdb;

// 1. 检查API规范原始数据
echo "1. 检查API规范原始数据:\n\n";

$spec_table = $wpdb->prefix . 'walmart_product_attributes';
$luggage_specs = $wpdb->get_results($wpdb->prepare(
    "SELECT attribute_name, attribute_type, default_type, is_required, allowed_values 
     FROM $spec_table 
     WHERE product_type_id = %s 
     ORDER BY attribute_name",
    'Luggage & Luggage Sets'
));

if (empty($luggage_specs)) {
    echo "❌ 没有找到拉杆箱的API规范数据\n";
    exit;
}

echo "✅ 找到 " . count($luggage_specs) . " 个字段的API规范\n\n";

// 检查出错字段的API规范
$error_fields = [
    'luggage_lock_type',
    'luggageStyle', 
    'season',
    'netContent',
    'isProp65WarningRequired',
    'productNetContentMeasure',
    'productNetContentUnit'
];

echo "出错字段的API规范:\n";
foreach ($error_fields as $field) {
    $found = false;
    foreach ($luggage_specs as $spec) {
        if ($spec->attribute_name === $field) {
            echo "✅ $field:\n";
            echo "    attribute_type: {$spec->attribute_type}\n";
            echo "    default_type: {$spec->default_type}\n";
            echo "    is_required: " . ($spec->is_required ? 'Yes' : 'No') . "\n";
            if (!empty($spec->allowed_values)) {
                $values = json_decode($spec->allowed_values, true);
                if (is_array($values)) {
                    echo "    allowed_values: " . implode('|', array_slice($values, 0, 3)) . (count($values) > 3 ? '...' : '') . "\n";
                } else {
                    echo "    allowed_values: {$spec->allowed_values}\n";
                }
            }
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "❌ $field: 未找到API规范\n";
    }
    echo "\n";
}

// 2. 检查API规范解析逻辑
echo "2. 检查API规范解析逻辑:\n\n";

// 查找最近的API规范获取日志
$api_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}walmart_sync_logs 
     WHERE action LIKE %s 
     ORDER BY created_at DESC LIMIT 3",
    '%属性同步%'
));

if (empty($api_logs)) {
    echo "❌ 没有找到API规范获取日志\n";
} else {
    echo "✅ 找到API规范获取日志:\n";
    foreach ($api_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "动作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->request_data)) {
            $request_data = json_decode($log->request_data, true);
            if (isset($request_data['product_type_id'])) {
                echo "产品类型: {$request_data['product_type_id']}\n";
            }
        }
        echo "\n";
    }
}

// 3. 检查字段类型映射逻辑
echo "3. 检查字段类型映射逻辑:\n\n";

// 检查extract_attribute_from_schema_v2函数是否正确映射类型
echo "检查API响应中的字段类型如何映射到attribute_type:\n";

// 模拟不同的API响应格式，看看如何被解析
$test_api_responses = [
    'multiselect_field' => [
        'type' => 'array',
        'items' => [
            'type' => 'string',
            'enum' => ['Option1', 'Option2', 'Option3']
        ]
    ],
    'select_field' => [
        'type' => 'string',
        'enum' => ['Yes', 'No']
    ],
    'measurement_field' => [
        'type' => 'object',
        'properties' => [
            'measure' => ['type' => 'number'],
            'unit' => ['type' => 'string', 'enum' => ['in', 'cm']]
        ]
    ],
    'array_of_objects_field' => [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'measure' => ['type' => 'number'],
                'unit' => ['type' => 'string']
            ]
        ]
    ]
];

foreach ($test_api_responses as $field_name => $api_def) {
    echo "字段: $field_name\n";
    echo "  API定义: " . json_encode($api_def, JSON_UNESCAPED_UNICODE) . "\n";
    
    // 模拟extract_attribute_from_schema_v2的逻辑
    $expected_type = 'text'; // 默认
    
    if (isset($api_def['type'])) {
        switch ($api_def['type']) {
            case 'array':
                if (isset($api_def['items']['enum'])) {
                    $expected_type = 'multiselect';
                } elseif (isset($api_def['items']['type']) && $api_def['items']['type'] === 'object') {
                    $expected_type = 'array'; // 对象数组
                } else {
                    $expected_type = 'array';
                }
                break;
            case 'string':
                if (isset($api_def['enum'])) {
                    $expected_type = 'select';
                } else {
                    $expected_type = 'text';
                }
                break;
            case 'object':
                if (isset($api_def['properties']['measure']) && isset($api_def['properties']['unit'])) {
                    $expected_type = 'measurement_object';
                } else {
                    $expected_type = 'object';
                }
                break;
        }
    }
    
    echo "  预期类型: $expected_type\n";
    echo "\n";
}

// 4. 检查convert_value_to_spec_type是否支持所有类型
echo "4. 检查convert_value_to_spec_type支持的类型:\n\n";

if (class_exists('Walmart_Spec_Service')) {
    $spec_service = new Walmart_Spec_Service();
    
    // 测试不同类型的转换
    $test_conversions = [
        ['type' => 'multiselect', 'value' => 'Hardside', 'expected' => 'array'],
        ['type' => 'select', 'value' => 'Yes', 'expected' => 'string'],
        ['type' => 'measurement_object', 'value' => '1', 'expected' => 'object'],
        ['type' => 'array', 'value' => 'item1,item2', 'expected' => 'array']
    ];
    
    foreach ($test_conversions as $test) {
        echo "类型: {$test['type']}, 输入: '{$test['value']}'\n";
        
        $mock_spec = ['type' => $test['type']];
        
        try {
            $result = $spec_service->convert_value_to_spec_type($test['value'], $mock_spec);
            $actual_type = is_array($result) ? 'array' : gettype($result);
            
            echo "  结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
            echo "  类型: $actual_type\n";
            echo "  符合期望: " . ($actual_type === $test['expected'] ? '✅' : '❌') . "\n";
        } catch (Exception $e) {
            echo "  ❌ 转换失败: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Walmart_Spec_Service 类不可用\n";
}

echo "=== 调试完成 ===\n";
