<?php
/**
 * 检查seat_depth和arm_height字段的分类映射配置
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查seat_depth和arm_height字段的分类映射配置 ===\n\n";

global $wpdb;

// 1. 查找包含这些字段的分类映射
$mapping_table = $wpdb->prefix . 'woo_walmart_category_mapping';

echo "=== 查找包含seat_depth的分类映射 ===\n";
$seat_depth_mappings = $wpdb->get_results("
    SELECT id, wc_category_name, walmart_category_path, walmart_attributes 
    FROM {$mapping_table} 
    WHERE walmart_attributes LIKE '%seat_depth%'
");

foreach ($seat_depth_mappings as $mapping) {
    echo "分类映射ID: {$mapping->id}\n";
    echo "WC分类: {$mapping->wc_category_name}\n";
    echo "沃尔玛分类: {$mapping->walmart_category_path}\n";
    
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        echo "属性配置:\n";
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
                    echo "  ❌ 问题：seat_depth配置为default_value类型，值为'{$source}'\n";
                    echo "  ❌ 但沃尔玛API要求JSONObject格式！\n";
                } else if ($type === 'auto_generate') {
                    echo "  ✅ 配置为auto_generate，应该会自动转换为JSONObject\n";
                }
            }
        }
    }
    echo "\n";
}

echo "=== 查找包含arm_height的分类映射 ===\n";
$arm_height_mappings = $wpdb->get_results("
    SELECT id, wc_category_name, walmart_category_path, walmart_attributes 
    FROM {$mapping_table} 
    WHERE walmart_attributes LIKE '%arm_height%'
");

foreach ($arm_height_mappings as $mapping) {
    echo "分类映射ID: {$mapping->id}\n";
    echo "WC分类: {$mapping->wc_category_name}\n";
    echo "沃尔玛分类: {$mapping->walmart_category_path}\n";
    
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        echo "属性配置:\n";
        foreach ($attributes['name'] as $index => $field_name) {
            if (strpos(strtolower($field_name), 'arm_height') !== false) {
                $type = $attributes['type'][$index] ?? '';
                $source = $attributes['source'][$index] ?? '';
                $format = $attributes['format'][$index] ?? '';
                
                echo "  字段: {$field_name}\n";
                echo "  类型: {$type}\n";
                echo "  来源: {$source}\n";
                echo "  格式: {$format}\n";
                
                if ($type === 'default_value') {
                    echo "  ❌ 问题：arm_height配置为default_value类型，值为'{$source}'\n";
                    echo "  ❌ 但沃尔玛API要求JSONObject格式！\n";
                } else if ($type === 'auto_generate') {
                    echo "  ✅ 配置为auto_generate，应该会自动转换为JSONObject\n";
                }
            }
        }
    }
    echo "\n";
}

// 2. 检查这些字段的API规范
echo "=== 检查API规范 ===\n";

$attr_table = $wpdb->prefix . 'walmart_product_attributes';

$seat_depth_spec = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$attr_table} 
    WHERE attribute_name = %s 
    LIMIT 1
", 'seat_depth'));

if ($seat_depth_spec) {
    echo "seat_depth API规范:\n";
    echo "  默认类型: {$seat_depth_spec->default_type}\n";
    echo "  属性类型: {$seat_depth_spec->attribute_type}\n";
    echo "  描述: {$seat_depth_spec->description}\n";
    
    if ($seat_depth_spec->default_type === 'measurement_object') {
        echo "  ✅ API规范要求measurement_object格式\n";
    }
} else {
    echo "❌ 未找到seat_depth的API规范\n";
}

$arm_height_spec = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$attr_table} 
    WHERE attribute_name = %s 
    LIMIT 1
", 'arm_height'));

if ($arm_height_spec) {
    echo "\narm_height API规范:\n";
    echo "  默认类型: {$arm_height_spec->default_type}\n";
    echo "  属性类型: {$arm_height_spec->attribute_type}\n";
    echo "  描述: {$arm_height_spec->description}\n";
    
    if ($arm_height_spec->default_type === 'measurement_object') {
        echo "  ✅ API规范要求measurement_object格式\n";
    }
} else {
    echo "❌ 未找到arm_height的API规范\n";
}

// 3. 模拟字段处理过程
echo "\n=== 模拟字段处理过程 ===\n";

if (!empty($seat_depth_mappings)) {
    $mapping = $seat_depth_mappings[0];
    $attributes = json_decode($mapping->walmart_attributes, true);
    
    if ($attributes && isset($attributes['name'])) {
        foreach ($attributes['name'] as $index => $field_name) {
            if (strpos(strtolower($field_name), 'seat_depth') !== false) {
                $type = $attributes['type'][$index] ?? '';
                $source = $attributes['source'][$index] ?? '';
                
                echo "模拟处理seat_depth字段:\n";
                echo "  字段名: {$field_name}\n";
                echo "  映射类型: {$type}\n";
                echo "  映射源: {$source}\n";
                
                if ($type === 'default_value') {
                    echo "  处理结果: 直接使用默认值 '{$source}'\n";
                    echo "  ❌ 问题：这是字符串，但API要求JSONObject！\n";
                    echo "  ❌ convert_field_data_type方法应该将其转换为measurement_object\n";
                    
                    // 测试转换
                    require_once 'includes/class-product-mapper.php';
                    $mapper = new Woo_Walmart_Product_Mapper();
                    
                    $reflection = new ReflectionClass($mapper);
                    $method = $reflection->getMethod('convert_field_data_type');
                    $method->setAccessible(true);
                    
                    // 设置产品类型
                    $product_type_property = $reflection->getProperty('current_product_type_id');
                    $product_type_property->setAccessible(true);
                    $product_type_property->setValue($mapper, $mapping->walmart_category_path);
                    
                    try {
                        $converted = $method->invoke($mapper, $field_name, $source, 'auto');
                        echo "  转换结果: " . json_encode($converted) . "\n";
                        echo "  转换类型: " . gettype($converted) . "\n";
                        
                        if (is_array($converted) && isset($converted['measure']) && isset($converted['unit'])) {
                            echo "  ✅ 转换成功！生成了measurement_object格式\n";
                        } else {
                            echo "  ❌ 转换失败！仍然是原始格式\n";
                        }
                    } catch (Exception $e) {
                        echo "  ❌ 转换异常: " . $e->getMessage() . "\n";
                    }
                }
                break;
            }
        }
    }
}

// 4. 总结问题
echo "\n=== 问题总结 ===\n";
echo "根据分析，问题可能是：\n";
echo "1. seat_depth和arm_height字段配置为default_value类型\n";
echo "2. 虽然有convert_field_data_type方法进行类型转换\n";
echo "3. 但转换可能没有正确执行或者转换逻辑有问题\n";
echo "4. 最终发送给沃尔玛的仍然是字符串而不是JSONObject\n";
echo "\n建议解决方案：\n";
echo "1. 将这些字段的映射类型改为auto_generate\n";
echo "2. 或者确保convert_field_data_type方法正确转换measurement_object类型\n";
echo "3. 检查API规范服务是否正常工作\n";

?>
