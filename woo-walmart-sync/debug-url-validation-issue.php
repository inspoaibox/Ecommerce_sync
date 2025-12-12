<?php
/**
 * 验证URL验证逻辑是否导致了交替错误
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== URL验证问题诊断 ===\n";
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

// 测试失败的SKU
$test_skus = [
    'W15BAU194E84',  // index 2 - 错误
    'W89CT5036E',    // index 3 - 错误  
    'W18B96281B6',   // index 4 - 错误
];

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
    
    // 获取远程图库
    $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
    if (!is_array($remote_gallery_urls) || empty($remote_gallery_urls)) {
        echo "❌ 没有远程图库\n";
        continue;
    }
    
    echo "远程图库数量: " . count($remote_gallery_urls) . "\n";
    echo "跳过索引: ";
    $skip_indices = get_post_meta($product->get_id(), '_walmart_skip_image_indices', true);
    if (is_array($skip_indices) && !empty($skip_indices)) {
        echo implode(', ', $skip_indices) . "\n";
    } else {
        echo "无\n";
        $skip_indices = [];
    }
    
    echo "\n【逐个检查图片URL验证】\n";
    echo str_repeat("-", 50) . "\n";
    
    $selected_url = null;
    $selected_index = null;
    
    foreach ($remote_gallery_urls as $index => $remote_url) {
        echo "\n索引 {$index}:\n";
        echo "URL: {$remote_url}\n";
        
        // 检查是否被跳过
        if (in_array($index, $skip_indices)) {
            echo "❌ 被跳过\n";
            continue;
        } else {
            echo "✅ 未被跳过\n";
        }
        
        // 检查URL验证
        $is_valid = filter_var($remote_url, FILTER_VALIDATE_URL);
        if ($is_valid) {
            echo "✅ URL验证通过\n";
            if ($selected_url === null) {
                $selected_url = $remote_url;
                $selected_index = $index;
                echo "🎯 **这是被选中的URL**\n";
            }
        } else {
            echo "❌ URL验证失败\n";
            echo "filter_var结果: " . var_export($is_valid, true) . "\n";
        }
        
        // 检查URL特征
        if (strlen($remote_url) > 200) {
            echo "⚠️ URL过长: " . strlen($remote_url) . " 字符\n";
        }
        
        if (strpos($remote_url, ' ') !== false) {
            echo "⚠️ URL包含空格\n";
        }
        
        if (!preg_match('/^https?:\/\//', $remote_url)) {
            echo "⚠️ URL协议异常\n";
        }
        
        // 只检查前5个，避免输出过多
        if ($index >= 4) {
            echo "\n... (省略其余图片)\n";
            break;
        }
    }
    
    echo "\n【选择结果】\n";
    echo str_repeat("-", 30) . "\n";
    if ($selected_url) {
        echo "✅ 选中索引: {$selected_index}\n";
        echo "✅ 选中URL: {$selected_url}\n";
    } else {
        echo "❌ 没有选中任何URL\n";
    }
    
    // 对比实际生成的URL
    require_once 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    $reflection = new ReflectionClass($mapper);
    $generate_method = $reflection->getMethod('generate_special_attribute_value');
    $generate_method->setAccessible(true);
    
    $generated_url = $generate_method->invoke($mapper, 'mainImageUrl', $product, 1);
    
    echo "\n【对比实际生成】\n";
    echo str_repeat("-", 30) . "\n";
    echo "实际生成URL: {$generated_url}\n";
    
    if ($selected_url && $generated_url) {
        if ($selected_url === $generated_url) {
            echo "✅ 选择逻辑一致\n";
        } else {
            echo "❌ 选择逻辑不一致！\n";
            echo "预期: {$selected_url}\n";
            echo "实际: {$generated_url}\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【分析总结】\n";
echo str_repeat("=", 80) . "\n";

echo "如果发现问题，可能的原因:\n";
echo "1. filter_var() 对某些URL返回false\n";
echo "2. URL中包含特殊字符导致验证失败\n";
echo "3. URL长度超过限制\n";
echo "4. URL编码问题\n";
echo "5. 网络相关的URL验证问题\n";

echo "\n=== 诊断完成 ===\n";
?>
