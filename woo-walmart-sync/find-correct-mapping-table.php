<?php
/**
 * 找到正确的分类映射表
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 找到正确的分类映射表 ===\n\n";

global $wpdb;

// 1. 查找所有可能的映射表
echo "=== 查找所有映射相关的表 ===\n";

$tables = $wpdb->get_results("SHOW TABLES LIKE '%mapping%'");
foreach ($tables as $table) {
    $table_name = array_values((array)$table)[0];
    echo "找到表: {$table_name}\n";
    
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "  记录数: {$count}\n";
    
    if ($count > 0) {
        echo "  表结构:\n";
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        foreach ($columns as $column) {
            echo "    - {$column->Field} ({$column->Type})\n";
        }
        
        echo "  前3条记录:\n";
        $records = $wpdb->get_results("SELECT * FROM {$table_name} LIMIT 3");
        foreach ($records as $record) {
            $record_array = (array)$record;
            echo "    记录ID: " . ($record_array['id'] ?? 'N/A') . "\n";
            foreach ($record_array as $key => $value) {
                if (strlen($value) > 100) {
                    $value = substr($value, 0, 100) . '...';
                }
                echo "      {$key}: {$value}\n";
            }
            echo "    ---\n";
        }
    }
    echo "\n";
}

// 2. 查找所有walmart相关的表
echo "=== 查找所有walmart相关的表 ===\n";

$walmart_tables = $wpdb->get_results("SHOW TABLES LIKE '%walmart%'");
foreach ($walmart_tables as $table) {
    $table_name = array_values((array)$table)[0];
    echo "找到表: {$table_name}\n";
    
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "  记录数: {$count}\n";
    
    if ($count > 0 && strpos($table_name, 'mapping') !== false) {
        echo "  这可能是分类映射表！\n";
        
        echo "  表结构:\n";
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        foreach ($columns as $column) {
            echo "    - {$column->Field} ({$column->Type})\n";
        }
        
        echo "  所有记录:\n";
        $records = $wpdb->get_results("SELECT * FROM {$table_name}");
        foreach ($records as $record) {
            $record_array = (array)$record;
            echo "    记录ID: " . ($record_array['id'] ?? 'N/A') . "\n";
            
            // 显示关键字段
            if (isset($record_array['wc_category_name'])) {
                echo "      WC分类: {$record_array['wc_category_name']}\n";
            }
            if (isset($record_array['walmart_category_path'])) {
                echo "      沃尔玛分类: {$record_array['walmart_category_path']}\n";
            }
            if (isset($record_array['local_category_id'])) {
                echo "      本地分类ID: {$record_array['local_category_id']}\n";
            }
            if (isset($record_array['local_category_ids'])) {
                echo "      共享分类ID: {$record_array['local_category_ids']}\n";
            }
            
            // 检查是否包含seat_depth或arm_height
            if (isset($record_array['walmart_attributes'])) {
                $attributes = $record_array['walmart_attributes'];
                if (strpos($attributes, 'seat_depth') !== false || strpos($attributes, 'arm_height') !== false) {
                    echo "      ✅ 包含seat_depth或arm_height字段！\n";
                    
                    $attr_data = json_decode($attributes, true);
                    if ($attr_data && isset($attr_data['name'])) {
                        foreach ($attr_data['name'] as $index => $field_name) {
                            if (in_array(strtolower($field_name), ['seat_depth', 'arm_height'])) {
                                $type = $attr_data['type'][$index] ?? '';
                                $source = $attr_data['source'][$index] ?? '';
                                echo "        字段: {$field_name} (类型: {$type}, 来源: {$source})\n";
                            }
                        }
                    }
                }
            }
            echo "    ---\n";
        }
    }
    echo "\n";
}

// 3. 检查报错产品的分类
echo "=== 检查报错产品的分类 ===\n";

$error_skus = ['W116465061', 'N771P254005L'];

foreach ($error_skus as $sku) {
    echo "产品 {$sku}:\n";
    
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if ($product_id) {
        $product = wc_get_product($product_id);
        $category_ids = $product->get_category_ids();
        echo "  分类ID: " . implode(', ', $category_ids) . "\n";
        
        // 获取分类名称
        foreach ($category_ids as $cat_id) {
            $category = get_term($cat_id, 'product_cat');
            if ($category) {
                echo "    分类 {$cat_id}: {$category->name}\n";
            }
        }
        
        // 在所有映射表中查找这些分类
        foreach ($walmart_tables as $table) {
            $table_name = array_values((array)$table)[0];
            if (strpos($table_name, 'mapping') !== false) {
                
                // 检查直接映射
                foreach ($category_ids as $cat_id) {
                    $direct_mapping = $wpdb->get_row($wpdb->prepare("
                        SELECT * FROM {$table_name} 
                        WHERE local_category_id = %d
                    ", $cat_id));
                    
                    if ($direct_mapping) {
                        echo "  ✅ 在表 {$table_name} 中找到直接映射:\n";
                        echo "    映射ID: {$direct_mapping->id}\n";
                        echo "    沃尔玛分类: {$direct_mapping->walmart_category_path}\n";
                        
                        // 检查是否包含问题字段
                        if (isset($direct_mapping->walmart_attributes)) {
                            $attributes = $direct_mapping->walmart_attributes;
                            if (strpos($attributes, 'seat_depth') !== false || strpos($attributes, 'arm_height') !== false) {
                                echo "    ✅ 包含seat_depth或arm_height字段！\n";
                            }
                        }
                        break 2; // 找到就退出
                    }
                }
                
                // 检查共享映射
                $shared_mappings = $wpdb->get_results("
                    SELECT * FROM {$table_name} 
                    WHERE local_category_ids IS NOT NULL 
                    AND local_category_ids != ''
                ");
                
                foreach ($shared_mappings as $mapping) {
                    $shared_ids = json_decode($mapping->local_category_ids, true);
                    if (is_array($shared_ids)) {
                        $intersection = array_intersect($category_ids, array_map('intval', $shared_ids));
                        if (!empty($intersection)) {
                            echo "  ✅ 在表 {$table_name} 中找到共享映射:\n";
                            echo "    映射ID: {$mapping->id}\n";
                            echo "    沃尔玛分类: {$mapping->walmart_category_path}\n";
                            echo "    匹配的分类: " . implode(', ', $intersection) . "\n";
                            
                            // 检查是否包含问题字段
                            if (isset($mapping->walmart_attributes)) {
                                $attributes = $mapping->walmart_attributes;
                                if (strpos($attributes, 'seat_depth') !== false || strpos($attributes, 'arm_height') !== false) {
                                    echo "    ✅ 包含seat_depth或arm_height字段！\n";
                                }
                            }
                            break 2; // 找到就退出
                        }
                    }
                }
            }
        }
    }
    echo "\n";
}

?>
