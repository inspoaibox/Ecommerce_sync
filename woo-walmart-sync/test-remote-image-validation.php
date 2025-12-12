<?php
/**
 * 远程图片验证功能测试脚本
 * 
 * 使用方法：
 * 1. 在浏览器中访问：http://your-domain/wp-content/plugins/woo-walmart-sync/test-remote-image-validation.php
 * 2. 或者通过命令行运行：php test-remote-image-validation.php
 */

// 加载WordPress环境
if (!defined('ABSPATH')) {
    // 尝试找到WordPress根目录
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('无法加载WordPress环境，请确保脚本位于正确的插件目录中');
    }
}

// 确保远程图片验证器已加载
require_once plugin_dir_path(__FILE__) . 'includes/class-remote-image-validator.php';

echo "<h1>🔍 远程图片验证功能测试</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: #28a745; }
.warning { color: #ffc107; }
.error { color: #dc3545; }
.info { color: #17a2b8; }
.test-section { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
.image-info { background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 3px; }
</style>\n";

// 测试图片URLs（包含各种情况）
$test_images = [
    // 符合要求的图片
    'valid_large' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=2200&h=2200&fit=crop',
    
    // 尺寸过小的图片
    'too_small' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&h=800&fit=crop',
    
    // 非正方形图片
    'not_square' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=2200&h=1500&fit=crop',
    
    // 不存在的图片
    'not_found' => 'https://example.com/non-existent-image.jpg',
    
    // PNG格式图片（不符合JPEG要求）
    'wrong_format' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=2200&h=2200&fit=crop&fm=png'
];

$validator = new WooWalmartSync_Remote_Image_Validator();

echo "<div class='test-section'>\n";
echo "<h2>📋 测试配置</h2>\n";
echo "<div class='info'>\n";
echo "<strong>Walmart图片要求：</strong><br>\n";
echo "• 最小尺寸：1500x1500px<br>\n";
echo "• 推荐尺寸：2200x2200px<br>\n";
echo "• 最大文件大小：5MB<br>\n";
echo "• 格式要求：JPEG (.jpg)<br>\n";
echo "• 宽高比：1:1 (正方形)<br>\n";
echo "</div>\n";
echo "</div>\n";

// 单个图片验证测试
echo "<div class='test-section'>\n";
echo "<h2>🔍 单个图片验证测试</h2>\n";

foreach ($test_images as $test_name => $image_url) {
    echo "<h3>测试：{$test_name}</h3>\n";
    echo "<div class='image-info'>\n";
    echo "<strong>URL:</strong> " . htmlspecialchars($image_url) . "<br>\n";
    
    $start_time = microtime(true);
    $result = $validator->validate_remote_image($image_url, false, true);
    $end_time = microtime(true);
    
    echo "<strong>验证时间:</strong> " . number_format(($end_time - $start_time) * 1000, 2) . "ms<br>\n";
    echo "<strong>缓存状态:</strong> " . ($result['cached'] ? '命中' : '未命中') . "<br>\n";
    
    if ($result['valid']) {
        echo "<span class='success'>✅ 验证通过</span><br>\n";
    } else {
        echo "<span class='error'>❌ 验证失败</span><br>\n";
    }
    
    // 显示图片信息
    if ($result['image_info']) {
        $info = $result['image_info'];
        echo "<strong>图片信息:</strong><br>\n";
        echo "• 尺寸：{$info['width']}x{$info['height']}px<br>\n";
        echo "• 格式：{$info['format']}<br>\n";
        echo "• 大小：" . number_format($info['size'] / 1024, 2) . "KB<br>\n";
        echo "• 宽高比：" . number_format($info['width'] / $info['height'], 2) . ":1<br>\n";
    }
    
    // 显示错误信息
    if (!empty($result['errors'])) {
        echo "<span class='error'><strong>错误：</strong></span><br>\n";
        foreach ($result['errors'] as $error) {
            echo "<span class='error'>• " . htmlspecialchars($error) . "</span><br>\n";
        }
    }
    
    // 显示警告信息
    if (!empty($result['warnings'])) {
        echo "<span class='warning'><strong>警告：</strong></span><br>\n";
        foreach ($result['warnings'] as $warning) {
            echo "<span class='warning'>• " . htmlspecialchars($warning) . "</span><br>\n";
        }
    }
    
    echo "</div>\n";
    echo "<hr>\n";
}

echo "</div>\n";

// 批量验证测试
echo "<div class='test-section'>\n";
echo "<h2>⚡ 批量验证测试</h2>\n";

$batch_urls = array_values($test_images);
echo "<div class='info'>\n";
echo "<strong>批量验证图片数量：</strong> " . count($batch_urls) . "<br>\n";
echo "</div>\n";

$batch_start_time = microtime(true);
$batch_result = $validator->batch_validate_remote_images($batch_urls, true);
$batch_end_time = microtime(true);

echo "<div class='image-info'>\n";
echo "<strong>批量验证结果：</strong><br>\n";
echo "• 总图片数：{$batch_result['total_images']}<br>\n";
echo "• 有效图片：<span class='success'>{$batch_result['valid_images']}</span><br>\n";
echo "• 无效图片：<span class='error'>{$batch_result['invalid_images']}</span><br>\n";
echo "• 缓存命中：<span class='info'>{$batch_result['cached_results']}</span><br>\n";
echo "• 总验证时间：" . number_format(($batch_end_time - $batch_start_time) * 1000, 2) . "ms<br>\n";
echo "• 平均每张图片：" . number_format($batch_result['validation_time'] * 1000 / $batch_result['total_images'], 2) . "ms<br>\n";
echo "</div>\n";

echo "</div>\n";

// 缓存测试
echo "<div class='test-section'>\n";
echo "<h2>💾 缓存效果测试</h2>\n";

$cache_test_url = $test_images['valid_large'];
echo "<div class='info'>\n";
echo "<strong>测试图片：</strong> " . htmlspecialchars($cache_test_url) . "<br>\n";
echo "</div>\n";

// 第一次验证（无缓存）
echo "<h3>第一次验证（无缓存）</h3>\n";
$first_start = microtime(true);
$first_result = $validator->validate_remote_image($cache_test_url, false, true);
$first_end = microtime(true);

echo "<div class='image-info'>\n";
echo "• 验证时间：" . number_format(($first_end - $first_start) * 1000, 2) . "ms<br>\n";
echo "• 缓存状态：" . ($first_result['cached'] ? '命中' : '未命中') . "<br>\n";
echo "</div>\n";

// 第二次验证（有缓存）
echo "<h3>第二次验证（有缓存）</h3>\n";
$second_start = microtime(true);
$second_result = $validator->validate_remote_image($cache_test_url, false, true);
$second_end = microtime(true);

echo "<div class='image-info'>\n";
echo "• 验证时间：" . number_format(($second_end - $second_start) * 1000, 2) . "ms<br>\n";
echo "• 缓存状态：" . ($second_result['cached'] ? '命中' : '未命中') . "<br>\n";
echo "• 性能提升：" . number_format((($first_end - $first_start) / ($second_end - $second_start)), 2) . "倍<br>\n";
echo "</div>\n";

echo "</div>\n";

// 实际产品测试（如果有产品数据）
$products = get_posts([
    'post_type' => 'product',
    'posts_per_page' => 5,
    'meta_query' => [
        [
            'key' => '_remote_gallery_urls',
            'compare' => 'EXISTS'
        ]
    ]
]);

if (!empty($products)) {
    echo "<div class='test-section'>\n";
    echo "<h2>🛍️ 实际产品远程图片验证测试</h2>\n";
    
    require_once plugin_dir_path(__FILE__) . 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    
    foreach (array_slice($products, 0, 3) as $product_post) {
        $product = wc_get_product($product_post->ID);
        if (!$product) continue;
        
        echo "<h3>产品：{$product->get_name()} (ID: {$product->get_id()})</h3>\n";
        
        $validation_result = $mapper->batch_validate_product_remote_images($product->get_id());
        
        echo "<div class='image-info'>\n";
        if ($validation_result['success']) {
            echo "<span class='success'>✅ " . htmlspecialchars($validation_result['message']) . "</span><br>\n";
            
            if (isset($validation_result['validation_results'])) {
                $vr = $validation_result['validation_results'];
                echo "• 远程图片总数：{$vr['total_images']}<br>\n";
                echo "• 有效图片：<span class='success'>{$vr['valid_images']}</span><br>\n";
                echo "• 无效图片：<span class='error'>{$vr['invalid_images']}</span><br>\n";
                echo "• 验证时间：" . number_format($vr['validation_time'] * 1000, 2) . "ms<br>\n";
            }
        } else {
            echo "<span class='info'>ℹ️ " . htmlspecialchars($validation_result['message']) . "</span><br>\n";
        }
        echo "</div>\n";
    }
    
    echo "</div>\n";
} else {
    echo "<div class='test-section'>\n";
    echo "<div class='info'>ℹ️ 没有找到包含远程图片的产品数据</div>\n";
    echo "</div>\n";
}

echo "<div class='test-section'>\n";
echo "<h2>✅ 测试完成</h2>\n";
echo "<div class='success'>\n";
echo "远程图片验证功能测试已完成！<br>\n";
echo "功能特点：<br>\n";
echo "• ✅ 支持远程图片尺寸、格式、大小验证<br>\n";
echo "• ✅ 智能缓存机制，提升验证性能<br>\n";
echo "• ✅ 批量验证支持，提高处理效率<br>\n";
echo "• ✅ 详细的错误和警告信息<br>\n";
echo "• ✅ 与现有产品同步流程无缝集成<br>\n";
echo "</div>\n";
echo "</div>\n";

?>
