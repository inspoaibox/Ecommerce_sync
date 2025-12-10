<?php
/**
 * 详细分析SKU B202S00493的图片情况
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 详细分析SKU B202S00493 ===\n\n";

$sku = 'B202S00493';

// 1. 找到产品ID
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare("
    SELECT post_id FROM {$wpdb->postmeta} 
    WHERE meta_key = '_sku' AND meta_value = %s
", $sku));

if (!$product_id) {
    echo "❌ 未找到SKU对应的产品\n";
    exit;
}

echo "产品ID: {$product_id}\n";
echo "SKU: {$sku}\n\n";

// 2. 获取产品对象
$product = wc_get_product($product_id);
if (!$product) {
    echo "❌ 无法获取产品对象\n";
    exit;
}

echo "=== 产品基本信息 ===\n";
echo "产品名称: " . $product->get_name() . "\n";
echo "产品类型: " . $product->get_type() . "\n";
echo "产品状态: " . $product->get_status() . "\n\n";

// 3. 详细分析图片情况
echo "=== 图片详细分析 ===\n";

// 主图分析
echo "【主图分析】\n";
$main_image_id = $product->get_image_id();
if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "✅ 主图ID: {$main_image_id}\n";
    echo "✅ 主图URL: {$main_image_url}\n";
    
    // 检查是否是远程URL
    if (strpos($main_image_url, 'http') === 0 && strpos($main_image_url, $_SERVER['HTTP_HOST']) === false) {
        echo "✅ 主图是远程URL\n";
    } else {
        echo "ℹ️ 主图是本地URL\n";
    }
} else {
    echo "❌ 没有主图\n";
}

echo "\n【图库图片分析】\n";
$gallery_image_ids = $product->get_gallery_image_ids();
echo "图库图片ID数量: " . count($gallery_image_ids) . "\n";

if (!empty($gallery_image_ids)) {
    foreach ($gallery_image_ids as $index => $image_id) {
        $image_url = wp_get_attachment_url($image_id);
        echo "图库图片" . ($index + 1) . ":\n";
        echo "  ID: {$image_id}\n";
        echo "  URL: {$image_url}\n";
        
        // 检查是否是远程URL
        if (strpos($image_url, 'http') === 0 && strpos($image_url, $_SERVER['HTTP_HOST']) === false) {
            echo "  类型: 远程URL\n";
        } else {
            echo "  类型: 本地URL\n";
        }
        echo "\n";
    }
} else {
    echo "❌ 没有图库图片\n";
}

echo "【远程图库分析】\n";
$remote_gallery_urls = get_post_meta($product_id, '_remote_gallery_urls', true);
echo "远程图库meta值类型: " . gettype($remote_gallery_urls) . "\n";

if (is_array($remote_gallery_urls)) {
    echo "远程图库数量: " . count($remote_gallery_urls) . "\n";
    foreach ($remote_gallery_urls as $index => $url) {
        echo "远程图片" . ($index + 1) . ": {$url}\n";
    }
} else if (!empty($remote_gallery_urls)) {
    echo "远程图库数据: {$remote_gallery_urls}\n";
    echo "⚠️ 远程图库不是数组格式\n";
} else {
    echo "❌ 没有远程图库数据\n";
}

// 4. 检查所有相关的meta字段
echo "\n=== 所有图片相关Meta字段 ===\n";
$image_meta_keys = [
    '_thumbnail_id',
    '_product_image_gallery', 
    '_remote_gallery_urls',
    '_remote_main_image_url',
    '_additional_images',
    '_product_images',
    '_gallery_images'
];

foreach ($image_meta_keys as $meta_key) {
    $meta_value = get_post_meta($product_id, $meta_key, true);
    if (!empty($meta_value)) {
        echo "{$meta_key}: ";
        if (is_array($meta_value)) {
            echo "数组(" . count($meta_value) . "项) - " . implode(', ', array_slice($meta_value, 0, 3));
            if (count($meta_value) > 3) {
                echo "...";
            }
        } else {
            echo substr($meta_value, 0, 100);
            if (strlen($meta_value) > 100) {
                echo "...";
            }
        }
        echo "\n";
    }
}

// 5. 模拟图片获取过程
echo "\n=== 模拟图片获取过程 ===\n";

// 获取主图
$main_image_url = '';
if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
}
echo "步骤1 - 主图: " . ($main_image_url ? "✅ 获取成功" : "❌ 获取失败") . "\n";

// 获取图库图片
$gallery_urls = [];
foreach ($gallery_image_ids as $image_id) {
    $url = wp_get_attachment_url($image_id);
    if ($url) {
        $gallery_urls[] = $url;
    }
}
echo "步骤2 - 图库图片: " . count($gallery_urls) . "张\n";

// 获取远程图库
$remote_urls = [];
if (is_array($remote_gallery_urls)) {
    $remote_urls = $remote_gallery_urls;
}
echo "步骤3 - 远程图库: " . count($remote_urls) . "张\n";

// 合并所有副图
$all_additional_images = array_merge($gallery_urls, $remote_urls);
echo "步骤4 - 合并副图: " . count($all_additional_images) . "张\n";

// 去重
$unique_images = array_unique($all_additional_images);
echo "步骤5 - 去重后: " . count($unique_images) . "张\n";

if (count($all_additional_images) != count($unique_images)) {
    echo "⚠️ 发现重复图片: " . (count($all_additional_images) - count($unique_images)) . "张\n";
    
    // 找出重复的图片
    $duplicates = array_diff_assoc($all_additional_images, $unique_images);
    if (!empty($duplicates)) {
        echo "重复的图片:\n";
        foreach ($duplicates as $dup_url) {
            echo "  - {$dup_url}\n";
        }
    }
}

echo "\n=== 最终图片列表 ===\n";
echo "主图: {$main_image_url}\n";
echo "副图数量: " . count($unique_images) . "\n";
foreach ($unique_images as $index => $url) {
    echo "副图" . ($index + 1) . ": {$url}\n";
}

// 6. 检查为什么会有8张图片的记录
echo "\n=== 分析8张图片的来源 ===\n";
echo "图库图片: " . count($gallery_image_ids) . "张\n";
echo "远程图库: " . count($remote_urls) . "张\n";
echo "总计: " . (count($gallery_image_ids) + count($remote_urls)) . "张\n";

if ((count($gallery_image_ids) + count($remote_urls)) == 8) {
    echo "✅ 8张图片来源确认: 图库图片 + 远程图库 = 8张\n";
    echo "但根据您的说明，本地图库应该是空的，只有远程URL\n";
    echo "🔍 需要检查为什么图库图片不为空\n";
}

?>
