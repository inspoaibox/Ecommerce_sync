<?php
/**
 * 实际检查B202S00493的主图和副图URL
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 实际检查B202S00493的图片URL ===\n\n";

$product_id = 13917;

// 从日志中获取的实际URL
echo "=== 从日志中获取的实际URL ===\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$image_log = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品图片获取' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 1
", '%' . $product_id . '%'));

if ($image_log) {
    $request_data = json_decode($image_log->request, true);
    
    $main_image_url = $request_data['main_image_url'] ?? '';
    $additional_images = $request_data['additional_images'] ?? [];
    
    echo "主图URL:\n";
    echo $main_image_url . "\n\n";
    
    echo "副图URLs (" . count($additional_images) . "张):\n";
    foreach ($additional_images as $i => $url) {
        echo ($i + 1) . ". " . $url . "\n";
    }
    
    echo "\n=== 比较主图和副图 ===\n";
    
    // 检查主图是否与任何副图相同
    $duplicate_found = false;
    foreach ($additional_images as $i => $additional_url) {
        if ($main_image_url === $additional_url) {
            echo "❌ 发现重复！主图与副图" . ($i + 1) . "相同\n";
            $duplicate_found = true;
        }
    }
    
    if (!$duplicate_found) {
        echo "✅ 主图与所有副图都不相同\n";
    }
    
    // 检查副图之间是否有重复
    echo "\n=== 检查副图内部重复 ===\n";
    $unique_additional = array_unique($additional_images);
    
    if (count($additional_images) === count($unique_additional)) {
        echo "✅ 副图之间没有重复\n";
    } else {
        echo "❌ 副图之间有重复\n";
        echo "原始副图数量: " . count($additional_images) . "\n";
        echo "去重后数量: " . count($unique_additional) . "\n";
    }
    
    // 计算总的唯一图片数量
    $all_images = array_merge([$main_image_url], $additional_images);
    $all_unique = array_unique($all_images);
    
    echo "\n=== 总计算 ===\n";
    echo "主图: 1张\n";
    echo "副图: " . count($additional_images) . "张\n";
    echo "总计: " . count($all_images) . "张\n";
    echo "去重后总计: " . count($all_unique) . "张\n";
    
    if (count($all_unique) < 5) {
        echo "❌ 去重后不足5张，这就是沃尔玛报错的原因\n";
    } else {
        echo "✅ 去重后满足5张要求\n";
    }
    
} else {
    echo "❌ 没有找到图片获取日志\n";
}

// 直接从meta数据检查
echo "\n=== 直接从Meta数据检查 ===\n";

$remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);

if (is_array($remote_gallery)) {
    echo "远程图库URLs (" . count($remote_gallery) . "张):\n";
    foreach ($remote_gallery as $i => $url) {
        echo ($i + 1) . ". " . $url . "\n";
    }
    
    echo "\n根据代码逻辑:\n";
    echo "主图应该是: " . (isset($remote_gallery[0]) ? $remote_gallery[0] : '无') . "\n";
    echo "副图应该是: 全部4张远程图库URL\n";
    
    if (isset($remote_gallery[0])) {
        echo "\n这意味着主图和副图第1张确实相同！\n";
    }
}

?>
