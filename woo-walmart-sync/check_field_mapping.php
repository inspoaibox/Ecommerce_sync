<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;

echo "=== 检查分类映射和字段处理 ===\n\n";

// 1. 检查分类映射表
$category_map_table = $wpdb->prefix . 'walmart_category_map';

// 先检查表结构
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$category_map_table'") == $category_map_table;
if ($table_exists) {
    $columns = $wpdb->get_results("DESCRIBE $category_map_table");
    echo "1. 分类映射表结构:\n";
    foreach ($columns as $col) {
        echo "- {$col->Field} ({$col->Type})\n";
    }

    $category_mappings = $wpdb->get_results("SELECT * FROM $category_map_table LIMIT 5");
    echo "\n分类映射数量: " . count($category_mappings) . "\n";
    foreach ($category_mappings as $mapping) {
        echo "映射记录: ";
        print_r($mapping);
    }
} else {
    echo "1. 分类映射表不存在\n";
}

// 2. 检查属性映射表
$attributes_table = $wpdb->prefix . 'walmart_category_attributes';
$attr_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$attributes_table'") == $attributes_table;

echo "\n2. 属性映射表检查:\n";
if ($attr_table_exists) {
    // 检查表结构
    $attr_columns = $wpdb->get_results("DESCRIBE $attributes_table");
    echo "属性映射表结构:\n";
    foreach ($attr_columns as $col) {
        echo "- {$col->Field} ({$col->Type})\n";
    }

    // 检查所有分类的属性
    $all_categories = $wpdb->get_results("SELECT DISTINCT category_name FROM $attributes_table");
    echo "\n已配置的分类:\n";
    foreach ($all_categories as $cat) {
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $attributes_table WHERE category_name = %s", $cat->category_name));
        echo "- {$cat->category_name}: {$count} 个属性\n";
    }

    // 检查Furniture分类的具体属性
    $furniture_attrs = $wpdb->get_results("
        SELECT attribute_name, map_type, map_source
        FROM $attributes_table
        WHERE category_name = 'Furniture'
        LIMIT 10
    ");

    echo "\nFurniture分类的属性映射:\n";
    foreach ($furniture_attrs as $attr) {
        echo "- {$attr->attribute_name}: {$attr->map_type} -> {$attr->map_source}\n";
    }
} else {
    echo "❌ 属性映射表不存在！这是问题的根源。\n";
    echo "没有属性映射表，所有字段都使用默认值，无法从商品属性中读取数据。\n";
}

// 3. 测试具体商品的字段生成
echo "\n3. 测试商品字段生成:\n";

// 获取一个测试商品
$test_product_id = $wpdb->get_var("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    LIMIT 1
");

if ($test_product_id) {
    $product = wc_get_product($test_product_id);
    echo "测试商品ID: {$test_product_id}\n";
    echo "商品名称: " . $product->get_name() . "\n";
    echo "商品SKU: " . $product->get_sku() . "\n";
    
    // 测试mapper
    require_once 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    
    // 获取属性规则
    $walmart_category_name = 'Furniture';
    $attribute_rules = [];
    if ($table_exists) {
        $rules = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $attributes_table 
            WHERE category_name = %s
        ", $walmart_category_name));
        
        foreach ($rules as $rule) {
            $attribute_rules[] = [
                'walmart_attr_name' => $rule->attribute_name,
                'map_type' => $rule->map_type,
                'map_source' => $rule->map_source
            ];
        }
    }
    
    echo "属性规则数量: " . count($attribute_rules) . "\n";
    
    // 测试映射
    $upc = '123456789012';
    $fulfillment_lag_time = 1;
    
    try {
        $walmart_data = $mapper->map($product, $walmart_category_name, $upc, $attribute_rules, $fulfillment_lag_time);
        
        echo "\n4. 生成的字段检查:\n";
        
        // 检查Orderable部分的关键字段
        if (isset($walmart_data['MPItem'][0]['Orderable'])) {
            $orderable = $walmart_data['MPItem'][0]['Orderable'];
            
            $key_fields = [
                'fulfillmentLagTime',
                'electronicsIndicator', 
                'chemicalAerosolPesticide',
                'batteryTechnologyType',
                'shipsInOriginalPackaging',
                'MustShipAlone',
                'IsPreorder'
            ];
            
            foreach ($key_fields as $field) {
                if (isset($orderable[$field])) {
                    $value = $orderable[$field];
                    $type = gettype($value);
                    echo "✓ {$field}: {$value} ({$type})\n";
                } else {
                    echo "❌ {$field}: 缺失\n";
                }
            }
        }
        
        // 检查Visible部分
        if (isset($walmart_data['MPItem'][0]['Visible'][$walmart_category_name])) {
            $visible = $walmart_data['MPItem'][0]['Visible'][$walmart_category_name];
            
            echo "\nVisible部分字段数量: " . count($visible) . "\n";
            
            $required_visible_fields = [
                'productName',
                'brand', 
                'shortDescription',
                'keyFeatures',
                'mainImageUrl'
            ];
            
            foreach ($required_visible_fields as $field) {
                if (isset($visible[$field])) {
                    $value = is_array($visible[$field]) ? '[数组]' : $visible[$field];
                    echo "✓ {$field}: " . substr($value, 0, 50) . "...\n";
                } else {
                    echo "❌ {$field}: 缺失\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "映射测试失败: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "未找到测试商品\n";
}
?>
