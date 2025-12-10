<?php
/**
 * 调试图片数量计算逻辑
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试图片数量计算逻辑 ===\n\n";

$target_sku = 'B081S00179';

// 获取产品
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $target_sku
));

$product = wc_get_product($product_id);
echo "产品: {$product->get_name()}\n";
echo "SKU: {$target_sku}\n\n";

// 1. 获取主图
echo "1. 获取主图:\n";
$main_image_id = $product->get_image_id();
echo "主图ID: {$main_image_id}\n";

$main_image_url = '';
if (strpos($main_image_id, 'remote_') === 0) {
    $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
    if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
        $main_image_url = reset($remote_gallery_urls);
        echo "主图URL: " . substr($main_image_url, 0, 80) . "...\n";
    }
} else {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "主图URL: " . ($main_image_url ?: '无') . "\n";
}

// 2. 获取副图（模拟映射器逻辑）
echo "\n2. 获取副图（模拟映射器逻辑）:\n";

$gallery_image_ids = $product->get_gallery_image_ids();
echo "图库图片IDs: " . implode(', ', $gallery_image_ids) . "\n";

$additional_images = [];

if (!empty($gallery_image_ids)) {
    foreach ($gallery_image_ids as $gallery_image_id) {
        echo "处理图库ID: {$gallery_image_id}\n";
        
        if ($gallery_image_id > 0) {
            // 处理本地图库图片
            $gallery_image_url = wp_get_attachment_url($gallery_image_id);
            if ($gallery_image_url && filter_var($gallery_image_url, FILTER_VALIDATE_URL)) {
                $additional_images[] = $gallery_image_url;
                echo "  添加本地图片: " . substr($gallery_image_url, 0, 60) . "...\n";
            }
        } else if ($gallery_image_id < 0) {
            // 处理GigaCloud远程图库（负数ID）
            $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
            if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                // 计算在远程图库数组中的索引
                $remote_index = abs($gallery_image_id + 1000);
                if (isset($remote_gallery_urls[$remote_index])) {
                    $remote_url = $remote_gallery_urls[$remote_index];
                    if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                        $additional_images[] = $remote_url;
                        echo "  添加远程图片: " . substr($remote_url, 0, 60) . "...\n";
                    }
                }
            }
        }
    }
}

// 如果没有通过图库ID获取到图片，直接尝试从远程图库元数据获取
if (empty($additional_images)) {
    echo "图库ID没有获取到图片，尝试从远程图库元数据获取\n";
    $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
    if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
        foreach ($remote_gallery_urls as $i => $remote_url) {
            if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                $additional_images[] = $remote_url;
                echo "  添加远程图片[{$i}]: " . substr($remote_url, 0, 60) . "...\n";
            }
        }
    }
}

echo "\n获取到的副图数量: " . count($additional_images) . "\n";

// 3. 去重处理（包含主图去重修复）
echo "\n3. 去重处理（包含主图去重修复）:\n";
$before_unique_count = count($additional_images);
echo "去重前数量: {$before_unique_count}\n";

$additional_images = array_unique($additional_images);
$before_main_dedup_count = count($additional_images);
echo "普通去重后数量: {$before_main_dedup_count}\n";

// 🔧 重要修复：从副图中移除与主图相同的URL
if (!empty($main_image_url)) {
    $additional_images = array_filter($additional_images, function($url) use ($main_image_url) {
        return $url !== $main_image_url;
    });
    // 重新索引数组
    $additional_images = array_values($additional_images);
}
$original_count = count($additional_images);
echo "主图去重后数量: {$original_count}\n";

$main_duplicates_removed = $before_main_dedup_count - $original_count;
if ($main_duplicates_removed > 0) {
    echo "✅ 检测到主图重复，已移除 {$main_duplicates_removed} 张重复的主图\n";
}

if ($before_unique_count != $original_count) {
    echo "最终去重后的图片列表:\n";
    foreach ($additional_images as $i => $url) {
        echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
    }
}

// 4. 检查主图是否在副图中
echo "\n4. 检查主图是否在副图中:\n";
$main_in_additional = false;
if (!empty($main_image_url)) {
    foreach ($additional_images as $url) {
        if ($url === $main_image_url) {
            $main_in_additional = true;
            echo "⚠️ 主图也在副图列表中: " . substr($main_image_url, 0, 80) . "...\n";
            break;
        }
    }
}

if (!$main_in_additional) {
    echo "✅ 主图不在副图列表中\n";
}

// 5. 模拟占位符填充逻辑
echo "\n5. 模拟占位符填充逻辑:\n";
echo "原始副图数量: {$original_count}\n";

$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "占位符1: " . ($placeholder_1 ? '已配置' : '未配置') . "\n";
echo "占位符2: " . ($placeholder_2 ? '已配置' : '未配置') . "\n";

if ($original_count == 4) {
    echo "触发条件: 副图 = 4张，添加占位符1\n";
    if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
        $additional_images[] = $placeholder_1;
        echo "✅ 添加占位符1\n";
    } else {
        echo "❌ 占位符1无效\n";
    }
} elseif ($original_count == 3) {
    echo "触发条件: 副图 = 3张，添加占位符1+2\n";
    if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
        $additional_images[] = $placeholder_1;
        echo "✅ 添加占位符1\n";
    } else {
        echo "❌ 占位符1无效\n";
    }
    if (!empty($placeholder_2) && filter_var($placeholder_2, FILTER_VALIDATE_URL)) {
        $additional_images[] = $placeholder_2;
        echo "✅ 添加占位符2\n";
    } else {
        echo "❌ 占位符2无效\n";
    }
} elseif ($original_count < 3) {
    echo "触发条件: 副图 < 3张，不进行补足\n";
} else {
    echo "触发条件: 副图 >= 5张，无需补足\n";
}

echo "\n最终副图数量: " . count($additional_images) . "\n";
echo "是否满足Walmart要求: " . (count($additional_images) >= 5 ? '是' : '否') . "\n";

echo "\n=== 问题诊断 ===\n";
if (count($additional_images) < 5) {
    echo "🚨 副图数量不足！\n";
    echo "可能的原因:\n";
    echo "1. 原始图片数量计算错误\n";
    echo "2. 去重逻辑有问题\n";
    echo "3. 占位符填充条件判断错误\n";
    echo "4. 占位符配置无效\n";
} else {
    echo "✅ 副图数量充足\n";
}

?>
