<?php
/**
 * 测试mainImageUrl修复效果
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== mainImageUrl修复测试 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
if (!defined('ABSPATH')) {
    $wp_paths = [
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../../../wp-load.php',
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            echo "✅ WordPress加载成功: {$path}\n";
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("❌ 错误：无法找到WordPress。请手动修改路径。\n");
    }
}

// 加载必要的类
require_once 'includes/class-product-mapper.php';

// 测试失败的SKU
$test_skus = [
    'W15BAU194E84',  // index 2 - 错误
    'W89CT5036E',    // index 3 - 错误  
    'W18B96281B6',   // index 4 - 错误
];

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);
$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

foreach ($test_skus as $sku) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "测试SKU: {$sku}\n";
    echo str_repeat("=", 70) . "\n";
    
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "❌ 找不到产品\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    echo "产品: {$product->get_name()}\n\n";
    
    // ============================================
    // 测试修复后的generate_special_attribute_value
    // ============================================
    echo "【测试generate_special_attribute_value】\n";
    echo str_repeat("-", 50) . "\n";
    
    try {
        $generated_url = $generate_method->invoke($mapper, 'mainImageUrl', $product, 1);
        
        if ($generated_url) {
            echo "✅ 生成成功: {$generated_url}\n";
            echo "URL长度: " . strlen($generated_url) . " 字符\n";
            
            // 检查URL特征
            if (strpos($generated_url, '?') !== false) {
                echo "⚠️ 包含查询参数\n";
            } else {
                echo "✅ 不包含查询参数\n";
            }
            
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $generated_url)) {
                echo "✅ 以图片扩展名结尾\n";
            } else {
                echo "❌ 不以图片扩展名结尾\n";
            }
            
        } else {
            echo "❌ 生成失败，返回: " . var_export($generated_url, true) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 生成异常: " . $e->getMessage() . "\n";
    }
    
    // ============================================
    // 对比主映射逻辑
    // ============================================
    echo "\n【对比主映射逻辑】\n";
    echo str_repeat("-", 50) . "\n";
    
    // 获取分类映射
    global $wpdb;
    $map_table = $wpdb->prefix . 'walmart_category_map';
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    $mapping_found = false;
    $attribute_rules = null;
    $walmart_category_name = null;
    
    foreach ($product_categories as $cat_id) {
        $direct_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $map_table WHERE wc_category_id = %d", 
            $cat_id
        ));
        
        if ($direct_mapping) {
            $mapping_found = true;
            $attribute_rules = json_decode($direct_mapping->walmart_attributes, true);
            $walmart_category_name = $direct_mapping->walmart_category_path;
            break;
        }
    }
    
    if ($mapping_found) {
        try {
            $full_mapping = $mapper->map($product, $walmart_category_name, '123456789012', $attribute_rules, 1);
            
            if ($full_mapping && isset($full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'])) {
                $main_mapping_url = $full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'];
                echo "主映射URL: {$main_mapping_url}\n";
                
                // 对比两个URL
                if (isset($generated_url) && $generated_url && $main_mapping_url) {
                    if ($generated_url === $main_mapping_url) {
                        echo "✅ 两个逻辑生成的URL一致！\n";
                    } else {
                        echo "❌ 两个逻辑生成的URL不一致\n";
                        echo "  generate方法: {$generated_url}\n";
                        echo "  主映射方法: {$main_mapping_url}\n";
                    }
                } else {
                    echo "⚠️ 无法对比（其中一个为空）\n";
                }
                
            } else {
                echo "❌ 主映射中没有mainImageUrl\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 主映射失败: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ 没有找到分类映射\n";
    }
    
    // ============================================
    // 检查远程图片信息
    // ============================================
    echo "\n【远程图片信息】\n";
    echo str_repeat("-", 30) . "\n";
    
    $main_image_id = $product->get_image_id();
    echo "主图ID: {$main_image_id}\n";
    
    if (strpos($main_image_id, 'remote_') === 0) {
        $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
        if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
            echo "远程图库数量: " . count($remote_gallery_urls) . "\n";
            echo "第一张图片: " . reset($remote_gallery_urls) . "\n";
            
            $skip_indices = get_post_meta($product->get_id(), '_walmart_skip_image_indices', true);
            if (is_array($skip_indices) && !empty($skip_indices)) {
                echo "跳过索引: " . implode(', ', $skip_indices) . "\n";
            } else {
                echo "跳过索引: 无\n";
            }
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【修复总结】\n";
echo str_repeat("=", 80) . "\n";

echo "修复内容:\n";
echo "1. ✅ 修复了generate_special_attribute_value中mainImageUrl的远程图片支持\n";
echo "2. ✅ 统一了两套图片处理逻辑\n";
echo "3. ✅ 添加了URL清理功能\n";
echo "4. ✅ 保持了跳过索引的支持\n\n";

echo "预期效果:\n";
echo "- generate_special_attribute_value不再返回null\n";
echo "- 两套逻辑生成相同的URL\n";
echo "- mainImageUrl错误应该消失\n";
echo "- 交替错误模式应该消失\n\n";

echo "建议:\n";
echo "- 重新同步之前失败的产品\n";
echo "- 监控是否还有mainImageUrl错误\n";
echo "- 检查其他可能受影响的字段\n";

echo "\n=== 测试完成 ===\n";
?>
