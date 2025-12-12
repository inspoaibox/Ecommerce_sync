<?php
/**
 * 完整测试修复效果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 完整测试修复效果 ===\n\n";

// 1. 测试API规范服务是否正确加载
echo "1. 测试API规范服务加载:\n";
if (class_exists('Walmart_Spec_Service')) {
    echo "✅ Walmart_Spec_Service 类已加载\n";
    
    try {
        $spec_service = new Walmart_Spec_Service();
        echo "✅ 可以正常实例化\n";
    } catch (Exception $e) {
        echo "❌ 实例化失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Walmart_Spec_Service 类未加载\n";
}

// 2. 测试产品映射器是否正确初始化API规范服务
echo "\n2. 测试产品映射器初始化:\n";
try {
    $mapper = new Woo_Walmart_Product_Mapper();
    echo "✅ 产品映射器创建成功\n";
    
    // 使用反射检查spec_service属性
    $reflection = new ReflectionClass($mapper);
    $spec_service_property = $reflection->getProperty('spec_service');
    $spec_service_property->setAccessible(true);
    $spec_service = $spec_service_property->getValue($mapper);
    
    if ($spec_service) {
        echo "✅ spec_service 已正确初始化\n";
    } else {
        echo "❌ spec_service 未初始化\n";
    }
} catch (Exception $e) {
    echo "❌ 产品映射器创建失败: " . $e->getMessage() . "\n";
}

// 3. 测试所有出错字段的转换
echo "\n3. 测试出错字段的转换:\n\n";

$error_fields_from_api = [
    'luggage_lock_type' => 'TSA Lock',
    'productNetContentMeasure' => '1',
    'productNetContentUnit' => 'lb',
    'luggage_inner_dimension_depth' => '10',
    'height_with_handle_extended' => '42',
    'isProp65WarningRequired' => '',
    'luggage_overall_dimension_depth' => '8.66',
    'luggageStyle' => 'Hardside',
    'season' => 'All-Season',
    'netContent' => '1'
];

// 获取分类映射
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$mapping = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $map_table WHERE wc_category_id = %d ORDER BY id DESC LIMIT 1",
    100
));

if (!$mapping) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

echo "使用分类: {$mapping->walmart_category_path}\n\n";

// 设置产品类型ID
$product_type_property = $reflection->getProperty('current_product_type_id');
$product_type_property->setAccessible(true);
$product_type_property->setValue($mapper, $mapping->walmart_category_path);

// 测试转换
$method = $reflection->getMethod('convert_field_data_type');
$method->setAccessible(true);

$results = [];

foreach ($error_fields_from_api as $field => $value) {
    echo "测试字段: $field\n";
    echo "  输入值: '$value'\n";
    
    try {
        $result = $method->invoke($mapper, $field, $value, 'auto');
        $results[$field] = $result;
        
        echo "  输出值: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "  输出类型: " . gettype($result) . "\n";
        
        // 检查是否符合沃尔玛API要求
        $correct = false;
        $expected = '';
        
        switch ($field) {
            case 'luggage_lock_type':
                $correct = is_string($result);
                $expected = 'string (枚举值)';
                break;
            case 'luggageStyle':
            case 'season':
                $correct = is_array($result);
                $expected = 'array (多选)';
                break;
            case 'luggage_inner_dimension_depth':
            case 'height_with_handle_extended':
            case 'luggage_overall_dimension_depth':
                $correct = is_array($result) && isset($result[0]) && is_array($result[0]) && isset($result[0]['measure']);
                $expected = 'array of measurement objects';
                break;
            case 'netContent':
            case 'productNetContentMeasure':
                $correct = is_array($result) && isset($result['measure']) && isset($result['unit']);
                $expected = 'measurement object';
                break;
            case 'isProp65WarningRequired':
                $correct = is_string($result) && in_array($result, ['Yes', 'No']);
                $expected = 'string (Yes|No)';
                break;
            case 'productNetContentUnit':
                $correct = is_string($result);
                $expected = 'string (单位)';
                break;
        }
        
        echo "  期望格式: $expected\n";
        echo "  格式正确: " . ($correct ? "✅" : "❌") . "\n";
        
    } catch (Exception $e) {
        echo "  ❌ 转换失败: " . $e->getMessage() . "\n";
        $results[$field] = null;
    }
    
    echo "\n";
}

// 4. 总结修复效果
echo "4. 修复效果总结:\n\n";

$fixed_count = 0;
$total_count = count($error_fields_from_api);

foreach ($results as $field => $result) {
    if ($result !== null) {
        switch ($field) {
            case 'luggageStyle':
            case 'season':
                if (is_array($result)) {
                    echo "✅ $field: 成功转换为数组\n";
                    $fixed_count++;
                } else {
                    echo "❌ $field: 未转换为数组\n";
                }
                break;
            case 'luggage_inner_dimension_depth':
            case 'height_with_handle_extended':
            case 'luggage_overall_dimension_depth':
                if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                    echo "✅ $field: 成功转换为尺寸对象数组\n";
                    $fixed_count++;
                } else {
                    echo "❌ $field: 未转换为尺寸对象数组\n";
                }
                break;
            case 'netContent':
            case 'productNetContentMeasure':
                if (is_array($result) && isset($result['measure'])) {
                    echo "✅ $field: 成功转换为尺寸对象\n";
                    $fixed_count++;
                } else {
                    echo "❌ $field: 未转换为尺寸对象\n";
                }
                break;
            default:
                if ($result !== $error_fields_from_api[$field]) {
                    echo "✅ $field: 已处理\n";
                    $fixed_count++;
                } else {
                    echo "⚠️ $field: 未改变\n";
                }
        }
    } else {
        echo "❌ $field: 转换失败\n";
    }
}

echo "\n修复成功率: $fixed_count/$total_count (" . round($fixed_count/$total_count*100, 1) . "%)\n";

if ($fixed_count == $total_count) {
    echo "🎉 所有字段都已正确修复！\n";
} elseif ($fixed_count > $total_count * 0.8) {
    echo "✅ 大部分字段已修复，还有少量问题需要解决\n";
} else {
    echo "❌ 修复效果不理想，需要进一步检查\n";
}

echo "\n=== 测试完成 ===\n";
