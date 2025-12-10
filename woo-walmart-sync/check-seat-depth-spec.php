<?php
/**
 * 检查seat_depth字段的规范和处理逻辑
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查seat_depth字段的问题 ===\n\n";

global $wpdb;

// 1. 检查seat_depth字段的API规范
echo "1. 检查API规范数据库:\n";
$spec = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}walmart_product_attributes 
    WHERE attribute_name = %s 
    LIMIT 1
", 'seat_depth'));

if ($spec) {
    echo "✅ 找到seat_depth规范:\n";
    echo "  产品类型: {$spec->product_type_id}\n";
    echo "  默认类型: {$spec->default_type}\n";
    echo "  属性类型: {$spec->attribute_type}\n";
    echo "  允许值: {$spec->allowed_values}\n";
    echo "  是否必填: " . ($spec->is_required ? '是' : '否') . "\n";
} else {
    echo "❌ 未找到seat_depth规范\n";
}

// 2. 检查分类映射中的seat_depth配置
echo "\n2. 检查分类映射配置:\n";
$mappings = $wpdb->get_results("
    SELECT id, wc_category_name, walmart_category_path, walmart_attributes 
    FROM {$wpdb->prefix}woo_walmart_category_mapping 
    WHERE walmart_attributes LIKE '%seat_depth%'
");

foreach ($mappings as $mapping) {
    echo "分类映射ID: {$mapping->id}\n";
    echo "WC分类: {$mapping->wc_category_name}\n";
    echo "沃尔玛分类: {$mapping->walmart_category_path}\n";
    
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        foreach ($attributes['name'] as $index => $field_name) {
            if (strpos(strtolower($field_name), 'seat_depth') !== false) {
                $type = $attributes['type'][$index] ?? '';
                $source = $attributes['source'][$index] ?? '';
                $format = $attributes['format'][$index] ?? '';
                
                echo "  字段: {$field_name}\n";
                echo "  类型: {$type}\n";
                echo "  来源: {$source}\n";
                echo "  格式: {$format}\n";
                
                if ($type === 'default_value') {
                    echo "  ❌ 问题：配置为default_value，值为'{$source}'\n";
                    echo "  ❌ 但API要求JSONObject格式！\n";
                }
            }
        }
    }
    echo "\n";
}

// 3. 模拟字段处理过程
echo "3. 模拟字段处理过程:\n";

// 加载必要的类
require_once plugin_dir_path(__FILE__) . 'includes/class-walmart-spec-service.php';

$spec_service = new Walmart_Spec_Service();

// 测试不同的seat_depth值
$test_values = [
    '20',           // 纯数字字符串
    20,             // 数字
    '20 in',        // 带单位的字符串
    ['measure' => 20, 'unit' => 'in'], // 正确的对象格式
];

foreach ($test_values as $test_value) {
    echo "\n测试值: " . (is_array($test_value) ? json_encode($test_value) : $test_value) . "\n";
    
    if ($spec) {
        $spec_array = [
            'type' => $spec->default_type,
            'required' => $spec->is_required,
            'allowed_values' => $spec->allowed_values ? json_decode($spec->allowed_values, true) : []
        ];
        
        $result = $spec_service->validate_field_value($spec->product_type_id, 'seat_depth', $test_value);
        
        echo "  验证结果: " . ($result['valid'] ? '✅ 通过' : '❌ 失败') . "\n";
        echo "  消息: {$result['message']}\n";
        echo "  修正值: " . json_encode($result['corrected_value']) . "\n";
        
        // 检查修正值的类型
        if (isset($result['corrected_value'])) {
            $corrected = $result['corrected_value'];
            if (is_array($corrected) && isset($corrected['measure']) && isset($corrected['unit'])) {
                echo "  ✅ 修正值是正确的JSONObject格式\n";
            } else {
                echo "  ❌ 修正值不是JSONObject格式: " . gettype($corrected) . "\n";
            }
        }
    }
}

// 4. 检查问题产品的实际数据
echo "\n4. 检查问题产品的实际数据:\n";
$problem_sku = 'W2791P306821';

$product_id = $wpdb->get_var($wpdb->prepare("
    SELECT post_id FROM {$wpdb->prefix}postmeta 
    WHERE meta_key = '_sku' AND meta_value = %s
", $problem_sku));

if ($product_id) {
    echo "找到产品ID: {$product_id}\n";
    
    $product = wc_get_product($product_id);
    if ($product) {
        echo "产品名称: {$product->get_name()}\n";
        
        // 检查产品是否有seat_depth相关属性
        $seat_depth_attr = $product->get_attribute('seat_depth');
        echo "seat_depth属性值: " . ($seat_depth_attr ?: '无') . "\n";
        
        // 检查产品分类
        $categories = $product->get_category_ids();
        echo "产品分类ID: " . implode(', ', $categories) . "\n";
        
        foreach ($categories as $cat_id) {
            $cat = get_term($cat_id);
            if ($cat) {
                echo "分类名称: {$cat->name}\n";
            }
        }
    }
} else {
    echo "❌ 未找到SKU为 {$problem_sku} 的产品\n";
}

echo "\n=== 问题分析 ===\n";
echo "根据Walmart API错误信息：\n";
echo "- 错误类型: DATA_ERROR\n";
echo "- 错误代码: IB_DATA_TYPE\n";
echo "- 字段: seat_depth\n";
echo "- 描述: The 'seat_depth' value is invalid. Enter a 'JSONObject' data type.\n\n";

echo "可能的原因:\n";
echo "1. 分类映射中seat_depth配置为default_value，发送的是字符串而不是对象\n";
echo "2. 规范服务没有正确识别seat_depth为measurement_object类型\n";
echo "3. 字段值没有被正确转换为{measure: number, unit: string}格式\n\n";

echo "建议的修复方案:\n";
echo "1. 检查并修正分类映射中seat_depth的配置\n";
echo "2. 确保规范服务正确处理measurement_object类型\n";
echo "3. 验证字段转换逻辑是否正确\n";

?>
