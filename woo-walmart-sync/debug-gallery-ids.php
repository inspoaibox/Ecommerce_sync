<?php
/**
 * 调试产品图库ID格式
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试产品图库ID格式 ===\n\n";

$product_id = 13917;
$product = wc_get_product($product_id);

if (!$product) {
    echo "❌ 无法获取产品信息\n";
    exit;
}

echo "产品ID: {$product_id}\n";
echo "SKU: {$product->get_sku()}\n\n";

// 1. 检查图库ID
$gallery_image_ids = $product->get_gallery_image_ids();
echo "=== 图库ID信息 ===\n";
echo "图库ID数量: " . count($gallery_image_ids) . "\n";
echo "图库ID列表:\n";

foreach ($gallery_image_ids as $i => $image_id) {
    echo "  " . ($i + 1) . ". ID: {$image_id} | 类型: " . gettype($image_id) . "\n";
    
    if (is_numeric($image_id)) {
        if ($image_id > 0) {
            echo "    ✅ 正数ID - 本地图库\n";
            $url = wp_get_attachment_image_url($image_id, 'full');
            echo "    URL: " . ($url ?: '无法获取') . "\n";
        } else if ($image_id < 0) {
            echo "    ✅ 负数ID - 远程图库\n";
            $remote_index = abs($image_id + 1000);
            echo "    远程索引: {$remote_index}\n";
        } else {
            echo "    ❓ ID为0\n";
        }
    } else if (is_string($image_id)) {
        echo "    ✅ 字符串ID - 可能是远程图库\n";
        if (strpos($image_id, 'remote_') === 0) {
            echo "    远程图库标识符\n";
        }
    }
}

// 2. 检查远程图库URLs
echo "\n=== 远程图库URLs ===\n";
$remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);

if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
    echo "远程图库URL数量: " . count($remote_gallery_urls) . "\n";
    echo "远程图库URLs:\n";
    
    foreach ($remote_gallery_urls as $index => $url) {
        echo "  索引 {$index}: " . substr($url, 0, 80) . "...\n";
    }
} else {
    echo "❌ 没有远程图库URLs或格式错误\n";
    echo "原始数据: " . print_r($remote_gallery_urls, true) . "\n";
}

// 3. 模拟新的处理逻辑
echo "\n=== 模拟新的处理逻辑 ===\n";

$gallery_images = [];

foreach ($gallery_image_ids as $gallery_image_id) {
    echo "处理ID: {$gallery_image_id}\n";
    
    if (is_numeric($gallery_image_id) && $gallery_image_id > 0) {
        echo "  -> 本地图库处理\n";
        $image_url = wp_get_attachment_image_url($gallery_image_id, 'full');
        if ($image_url && filter_var($image_url, FILTER_VALIDATE_URL)) {
            $gallery_images[] = $image_url;
            echo "  -> ✅ 添加: " . substr($image_url, 0, 60) . "...\n";
        } else {
            echo "  -> ❌ 无效URL\n";
        }
    } else if ($gallery_image_id < 0) {
        echo "  -> 负数ID远程图库处理\n";
        if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
            $remote_index = abs($gallery_image_id + 1000);
            echo "  -> 计算远程索引: {$remote_index}\n";
            if (isset($remote_gallery_urls[$remote_index])) {
                $remote_url = $remote_gallery_urls[$remote_index];
                if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                    $gallery_images[] = $remote_url;
                    echo "  -> ✅ 添加: " . substr($remote_url, 0, 60) . "...\n";
                } else {
                    echo "  -> ❌ 无效远程URL\n";
                }
            } else {
                echo "  -> ❌ 远程索引不存在\n";
            }
        } else {
            echo "  -> ❌ 没有远程图库数据\n";
        }
    } else if (is_string($gallery_image_id) && strpos($gallery_image_id, 'remote_') === 0) {
        echo "  -> 字符串远程图库处理\n";
        // 这里需要特殊处理字符串格式的远程ID
        if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
            // 尝试从远程图库中找到对应的图片
            // 可能需要根据实际的远程图片系统逻辑来匹配
            echo "  -> ❓ 字符串远程ID处理逻辑待完善\n";
        }
    } else {
        echo "  -> ❓ 未知ID格式\n";
    }
}

echo "\n模拟处理结果: " . count($gallery_images) . "张图片\n";

if (count($gallery_images) == 0) {
    echo "❌ 没有处理到任何图片，需要检查处理逻辑\n";
} else {
    foreach ($gallery_images as $i => $url) {
        echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
    }
}

?>
