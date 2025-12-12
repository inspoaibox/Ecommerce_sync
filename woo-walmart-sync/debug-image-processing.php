<?php
/**
 * 调试图片处理流程
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试图片处理流程 ===\n\n";

$test_sku = 'W1191S00043';
$product_id = 25926;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n\n";

// 1. 测试产品映射器的图片获取
echo "1. 测试产品映射器的图片获取:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);

// 测试 is_remote_image 方法
$is_remote_method = $reflection->getMethod('is_remote_image');
$is_remote_method->setAccessible(true);

// 测试一些URL
$test_urls = [
    'https://b2bfiles1.gigab2b.cn/image/wkseller/12978/bf850d1ab742d8fcf82a0087efd290fa.jpg',
    'http://example.com/image.jpg',
    '/wp-content/uploads/image.jpg',
    'remote_f5e4f89782d4c3ade0b08bd6d94df778'
];

foreach ($test_urls as $url) {
    $is_remote = $is_remote_method->invoke($mapper, $url);
    echo "URL: " . substr($url, 0, 60) . "...\n";
    echo "是否远程: " . ($is_remote ? '是' : '否') . "\n\n";
}

// 2. 测试主图获取
echo "2. 测试主图获取:\n";

$main_image_id = $product->get_image_id();
echo "主图ID: {$main_image_id}\n";

if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "wp_get_attachment_url结果: " . ($main_image_url ?: '(空)') . "\n";
    
    if ($main_image_url) {
        $is_remote = $is_remote_method->invoke($mapper, $main_image_url);
        echo "是否远程图片: " . ($is_remote ? '是' : '否') . "\n";
    }
}

// 检查远程主图
$remote_main_image = get_post_meta($product_id, '_remote_main_image_url', true);
echo "远程主图meta: " . ($remote_main_image ?: '(空)') . "\n";

if ($remote_main_image) {
    $is_remote = $is_remote_method->invoke($mapper, $remote_main_image);
    echo "远程主图是否远程: " . ($is_remote ? '是' : '否') . "\n";
}

// 3. 测试图库图片获取
echo "\n3. 测试图库图片获取:\n";

$get_gallery_method = $reflection->getMethod('get_gallery_images');
$get_gallery_method->setAccessible(true);

$gallery_images = $get_gallery_method->invoke($mapper, $product);
echo "图库图片数量: " . count($gallery_images) . "\n";

foreach ($gallery_images as $index => $image_url) {
    echo "图库图片 " . ($index + 1) . ": " . substr($image_url, 0, 80) . "...\n";
    
    $is_remote = $is_remote_method->invoke($mapper, $image_url);
    echo "  是否远程: " . ($is_remote ? '是' : '否') . "\n";
    
    if ($index >= 2) {
        echo "  ... (省略其余)\n";
        break;
    }
}

// 4. 模拟完整的映射过程
echo "\n4. 模拟完整的映射过程:\n";

// 获取分类映射
global $wpdb;
$categories = $product->get_category_ids();
$walmart_category = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $walmart_category = $mapping->walmart_category_path;
        break;
    }
}

if ($walmart_category) {
    echo "Walmart分类: {$walmart_category}\n";
    
    // 模拟映射过程
    try {
        echo "开始映射过程...\n";
        
        // 这里应该会调用图片验证
        $mapping_result = $mapper->map($product, $walmart_category, '123456789012', [], 1);
        
        echo "映射完成\n";
        
        // 检查映射结果中的图片
        if (isset($mapping_result['MPItem'][0]['Visible'][$walmart_category])) {
            $visible_data = $mapping_result['MPItem'][0]['Visible'][$walmart_category];
            
            echo "\n映射结果中的图片:\n";
            
            if (isset($visible_data['mainImageUrl'])) {
                echo "主图: " . substr($visible_data['mainImageUrl'], 0, 80) . "...\n";
            } else {
                echo "❌ 缺少主图\n";
            }
            
            if (isset($visible_data['productSecondaryImageURL'])) {
                $secondary_images = $visible_data['productSecondaryImageURL'];
                echo "副图数量: " . count($secondary_images) . "\n";
                
                foreach ($secondary_images as $i => $img_url) {
                    echo "  副图 " . ($i + 1) . ": " . substr($img_url, 0, 60) . "...\n";
                    if ($i >= 2) break;
                }
            } else {
                echo "❌ 缺少副图\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 映射失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ 没有找到Walmart分类映射\n";
}

// 5. 检查图片验证日志
echo "\n5. 检查图片验证相关日志:\n";

$recent_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs 
     WHERE product_id = %d 
     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
     ORDER BY created_at DESC 
     LIMIT 20",
    $product_id
));

if ($recent_logs) {
    foreach ($recent_logs as $log) {
        if (strpos($log->message, '图片') !== false || strpos($log->message, 'image') !== false) {
            echo "时间: {$log->created_at}\n";
            echo "消息: {$log->message}\n";
            echo "---\n";
        }
    }
} else {
    echo "没有找到最近1小时的日志\n";
}

echo "\n=== 调试完成 ===\n";

// 总结
echo "\n=== 问题总结 ===\n";
echo "1. 图片验证器本身工作正常\n";
echo "2. 需要检查图片验证是否在映射过程中被调用\n";
echo "3. 需要检查远程图片的识别逻辑\n";
echo "4. 需要确认图片验证失败后的处理逻辑\n";

?>
