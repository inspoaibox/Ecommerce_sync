<?php
/**
 * 简化的图片结构分析
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 简化分析B202S00493图片结构 ===\n\n";

$product_id = 13917;

// 1. 基本Meta数据
echo "=== 基本图片Meta数据 ===\n";
$thumbnail_id = get_post_meta($product_id, '_thumbnail_id', true);
$gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
$remote_main = get_post_meta($product_id, '_remote_main_image_url', true);
$remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);

echo "主图ID (_thumbnail_id): {$thumbnail_id}\n";
echo "图库IDs (_product_image_gallery): {$gallery_ids}\n";
echo "远程主图 (_remote_main_image_url): " . ($remote_main ?: '(空)') . "\n";
echo "远程图库类型: " . gettype($remote_gallery) . "\n";

if (is_array($remote_gallery)) {
    echo "远程图库数量: " . count($remote_gallery) . "\n";
    foreach ($remote_gallery as $i => $url) {
        echo "  远程图{$i+1}: " . substr($url, 0, 80) . "...\n";
    }
}

// 2. 检查remote_开头的ID是否真的存在
echo "\n=== 检查Remote ID ===\n";
if ($thumbnail_id && strpos($thumbnail_id, 'remote_') === 0) {
    echo "主图是remote ID: {$thumbnail_id}\n";
    
    // 检查这个ID是否存在于posts表
    global $wpdb;
    $post_exists = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %s", $thumbnail_id));
    echo "在posts表中存在: " . ($post_exists ? '是' : '否') . "\n";
    
    if ($post_exists) {
        $post_data = get_post($thumbnail_id);
        echo "Post类型: {$post_data->post_type}\n";
        echo "Post状态: {$post_data->post_status}\n";
        
        // 检查_wp_attached_file
        $attached_file = get_post_meta($thumbnail_id, '_wp_attached_file', true);
        echo "_wp_attached_file: " . ($attached_file ?: '(空)') . "\n";
    }
}

// 3. 检查图库IDs
if ($gallery_ids) {
    $gallery_array = explode(',', $gallery_ids);
    echo "\n图库ID数组: " . count($gallery_array) . "个\n";
    
    foreach ($gallery_array as $i => $gid) {
        $gid = trim($gid);
        echo "图库ID{$i+1}: {$gid}\n";
        
        if (strpos($gid, 'remote_') === 0) {
            echo "  这是remote ID\n";
            $post_exists = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %s", $gid));
            echo "  在posts表中存在: " . ($post_exists ? '是' : '否') . "\n";
        }
    }
}

// 4. 实际的图片获取测试
echo "\n=== 实际图片获取测试 ===\n";

$product = wc_get_product($product_id);

// 主图测试
$main_id = $product->get_image_id();
echo "WC主图ID: {$main_id}\n";

if ($main_id) {
    $main_url = wp_get_attachment_url($main_id);
    echo "WC主图URL: " . ($main_url ?: '(空)') . "\n";
    
    // 如果为空，尝试其他方法
    if (empty($main_url) && !empty($remote_main)) {
        echo "使用远程主图URL: {$remote_main}\n";
    }
}

// 图库测试
$gallery_ids_wc = $product->get_gallery_image_ids();
echo "WC图库ID数量: " . count($gallery_ids_wc) . "\n";

$valid_gallery_count = 0;
foreach ($gallery_ids_wc as $gid) {
    $url = wp_get_attachment_url($gid);
    if ($url) {
        $valid_gallery_count++;
    }
}

echo "有效图库URL数量: {$valid_gallery_count}\n";
echo "远程图库数量: " . (is_array($remote_gallery) ? count($remote_gallery) : 0) . "\n";

$total_secondary = $valid_gallery_count + (is_array($remote_gallery) ? count($remote_gallery) : 0);
echo "总副图数量: {$total_secondary}\n";

if ($total_secondary < 5) {
    echo "❌ 副图不足5张\n";
} else {
    echo "✅ 副图充足\n";
}

?>
