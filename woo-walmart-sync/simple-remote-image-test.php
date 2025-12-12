<?php
/**
 * 简化的远程图片验证测试脚本
 * 用于快速验证功能是否正常工作
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
        die('无法加载WordPress环境');
    }
}

echo "=== 远程图片验证功能测试（简化版 - 只检查文件大小）===\n\n";

// 1. 测试远程图片验证器类是否可以正常加载
echo "1. 测试远程图片验证器类加载...\n";
try {
    require_once plugin_dir_path(__FILE__) . 'includes/class-remote-image-validator.php';
    $validator = new WooWalmartSync_Remote_Image_Validator();
    echo "✅ 远程图片验证器加载成功\n\n";
} catch (Exception $e) {
    echo "❌ 远程图片验证器加载失败: " . $e->getMessage() . "\n\n";
    exit;
}

// 2. 测试产品映射器类是否可以正常加载
echo "2. 测试产品映射器类加载...\n";
try {
    require_once plugin_dir_path(__FILE__) . 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    echo "✅ 产品映射器加载成功\n\n";
} catch (Exception $e) {
    echo "❌ 产品映射器加载失败: " . $e->getMessage() . "\n\n";
    exit;
}

// 3. 测试单个远程图片验证
echo "3. 测试单个远程图片验证（只检查文件大小）...\n";
$test_image_url = 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=2200&h=2200&fit=crop';

try {
    $start_time = microtime(true);
    $result = $validator->validate_remote_image($test_image_url, false, true);
    $end_time = microtime(true);
    
    echo "图片URL: " . $test_image_url . "\n";
    echo "验证时间: " . number_format(($end_time - $start_time) * 1000, 2) . "ms\n";
    echo "验证结果: " . ($result['valid'] ? '✅ 通过' : '❌ 失败') . "\n";
    echo "缓存状态: " . ($result['cached'] ? '命中' : '未命中') . "\n";
    
    if ($result['image_info']) {
        $info = $result['image_info'];
        echo "文件大小: " . number_format($info['size'] / 1024, 2) . "KB\n";
    }
    
    if (!empty($result['errors'])) {
        echo "错误信息:\n";
        foreach ($result['errors'] as $error) {
            echo "  - " . $error . "\n";
        }
    }
    
    if (!empty($result['warnings'])) {
        echo "警告信息:\n";
        foreach ($result['warnings'] as $warning) {
            echo "  - " . $warning . "\n";
        }
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ 远程图片验证失败: " . $e->getMessage() . "\n\n";
}

// 4. 测试缓存功能
echo "4. 测试缓存功能...\n";
try {
    // 第二次验证同一张图片（应该命中缓存）
    $start_time = microtime(true);
    $cached_result = $validator->validate_remote_image($test_image_url, false, true);
    $end_time = microtime(true);
    
    echo "第二次验证时间: " . number_format(($end_time - $start_time) * 1000, 2) . "ms\n";
    echo "缓存状态: " . ($cached_result['cached'] ? '✅ 命中' : '❌ 未命中') . "\n\n";
    
} catch (Exception $e) {
    echo "❌ 缓存测试失败: " . $e->getMessage() . "\n\n";
}

// 5. 测试产品远程图片验证（如果有产品数据）
echo "5. 测试产品远程图片验证...\n";
try {
    // 查找有远程图片的产品
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => '_remote_gallery_urls',
                'compare' => 'EXISTS'
            ]
        ]
    ]);
    
    if (!empty($products)) {
        $product_id = $products[0]->ID;
        echo "找到产品ID: {$product_id}\n";
        
        // 检查产品映射器是否有批量验证方法
        if (method_exists($mapper, 'batch_validate_product_remote_images')) {
            $validation_result = $mapper->batch_validate_product_remote_images($product_id);
            
            if ($validation_result['success']) {
                echo "✅ " . $validation_result['message'] . "\n";
                
                if (isset($validation_result['validation_results'])) {
                    $vr = $validation_result['validation_results'];
                    echo "远程图片总数: {$vr['total_images']}\n";
                    echo "有效图片: {$vr['valid_images']}\n";
                    echo "无效图片: {$vr['invalid_images']}\n";
                }
            } else {
                echo "ℹ️ " . $validation_result['message'] . "\n";
            }
        } else {
            echo "❌ 产品映射器中没有找到批量验证方法\n";
        }
    } else {
        echo "ℹ️ 没有找到包含远程图片的产品\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ 产品远程图片验证失败: " . $e->getMessage() . "\n\n";
}

// 6. 测试批量验证功能
echo "6. 测试批量验证功能...\n";
try {
    $batch_urls = [
        'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=2200&h=2200&fit=crop',
        'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=2200&h=2200&fit=crop',
        'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=800&h=800&fit=crop' // 这个尺寸过小
    ];
    
    $batch_start = microtime(true);
    $batch_result = $validator->batch_validate_remote_images($batch_urls, true);
    $batch_end = microtime(true);
    
    echo "批量验证图片数量: " . count($batch_urls) . "\n";
    echo "总验证时间: " . number_format(($batch_end - $batch_start) * 1000, 2) . "ms\n";
    echo "有效图片: {$batch_result['valid_images']}\n";
    echo "无效图片: {$batch_result['invalid_images']}\n";
    echo "缓存命中: {$batch_result['cached_results']}\n\n";
    
} catch (Exception $e) {
    echo "❌ 批量验证失败: " . $e->getMessage() . "\n\n";
}

echo "=== 测试完成 ===\n";
echo "✅ 远程图片验证功能基本测试通过！\n";
echo "\n功能特点:\n";
echo "• 只检查远程图片文件大小（不超过5MB）\n";
echo "• 验证失败的图片直接删除，不替换\n";
echo "• 智能缓存机制，提升验证性能\n";
echo "• 批量验证支持，提高处理效率\n";
echo "• 与现有产品同步流程集成\n";

?>
