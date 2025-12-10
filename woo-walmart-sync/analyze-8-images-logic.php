<?php
/**
 * 分析为什么检测到8张图片的逻辑
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 分析8张图片的检测逻辑 ===\n\n";

$product_id = 13917;
$product = wc_get_product($product_id);

echo "产品ID: {$product_id}\n";
echo "SKU: " . $product->get_sku() . "\n\n";

// 1. 检查WooCommerce的图片获取方法
echo "=== WooCommerce图片获取 ===\n";

$main_image_id = $product->get_image_id();
$gallery_image_ids = $product->get_gallery_image_ids();

echo "主图ID: {$main_image_id}\n";
echo "图库ID数量: " . count($gallery_image_ids) . "\n";

if (!empty($gallery_image_ids)) {
    echo "图库IDs: " . implode(', ', $gallery_image_ids) . "\n";
    
    // 检查每个图库ID的URL获取情况
    $valid_gallery_count = 0;
    foreach ($gallery_image_ids as $i => $gid) {
        $url = wp_get_attachment_url($gid);
        echo "图库ID {$gid}: " . ($url ? "有URL" : "无URL") . "\n";
        if ($url) {
            $valid_gallery_count++;
        }
    }
    echo "有效图库URL数量: {$valid_gallery_count}\n";
}

// 2. 检查远程图库
echo "\n=== 远程图库检查 ===\n";

$remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
echo "远程图库类型: " . gettype($remote_gallery) . "\n";

if (is_array($remote_gallery)) {
    echo "远程图库数量: " . count($remote_gallery) . "\n";
    foreach ($remote_gallery as $i => $url) {
        echo "远程图{$i+1}: " . (filter_var($url, FILTER_VALIDATE_URL) ? "有效URL" : "无效URL") . "\n";
    }
} else {
    echo "远程图库: " . ($remote_gallery ?: '(空)') . "\n";
}

// 3. 模拟映射器中的图片处理逻辑
echo "\n=== 模拟映射器图片处理逻辑 ===\n";

// 这是映射器中第143-181行的逻辑
$additional_images = [];

// 处理图库图片
foreach ($gallery_image_ids as $image_id) {
    $image_url = wp_get_attachment_url($image_id);
    if (!empty($image_url)) {
        $additional_images[] = $image_url;
        echo "添加图库图片: " . substr($image_url, 0, 50) . "...\n";
    } else {
        echo "跳过无效图库ID: {$image_id}\n";
    }
}

echo "图库图片处理后数量: " . count($additional_images) . "\n";

// 处理远程图库（第172-179行逻辑）
if (is_array($remote_gallery) && !empty($remote_gallery)) {
    echo "\n处理远程图库:\n";
    foreach ($remote_gallery as $remote_url) {
        if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
            $additional_images[] = $remote_url;
            echo "添加远程图片: " . substr($remote_url, 0, 50) . "...\n";
        } else {
            echo "跳过无效远程URL: {$remote_url}\n";
        }
    }
}

echo "远程图库处理后总数量: " . count($additional_images) . "\n";

// 4. 去重处理（第273-274行）
echo "\n=== 去重处理 ===\n";
$before_unique = count($additional_images);
$additional_images = array_unique($additional_images);
$after_unique = count($additional_images);

echo "去重前: {$before_unique}张\n";
echo "去重后: {$after_unique}张\n";

if ($before_unique != $after_unique) {
    echo "⚠️ 发现重复图片: " . ($before_unique - $after_unique) . "张\n";
}

// 5. 分析为什么是8张
echo "\n=== 分析8张图片的来源 ===\n";

$gallery_count = count($gallery_image_ids);
$remote_count = is_array($remote_gallery) ? count($remote_gallery) : 0;
$total_before_processing = $gallery_count + $remote_count;

echo "图库图片: {$gallery_count}张\n";
echo "远程图库: {$remote_count}张\n";
echo "理论总数: {$total_before_processing}张\n";

if ($total_before_processing == 8) {
    echo "✅ 8张图片来源确认\n";
    
    // 但是为什么最终只有4张？
    echo "\n=== 分析为什么最终只有4张 ===\n";
    
    if ($gallery_count == 4 && $after_unique == 4) {
        echo "可能原因1: 图库图片的URL都是空的，只有远程图库有效\n";
        
        // 验证这个假设
        $valid_gallery_urls = [];
        foreach ($gallery_image_ids as $gid) {
            $url = wp_get_attachment_url($gid);
            if ($url) {
                $valid_gallery_urls[] = $url;
            }
        }
        
        echo "实际有效的图库URL数量: " . count($valid_gallery_urls) . "\n";
        
        if (count($valid_gallery_urls) == 0) {
            echo "✅ 确认：图库图片URL都是空的\n";
            echo "只有远程图库的4张图片有效\n";
        }
    }
    
    if ($before_unique > $after_unique) {
        echo "可能原因2: 存在重复图片被去重\n";
    }
}

// 6. 检查占位符补足逻辑的执行条件
echo "\n=== 检查占位符补足条件 ===\n";

$original_count = $after_unique;
echo "去重后图片数量: {$original_count}\n";

if ($original_count == 4) {
    echo "✅ 符合4张图片补足条件\n";
    
    $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
    echo "占位符1: " . ($placeholder_1 ?: '(空)') . "\n";
    
    if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
        echo "✅ 占位符1有效，应该补足至5张\n";
        
        // 模拟补足
        $test_images = $additional_images;
        $test_images[] = $placeholder_1;
        echo "补足后应该有: " . count($test_images) . "张\n";
        
        if (count($test_images) >= 5) {
            echo "✅ 补足后满足沃尔玛要求\n";
            echo "❓ 但为什么实际发送时只有4张？\n";
        }
    } else {
        echo "❌ 占位符1无效，无法补足\n";
    }
} else {
    echo "不符合4张补足条件，当前: {$original_count}张\n";
}

?>
