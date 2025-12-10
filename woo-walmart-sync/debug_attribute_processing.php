<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;

echo "=== 调试属性规则处理 ===\n\n";

// 1. 获取测试商品和分类映射
$test_product_id = 173;
$product = wc_get_product($test_product_id);
$product_categories = wp_get_post_terms($test_product_id, 'product_cat');
$first_category_id = $product_categories[0]->term_id ?? 0;

$category_map_table = $wpdb->prefix . 'walmart_category_map';
$mapped_category_data = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM $category_map_table 
    WHERE wc_category_id = %d
", $first_category_id));

if (!$mapped_category_data) {
    echo "❌ 未找到分类映射\n";
    exit;
}

echo "商品: {$product->get_name()}\n";
echo "分类: {$mapped_category_data->wc_category_name}\n";
echo "Walmart分类: {$mapped_category_data->walmart_category_path}\n\n";

// 2. 解析属性规则
$attribute_rules = json_decode($mapped_category_data->walmart_attributes, true);
echo "原始属性规则:\n";
print_r($attribute_rules);

if (!isset($attribute_rules['name'])) {
    echo "❌ 属性规则格式错误\n";
    exit;
}

echo "\n=== 逐个处理属性规则 ===\n";

foreach ($attribute_rules['name'] as $index => $walmart_attr_name) {
    $map_type = $attribute_rules['type'][$index] ?? 'default_value';
    $map_source = $attribute_rules['source'][$index] ?? '';
    
    echo "\n处理字段: {$walmart_attr_name}\n";
    echo "- 映射类型: {$map_type}\n";
    echo "- 映射源: {$map_source}\n";
    
    $value = null;
    
    if ($map_type === 'auto_generate') {
        echo "- 处理方式: 自动生成\n";
        
        // 模拟mapper中的自动生成逻辑
        require_once 'includes/class-product-mapper.php';
        $mapper = new Woo_Walmart_Product_Mapper();
        
        // 使用反射调用私有方法
        $reflection = new ReflectionClass($mapper);
        $method = $reflection->getMethod('generate_special_attribute_value');
        $method->setAccessible(true);
        
        try {
            $generated_value = $method->invoke($mapper, $product, $walmart_attr_name, $attribute_rules, $index);
            echo "- 生成的值: " . (is_array($generated_value) ? '[数组]' : $generated_value) . "\n";
            $value = $generated_value;
        } catch (Exception $e) {
            echo "- 生成失败: " . $e->getMessage() . "\n";
        }
        
    } elseif ($map_type === 'default_value') {
        echo "- 处理方式: 默认值\n";
        $value = $map_source;
        echo "- 使用值: {$value}\n";
        
    } elseif ($map_type === 'wc_attribute') {
        echo "- 处理方式: WC属性\n";
        $wc_value = $product->get_attribute($map_source);
        echo "- WC属性值: " . ($wc_value ?: '空') . "\n";
        $value = $wc_value;
    }
    
    if (empty($value)) {
        echo "- ❌ 最终值为空，字段将被跳过\n";
    } else {
        echo "- ✅ 最终值: " . (is_array($value) ? '[数组]' : $value) . "\n";
    }
}

echo "\n=== 检查mapper中的实际处理 ===\n";

// 3. 实际调用mapper看看结果
require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

try {
    $walmart_data = $mapper->map($product, $mapped_category_data->walmart_category_path, '123456789012', $attribute_rules, 1);
    
    if (isset($walmart_data['MPItem'][0]['Visible'][$mapped_category_data->walmart_category_path])) {
        $visible_fields = $walmart_data['MPItem'][0]['Visible'][$mapped_category_data->walmart_category_path];
        
        echo "实际生成的字段:\n";
        foreach ($attribute_rules['name'] as $field_name) {
            if (isset($visible_fields[$field_name])) {
                $value = $visible_fields[$field_name];
                $display_value = is_array($value) ? '[数组，长度:' . count($value) . ']' : $value;
                echo "✓ {$field_name}: {$display_value}\n";
            } else {
                echo "❌ {$field_name}: 未生成\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "映射失败: " . $e->getMessage() . "\n";
}
?>
