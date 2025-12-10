<?php
/**
 * 检查报错产品的分类映射
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查报错产品的分类映射 ===\n\n";

// 报错的产品SKU
$error_skus = ['W116465061', 'N771P254005L'];

foreach ($error_skus as $sku) {
    echo "=== 检查产品 {$sku} ===\n";
    
    // 1. 查找产品
    global $wpdb;
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if (!$product_id) {
        echo "❌ 未找到SKU为 {$sku} 的产品\n\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        echo "❌ 无法加载产品 {$product_id}\n\n";
        continue;
    }
    
    echo "产品ID: {$product_id}\n";
    echo "产品名称: {$product->get_name()}\n";
    
    // 2. 获取产品分类
    $category_ids = $product->get_category_ids();
    echo "分类ID: " . implode(', ', $category_ids) . "\n";
    
    // 3. 查找分类映射
    $mapping_table = $wpdb->prefix . 'woo_walmart_category_mapping';
    
    $walmart_category = null;
    $mapping_attributes = null;
    
    foreach ($category_ids as $cat_id) {
        // 直接映射
        $direct_mapping = $wpdb->get_row($wpdb->prepare("
            SELECT walmart_category_path, walmart_attributes 
            FROM {$mapping_table} 
            WHERE local_category_id = %d
        ", $cat_id));
        
        if ($direct_mapping) {
            $walmart_category = $direct_mapping->walmart_category_path;
            $mapping_attributes = $direct_mapping->walmart_attributes;
            echo "找到直接映射: {$walmart_category}\n";
            break;
        }
        
        // 共享映射
        $shared_mapping = $wpdb->get_row($wpdb->prepare("
            SELECT walmart_category_path, walmart_attributes, local_category_ids 
            FROM {$mapping_table} 
            WHERE local_category_ids IS NOT NULL 
            AND JSON_CONTAINS(local_category_ids, %s)
        ", json_encode(strval($cat_id))));
        
        if ($shared_mapping) {
            $walmart_category = $shared_mapping->walmart_category_path;
            $mapping_attributes = $shared_mapping->walmart_attributes;
            echo "找到共享映射: {$walmart_category}\n";
            break;
        }
    }
    
    if (!$walmart_category) {
        echo "❌ 未找到沃尔玛分类映射\n\n";
        continue;
    }
    
    // 4. 检查映射属性中是否包含seat_depth和arm_height
    if ($mapping_attributes) {
        $attributes = json_decode($mapping_attributes, true);
        
        if ($attributes && isset($attributes['name'])) {
            echo "映射的字段:\n";
            
            $has_seat_depth = false;
            $has_arm_height = false;
            
            foreach ($attributes['name'] as $index => $field_name) {
                $type = $attributes['type'][$index] ?? '';
                $source = $attributes['source'][$index] ?? '';
                
                echo "  - {$field_name} (类型: {$type}, 来源: {$source})\n";
                
                if (strtolower($field_name) === 'seat_depth') {
                    $has_seat_depth = true;
                    echo "    ✅ 找到seat_depth字段配置\n";
                }
                
                if (strtolower($field_name) === 'arm_height') {
                    $has_arm_height = true;
                    echo "    ✅ 找到arm_height字段配置\n";
                }
            }
            
            if (!$has_seat_depth) {
                echo "  ❌ 缺少seat_depth字段配置\n";
            }
            
            if (!$has_arm_height) {
                echo "  ❌ 缺少arm_height字段配置\n";
            }
            
        } else {
            echo "❌ 映射属性格式错误\n";
        }
    } else {
        echo "❌ 没有映射属性配置\n";
    }
    
    // 5. 检查API规范中是否要求这些字段
    $attr_table = $wpdb->prefix . 'walmart_product_attributes';
    
    $required_fields = $wpdb->get_results($wpdb->prepare("
        SELECT attribute_name, is_required 
        FROM {$attr_table} 
        WHERE product_type_id = %s 
        AND attribute_name IN ('seat_depth', 'arm_height')
    ", $walmart_category));
    
    if (!empty($required_fields)) {
        echo "API规范要求的字段:\n";
        foreach ($required_fields as $field) {
            $required_text = $field->is_required ? '必填' : '可选';
            echo "  - {$field->attribute_name} ({$required_text})\n";
        }
    } else {
        echo "❌ API规范中没有找到这些字段\n";
    }
    
    echo "\n";
}

// 6. 总结问题
echo "=== 问题总结 ===\n";
echo "如果产品报错说seat_depth和arm_height字段类型错误，\n";
echo "但分类映射中没有配置这些字段，说明：\n";
echo "1. 系统在某个地方自动添加了这些字段\n";
echo "2. 但没有通过正确的映射和转换流程\n";
echo "3. 可能是在generate_special_attribute_value中自动生成的\n";
echo "4. 或者是API规范要求的必填字段被自动添加\n";

?>
