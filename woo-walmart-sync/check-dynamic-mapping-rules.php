<?php
/**
 * 检查动态映射规则中的productSecondaryImageURL字段
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查动态映射规则中的productSecondaryImageURL字段 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 检查动态映射-Visible字段的日志，查找productSecondaryImageURL
echo "=== 检查动态映射日志中的productSecondaryImageURL ===\n";

$dynamic_mapping_logs = $wpdb->get_results("
    SELECT id, created_at, request, response 
    FROM {$logs_table} 
    WHERE action = '动态映射-Visible字段' 
    AND (
        request LIKE '%productSecondaryImageURL%' OR
        response LIKE '%productSecondaryImageURL%'
    )
    AND created_at BETWEEN '2025-08-10 15:20:30' AND '2025-08-10 15:20:35'
    ORDER BY id DESC 
    LIMIT 10
");

if (!empty($dynamic_mapping_logs)) {
    echo "找到 " . count($dynamic_mapping_logs) . " 条包含productSecondaryImageURL的动态映射日志:\n";
    
    foreach ($dynamic_mapping_logs as $log) {
        echo "\n=== 日志ID: {$log->id} | 时间: {$log->created_at} ===\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "字段: {$request_data['field']}\n";
            echo "类型: {$request_data['type']}\n";
            echo "来源: {$request_data['source']}\n";
            
            if (isset($request_data['value'])) {
                if (is_array($request_data['value'])) {
                    echo "值: [数组，" . count($request_data['value']) . "个元素]\n";
                    if ($request_data['field'] === 'productSecondaryImageURL') {
                        echo "副图URLs:\n";
                        foreach ($request_data['value'] as $i => $url) {
                            echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
                        }
                    }
                } else {
                    echo "值: {$request_data['value']}\n";
                }
            }
        }
    }
} else {
    echo "❌ 没有找到包含productSecondaryImageURL的动态映射日志\n";
    echo "这说明动态映射规则中没有配置productSecondaryImageURL字段\n";
}

// 2. 检查所有动态映射-Visible字段的日志，看看都映射了哪些字段
echo "\n=== 检查所有动态映射的字段 ===\n";

$all_dynamic_logs = $wpdb->get_results("
    SELECT request 
    FROM {$logs_table} 
    WHERE action = '动态映射-Visible字段' 
    AND created_at BETWEEN '2025-08-10 15:20:33' AND '2025-08-10 15:20:34'
    ORDER BY id ASC 
    LIMIT 50
");

$mapped_fields = [];
foreach ($all_dynamic_logs as $log) {
    $request_data = json_decode($log->request, true);
    if ($request_data && isset($request_data['field'])) {
        $field = $request_data['field'];
        if (!isset($mapped_fields[$field])) {
            $mapped_fields[$field] = 0;
        }
        $mapped_fields[$field]++;
    }
}

if (!empty($mapped_fields)) {
    echo "动态映射的字段统计:\n";
    arsort($mapped_fields);
    foreach ($mapped_fields as $field => $count) {
        echo "- {$field}: {$count}次\n";
        
        if ($field === 'productSecondaryImageURL') {
            echo "  ❌ 发现问题！动态映射覆盖了productSecondaryImageURL字段！\n";
        }
    }
} else {
    echo "❌ 没有找到动态映射字段数据\n";
}

// 3. 检查映射规则配置
echo "\n=== 检查映射规则配置 ===\n";

// 查找产品13917的分类映射
$product_id = 13917;
$product = wc_get_product($product_id);

if ($product) {
    $category_ids = $product->get_category_ids();
    echo "产品分类ID: " . implode(', ', $category_ids) . "\n";
    
    // 查找对应的沃尔玛分类映射
    $mapping_table = $wpdb->prefix . 'woo_walmart_category_mapping';
    
    foreach ($category_ids as $cat_id) {
        echo "\n检查分类ID {$cat_id} 的映射:\n";
        
        // 直接映射查询
        $direct_mapping = $wpdb->get_row($wpdb->prepare("
            SELECT walmart_category_path, walmart_attributes 
            FROM {$mapping_table} 
            WHERE local_category_id = %d
        ", $cat_id));
        
        if ($direct_mapping) {
            echo "✅ 找到直接映射: {$direct_mapping->walmart_category_path}\n";
            
            if (!empty($direct_mapping->walmart_attributes)) {
                $attributes = json_decode($direct_mapping->walmart_attributes, true);
                if ($attributes && isset($attributes['name'])) {
                    echo "映射的属性字段:\n";
                    foreach ($attributes['name'] as $index => $field_name) {
                        $type = $attributes['type'][$index] ?? '';
                        $source = $attributes['source'][$index] ?? '';
                        echo "  - {$field_name} (类型: {$type}, 来源: {$source})\n";
                        
                        if ($field_name === 'productSecondaryImageURL') {
                            echo "    ❌ 发现问题！映射规则中包含productSecondaryImageURL字段！\n";
                            echo "    这会覆盖占位符补足后的图片数组！\n";
                            echo "    类型: {$type}\n";
                            echo "    来源: {$source}\n";
                        }
                    }
                } else {
                    echo "映射属性为空或格式错误\n";
                }
            } else {
                echo "没有配置映射属性\n";
            }
        } else {
            // 共享映射查询
            $shared_mapping = $wpdb->get_row($wpdb->prepare("
                SELECT walmart_category_path, walmart_attributes, local_category_ids 
                FROM {$mapping_table} 
                WHERE local_category_ids IS NOT NULL 
                AND JSON_CONTAINS(local_category_ids, %s)
            ", json_encode(strval($cat_id))));
            
            if ($shared_mapping) {
                echo "✅ 找到共享映射: {$shared_mapping->walmart_category_path}\n";
                
                if (!empty($shared_mapping->walmart_attributes)) {
                    $attributes = json_decode($shared_mapping->walmart_attributes, true);
                    if ($attributes && isset($attributes['name'])) {
                        echo "共享映射的属性字段:\n";
                        foreach ($attributes['name'] as $index => $field_name) {
                            $type = $attributes['type'][$index] ?? '';
                            $source = $attributes['source'][$index] ?? '';
                            echo "  - {$field_name} (类型: {$type}, 来源: {$source})\n";
                            
                            if ($field_name === 'productSecondaryImageURL') {
                                echo "    ❌ 发现问题！共享映射规则中包含productSecondaryImageURL字段！\n";
                                echo "    这会覆盖占位符补足后的图片数组！\n";
                                echo "    类型: {$type}\n";
                                echo "    来源: {$source}\n";
                            }
                        }
                    }
                }
            } else {
                echo "❌ 没有找到映射配置\n";
            }
        }
    }
} else {
    echo "❌ 无法获取产品信息\n";
}

// 4. 总结分析
echo "\n=== 总结分析 ===\n";

if (isset($mapped_fields['productSecondaryImageURL'])) {
    echo "❌ 确认问题：动态映射规则中包含productSecondaryImageURL字段\n";
    echo "执行顺序:\n";
    echo "1. 第330行：设置占位符补足后的5张图片\n";
    echo "2. 第342行：记录图片字段状态（5张图片）\n";
    echo "3. 第526行：动态映射覆盖productSecondaryImageURL字段（变回4张）\n";
    echo "4. 第651行：返回最终数据（只有4张图片）\n";
    echo "\n解决方案：\n";
    echo "1. 从动态映射规则中移除productSecondaryImageURL字段\n";
    echo "2. 或者调整代码执行顺序，让动态映射在占位符补足之前执行\n";
} else {
    echo "✅ 动态映射规则中没有productSecondaryImageURL字段\n";
    echo "需要进一步调查其他可能的覆盖原因\n";
}

?>
