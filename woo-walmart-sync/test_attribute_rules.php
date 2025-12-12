<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;

echo "=== 测试属性规则处理 ===\n\n";

// 1. 获取一个有分类映射的商品
$test_product_id = 173; // 从之前的测试中知道这个商品ID
$product = wc_get_product($test_product_id);

echo "测试商品: {$product->get_name()}\n";
echo "商品SKU: {$product->get_sku()}\n";

// 2. 获取商品的分类
$product_categories = wp_get_post_terms($test_product_id, 'product_cat');
echo "商品分类:\n";
foreach ($product_categories as $cat) {
    echo "- {$cat->name} (ID: {$cat->term_id})\n";
}

// 3. 查找分类映射
$category_map_table = $wpdb->prefix . 'walmart_category_map';
$first_category_id = $product_categories[0]->term_id ?? 0;

$mapped_category_data = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM $category_map_table 
    WHERE wc_category_id = %d
", $first_category_id));

if ($mapped_category_data) {
    echo "\n找到分类映射:\n";
    echo "WC分类: {$mapped_category_data->wc_category_name}\n";
    echo "Walmart分类: {$mapped_category_data->walmart_category_path}\n";
    
    // 4. 解析属性规则
    $attribute_rules = json_decode($mapped_category_data->walmart_attributes, true);
    echo "\n属性规则:\n";
    print_r($attribute_rules);
    
    if (isset($attribute_rules['name'])) {
        echo "\n具体的属性映射:\n";
        foreach ($attribute_rules['name'] as $index => $walmart_attr_name) {
            $map_type = $attribute_rules['type'][$index] ?? 'unknown';
            $map_source = $attribute_rules['source'][$index] ?? 'unknown';
            echo "- {$walmart_attr_name}: {$map_type} -> {$map_source}\n";
        }
    }
    
    // 5. 测试商品属性获取
    echo "\n商品自定义属性:\n";
    $product_attributes = $product->get_attributes();
    foreach ($product_attributes as $attr_name => $attribute) {
        if ($attribute->is_taxonomy()) {
            $terms = wp_get_post_terms($test_product_id, $attribute->get_name());
            $values = wp_list_pluck($terms, 'name');
            echo "- {$attr_name}: " . implode(', ', $values) . "\n";
        } else {
            echo "- {$attr_name}: {$attribute->get_options()[0]}\n";
        }
    }
    
    // 6. 测试meta字段
    echo "\n商品Meta字段:\n";
    $meta_keys = ['_weight', '_length', '_width', '_height', 'brand', 'color', 'material'];
    foreach ($meta_keys as $key) {
        $value = get_post_meta($test_product_id, $key, true);
        if (!empty($value)) {
            echo "- {$key}: {$value}\n";
        }
    }
    
    // 7. 测试实际的字段生成
    echo "\n=== 测试字段生成 ===\n";
    require_once 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    
    // 转换属性规则格式（从JSON格式转换为mapper期望的格式）
    $converted_rules = [];
    if (isset($attribute_rules['name'])) {
        foreach ($attribute_rules['name'] as $index => $walmart_attr_name) {
            $converted_rules[] = [
                'walmart_attr_name' => $walmart_attr_name,
                'map_type' => $attribute_rules['type'][$index] ?? 'default_value',
                'map_source' => $attribute_rules['source'][$index] ?? ''
            ];
        }
    }
    
    echo "转换后的规则数量: " . count($converted_rules) . "\n";
    
    try {
        $walmart_data = $mapper->map($product, 'furniture_other', '123456789012', $converted_rules, 1);
        
        // 检查生成的字段
        if (isset($walmart_data['MPItem'][0]['Visible']['furniture_other'])) {
            $visible_fields = $walmart_data['MPItem'][0]['Visible']['furniture_other'];
            
            echo "\n生成的Visible字段:\n";
            foreach ($converted_rules as $rule) {
                $field_name = $rule['walmart_attr_name'];
                if (isset($visible_fields[$field_name])) {
                    $value = is_array($visible_fields[$field_name]) ? '[数组]' : $visible_fields[$field_name];
                    echo "✓ {$field_name}: " . substr($value, 0, 100) . "\n";
                } else {
                    echo "❌ {$field_name}: 未生成\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "映射失败: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "\n❌ 未找到该商品的分类映射\n";
}
?>
