<?php
/**
 * 重新检查产品分类映射
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 重新检查产品分类映射 ===\n\n";

// 报错的产品SKU
$error_skus = ['W116465061', 'N771P254005L'];

global $wpdb;
$mapping_table = $wpdb->prefix . 'woo_walmart_category_mapping';

foreach ($error_skus as $sku) {
    echo "=== 检查产品 {$sku} ===\n";
    
    // 1. 查找产品
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
    
    // 3. 查看所有分类映射，看看是否有匹配的
    echo "\n所有分类映射:\n";
    $all_mappings = $wpdb->get_results("
        SELECT id, local_category_id, local_category_ids, wc_category_name, walmart_category_path 
        FROM {$mapping_table} 
        ORDER BY id DESC
    ");
    
    $found_mapping = null;
    
    foreach ($all_mappings as $mapping) {
        // 检查直接映射
        if (in_array($mapping->local_category_id, $category_ids)) {
            echo "✅ 找到直接映射 (ID: {$mapping->id}): 分类{$mapping->local_category_id} -> {$mapping->walmart_category_path}\n";
            $found_mapping = $mapping;
            break;
        }
        
        // 检查共享映射
        if (!empty($mapping->local_category_ids)) {
            $shared_ids = json_decode($mapping->local_category_ids, true);
            if (is_array($shared_ids)) {
                $intersection = array_intersect($category_ids, array_map('intval', $shared_ids));
                if (!empty($intersection)) {
                    echo "✅ 找到共享映射 (ID: {$mapping->id}): 分类" . implode(',', $intersection) . " -> {$mapping->walmart_category_path}\n";
                    $found_mapping = $mapping;
                    break;
                }
            }
        }
    }
    
    if (!$found_mapping) {
        echo "❌ 确实没有找到分类映射\n";
        echo "但是如果其他字段能调取，说明映射存在，可能是查询条件有问题\n";
        
        // 显示前10个映射供参考
        echo "\n前10个映射供参考:\n";
        $sample_mappings = array_slice($all_mappings, 0, 10);
        foreach ($sample_mappings as $mapping) {
            echo "  ID: {$mapping->id} | 本地分类: {$mapping->local_category_id} | 共享分类: {$mapping->local_category_ids} | 沃尔玛分类: {$mapping->walmart_category_path}\n";
        }
    } else {
        // 4. 检查找到的映射的详细配置
        $detailed_mapping = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$mapping_table} WHERE id = %d
        ", $found_mapping->id));
        
        echo "\n映射详情:\n";
        echo "映射ID: {$detailed_mapping->id}\n";
        echo "沃尔玛分类: {$detailed_mapping->walmart_category_path}\n";
        
        if (!empty($detailed_mapping->walmart_attributes)) {
            $attributes = json_decode($detailed_mapping->walmart_attributes, true);
            
            if ($attributes && isset($attributes['name'])) {
                echo "配置的字段数量: " . count($attributes['name']) . "\n";
                
                $has_seat_depth = false;
                $has_arm_height = false;
                
                echo "配置的字段:\n";
                foreach ($attributes['name'] as $index => $field_name) {
                    $type = $attributes['type'][$index] ?? '';
                    $source = $attributes['source'][$index] ?? '';
                    
                    echo "  - {$field_name} (类型: {$type}, 来源: {$source})\n";
                    
                    if (strtolower($field_name) === 'seat_depth') {
                        $has_seat_depth = true;
                        echo "    ✅ 找到seat_depth字段\n";
                        
                        if ($type === 'default_value') {
                            echo "    ❌ 问题：配置为default_value，值为'{$source}'\n";
                            echo "    ❌ 但API要求JSONObject格式！\n";
                        }
                    }
                    
                    if (strtolower($field_name) === 'arm_height') {
                        $has_arm_height = true;
                        echo "    ✅ 找到arm_height字段\n";
                        
                        if ($type === 'default_value') {
                            echo "    ❌ 问题：配置为default_value，值为'{$source}'\n";
                            echo "    ❌ 但API要求JSONObject格式！\n";
                        }
                    }
                }
                
                if (!$has_seat_depth) {
                    echo "  ❌ 没有配置seat_depth字段\n";
                }
                
                if (!$has_arm_height) {
                    echo "  ❌ 没有配置arm_height字段\n";
                }
                
            } else {
                echo "❌ 映射属性格式错误\n";
            }
        } else {
            echo "❌ 没有配置映射属性\n";
        }
    }
    
    echo "\n";
}

// 5. 检查最近的同步日志，看看实际使用的分类
echo "=== 检查最近的同步日志 ===\n";

$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

foreach ($error_skus as $sku) {
    echo "产品 {$sku} 的最近同步日志:\n";
    
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if ($product_id) {
        $recent_logs = $wpdb->get_results($wpdb->prepare("
            SELECT action, request, created_at 
            FROM {$logs_table} 
            WHERE product_id = %d 
            AND (action LIKE '%映射%' OR action LIKE '%分类%')
            ORDER BY created_at DESC 
            LIMIT 3
        ", $product_id));
        
        foreach ($recent_logs as $log) {
            echo "  {$log->created_at} | {$log->action}\n";
            
            if (!empty($log->request)) {
                $request_data = json_decode($log->request, true);
                if ($request_data && isset($request_data['walmart_category'])) {
                    echo "    使用的沃尔玛分类: {$request_data['walmart_category']}\n";
                }
            }
        }
    }
    echo "\n";
}

?>
