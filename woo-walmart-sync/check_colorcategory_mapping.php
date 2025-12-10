<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 检查 colorCategory 分类映射规则 ===\n\n";

$product_id = 6203;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n";
echo "产品ID: {$product_id}\n\n";

// 1. 获取分类映射配置
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$product_cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
$main_cat_id = $product_cat_ids[0];

echo "1. 产品分类信息:\n";
echo "主分类ID: {$main_cat_id}\n";

$category = get_term($main_cat_id);
echo "分类名称: {$category->name}\n\n";

$mapped_data = $wpdb->get_row($wpdb->prepare(
    "SELECT walmart_category_path, walmart_attributes FROM $map_table WHERE wc_category_id = %d", 
    $main_cat_id
));

if (!$mapped_data) {
    echo "❌ 没有找到该分类的映射配置\n";
    exit;
}

echo "沃尔玛分类路径: {$mapped_data->walmart_category_path}\n\n";

$attribute_rules = json_decode($mapped_data->walmart_attributes, true);

// 2. 查找colorCategory字段的配置
echo "2. 查找colorCategory字段配置:\n";
$color_category_index = array_search('colorCategory', $attribute_rules['name'] ?? []);

if ($color_category_index !== false) {
    echo "✅ 找到colorCategory配置 (索引: {$color_category_index})\n\n";
    
    echo "配置详情:\n";
    $config_keys = ['type', 'source', 'default_value', 'wc_attribute', 'walmart_field'];
    foreach ($config_keys as $key) {
        if (isset($attribute_rules[$key][$color_category_index])) {
            $value = $attribute_rules[$key][$color_category_index];
            echo "  {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "  {$key}: 未设置\n";
        }
    }
    
    $map_type = $attribute_rules['type'][$color_category_index];
    $map_source = $attribute_rules['source'][$color_category_index] ?? '';
    
    echo "\n映射规则解读:\n";
    switch ($map_type) {
        case 'auto_generate':
            echo "  类型: 自动生成\n";
            echo "  数据源: {$map_source}\n";
            if ($map_source === 'auto') {
                echo "  说明: 系统自动从产品属性中提取颜色信息\n";
            }
            break;
            
        case 'default_value':
            echo "  类型: 默认值\n";
            echo "  默认值: {$map_source}\n";
            echo "  说明: 使用固定的默认值\n";
            break;
            
        case 'wc_attribute':
            echo "  类型: WooCommerce属性\n";
            echo "  属性名: {$map_source}\n";
            echo "  说明: 从指定的产品属性获取值\n";
            break;
            
        default:
            echo "  类型: {$map_type} (未知类型)\n";
    }
    
} else {
    echo "❌ 未找到colorCategory字段配置\n";
    
    // 显示所有配置的字段名，帮助调试
    echo "\n已配置的字段:\n";
    if (isset($attribute_rules['name'])) {
        foreach ($attribute_rules['name'] as $index => $name) {
            echo "  [{$index}] {$name}\n";
        }
    }
    exit;
}

// 3. 检查产品的颜色相关属性
echo "\n3. 检查产品的颜色相关属性:\n";

// 检查所有可能的颜色属性
$color_attributes = [
    'color', 'Color', 'Main Color', 'main_color', 'colour', 'Colour',
    'Product Color', 'product_color', 'item_color', 'Item Color'
];

$found_color_attributes = [];
foreach ($color_attributes as $attr) {
    $value = $product->get_attribute($attr);
    if ($value) {
        $found_color_attributes[$attr] = $value;
        echo "  ✅ {$attr}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

if (empty($found_color_attributes)) {
    echo "  ❌ 没有找到颜色相关属性\n";
}

// 4. 测试映射器处理
echo "\n4. 测试映射器处理colorCategory:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

if ($map_type === 'auto_generate') {
    echo "测试auto_generate类型处理:\n";
    
    $reflection = new ReflectionClass($mapper);
    $generate_method = $reflection->getMethod('generate_special_attribute_value');
    $generate_method->setAccessible(true);
    
    try {
        $result = $generate_method->invoke($mapper, 'colorCategory', $product, 1);
        echo "  generate_special_attribute_value结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "  结果类型: " . gettype($result) . "\n";
        
        if (is_array($result)) {
            echo "  数组长度: " . count($result) . "\n";
            if (!empty($result)) {
                echo "  第一个元素: " . json_encode($result[0], JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    } catch (Exception $e) {
        echo "  ❌ 调用失败: " . $e->getMessage() . "\n";
    }
}

// 5. 执行完整映射
echo "\n5. 执行完整映射测试:\n";

$walmart_data = $mapper->map($product, $mapped_data->walmart_category_path, '123456789012', $attribute_rules, 1);
$visible = $walmart_data['MPItem'][0]['Visible'][$mapped_data->walmart_category_path] ?? [];

if (isset($visible['colorCategory'])) {
    $mapped_value = $visible['colorCategory'];
    echo "✅ 映射结果: " . json_encode($mapped_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "结果类型: " . gettype($mapped_value) . "\n";
    
    // 检查格式是否正确
    if (is_array($mapped_value)) {
        echo "数组长度: " . count($mapped_value) . "\n";
        if (!empty($mapped_value)) {
            echo "数组内容: " . implode(', ', $mapped_value) . "\n";
            
            // 检查是否符合API要求
            $valid_colors = true;
            foreach ($mapped_value as $color) {
                if (!is_string($color) || empty(trim($color))) {
                    $valid_colors = false;
                    break;
                }
            }
            
            if ($valid_colors) {
                echo "✅ 格式正确: 非空字符串数组\n";
            } else {
                echo "❌ 格式错误: 包含空值或非字符串\n";
            }
        } else {
            echo "❌ 格式错误: 空数组\n";
        }
    } else {
        echo "❌ 格式错误: 应该是数组类型\n";
    }
} else {
    echo "❌ colorCategory字段缺失\n";
}

// 6. 检查数据来源分析
echo "\n6. 数据来源分析:\n";

if (isset($visible['colorCategory']) && !empty($found_color_attributes)) {
    $mapped_value = $visible['colorCategory'];
    
    echo "映射结果与产品属性对比:\n";
    foreach ($found_color_attributes as $attr_name => $attr_value) {
        if (is_array($mapped_value) && in_array($attr_value, $mapped_value)) {
            echo "  ✅ 映射结果包含 {$attr_name} 的值: {$attr_value}\n";
        } elseif (is_array($mapped_value)) {
            // 检查是否是部分匹配
            foreach ($mapped_value as $mapped_color) {
                if (stripos($attr_value, $mapped_color) !== false || stripos($mapped_color, $attr_value) !== false) {
                    echo "  🔍 映射结果 '{$mapped_color}' 可能来自 {$attr_name}: '{$attr_value}'\n";
                }
            }
        }
    }
}

echo "\n=== 检查完成 ===\n";
echo "如果映射规则配置正确但结果不符合预期，可能需要检查映射器的处理逻辑\n";
?>
