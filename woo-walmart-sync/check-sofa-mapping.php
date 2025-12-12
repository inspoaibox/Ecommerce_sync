<?php
/**
 * 检查沙发产品的分类映射配置
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查沙发产品的分类映射配置 ===\n\n";

global $wpdb;

// 1. 查找沙发相关的分类映射
echo "1. 查找沙发相关的分类映射:\n";
$mappings = $wpdb->get_results("
    SELECT id, wc_category_name, walmart_category_path, walmart_attributes 
    FROM {$wpdb->prefix}woo_walmart_category_mapping 
    WHERE wc_category_name LIKE '%沙发%' OR walmart_category_path LIKE '%sofa%'
");

foreach ($mappings as $mapping) {
    echo "ID: {$mapping->id}\n";
    echo "WC分类: {$mapping->wc_category_name}\n";
    echo "沃尔玛分类: {$mapping->walmart_category_path}\n";
    
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        echo "配置的字段数量: " . count($attributes['name']) . "\n";
        
        // 检查是否包含seat_depth
        $has_seat_depth = false;
        foreach ($attributes['name'] as $index => $field_name) {
            if (strpos(strtolower($field_name), 'seat_depth') !== false) {
                $has_seat_depth = true;
                $type = $attributes['type'][$index] ?? '';
                $source = $attributes['source'][$index] ?? '';
                
                echo "  ❌ 找到seat_depth配置:\n";
                echo "    字段: {$field_name}\n";
                echo "    类型: {$type}\n";
                echo "    来源: {$source}\n";
                
                if ($type === 'default_value') {
                    echo "    ❌ 问题：配置为default_value，值为'{$source}'\n";
                    echo "    ❌ 这会发送字符串而不是JSONObject！\n";
                }
                break;
            }
        }
        
        if (!$has_seat_depth) {
            echo "  ✅ 该映射不包含seat_depth字段\n";
        }
    }
    echo "\n";
}

// 2. 查找问题产品使用的具体映射
echo "2. 查找问题产品使用的具体映射:\n";
$problem_sku = 'W2791P306821';

$product_id = $wpdb->get_var($wpdb->prepare("
    SELECT post_id FROM {$wpdb->prefix}postmeta 
    WHERE meta_key = '_sku' AND meta_value = %s
", $problem_sku));

if ($product_id) {
    $product = wc_get_product($product_id);
    $categories = $product->get_category_ids();
    
    echo "产品分类ID: " . implode(', ', $categories) . "\n";
    
    foreach ($categories as $cat_id) {
        $cat = get_term($cat_id);
        if ($cat) {
            echo "分类名称: {$cat->name}\n";
            
            // 查找该分类的映射
            $cat_mapping = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}woo_walmart_category_mapping 
                WHERE wc_category_id = %d
            ", $cat_id));
            
            if ($cat_mapping) {
                echo "  找到映射配置:\n";
                echo "  沃尔玛分类: {$cat_mapping->walmart_category_path}\n";
                
                $attributes = json_decode($cat_mapping->walmart_attributes, true);
                if ($attributes && isset($attributes['name'])) {
                    // 检查seat_depth配置
                    foreach ($attributes['name'] as $index => $field_name) {
                        if (strpos(strtolower($field_name), 'seat_depth') !== false) {
                            $type = $attributes['type'][$index] ?? '';
                            $source = $attributes['source'][$index] ?? '';
                            
                            echo "  ❌ 找到seat_depth配置:\n";
                            echo "    字段: {$field_name}\n";
                            echo "    类型: {$type}\n";
                            echo "    来源: {$source}\n";
                            
                            if ($type === 'default_value') {
                                echo "    ❌ 问题根源：配置为default_value！\n";
                                echo "    ❌ 系统会发送字符串'{$source}'而不是JSONObject\n";
                                echo "    ✅ 解决方案：改为auto_generate类型\n";
                            }
                        }
                    }
                }
            } else {
                echo "  ❌ 该分类没有映射配置\n";
            }
        }
    }
}

// 3. 检查所有包含seat_depth的映射
echo "\n3. 检查所有包含seat_depth的映射:\n";
$all_mappings = $wpdb->get_results("
    SELECT id, wc_category_name, walmart_category_path, walmart_attributes 
    FROM {$wpdb->prefix}woo_walmart_category_mapping 
    WHERE walmart_attributes LIKE '%seat_depth%'
");

foreach ($all_mappings as $mapping) {
    echo "映射ID: {$mapping->id}\n";
    echo "WC分类: {$mapping->wc_category_name}\n";
    echo "沃尔玛分类: {$mapping->walmart_category_path}\n";
    
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        foreach ($attributes['name'] as $index => $field_name) {
            if (strpos(strtolower($field_name), 'seat_depth') !== false) {
                $type = $attributes['type'][$index] ?? '';
                $source = $attributes['source'][$index] ?? '';
                
                echo "  字段: {$field_name}\n";
                echo "  类型: {$type}\n";
                echo "  来源: {$source}\n";
                
                if ($type === 'default_value') {
                    echo "  ❌ 问题：配置为default_value，值为'{$source}'\n";
                    echo "  ❌ 需要修改为auto_generate类型\n";
                } else if ($type === 'auto_generate') {
                    echo "  ✅ 配置正确：auto_generate类型\n";
                }
            }
        }
    }
    echo "\n";
}

echo "=== 结论 ===\n";
echo "问题根源：seat_depth字段在分类映射中配置为default_value类型\n";
echo "这导致系统发送字符串值而不是Walmart API要求的JSONObject格式\n\n";

echo "解决方案：\n";
echo "1. 将seat_depth字段的类型从'default_value'改为'auto_generate'\n";
echo "2. 或者完全移除seat_depth字段（因为它不是必填字段）\n";
echo "3. 重新同步产品\n";

?>
