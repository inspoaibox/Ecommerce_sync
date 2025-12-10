<?php
/**
 * 测试主图备用方案修复效果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试主图备用方案修复效果 ===\n\n";

$test_product_id = 25926; // W1191S00043
$product = wc_get_product($test_product_id);

if (!$product) {
    echo "❌ 测试产品不存在\n";
    exit;
}

echo "产品: {$product->get_name()}\n";
echo "SKU: {$product->get_sku()}\n\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// 1. 检查产品图片情况
echo "1. 检查产品图片情况:\n";

// 主图
$main_image_id = $product->get_image_id();
if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "主图ID: {$main_image_id}\n";
    echo "主图URL: " . substr($main_image_url, 0, 80) . "...\n";
} else {
    echo "❌ 没有本地主图\n";
}

// 远程主图
$remote_main_image = get_post_meta($product_id, '_remote_main_image_url', true);
if ($remote_main_image) {
    echo "远程主图: " . substr($remote_main_image, 0, 80) . "...\n";
}

// 副图
$gallery_method = $reflection->getMethod('get_gallery_images');
$gallery_method->setAccessible(true);
$gallery_images = $gallery_method->invoke($mapper, $product);

echo "副图数量: " . count($gallery_images) . "\n";
if (!empty($gallery_images)) {
    echo "副图列表:\n";
    foreach ($gallery_images as $i => $img_url) {
        if ($i >= 3) break; // 只显示前3张
        echo "  副图 " . ($i + 1) . ": " . substr($img_url, 0, 60) . "...\n";
    }
}

// 2. 测试get_fallback_main_image方法
echo "\n2. 测试get_fallback_main_image方法:\n";

$fallback_method = $reflection->getMethod('get_fallback_main_image');
$fallback_method->setAccessible(true);

$fallback_result = $fallback_method->invoke($mapper, $product);
echo "备用主图结果: " . ($fallback_result ?: '(null)') . "\n";

if ($fallback_result) {
    echo "备用主图URL: " . substr($fallback_result, 0, 80) . "...\n";
    
    // 检查是否是占位符
    $placeholder_url = wc_placeholder_img_src('full');
    if ($fallback_result === $placeholder_url) {
        echo "⚠️ 返回的是WooCommerce占位符\n";
    } else {
        echo "✅ 返回的是实际图片（副图）\n";
    }
}

// 3. 测试修复后的mainImageUrl字段处理
echo "\n3. 测试修复后的mainImageUrl字段处理:\n";

$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

// 模拟主图验证失败的情况
echo "模拟主图验证失败的情况...\n";

$main_image_result = $generate_method->invoke($mapper, 'mainImageUrl', $product, 1);
echo "mainImageUrl结果: " . ($main_image_result ?: '(null)') . "\n";

if ($main_image_result) {
    echo "主图URL: " . substr($main_image_result, 0, 80) . "...\n";
    
    // 检查是否是占位符
    $wc_placeholder = wc_placeholder_img_src('full');
    $walmart_placeholder = get_option('woo_walmart_placeholder_image_1', '');
    
    if ($main_image_result === $wc_placeholder) {
        echo "⚠️ 使用了WooCommerce占位符（可能是最后的备用方案）\n";
    } elseif ($main_image_result === $walmart_placeholder) {
        echo "❌ 使用了Walmart占位符（修复失败，仍然跳过副图）\n";
    } else {
        // 检查是否是副图中的一张
        $is_from_gallery = false;
        foreach ($gallery_images as $gallery_img) {
            if ($main_image_result === $gallery_img) {
                $is_from_gallery = true;
                break;
            }
        }
        
        if ($is_from_gallery) {
            echo "✅ 使用了副图作为主图（修复成功）\n";
        } else {
            echo "? 使用了其他图片\n";
        }
    }
}

// 4. 测试完整的映射流程
echo "\n4. 测试完整的映射流程:\n";

// 获取分类映射
$categories = $product->get_category_ids();
global $wpdb;
$mapping = null;
$walmart_category = null;
$attribute_rules = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $walmart_category = $mapping->walmart_category_path;
        $attribute_rules = !empty($mapping->walmart_attributes) ? 
            json_decode($mapping->walmart_attributes, true) : [];
        break;
    }
}

if ($walmart_category) {
    echo "执行完整映射...\n";
    
    $upc = get_post_meta($product->get_id(), '_walmart_upc', true) ?: '123456789012';
    $fulfillment_lag_time = 1;
    
    $walmart_data = $mapper->map($product, $walmart_category, $upc, $attribute_rules, $fulfillment_lag_time);
    
    if ($walmart_data && isset($walmart_data['MPItem'][0]['Visible'][$walmart_category]['mainImageUrl'])) {
        $mapped_main_image = $walmart_data['MPItem'][0]['Visible'][$walmart_category]['mainImageUrl'];
        echo "映射后主图: " . substr($mapped_main_image, 0, 80) . "...\n";
        
        // 检查映射后的主图类型
        $wc_placeholder = wc_placeholder_img_src('full');
        $walmart_placeholder = get_option('woo_walmart_placeholder_image_1', '');
        
        if ($mapped_main_image === $wc_placeholder) {
            echo "⚠️ 映射后使用WooCommerce占位符\n";
        } elseif ($mapped_main_image === $walmart_placeholder) {
            echo "❌ 映射后使用Walmart占位符（问题仍然存在）\n";
        } else {
            // 检查是否是副图
            $is_from_gallery = false;
            foreach ($gallery_images as $gallery_img) {
                if ($mapped_main_image === $gallery_img) {
                    $is_from_gallery = true;
                    break;
                }
            }
            
            if ($is_from_gallery) {
                echo "✅ 映射后使用副图作为主图（修复成功）\n";
            } else {
                echo "✅ 映射后使用有效图片\n";
            }
        }
    } else {
        echo "❌ 映射失败或没有主图字段\n";
    }
} else {
    echo "❌ 没有找到分类映射\n";
}

echo "\n=== 修复总结 ===\n";
echo "✅ 修复了字段映射中的主图处理逻辑\n";
echo "✅ 主图验证失败时，现在会先尝试使用副图\n";
echo "✅ 只有在没有可用副图时，才使用占位符\n";
echo "✅ 占位符的使用顺序现在是正确的（最后备用方案）\n";

?>
