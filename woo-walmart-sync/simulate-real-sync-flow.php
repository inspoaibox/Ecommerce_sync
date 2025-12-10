<?php
/**
 * 模拟真实同步流程，检查占位符填充
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 模拟真实同步流程 ===\n\n";

$target_sku = 'B081S00179';

// 1. 获取产品
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $target_sku
));

if (!$product_id) {
    echo "❌ 产品不存在\n";
    exit;
}

$product = wc_get_product($product_id);
echo "产品: {$product->get_name()}\n";
echo "SKU: {$target_sku}\n\n";

// 2. 获取Walmart分类映射（修复版本）
$categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
echo "产品分类IDs: " . implode(', ', $categories) . "\n";

$mapping_table = $wpdb->prefix . 'walmart_category_mappings_new';
$walmart_category = null;

// 检查所有可能的映射表
$possible_tables = [
    $wpdb->prefix . 'walmart_category_mappings_new',
    $wpdb->prefix . 'walmart_category_mappings',
    $wpdb->prefix . 'woo_walmart_category_mappings'
];

foreach ($possible_tables as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($table_exists) {
        echo "检查映射表: {$table}\n";

        foreach ($categories as $cat_id) {
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT walmart_category_path FROM {$table} WHERE wc_category_id = %d",
                $cat_id
            ));

            if ($mapping) {
                $walmart_category = $mapping->walmart_category_path;
                echo "✅ 找到映射: 分类{$cat_id} → {$walmart_category}\n";
                break 2;
            }
        }
    }
}

// 如果还是没找到，检查产品是否有直接的分类映射meta
if (!$walmart_category) {
    $direct_mapping = get_post_meta($product_id, '_walmart_category', true);
    if ($direct_mapping) {
        $walmart_category = $direct_mapping;
        echo "✅ 找到直接映射: {$walmart_category}\n";
    }
}

// 如果还是没找到，使用默认分类
if (!$walmart_category) {
    // 使用实际找到的映射
    $walmart_category = 'Dining Furniture Sets';
    echo "✅ 使用实际映射的分类: {$walmart_category}\n";
}

echo "Walmart分类: {$walmart_category}\n\n";

// 3. 模拟真实的产品映射过程
echo "3. 模拟真实的产品映射过程:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

try {
    echo "调用 map()...\n";

    // 获取必要的参数
    $upc = '123456789012'; // 模拟UPC
    $attribute_rules = []; // 简化的属性规则
    $fulfillment_lag_time = 1;

    // 启用详细日志
    $start_time = microtime(true);

    $item_data = $mapper->map($product, $walmart_category, $upc, $attribute_rules, $fulfillment_lag_time);

    $end_time = microtime(true);
    echo "映射耗时: " . round(($end_time - $start_time) * 1000, 2) . "ms\n\n";
    
    // 4. 检查映射结果中的图片字段
    echo "4. 检查映射结果中的图片字段:\n";

    // 先检查返回数据的结构
    echo "返回数据结构:\n";
    if (is_array($item_data)) {
        echo "顶级键: " . implode(', ', array_keys($item_data)) . "\n";

        if (isset($item_data['Visible'])) {
            echo "Visible键: " . implode(', ', array_keys($item_data['Visible'])) . "\n";
        }
    } else {
        echo "返回数据类型: " . gettype($item_data) . "\n";
        echo "返回数据: " . var_export($item_data, true) . "\n";
    }

    $category_parts = explode(' > ', $walmart_category);
    $walmart_category_name = end($category_parts);
    echo "查找分类键: {$walmart_category_name}\n";

    // 检查MPItem结构
    if (isset($item_data['MPItem'])) {
        echo "检查MPItem结构...\n";
        $mp_item = $item_data['MPItem'];

        // MPItem可能是数组
        if (is_array($mp_item) && isset($mp_item[0])) {
            echo "MPItem是数组，使用第一个元素\n";
            $mp_item = $mp_item[0];
        }

        if (isset($mp_item['Visible'])) {
            echo "Visible键: " . implode(', ', array_keys($mp_item['Visible'])) . "\n";

            // 尝试找到正确的分类键
            $visible_data = null;
            foreach ($mp_item['Visible'] as $key => $data) {
                if (strpos($key, 'Dining') !== false || strpos($key, 'Furniture') !== false) {
                    echo "找到匹配的分类键: {$key}\n";
                    $visible_data = $data;
                    break;
                }
            }

            // 如果没找到匹配的，使用第一个
            if (!$visible_data && !empty($mp_item['Visible'])) {
                $first_key = array_keys($mp_item['Visible'])[0];
                echo "使用第一个分类键: {$first_key}\n";
                $visible_data = $mp_item['Visible'][$first_key];
            }

        } else {
            echo "MPItem中没有Visible字段\n";
            echo "MPItem键: " . implode(', ', array_keys($mp_item)) . "\n";
        }
    } else {
        echo "没有找到MPItem\n";
    }

    if (isset($visible_data)) {
        
        // 主图
        if (isset($visible_data['mainImageUrl'])) {
            echo "✅ 主图字段存在\n";
            echo "主图URL: " . substr($visible_data['mainImageUrl'], 0, 80) . "...\n";
        } else {
            echo "❌ 主图字段缺失\n";
        }
        
        // 副图
        if (isset($visible_data['productSecondaryImageURL'])) {
            $secondary_images = $visible_data['productSecondaryImageURL'];
            echo "✅ 副图字段存在\n";
            echo "副图数量: " . count($secondary_images) . "\n";
            
            echo "副图列表:\n";
            foreach ($secondary_images as $i => $url) {
                $is_placeholder = (strpos($url, 'walmartimages.com') !== false);
                $type = $is_placeholder ? '[占位符]' : '[产品图]';
                echo "  " . ($i + 1) . ". {$type} " . substr($url, 0, 60) . "...\n";
            }
            
            // 统计占位符数量
            $placeholder_count = 0;
            $product_image_count = 0;
            
            foreach ($secondary_images as $url) {
                if (strpos($url, 'walmartimages.com') !== false) {
                    $placeholder_count++;
                } else {
                    $product_image_count++;
                }
            }
            
            echo "\n副图统计:\n";
            echo "  产品图片: {$product_image_count}张\n";
            echo "  占位符图: {$placeholder_count}张\n";
            echo "  总计: " . count($secondary_images) . "张\n";
            
            // 判断是否满足要求
            if (count($secondary_images) >= 5) {
                echo "✅ 满足Walmart要求（≥5张）\n";
                
                if ($placeholder_count > 0) {
                    echo "🎯 占位符填充生效！添加了 {$placeholder_count} 张占位符\n";
                } else {
                    echo "ℹ️ 产品图片充足，无需占位符\n";
                }
            } else {
                echo "❌ 不满足Walmart要求（<5张）\n";
                echo "🚨 占位符填充逻辑可能没有生效\n";
            }
            
        } else {
            echo "❌ 副图字段缺失\n";
        }
        
    } else {
        echo "❌ 没有找到分类数据: {$walmart_category_name}\n";
        echo "可用的分类键: " . implode(', ', array_keys($item_data['Visible'] ?? [])) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 映射过程异常: " . $e->getMessage() . "\n";
    echo "异常堆栈:\n" . $e->getTraceAsString() . "\n";
}

// 5. 检查同步日志
echo "\n5. 检查最近的同步日志:\n";

$recent_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT action, level, message, details, created_at FROM {$wpdb->prefix}woo_walmart_sync_logs 
     WHERE product_id = %d 
     AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
     ORDER BY created_at DESC 
     LIMIT 10",
    $product_id
));

if (!empty($recent_logs)) {
    foreach ($recent_logs as $log) {
        $details = json_decode($log->details, true);
        echo "[{$log->created_at}] {$log->level}: {$log->message}\n";
        
        // 特别关注图片相关的日志
        if (strpos($log->message, '图片') !== false || strpos($log->message, '占位符') !== false) {
            if ($details) {
                echo "  详情: " . json_encode($details, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "没有找到最近的同步日志\n";
}

echo "=== 模拟完成 ===\n";

echo "\n🎯 **关键问题诊断**:\n";
echo "1. 映射过程是否正常执行？\n";
echo "2. 副图数量是否正确统计？\n";
echo "3. 占位符填充逻辑是否被触发？\n";
echo "4. 最终的副图数量是否≥5张？\n";
echo "5. 如果没有填充，是什么原因？\n";

?>
