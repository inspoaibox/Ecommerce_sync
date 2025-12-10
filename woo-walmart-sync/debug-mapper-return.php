<?php
/**
 * 调试 Mapper 返回值结构
 * 检查为什么 $item_data['MPItem'] 可能为空
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 调试 Mapper 返回值结构 ===\n\n";

// 使用一个真实的产品ID
$product_id = 17801;
$product = wc_get_product($product_id);

if (!$product) {
    echo "❌ 产品不存在\n";
    exit;
}

echo "测试产品: {$product->get_name()}\n";
echo "产品ID: {$product_id}\n";
echo "SKU: {$product->get_sku()}\n\n";

// 获取UPC
global $wpdb;
$upc = $wpdb->get_var($wpdb->prepare(
    "SELECT upc_code FROM {$wpdb->prefix}walmart_upc_pool WHERE product_id = %d AND is_used = 1",
    $product_id
));

if (!$upc) {
    echo "❌ 没有找到UPC\n";
    exit;
}

echo "UPC: {$upc}\n";

// 获取分类映射
$product_categories = wp_get_post_terms($product->get_id(), 'product_cat');
$category_mapping = null;

foreach ($product_categories as $category) {
    $cat_id = $category->term_id;
    
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, walmart_attributes FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $category_mapping = [
            'walmart_category' => $mapping->walmart_category_path,
            'attributes' => json_decode($mapping->walmart_attributes, true) ?: []
        ];
        break;
    }
}

if (!$category_mapping) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

echo "Walmart分类: {$category_mapping['walmart_category']}\n";
echo "属性数量: " . count($category_mapping['attributes']) . "\n\n";

// 现在测试 Mapper
echo "=== 测试 Mapper 返回值 ===\n";

try {
    $mapper = new Woo_Walmart_Product_Mapper();
    
    echo "开始调用 mapper->map()...\n";
    
    $item_data = $mapper->map(
        $product,
        $category_mapping['walmart_category'],
        $upc,
        $category_mapping['attributes'],
        get_option('woo_walmart_fulfillment_lag_time', 2)
    );
    
    echo "✅ Mapper 调用完成\n\n";
    
    // 分析返回值结构
    echo "=== 返回值结构分析 ===\n";
    
    if ($item_data === null) {
        echo "❌ 返回值为 NULL\n";
    } elseif ($item_data === false) {
        echo "❌ 返回值为 FALSE\n";
    } elseif (!is_array($item_data)) {
        echo "❌ 返回值不是数组，类型: " . gettype($item_data) . "\n";
    } else {
        echo "✅ 返回值是数组\n";
        echo "顶级键: " . implode(', ', array_keys($item_data)) . "\n";
        
        // 检查 MPItemFeedHeader
        if (isset($item_data['MPItemFeedHeader'])) {
            echo "✅ MPItemFeedHeader 存在\n";
            echo "  内容: " . json_encode($item_data['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ MPItemFeedHeader 不存在\n";
        }
        
        // 检查 MPItem - 这是关键！
        if (isset($item_data['MPItem'])) {
            echo "✅ MPItem 存在\n";
            echo "  类型: " . gettype($item_data['MPItem']) . "\n";
            
            if (is_array($item_data['MPItem'])) {
                echo "  数组长度: " . count($item_data['MPItem']) . "\n";
                
                if (count($item_data['MPItem']) > 0) {
                    echo "  第一个元素存在: ✅\n";
                    echo "  第一个元素类型: " . gettype($item_data['MPItem'][0]) . "\n";
                    
                    if (is_array($item_data['MPItem'][0])) {
                        $first_item = $item_data['MPItem'][0];
                        echo "  第一个元素的键: " . implode(', ', array_keys($first_item)) . "\n";
                        
                        // 检查关键字段
                        if (isset($first_item['Visible'])) {
                            echo "    ✅ Visible 字段存在\n";
                            $visible_keys = array_keys($first_item['Visible']);
                            echo "    Visible 的键: " . implode(', ', $visible_keys) . "\n";
                        } else {
                            echo "    ❌ Visible 字段不存在\n";
                        }
                        
                        if (isset($first_item['Orderable'])) {
                            echo "    ✅ Orderable 字段存在\n";
                        } else {
                            echo "    ❌ Orderable 字段不存在\n";
                        }
                    }
                } else {
                    echo "  ❌ MPItem 数组为空！这就是问题所在！\n";
                }
            } else {
                echo "  ❌ MPItem 不是数组\n";
            }
        } else {
            echo "❌ MPItem 不存在！这就是问题所在！\n";
        }
        
        // 输出完整结构（截断）
        $json_str = json_encode($item_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n完整结构（前1000字符）:\n";
        echo substr($json_str, 0, 1000) . "\n";
        
        if (strlen($json_str) > 1000) {
            echo "... (截断，总长度: " . strlen($json_str) . " 字符)\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Mapper 调用异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ Mapper 调用错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== 调试完成 ===\n";
