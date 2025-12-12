<?php
/**
 * 对比成功和失败产品的差异
 * 找出mainImageUrl错误的真正原因
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 成功vs失败产品对比分析 ===\n";
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

// 根据同步结果分组
$success_skus = ['W35C2X01185', 'GS269505BII', 'W1417S00079'];
$failed_skus = ['W15BAU194E84', 'W89CT5036E', 'W18B96281B6'];

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);
$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

// 分析函数
function analyze_product($sku, $status, $mapper, $generate_method) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "分析产品: {$sku} ({$status})\n";
    echo str_repeat("=", 70) . "\n";
    
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "❌ 找不到产品\n";
        return null;
    }
    
    $product = wc_get_product($product_id);
    echo "产品名: " . substr($product->get_name(), 0, 50) . "...\n";
    echo "产品ID: {$product_id}\n";
    
    $analysis = [];
    
    // 1. 检查主图ID
    $main_image_id = $product->get_image_id();
    $analysis['image_id'] = $main_image_id;
    $analysis['image_id_type'] = gettype($main_image_id);
    
    echo "\n【主图信息】\n";
    echo "主图ID: {$main_image_id}\n";
    echo "ID类型: " . gettype($main_image_id) . "\n";
    
    if (empty($main_image_id)) {
        echo "❌ 没有主图ID\n";
        $analysis['has_image'] = false;
    } elseif (is_numeric($main_image_id) && $main_image_id > 0) {
        echo "✅ 本地图片 (正数ID)\n";
        $analysis['image_type'] = 'local_positive';
        $analysis['has_image'] = true;
        
        $local_url = wp_get_attachment_url($main_image_id);
        echo "本地URL: {$local_url}\n";
        $analysis['local_url'] = $local_url;
        
    } elseif (is_numeric($main_image_id) && $main_image_id < 0) {
        echo "⚠️ 远程图片 (负数ID)\n";
        $analysis['image_type'] = 'remote_negative';
        $analysis['has_image'] = true;
        
    } elseif (is_string($main_image_id) && strpos($main_image_id, 'remote_') === 0) {
        echo "⚠️ 远程图片 (字符串ID)\n";
        $analysis['image_type'] = 'remote_string';
        $analysis['has_image'] = true;
        
    } else {
        echo "❓ 未知类型的主图ID\n";
        $analysis['image_type'] = 'unknown';
        $analysis['has_image'] = false;
    }
    
    // 2. 检查远程图库
    $remote_gallery_urls = get_post_meta($product_id, '_remote_gallery_urls', true);
    if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
        echo "远程图库: " . count($remote_gallery_urls) . " 张图片\n";
        $analysis['remote_gallery_count'] = count($remote_gallery_urls);
        $analysis['first_remote_url'] = reset($remote_gallery_urls);
        echo "第一张远程图: " . substr(reset($remote_gallery_urls), 0, 80) . "...\n";
    } else {
        echo "远程图库: 无\n";
        $analysis['remote_gallery_count'] = 0;
    }
    
    // 3. 测试generate_special_attribute_value
    echo "\n【generate_special_attribute_value测试】\n";
    try {
        $generated_url = $generate_method->invoke($mapper, 'mainImageUrl', $product, 1);
        if ($generated_url) {
            echo "✅ 生成成功: " . substr($generated_url, 0, 80) . "...\n";
            $analysis['generate_success'] = true;
            $analysis['generated_url'] = $generated_url;
        } else {
            echo "❌ 生成失败 (返回null)\n";
            $analysis['generate_success'] = false;
        }
    } catch (Exception $e) {
        echo "❌ 生成异常: " . $e->getMessage() . "\n";
        $analysis['generate_success'] = false;
        $analysis['generate_error'] = $e->getMessage();
    }
    
    // 4. 测试完整映射
    echo "\n【完整映射测试】\n";
    global $wpdb;
    $map_table = $wpdb->prefix . 'walmart_category_map';
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    $mapping_found = false;
    foreach ($product_categories as $cat_id) {
        $direct_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $map_table WHERE wc_category_id = %d", 
            $cat_id
        ));
        
        if ($direct_mapping) {
            $mapping_found = true;
            $attribute_rules = json_decode($direct_mapping->walmart_attributes, true);
            $walmart_category_name = $direct_mapping->walmart_category_path;
            
            try {
                $full_mapping = $mapper->map($product, $walmart_category_name, '123456789012', $attribute_rules, 1);
                
                if ($full_mapping && isset($full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'])) {
                    $final_url = $full_mapping['MPItem'][0]['Visible'][$walmart_category_name]['mainImageUrl'];
                    echo "✅ 完整映射成功: " . substr($final_url, 0, 80) . "...\n";
                    $analysis['mapping_success'] = true;
                    $analysis['final_url'] = $final_url;
                    
                    // 对比两个URL
                    if (isset($analysis['generated_url']) && $analysis['generated_url'] === $final_url) {
                        echo "✅ 两个URL一致\n";
                        $analysis['urls_consistent'] = true;
                    } else {
                        echo "❌ 两个URL不一致\n";
                        $analysis['urls_consistent'] = false;
                    }
                } else {
                    echo "❌ 完整映射中没有mainImageUrl\n";
                    $analysis['mapping_success'] = false;
                }
            } catch (Exception $e) {
                echo "❌ 完整映射异常: " . $e->getMessage() . "\n";
                $analysis['mapping_success'] = false;
            }
            break;
        }
    }
    
    if (!$mapping_found) {
        echo "❌ 没有找到分类映射\n";
        $analysis['mapping_found'] = false;
    }
    
    return $analysis;
}

// 分析所有产品
echo "【成功产品分析】\n";
echo str_repeat("=", 80) . "\n";

$success_analyses = [];
foreach ($success_skus as $sku) {
    $success_analyses[$sku] = analyze_product($sku, 'SUCCESS', $mapper, $generate_method);
}

echo "\n\n【失败产品分析】\n";
echo str_repeat("=", 80) . "\n";

$failed_analyses = [];
foreach ($failed_skus as $sku) {
    $failed_analyses[$sku] = analyze_product($sku, 'FAILED', $mapper, $generate_method);
}

// 对比分析
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "【对比分析】\n";
echo str_repeat("=", 80) . "\n";

echo "成功产品特征:\n";
foreach ($success_analyses as $sku => $analysis) {
    if ($analysis) {
        echo "- {$sku}: {$analysis['image_type']}, generate=" . ($analysis['generate_success'] ? '成功' : '失败') . 
             ", mapping=" . ($analysis['mapping_success'] ? '成功' : '失败') . "\n";
    }
}

echo "\n失败产品特征:\n";
foreach ($failed_analyses as $sku => $analysis) {
    if ($analysis) {
        echo "- {$sku}: {$analysis['image_type']}, generate=" . ($analysis['generate_success'] ? '成功' : '失败') . 
             ", mapping=" . ($analysis['mapping_success'] ? '成功' : '失败') . "\n";
    }
}

echo "\n关键差异:\n";
$success_image_types = array_column(array_filter($success_analyses), 'image_type');
$failed_image_types = array_column(array_filter($failed_analyses), 'image_type');

echo "成功产品图片类型: " . implode(', ', array_unique($success_image_types)) . "\n";
echo "失败产品图片类型: " . implode(', ', array_unique($failed_image_types)) . "\n";

echo "\n=== 分析完成 ===\n";
?>
