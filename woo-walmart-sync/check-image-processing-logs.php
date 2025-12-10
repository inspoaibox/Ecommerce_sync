<?php
/**
 * 检查B202S00493的图片处理日志
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查B202S00493的图片处理日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$product_id = 13917;

// 查找最近的图片获取日志
$image_logs = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品图片获取' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 3
", '%' . $product_id . '%'));

echo "找到 " . count($image_logs) . " 条图片获取日志\n\n";

foreach ($image_logs as $log) {
    echo "=== 时间: {$log->created_at} ===\n";
    
    $request_data = json_decode($log->request, true);
    if ($request_data) {
        echo "产品ID: " . ($request_data['product_id'] ?? '未知') . "\n";
        echo "主图ID: " . ($request_data['main_image_id'] ?? '未知') . "\n";
        echo "主图ID类型: " . ($request_data['main_image_id_type'] ?? '未知') . "\n";
        echo "主图URL: " . ($request_data['main_image_url'] ?? '未知') . "\n";
        echo "主图来源: " . ($request_data['main_image_source'] ?? '未知') . "\n";
        
        if (isset($request_data['gallery_image_ids'])) {
            $gallery_ids = $request_data['gallery_image_ids'];
            echo "图库ID数量: " . count($gallery_ids) . "\n";
            if (!empty($gallery_ids)) {
                echo "图库IDs: " . implode(', ', array_slice($gallery_ids, 0, 3)) . (count($gallery_ids) > 3 ? '...' : '') . "\n";
            }
        }
        
        if (isset($request_data['remote_gallery_urls'])) {
            $remote_urls = $request_data['remote_gallery_urls'];
            echo "远程图库数量: " . ($request_data['remote_gallery_count'] ?? count($remote_urls)) . "\n";
            if (is_array($remote_urls) && !empty($remote_urls)) {
                echo "远程URLs (前2个):\n";
                for ($i = 0; $i < min(2, count($remote_urls)); $i++) {
                    echo "  " . ($i + 1) . ". " . substr($remote_urls[$i], 0, 80) . "...\n";
                }
            }
        }
        
        if (isset($request_data['additional_images'])) {
            $additional = $request_data['additional_images'];
            echo "最终副图数量: " . ($request_data['additional_images_count'] ?? count($additional)) . "\n";
            if (is_array($additional) && !empty($additional)) {
                echo "最终副图URLs (前2个):\n";
                for ($i = 0; $i < min(2, count($additional)); $i++) {
                    echo "  " . ($i + 1) . ". " . substr($additional[$i], 0, 80) . "...\n";
                }
            }
        }
    } else {
        echo "❌ 无法解析日志数据\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

// 如果没有找到图片获取日志，查找其他相关日志
if (empty($image_logs)) {
    echo "没有找到图片获取日志，查找其他相关日志...\n";
    
    $other_logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$logs_table} 
        WHERE (action LIKE '%图片%' OR action LIKE '%image%')
        AND request LIKE %s
        ORDER BY created_at DESC 
        LIMIT 5
    ", '%' . $product_id . '%'));
    
    foreach ($other_logs as $log) {
        echo "{$log->created_at} - {$log->action} ({$log->status})\n";
    }
}

// 直接检查产品的meta数据
echo "\n=== 直接检查产品Meta数据 ===\n";
$thumbnail_id = get_post_meta($product_id, '_thumbnail_id', true);
$gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
$remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);

echo "主图ID: {$thumbnail_id}\n";
echo "图库IDs: {$gallery_ids}\n";
echo "远程图库类型: " . gettype($remote_gallery) . "\n";
if (is_array($remote_gallery)) {
    echo "远程图库数量: " . count($remote_gallery) . "\n";
}

// 分析图片处理逻辑的执行路径
echo "\n=== 分析图片处理逻辑 ===\n";

// 主图处理
if (strpos($thumbnail_id, 'remote_') === 0) {
    echo "✅ 主图是remote_类型，应该使用远程图库的第一张\n";
    if (is_array($remote_gallery) && !empty($remote_gallery)) {
        $first_remote = reset($remote_gallery);
        echo "第一张远程图片: " . substr($first_remote, 0, 80) . "...\n";
    }
} else {
    echo "主图不是remote_类型\n";
}

// 副图处理
if ($gallery_ids) {
    $gallery_array = explode(',', $gallery_ids);
    echo "图库ID数组: " . count($gallery_array) . "个\n";
    
    $remote_count = 0;
    foreach ($gallery_array as $gid) {
        if (strpos(trim($gid), 'remote_') === 0) {
            $remote_count++;
        }
    }
    echo "其中remote_类型: {$remote_count}个\n";
    
    if ($remote_count > 0) {
        echo "⚠️ 图库ID包含remote_类型，但wp_get_attachment_url()会返回空\n";
        echo "系统应该在第172行的逻辑中使用远程图库URLs\n";
    }
}

?>
