<?php
/**
 * 调试特定SKU的图片处理流程
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试SKU B081S00179 的图片处理流程 ===\n\n";

$target_sku = 'B081S00179';

// 1. 查找产品
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $target_sku
));

if (!$product_id) {
    echo "❌ 产品不存在\n";
    exit;
}

echo "产品ID: {$product_id}\n";

$product = wc_get_product($product_id);
if (!$product) {
    echo "❌ 无法获取产品对象\n";
    exit;
}

echo "产品名称: " . $product->get_name() . "\n\n";

// 2. 检查产品图片信息
echo "2. 检查产品图片信息:\n";

// 主图
$main_image_id = $product->get_image_id();
echo "主图ID: " . ($main_image_id ?: '无') . "\n";

if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "主图URL: " . ($main_image_url ?: '无法获取') . "\n";
} else {
    echo "主图URL: 无\n";
}

// 图库图片
$gallery_ids = $product->get_gallery_image_ids();
echo "图库图片ID数量: " . count($gallery_ids) . "\n";

if (!empty($gallery_ids)) {
    echo "图库图片IDs: " . implode(', ', $gallery_ids) . "\n";
    
    $gallery_urls = [];
    foreach ($gallery_ids as $id) {
        $url = wp_get_attachment_url($id);
        if ($url) {
            $gallery_urls[] = $url;
        }
    }
    echo "有效图库URL数量: " . count($gallery_urls) . "\n";
} else {
    echo "图库图片: 无\n";
}

// 远程图库
$remote_gallery_urls = get_post_meta($product_id, '_remote_gallery_urls', true);
echo "远程图库: " . (is_array($remote_gallery_urls) ? count($remote_gallery_urls) . '张' : '无') . "\n";

if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
    echo "远程图库前3张:\n";
    for ($i = 0; $i < min(3, count($remote_gallery_urls)); $i++) {
        echo "  " . ($i + 1) . ". " . substr($remote_gallery_urls[$i], 0, 80) . "...\n";
    }
}

// 3. 模拟产品映射器的图片处理逻辑
echo "\n3. 模拟产品映射器的图片处理逻辑:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射访问私有方法
$reflection = new ReflectionClass($mapper);

// 获取主图 - 直接调用公共方法
echo "获取主图:\n";
try {
    // 检查主图处理逻辑
    $main_image_id = $product->get_image_id();
    echo "原始主图ID: {$main_image_id}\n";

    if (strpos($main_image_id, 'remote_') === 0) {
        echo "这是远程图片ID\n";

        // 从远程图库获取对应URL
        if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
            $main_image_url = reset($remote_gallery_urls);
            echo "映射器主图: " . substr($main_image_url, 0, 80) . "...\n";
        } else {
            echo "❌ 无法从远程图库获取主图\n";
        }
    } else {
        $main_image_url = wp_get_attachment_url($main_image_id);
        echo "映射器主图: " . ($main_image_url ?: '无') . "\n";
    }
} catch (Exception $e) {
    echo "❌ 获取主图异常: " . $e->getMessage() . "\n";
}

// 获取副图 - 模拟映射器逻辑
echo "\n获取副图:\n";
try {
    // 模拟get_gallery_images方法的逻辑
    $gallery_images = [];

    // 1. 从图库ID获取URL
    foreach ($gallery_ids as $id) {
        if (strpos($id, 'remote_') === 0) {
            echo "远程图库ID: {$id}\n";
            // 远程图片需要从_remote_gallery_urls获取
        } else {
            $url = wp_get_attachment_url($id);
            if ($url) {
                $gallery_images[] = $url;
            }
        }
    }

    // 2. 如果没有本地图片，直接使用远程图库
    if (empty($gallery_images) && is_array($remote_gallery_urls)) {
        $gallery_images = $remote_gallery_urls;
        echo "使用远程图库作为副图\n";
    }

    echo "映射器副图数量: " . count($gallery_images) . "\n";

    if (!empty($gallery_images)) {
        echo "副图列表:\n";
        foreach ($gallery_images as $i => $url) {
            echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
        }
    }

    // 3. 分析副图数量问题
    echo "\n副图数量分析:\n";
    echo "可用副图: " . count($gallery_images) . "张\n";
    echo "Walmart要求: 至少5张\n";

    if (count($gallery_images) < 5) {
        echo "❌ 副图不足！缺少 " . (5 - count($gallery_images)) . " 张\n";
        echo "这就是导致 'productSecondaryImageURL' requires '5' entries 错误的原因\n";
    } else {
        echo "✅ 副图充足\n";
    }

} catch (Exception $e) {
    echo "❌ 获取副图异常: " . $e->getMessage() . "\n";
}

// 4. 检查Walmart分类映射
echo "\n4. 检查Walmart分类映射:\n";

$categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
echo "产品分类IDs: " . implode(', ', $categories) . "\n";

$mapping_table = $wpdb->prefix . 'walmart_category_mappings_new';
$walmart_category = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path FROM {$mapping_table} WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $walmart_category = $mapping->walmart_category_path;
        echo "找到Walmart分类映射: {$walmart_category}\n";
        break;
    }
}

if (!$walmart_category) {
    echo "❌ 没有找到Walmart分类映射\n";
}

// 5. 模拟完整的产品数据映射
echo "\n5. 模拟完整的产品数据映射:\n";

if ($walmart_category) {
    try {
        $item_data = $mapper->map_product_to_walmart_item($product, $walmart_category);
        
        // 检查图片字段
        $category_parts = explode(' > ', $walmart_category);
        $walmart_category_name = end($category_parts);
        
        if (isset($item_data['Visible'][$walmart_category_name])) {
            $visible_data = $item_data['Visible'][$walmart_category_name];
            
            echo "主图字段 (mainImageUrl): " . (isset($visible_data['mainImageUrl']) ? '有' : '无') . "\n";
            if (isset($visible_data['mainImageUrl'])) {
                echo "  值: " . substr($visible_data['mainImageUrl'], 0, 80) . "...\n";
            }
            
            echo "副图字段 (productSecondaryImageURL): " . (isset($visible_data['productSecondaryImageURL']) ? '有' : '无') . "\n";
            if (isset($visible_data['productSecondaryImageURL'])) {
                $secondary_images = $visible_data['productSecondaryImageURL'];
                echo "  数量: " . count($secondary_images) . "\n";
                echo "  是否满足要求: " . (count($secondary_images) >= 5 ? '是' : '否') . "\n";
                
                if (count($secondary_images) < 5) {
                    echo "  ❌ 这就是问题所在！副图不足5张\n";
                    echo "  实际副图:\n";
                    foreach ($secondary_images as $i => $url) {
                        echo "    " . ($i + 1) . ". " . substr($url, 0, 60) . "...\n";
                    }
                }
            } else {
                echo "  ❌ 完全没有副图字段！\n";
            }
        } else {
            echo "❌ 没有找到分类数据\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 产品映射异常: " . $e->getMessage() . "\n";
    }
} else {
    echo "跳过产品映射（没有分类映射）\n";
}

echo "\n=== 调试完成 ===\n";
echo "问题分析:\n";
echo "1. 检查产品是否有足够的图片（主图+副图）\n";
echo "2. 检查图片获取逻辑是否正常工作\n";
echo "3. 检查副图补足逻辑是否生效\n";
echo "4. 检查Walmart要求的最少5张副图是否满足\n";

?>
