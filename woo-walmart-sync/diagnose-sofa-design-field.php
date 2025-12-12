<?php
/**
 * 诊断 sofa_and_loveseat_design 字段为什么会报错
 * 检查失败的SKU产品数据
 */

require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 诊断 sofa_and_loveseat_design 字段问题 ===\n\n";

// 失败的SKU列表
$failed_skus = ['W714P357249', 'W487S00390', 'WF310165AAA'];

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

foreach ($failed_skus as $sku) {
    echo str_repeat("=", 80) . "\n";
    echo "检查 SKU: {$sku}\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // 1. 查找产品
    $product_id = wc_get_product_id_by_sku($sku);
    
    if (!$product_id) {
        echo "❌ 错误：找不到SKU为 {$sku} 的产品\n\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        echo "❌ 错误：无法加载产品 ID {$product_id}\n\n";
        continue;
    }
    
    echo "✅ 找到产品 ID: {$product_id}\n";
    echo "产品名称: {$product->get_name()}\n";
    echo "产品类型: {$product->get_type()}\n\n";
    
    // 2. 检查产品基本信息
    echo "【产品基本信息】\n";
    echo "标题: " . ($product->get_name() ?: '(空)') . "\n";
    echo "简短描述: " . (strip_tags($product->get_short_description()) ?: '(空)') . "\n";
    echo "完整描述: " . (strip_tags(substr($product->get_description(), 0, 200)) ?: '(空)') . "...\n\n";
    
    // 3. 检查产品分类
    echo "【产品分类】\n";
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
    if (!empty($categories)) {
        foreach ($categories as $cat) {
            echo "- {$cat}\n";
        }
    } else {
        echo "❌ 无分类\n";
    }
    echo "\n";
    
    // 4. 检查 Walmart 分类映射
    echo "【Walmart 分类映射】\n";
    global $wpdb;
    $category_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (!empty($category_ids)) {
        $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
        $query = $wpdb->prepare("
            SELECT local_category_id, wc_category_name, walmart_category_path, walmart_attributes
            FROM {$wpdb->prefix}walmart_category_map
            WHERE local_category_id IN ({$placeholders})
        ", $category_ids);
        
        $mappings = $wpdb->get_results($query);
        
        if (!empty($mappings)) {
            foreach ($mappings as $mapping) {
                echo "本地分类: {$mapping->wc_category_name}\n";
                echo "Walmart分类: {$mapping->walmart_category_path}\n";
                
                // 检查是否配置了 sofa_and_loveseat_design 字段
                $attributes = json_decode($mapping->walmart_attributes, true);
                $has_sofa_design = false;
                
                if (is_array($attributes)) {
                    foreach ($attributes as $attr) {
                        if (isset($attr['name']) && $attr['name'] === 'sofa_and_loveseat_design') {
                            $has_sofa_design = true;
                            echo "✅ 已配置 sofa_and_loveseat_design 字段\n";
                            echo "   映射类型: {$attr['type']}\n";
                            echo "   来源: {$attr['source']}\n";
                            break;
                        }
                    }
                }
                
                if (!$has_sofa_design) {
                    echo "❌ 未配置 sofa_and_loveseat_design 字段\n";
                }
                echo "\n";
            }
        } else {
            echo "❌ 没有找到 Walmart 分类映射\n\n";
        }
    } else {
        echo "❌ 产品没有分类\n\n";
    }
    
    // 5. 测试字段生成
    echo "【测试字段生成】\n";
    
    $mapper = new Woo_Walmart_Product_Mapper();
    $reflection = new ReflectionClass($mapper);
    
    // 测试 extract_sofa_loveseat_design 方法
    $method = $reflection->getMethod('extract_sofa_loveseat_design');
    $method->setAccessible(true);
    
    try {
        $result = $method->invoke($mapper, $product);
        echo "extract_sofa_loveseat_design() 返回值:\n";
        echo "  类型: " . gettype($result) . "\n";
        echo "  值: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        
        if (empty($result)) {
            echo "  ⚠️ 警告：返回值为空\n";
        } elseif (!is_array($result)) {
            echo "  ⚠️ 警告：返回值不是数组\n";
        }
    } catch (Exception $e) {
        echo "❌ 方法调用失败: {$e->getMessage()}\n";
    }
    echo "\n";
    
    // 6. 测试 generate_special_attribute_value 方法
    $method2 = $reflection->getMethod('generate_special_attribute_value');
    $method2->setAccessible(true);
    
    try {
        $result2 = $method2->invoke($mapper, 'sofa_and_loveseat_design', $product, 1);
        echo "generate_special_attribute_value('sofa_and_loveseat_design') 返回值:\n";
        echo "  类型: " . gettype($result2) . "\n";
        echo "  值: " . json_encode($result2, JSON_UNESCAPED_UNICODE) . "\n";
        
        if (empty($result2)) {
            echo "  ⚠️ 警告：返回值为空\n";
        }
    } catch (Exception $e) {
        echo "❌ 方法调用失败: {$e->getMessage()}\n";
    }
    echo "\n";
    
    // 7. 测试 convert_field_data_type 方法
    $method3 = $reflection->getMethod('convert_field_data_type');
    $method3->setAccessible(true);
    
    try {
        $test_value = $result2 ?? null;
        $result3 = $method3->invoke($mapper, 'sofa_and_loveseat_design', $test_value, null);
        echo "convert_field_data_type('sofa_and_loveseat_design') 返回值:\n";
        echo "  输入: " . json_encode($test_value, JSON_UNESCAPED_UNICODE) . "\n";
        echo "  输出类型: " . gettype($result3) . "\n";
        echo "  输出值: " . json_encode($result3, JSON_UNESCAPED_UNICODE) . "\n";
        
        if (empty($result3)) {
            echo "  ⚠️ 警告：转换后返回值为空\n";
        }
    } catch (Exception $e) {
        echo "❌ 方法调用失败: {$e->getMessage()}\n";
    }
    echo "\n";
    
    // 8. 模拟完整的映射流程
    echo "【模拟完整映射流程】\n";
    
    try {
        // 获取产品的 Walmart 映射数据
        $method4 = $reflection->getMethod('map_product_to_walmart_format');
        $method4->setAccessible(true);
        
        $walmart_data = $method4->invoke($mapper, $product, 1);
        
        // 检查 sofa_and_loveseat_design 字段是否存在
        if (isset($walmart_data['sofa_and_loveseat_design'])) {
            echo "✅ sofa_and_loveseat_design 字段存在于映射数据中\n";
            echo "  值: " . json_encode($walmart_data['sofa_and_loveseat_design'], JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ sofa_and_loveseat_design 字段不存在于映射数据中\n";
            echo "  可能原因：\n";
            echo "  1. 分类映射中未配置此字段\n";
            echo "  2. 字段生成返回了 null\n";
            echo "  3. 字段被过滤掉了\n";
        }
        
        // 显示映射数据中的所有字段
        echo "\n映射数据中的所有字段:\n";
        foreach (array_keys($walmart_data) as $key) {
            echo "  - {$key}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 映射流程失败: {$e->getMessage()}\n";
    }
    
    echo "\n\n";
}

echo str_repeat("=", 80) . "\n";
echo "诊断完成\n";
echo str_repeat("=", 80) . "\n";
?>

