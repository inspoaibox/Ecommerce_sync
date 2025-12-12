<?php
/**
 * 诊断 mainImageUrl 字段错误的具体原因
 * 检查图片URL格式、可访问性、文件类型等
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== mainImageUrl 字段错误诊断 ===\n";
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
$failed_skus = ['W18B9X011F8', 'W85AQ7221B9'];

foreach ($failed_skus as $sku) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "检查SKU: {$sku}\n";
    echo str_repeat("=", 80) . "\n";
    
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "❌ 找不到SKU为 {$sku} 的产品\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    echo "✅ 找到产品: {$product->get_name()} (ID: {$product_id})\n\n";
    
    // ============================================
    // 检查1: 产品主图ID和URL
    // ============================================
    echo "【检查1: 产品主图信息】\n";
    echo str_repeat("-", 50) . "\n";
    
    $main_image_id = $product->get_image_id();
    echo "主图ID: {$main_image_id}\n";
    echo "主图ID类型: " . gettype($main_image_id) . "\n";
    
    if (empty($main_image_id)) {
        echo "❌ 产品没有设置主图\n";
    } else {
        // 检查主图URL获取逻辑
        if (is_numeric($main_image_id) && $main_image_id > 0) {
            echo "主图类型: 本地图片 (数字ID)\n";
            $main_image_url = wp_get_attachment_url($main_image_id);
            echo "主图URL: {$main_image_url}\n";
        } elseif (strpos($main_image_id, 'remote_') === 0) {
            echo "主图类型: 远程图片 (remote_前缀)\n";
            $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
            if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                $main_image_url = reset($remote_gallery_urls);
                echo "主图URL: {$main_image_url}\n";
            } else {
                echo "❌ 无法获取远程图库URLs\n";
                $main_image_url = '';
            }
        } else {
            echo "主图类型: 其他格式\n";
            $main_image_url = wp_get_attachment_url($main_image_id);
            echo "主图URL: {$main_image_url}\n";
        }
    }
    
    // ============================================
    // 检查2: 图片URL验证
    // ============================================
    echo "\n【检查2: 图片URL验证】\n";
    echo str_repeat("-", 50) . "\n";
    
    if (empty($main_image_url)) {
        echo "❌ 主图URL为空\n";
        
        // 检查是否使用了占位符
        $placeholder_url = wc_placeholder_img_src('full');
        echo "WooCommerce占位符: {$placeholder_url}\n";
        $main_image_url = $placeholder_url;
    }
    
    // URL格式验证
    if (filter_var($main_image_url, FILTER_VALIDATE_URL)) {
        echo "✅ URL格式有效: {$main_image_url}\n";
    } else {
        echo "❌ URL格式无效: {$main_image_url}\n";
        continue;
    }
    
    // 检查URL可访问性
    echo "\n检查URL可访问性...\n";
    $headers = @get_headers($main_image_url, 1);
    if ($headers === false) {
        echo "❌ 无法获取URL头信息（网络错误或URL不存在）\n";
        continue;
    }
    
    $http_code = null;
    if (isset($headers[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches);
        $http_code = isset($matches[1]) ? intval($matches[1]) : null;
    }
    
    echo "HTTP状态码: {$http_code}\n";
    
    if ($http_code !== 200) {
        echo "❌ HTTP状态码不是200，图片可能不可访问\n";
        continue;
    }
    
    // ============================================
    // 检查3: 图片文件信息
    // ============================================
    echo "\n【检查3: 图片文件信息】\n";
    echo str_repeat("-", 50) . "\n";
    
    // 文件大小
    $content_length = null;
    if (isset($headers['Content-Length'])) {
        $content_length = is_array($headers['Content-Length']) ? 
            end($headers['Content-Length']) : $headers['Content-Length'];
        $size_mb = round($content_length / 1024 / 1024, 2);
        echo "文件大小: {$size_mb}MB ({$content_length} bytes)\n";
        
        if ($size_mb > 5) {
            echo "❌ 文件大小超过5MB限制！\n";
        } else {
            echo "✅ 文件大小符合要求\n";
        }
    } else {
        echo "⚠️ 无法获取文件大小信息\n";
    }
    
    // 文件类型
    $content_type = null;
    if (isset($headers['Content-Type'])) {
        $content_type = is_array($headers['Content-Type']) ? 
            end($headers['Content-Type']) : $headers['Content-Type'];
        echo "文件类型: {$content_type}\n";
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array(strtolower($content_type), $allowed_types)) {
            echo "✅ 文件类型符合要求\n";
        } else {
            echo "❌ 文件类型不符合要求！\n";
        }
    } else {
        echo "⚠️ 无法获取文件类型信息\n";
    }
    
    // ============================================
    // 检查4: Walmart图片要求
    // ============================================
    echo "\n【检查4: Walmart图片要求检查】\n";
    echo str_repeat("-", 50) . "\n";
    
    // 获取图片实际尺寸（需要下载部分内容）
    echo "获取图片尺寸信息...\n";
    $image_info = @getimagesize($main_image_url);
    
    if ($image_info === false) {
        echo "❌ 无法获取图片尺寸信息\n";
    } else {
        $width = $image_info[0];
        $height = $image_info[1];
        $mime_type = $image_info['mime'];
        
        echo "图片尺寸: {$width} x {$height}\n";
        echo "MIME类型: {$mime_type}\n";
        
        // Walmart尺寸要求检查
        if ($width < 500 || $height < 500) {
            echo "❌ 图片尺寸小于500x500像素最低要求！\n";
        } elseif ($width < 2000 || $height < 2000) {
            echo "⚠️ 图片尺寸小于2000x2000像素推荐尺寸\n";
        } else {
            echo "✅ 图片尺寸符合要求\n";
        }
        
        // 宽高比检查
        $aspect_ratio = $width / $height;
        if ($aspect_ratio < 0.8 || $aspect_ratio > 1.25) {
            echo "❌ 图片宽高比不在0.8-1.25范围内！\n";
        } else {
            echo "✅ 图片宽高比符合要求\n";
        }
    }
    
    // ============================================
    // 检查5: URL格式特殊要求
    // ============================================
    echo "\n【检查5: URL格式特殊要求】\n";
    echo str_repeat("-", 50) . "\n";
    
    // 检查URL中的特殊字符
    $url_issues = [];
    
    if (strpos($main_image_url, ' ') !== false) {
        $url_issues[] = "包含空格";
    }
    
    if (strpos($main_image_url, '中') !== false || preg_match('/[\x{4e00}-\x{9fff}]/u', $main_image_url)) {
        $url_issues[] = "包含中文字符";
    }
    
    if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $main_image_url)) {
        $url_issues[] = "URL末尾没有图片扩展名";
    }
    
    if (strpos($main_image_url, 'localhost') !== false || strpos($main_image_url, '127.0.0.1') !== false) {
        $url_issues[] = "使用了本地地址";
    }
    
    if (!empty($url_issues)) {
        echo "❌ URL格式问题:\n";
        foreach ($url_issues as $issue) {
            echo "  - {$issue}\n";
        }
    } else {
        echo "✅ URL格式符合基本要求\n";
    }
    
    // ============================================
    // 总结
    // ============================================
    echo "\n【总结】\n";
    echo str_repeat("-", 50) . "\n";
    
    if (!empty($url_issues) || (isset($size_mb) && $size_mb > 5) || 
        (isset($width) && ($width < 500 || $height < 500)) ||
        (isset($aspect_ratio) && ($aspect_ratio < 0.8 || $aspect_ratio > 1.25))) {
        echo "❌ 发现问题，这可能是导致Walmart API拒绝的原因\n";
    } else {
        echo "✅ 未发现明显问题，可能是其他原因导致的错误\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【Walmart图片要求参考】\n";
echo str_repeat("=", 80) . "\n";
echo "1. 文件格式: JPEG, PNG, GIF, WEBP\n";
echo "2. 文件大小: 最大5MB\n";
echo "3. 最小尺寸: 500x500像素\n";
echo "4. 推荐尺寸: 2000x2000像素或更高\n";
echo "5. 宽高比: 0.8-1.25之间\n";
echo "6. URL要求: 必须是可公开访问的HTTPS URL\n";
echo "7. 图片质量: 清晰、无水印、白色背景（主图）\n\n";

echo "=== 诊断完成 ===\n";
?>
