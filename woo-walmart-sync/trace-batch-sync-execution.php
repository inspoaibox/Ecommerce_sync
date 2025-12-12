<?php
/**
 * 追踪批量同步的实际执行过程
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 追踪批量同步的实际执行过程 ===\n\n";

$target_sku = 'B081S00179';

// 获取产品
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $target_sku
));

$product = wc_get_product($product_id);
echo "产品: {$product->get_name()}\n";
echo "SKU: {$target_sku}\n\n";

// 1. 模拟批量同步中的分类映射获取逻辑
echo "1. 模拟批量同步中的分类映射获取逻辑:\n";

$product_cat_ids = $product->get_category_ids();
echo "产品分类IDs: " . implode(', ', $product_cat_ids) . "\n";

$walmart_category_name = null;
$attribute_rules = null;

$map_table = $wpdb->prefix . 'walmart_category_map';
echo "使用映射表: {$map_table}\n";

foreach ($product_cat_ids as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$map_table} WHERE wc_category_id = %d",
        $cat_id
    ));

    if ($mapping) {
        $walmart_category_name = $mapping->walmart_category_path;
        $attribute_rules = !empty($mapping->walmart_attributes) ?
            json_decode($mapping->walmart_attributes, true) : [];
        echo "✅ 找到分类映射: 分类{$cat_id} → {$walmart_category_name}\n";
        break;
    } else {
        echo "❌ 分类{$cat_id}没有映射\n";
    }
}

if (!$walmart_category_name) {
    echo "❌ 没有找到任何分类映射，这就是问题所在！\n";
    echo "批量同步会跳过这个产品\n";
    exit;
}

// 2. 获取UPC
echo "\n2. 获取UPC:\n";
$upc = get_post_meta($product->get_id(), '_walmart_upc', true);
echo "产品UPC: {$upc}\n";

// 3. 模拟批量同步中的产品映射过程
echo "\n3. 模拟批量同步中的产品映射过程:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

$fulfillment_lag_time = get_option('woo_walmart_fulfillment_lag_time', 1);
$fulfillment_lag_time = max(0, min(1, (int)$fulfillment_lag_time));

echo "映射参数:\n";
echo "  产品ID: {$product->get_id()}\n";
echo "  Walmart分类: {$walmart_category_name}\n";
echo "  UPC: {$upc}\n";
echo "  属性规则: " . (is_array($attribute_rules) ? count($attribute_rules['name'] ?? []) . '个字段' : '无') . "\n";
echo "  备货时间: {$fulfillment_lag_time}\n";

try {
    $start_time = microtime(true);
    
    $product_data = $mapper->map($product, $walmart_category_name, $upc, $attribute_rules, $fulfillment_lag_time);
    
    $end_time = microtime(true);
    echo "映射耗时: " . round(($end_time - $start_time) * 1000, 2) . "ms\n";

    // 4. 检查映射结果中的图片字段
    echo "\n4. 检查映射结果中的图片字段:\n";
    
    if ($product_data && isset($product_data['MPItem'][0])) {
        $item_data = $product_data['MPItem'][0];
        
        if (isset($item_data['Visible'][$walmart_category_name])) {
            $visible_data = $item_data['Visible'][$walmart_category_name];
            
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
                    echo "🚨 占位符填充逻辑没有生效！\n";
                    
                    // 详细分析为什么没有生效
                    echo "\n🔍 分析占位符填充失效原因:\n";
                    
                    // 检查占位符配置
                    $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
                    $placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');
                    
                    echo "占位符1配置: " . ($placeholder_1 ? '✅ 已配置' : '❌ 未配置') . "\n";
                    echo "占位符2配置: " . ($placeholder_2 ? '✅ 已配置' : '❌ 未配置') . "\n";
                    
                    if (empty($placeholder_1)) {
                        echo "🚨 占位符1未配置，这是问题的根源！\n";
                    }
                }
                
            } else {
                echo "❌ 副图字段缺失\n";
            }
            
        } else {
            echo "❌ 没有找到分类数据: {$walmart_category_name}\n";
            echo "可用的分类键: " . implode(', ', array_keys($item_data['Visible'] ?? [])) . "\n";
        }
        
    } else {
        echo "❌ 映射结果格式异常\n";
        echo "映射结果结构: " . json_encode(array_keys($product_data ?? []), JSON_UNESCAPED_UNICODE) . "\n";
    }

} catch (Exception $e) {
    echo "❌ 映射过程异常: " . $e->getMessage() . "\n";
    echo "异常堆栈:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 对比分析 ===\n";
echo "现在我们可以对比:\n";
echo "1. 测试环境的映射结果\n";
echo "2. 批量同步的映射结果\n";
echo "3. 找出差异所在\n";

?>
