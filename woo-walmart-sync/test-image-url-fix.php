<?php
/**
 * 测试图片URL修复效果
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 图片URL修复测试 ===\n";
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
$test_skus = ['W18B9X011F8', 'W85AQ7221B9'];

foreach ($test_skus as $sku) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "测试SKU: {$sku}\n";
    echo str_repeat("=", 80) . "\n";
    
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "❌ 找不到SKU为 {$sku} 的产品\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    echo "✅ 找到产品: {$product->get_name()}\n\n";
    
    // ============================================
    // 测试URL清理功能
    // ============================================
    echo "【测试URL清理功能】\n";
    echo str_repeat("-", 50) . "\n";
    
    $mapper = new Woo_Walmart_Product_Mapper();
    $reflection = new ReflectionClass($mapper);
    
    // 获取原始主图URL
    $main_image_id = $product->get_image_id();
    echo "主图ID: {$main_image_id}\n";
    
    if (strpos($main_image_id, 'remote_') === 0) {
        $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
        if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
            $original_url = reset($remote_gallery_urls);
            echo "原始URL: {$original_url}\n";
            
            // 测试URL清理方法
            $clean_method = $reflection->getMethod('clean_image_url_for_walmart');
            $clean_method->setAccessible(true);
            
            $cleaned_url = $clean_method->invoke($mapper, $original_url);
            echo "清理后URL: {$cleaned_url}\n";
            
            // 对比差异
            if ($original_url !== $cleaned_url) {
                echo "✅ URL已清理\n";
                echo "变化:\n";
                echo "  - 移除了查询参数\n";
                if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $original_url)) {
                    echo "  - 确保了扩展名结尾\n";
                }
            } else {
                echo "⚠️ URL无需清理\n";
            }
        }
    }
    
    // ============================================
    // 测试完整映射
    // ============================================
    echo "\n【测试完整映射】\n";
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
        echo "✅ 找到分类映射: {$walmart_category_name}\n";
        
        try {
            $full_mapping = $mapper->map($product, $walmart_category_name, '123456789012', $attribute_rules, 1);
            
            if ($full_mapping && isset($full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'])) {
                $final_url = $full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'];
                echo "✅ 最终mainImageUrl: {$final_url}\n";
                
                // 检查最终URL是否符合Walmart要求
                echo "\n检查最终URL:\n";
                
                // 1. 检查扩展名
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $final_url)) {
                    echo "✅ URL以图片扩展名结尾\n";
                } else {
                    echo "❌ URL不以图片扩展名结尾\n";
                }
                
                // 2. 检查查询参数
                if (strpos($final_url, '?') === false) {
                    echo "✅ URL没有查询参数\n";
                } else {
                    echo "⚠️ URL仍包含查询参数\n";
                }
                
                // 3. 检查URL有效性
                if (filter_var($final_url, FILTER_VALIDATE_URL)) {
                    echo "✅ URL格式有效\n";
                } else {
                    echo "❌ URL格式无效\n";
                }
                
            } else {
                echo "❌ 完整映射中没有找到mainImageUrl\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 完整映射失败: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ 没有找到分类映射\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【修复总结】\n";
echo str_repeat("=", 80) . "\n";
echo "1. ✅ 添加了URL清理功能\n";
echo "2. ✅ 移除查询参数以符合Walmart要求\n";
echo "3. ✅ 确保URL以图片扩展名结尾\n";
echo "4. ⚠️ 大文件问题需要图片压缩或重新上传\n";
echo "\n建议:\n";
echo "- 对于超过5MB的图片，需要压缩或使用更小的图片\n";
echo "- 重新同步测试产品，检查是否还有mainImageUrl错误\n";

echo "\n=== 测试完成 ===\n";
?>
