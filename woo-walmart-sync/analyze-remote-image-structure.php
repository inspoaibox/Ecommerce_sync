<?php
/**
 * 分析产品的远程图片结构和处理机制
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 分析产品B202S00493的远程图片结构 ===\n\n";

$sku = 'B202S00493';
$product_id = 13917; // 从之前的分析得知

echo "产品ID: {$product_id}\n";
echo "SKU: {$sku}\n\n";

// 1. 检查所有图片相关的meta数据
echo "=== 所有图片Meta数据详细分析 ===\n";

$all_meta = get_post_meta($product_id);
$image_related_meta = [];

foreach ($all_meta as $key => $values) {
    if (strpos($key, 'image') !== false || 
        strpos($key, 'gallery') !== false || 
        strpos($key, 'thumbnail') !== false ||
        strpos($key, 'remote') !== false) {
        $image_related_meta[$key] = $values;
    }
}

foreach ($image_related_meta as $key => $values) {
    echo "Meta键: {$key}\n";
    foreach ($values as $index => $value) {
        if (is_array($value)) {
            echo "  值[{$index}]: 数组(" . count($value) . "项)\n";
            foreach ($value as $i => $item) {
                echo "    [{$i}]: " . substr($item, 0, 100) . (strlen($item) > 100 ? '...' : '') . "\n";
            }
        } else {
            echo "  值[{$index}]: " . substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '') . "\n";
        }
    }
    echo "\n";
}

// 2. 检查远程图片的attachment记录
echo "=== 远程图片Attachment记录分析 ===\n";

$remote_attachment_ids = [
    'remote_eec13d9306a56a74037da04b0393ed1d', // 主图
    'remote_7a8d655560bd3299aad3637b5355e52f', // 图库1
    'remote_6b08b74d1bb99b22ea852937dbea7104', // 图库2
    'remote_e7b4f7ce87b79e21030da54b0393ed1d', // 图库3
    'remote_3cc404b3ab1e677b492948395787ae47'  // 图库4
];

foreach ($remote_attachment_ids as $attachment_id) {
    echo "检查Attachment ID: {$attachment_id}\n";
    
    // 检查是否存在这个post
    $post = get_post($attachment_id);
    if ($post) {
        echo "  ✅ Post存在\n";
        echo "  标题: {$post->post_title}\n";
        echo "  类型: {$post->post_type}\n";
        echo "  状态: {$post->post_status}\n";
        
        // 检查attachment的URL
        $url = wp_get_attachment_url($attachment_id);
        echo "  wp_get_attachment_url(): " . ($url ?: '(空)') . "\n";
        
        // 检查attachment的meta数据
        $attachment_meta = get_post_meta($attachment_id);
        if (!empty($attachment_meta)) {
            echo "  Meta数据:\n";
            foreach ($attachment_meta as $meta_key => $meta_values) {
                foreach ($meta_values as $meta_value) {
                    echo "    {$meta_key}: " . substr($meta_value, 0, 80) . (strlen($meta_value) > 80 ? '...' : '') . "\n";
                }
            }
        }
        
        // 检查_wp_attached_file
        $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);
        echo "  _wp_attached_file: " . ($attached_file ?: '(空)') . "\n";
        
    } else {
        echo "  ❌ Post不存在\n";
    }
    echo "\n";
}

// 3. 检查远程图片URL的存储方式
echo "=== 远程图片URL存储分析 ===\n";

// 检查是否有专门存储远程URL的meta字段
$remote_main_image = get_post_meta($product_id, '_remote_main_image_url', true);
echo "远程主图URL (_remote_main_image_url): " . ($remote_main_image ?: '(空)') . "\n";

$remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
echo "远程图库URLs (_remote_gallery_urls): ";
if (is_array($remote_gallery)) {
    echo "数组(" . count($remote_gallery) . "项)\n";
    foreach ($remote_gallery as $i => $url) {
        echo "  [{$i}]: {$url}\n";
    }
} else {
    echo ($remote_gallery ?: '(空)') . "\n";
}

// 4. 模拟系统的图片获取逻辑
echo "\n=== 模拟系统图片获取逻辑 ===\n";

$product = wc_get_product($product_id);

// 获取主图
echo "【主图获取】\n";
$main_image_id = $product->get_image_id();
echo "主图ID: {$main_image_id}\n";

if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "wp_get_attachment_url结果: " . ($main_image_url ?: '(空)') . "\n";
    
    // 如果wp_get_attachment_url为空，检查是否有远程URL
    if (empty($main_image_url)) {
        echo "尝试获取远程主图URL...\n";
        $remote_main = get_post_meta($product_id, '_remote_main_image_url', true);
        if ($remote_main) {
            echo "✅ 找到远程主图: {$remote_main}\n";
        } else {
            echo "❌ 没有找到远程主图\n";
        }
    }
}

// 获取图库图片
echo "\n【图库图片获取】\n";
$gallery_ids = $product->get_gallery_image_ids();
echo "图库ID数量: " . count($gallery_ids) . "\n";

$valid_gallery_urls = [];
foreach ($gallery_ids as $i => $gallery_id) {
    echo "图库{$i+1} ID: {$gallery_id}\n";
    $gallery_url = wp_get_attachment_url($gallery_id);
    echo "  wp_get_attachment_url结果: " . ($gallery_url ?: '(空)') . "\n";
    
    if ($gallery_url) {
        $valid_gallery_urls[] = $gallery_url;
    }
}

echo "有效的图库URL数量: " . count($valid_gallery_urls) . "\n";

// 获取远程图库
echo "\n【远程图库获取】\n";
$remote_gallery_urls = get_post_meta($product_id, '_remote_gallery_urls', true);
if (is_array($remote_gallery_urls)) {
    echo "远程图库数量: " . count($remote_gallery_urls) . "\n";
    foreach ($remote_gallery_urls as $i => $url) {
        echo "远程图库{$i+1}: {$url}\n";
    }
} else {
    echo "远程图库: " . ($remote_gallery_urls ?: '(空)') . "\n";
}

// 5. 总结实际可用的图片
echo "\n=== 实际可用图片总结 ===\n";
$total_available = 0;

// 主图
if (!empty($main_image_url)) {
    echo "✅ 主图: 1张 (本地)\n";
    $total_available++;
} else if (!empty($remote_main)) {
    echo "✅ 主图: 1张 (远程)\n";
    $total_available++;
} else {
    echo "❌ 主图: 0张\n";
}

// 副图
$total_secondary = count($valid_gallery_urls);
if (is_array($remote_gallery_urls)) {
    $total_secondary += count($remote_gallery_urls);
}

echo "✅ 副图: {$total_secondary}张\n";
echo "总计可用图片: " . ($total_available + $total_secondary) . "张\n";

if ($total_secondary < 5) {
    echo "❌ 副图不足5张，不满足沃尔玛要求\n";
} else {
    echo "✅ 副图满足沃尔玛要求\n";
}

?>
