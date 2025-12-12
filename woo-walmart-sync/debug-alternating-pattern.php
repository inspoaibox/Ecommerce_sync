<?php
/**
 * 诊断交替出现的mainImageUrl错误模式
 * 检查是否存在索引、缓存或状态相关的问题
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 交替错误模式诊断 ===\n";
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

// 测试失败的SKU（按错误日志中的顺序）
$test_skus = [
    'W15BAU194E84',  // index 2 - 错误
    'W89CT5036E',    // index 3 - 错误  
    'W18B96281B6',   // index 4 - 错误
];

echo "【分析交替错误模式】\n";
echo str_repeat("-", 80) . "\n";

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

foreach ($test_skus as $index => $sku) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "测试SKU: {$sku} (错误日志中的index: " . ($index + 2) . ")\n";
    echo str_repeat("=", 60) . "\n";
    
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "❌ 找不到SKU为 {$sku} 的产品\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    echo "✅ 找到产品: {$product->get_name()}\n";
    echo "产品ID: {$product_id}\n";
    
    // ============================================
    // 检查1: 主图ID和类型
    // ============================================
    echo "\n【检查1: 主图信息】\n";
    echo str_repeat("-", 40) . "\n";
    
    $main_image_id = $product->get_image_id();
    echo "主图ID: {$main_image_id}\n";
    echo "主图ID类型: " . gettype($main_image_id) . "\n";
    
    if (empty($main_image_id)) {
        echo "❌ 产品没有主图\n";
    } elseif (is_numeric($main_image_id)) {
        echo "主图类型: 本地图片\n";
        $image_url = wp_get_attachment_url($main_image_id);
        echo "本地图片URL: {$image_url}\n";
    } elseif (strpos($main_image_id, 'remote_') === 0) {
        echo "主图类型: 远程图片\n";
        $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
        if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
            echo "远程图库数量: " . count($remote_gallery_urls) . "\n";
            $first_url = reset($remote_gallery_urls);
            echo "第一张远程图片: {$first_url}\n";
        } else {
            echo "❌ 远程图库为空\n";
        }
    }
    
    // ============================================
    // 检查2: 跳过索引
    // ============================================
    echo "\n【检查2: 跳过索引】\n";
    echo str_repeat("-", 40) . "\n";
    
    $skip_indices = get_post_meta($product->get_id(), '_walmart_skip_image_indices', true);
    if (is_array($skip_indices) && !empty($skip_indices)) {
        echo "跳过的图片索引: " . implode(', ', $skip_indices) . "\n";
    } else {
        echo "没有跳过的图片索引\n";
    }
    
    // ============================================
    // 检查3: 生成mainImageUrl
    // ============================================
    echo "\n【检查3: 生成mainImageUrl】\n";
    echo str_repeat("-", 40) . "\n";
    
    try {
        $generate_method = $reflection->getMethod('generate_special_attribute_value');
        $generate_method->setAccessible(true);
        
        $result = $generate_method->invoke($mapper, 'mainImageUrl', $product, 1);
        
        if ($result) {
            echo "✅ 生成成功: {$result}\n";
            echo "URL长度: " . strlen($result) . " 字符\n";
            
            // 检查URL特征
            if (strpos($result, '?') !== false) {
                echo "⚠️ 包含查询参数\n";
            } else {
                echo "✅ 不包含查询参数\n";
            }
            
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $result)) {
                echo "✅ 以图片扩展名结尾\n";
            } else {
                echo "❌ 不以图片扩展名结尾\n";
            }
            
        } else {
            echo "❌ 生成失败，返回: " . var_export($result, true) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 生成异常: " . $e->getMessage() . "\n";
    }
    
    // ============================================
    // 检查4: 完整映射中的mainImageUrl
    // ============================================
    echo "\n【检查4: 完整映射】\n";
    echo str_repeat("-", 40) . "\n";
    
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
        echo "分类映射: {$walmart_category_name}\n";
        
        try {
            $full_mapping = $mapper->map($product, $walmart_category_name, '123456789012', $attribute_rules, 1);
            
            if ($full_mapping && isset($full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'])) {
                $final_url = $full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'];
                echo "✅ 最终mainImageUrl: {$final_url}\n";
                
                // 对比生成的URL和最终URL
                if (isset($result) && $result !== $final_url) {
                    echo "⚠️ 生成的URL和最终URL不同！\n";
                    echo "  生成的: {$result}\n";
                    echo "  最终的: {$final_url}\n";
                }
                
            } else {
                echo "❌ 完整映射中没有mainImageUrl\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 完整映射失败: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ 没有找到分类映射\n";
    }
    
    // ============================================
    // 检查5: 产品特征对比
    // ============================================
    echo "\n【检查5: 产品特征】\n";
    echo str_repeat("-", 40) . "\n";
    
    echo "产品状态: " . $product->get_status() . "\n";
    echo "产品类型: " . $product->get_type() . "\n";
    echo "是否可见: " . ($product->is_visible() ? '是' : '否') . "\n";
    echo "库存状态: " . $product->get_stock_status() . "\n";
    
    // 检查产品分类
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
    echo "产品分类: " . implode(', ', $categories) . "\n";
    
    // 检查产品标签
    $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
    if (!empty($tags)) {
        echo "产品标签: " . implode(', ', $tags) . "\n";
    }
}

// ============================================
// 模式分析
// ============================================
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "【模式分析】\n";
echo str_repeat("=", 80) . "\n";

echo "观察到的模式:\n";
echo "- 产品按顺序处理时，每隔一个产品就出现mainImageUrl错误\n";
echo "- 错误产品的index: 2, 3, 4 (连续的)\n";
echo "- 这不是随机错误，而是有规律的模式\n\n";

echo "可能的原因:\n";
echo "1. **批处理索引问题**: 处理逻辑中使用了错误的索引计算\n";
echo "2. **状态变量污染**: 某个静态变量或类属性在处理过程中被修改\n";
echo "3. **缓存问题**: 图片URL缓存机制有问题\n";
echo "4. **数组索引错位**: 远程图库数组索引计算错误\n";
echo "5. **循环变量干扰**: 处理循环中的变量相互影响\n\n";

echo "建议检查:\n";
echo "1. 检查 clean_image_url_for_walmart 方法是否有状态问题\n";
echo "2. 检查远程图库URL获取逻辑\n";
echo "3. 检查是否有全局变量或静态变量被意外修改\n";
echo "4. 检查批处理过程中的索引计算\n";

echo "\n=== 诊断完成 ===\n";
?>
