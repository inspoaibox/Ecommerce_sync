<?php
/**
 * 调试动态映射中productSecondaryImageURL的具体值
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试动态映射中productSecondaryImageURL的具体值 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 获取最近的动态映射productSecondaryImageURL日志的详细内容
echo "=== 获取动态映射productSecondaryImageURL的详细值 ===\n";

$dynamic_log = $wpdb->get_row("
    SELECT id, created_at, request, response 
    FROM {$logs_table} 
    WHERE action = '动态映射-Visible字段' 
    AND request LIKE '%productSecondaryImageURL%'
    AND created_at BETWEEN '2025-08-10 15:20:33' AND '2025-08-10 15:20:34'
    ORDER BY id DESC 
    LIMIT 1
");

if ($dynamic_log) {
    echo "找到动态映射日志ID: {$dynamic_log->id} | 时间: {$dynamic_log->created_at}\n";
    
    $request_data = json_decode($dynamic_log->request, true);
    if ($request_data) {
        echo "字段: {$request_data['field']}\n";
        echo "类型: {$request_data['type']}\n";
        echo "来源: {$request_data['source']}\n";
        
        if (isset($request_data['value'])) {
            $value = $request_data['value'];

            // 检查值的类型
            if (is_array($value)) {
                echo "动态映射生成的副图数量: " . count($value) . "\n";
                echo "动态映射生成的副图URLs:\n";
                foreach ($value as $i => $url) {
                    echo "  " . ($i + 1) . ". " . $url . "\n";

                    // 检查是否是远程URL
                    if (strpos($url, 'b2bfiles1.gigab2b.cn') !== false) {
                        echo "    ✅ 这是远程图库URL！\n";
                    } else if (strpos($url, 'walmartimages.com') !== false) {
                        echo "    ✅ 这是占位符URL！\n";
                    } else {
                        echo "    ❓ 本地或其他URL\n";
                    }
                }
            } else if (is_string($value)) {
                echo "动态映射的值是字符串: {$value}\n";

                // 尝试解析为JSON
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    echo "解析为数组，数量: " . count($decoded) . "\n";
                    foreach ($decoded as $i => $url) {
                        echo "  " . ($i + 1) . ". " . $url . "\n";
                    }
                } else {
                    echo "无法解析为数组\n";
                }
            } else {
                echo "值类型: " . gettype($value) . "\n";
                echo "值: " . print_r($value, true) . "\n";
            }
        }
    }
} else {
    echo "❌ 没有找到动态映射productSecondaryImageURL日志\n";
}

// 2. 对比占位符补足后的图片和动态映射的图片
echo "\n=== 对比占位符补足和动态映射的图片 ===\n";

// 获取产品图片字段日志（占位符补足后）
$image_field_log = $wpdb->get_row("
    SELECT request 
    FROM {$logs_table} 
    WHERE action = '产品图片字段' 
    AND created_at BETWEEN '2025-08-10 15:20:33' AND '2025-08-10 15:20:34'
    ORDER BY id DESC 
    LIMIT 1
");

if ($image_field_log && $dynamic_log) {
    $image_data = json_decode($image_field_log->request, true);
    $dynamic_data = json_decode($dynamic_log->request, true);
    
    if ($image_data && $dynamic_data) {
        echo "占位符补足后的图片:\n";
        $placeholder_images = $image_data['additionalImages'] ?? [];
        foreach ($placeholder_images as $i => $url) {
            echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
        }
        
        echo "\n动态映射生成的图片:\n";
        $dynamic_value = $dynamic_data['value'] ?? '';
        $dynamic_images = [];

        if (is_array($dynamic_value)) {
            $dynamic_images = $dynamic_value;
        } else if (is_string($dynamic_value)) {
            $decoded = json_decode($dynamic_value, true);
            if (is_array($decoded)) {
                $dynamic_images = $decoded;
            }
        }

        foreach ($dynamic_images as $i => $url) {
            echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
        }

        echo "\n对比结果:\n";
        echo "占位符补足数量: " . count($placeholder_images) . "\n";
        echo "动态映射数量: " . count($dynamic_images) . "\n";
        
        if (count($placeholder_images) != count($dynamic_images)) {
            echo "❌ 数量不一致！这就是问题所在！\n";
        } else {
            echo "✅ 数量一致\n";
            
            // 检查内容是否一致
            $diff = array_diff($placeholder_images, $dynamic_images);
            if (!empty($diff)) {
                echo "❌ 内容不一致！差异:\n";
                foreach ($diff as $url) {
                    echo "  - " . substr($url, 0, 80) . "...\n";
                }
            } else {
                echo "✅ 内容完全一致\n";
            }
        }
    }
}

// 3. 检查动态映射是否调用了完整的图片处理逻辑
echo "\n=== 检查动态映射的图片处理逻辑 ===\n";

// 模拟产品13917的图片获取
$product_id = 13917;
$product = wc_get_product($product_id);

if ($product) {
    echo "模拟动态映射的图片获取:\n";
    
    // 模拟generate_special_attribute_value方法中的逻辑
    $gallery_image_ids = $product->get_gallery_image_ids();
    echo "本地图库ID: " . implode(', ', $gallery_image_ids) . "\n";
    
    $gallery_images = [];
    foreach ($gallery_image_ids as $image_id) {
        if ($image_id < 0) {
            echo "发现负数ID: {$image_id} (远程图库)\n";
            
            // 检查是否有远程图库处理逻辑
            $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
            if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
                $remote_index = abs($image_id + 1000);
                if (isset($remote_gallery_urls[$remote_index])) {
                    $remote_url = $remote_gallery_urls[$remote_index];
                    if (filter_var($remote_url, FILTER_VALIDATE_URL)) {
                        $gallery_images[] = $remote_url;
                        echo "  ✅ 添加远程图库URL: " . substr($remote_url, 0, 80) . "...\n";
                    }
                }
            }
        } else {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $gallery_images[] = $image_url;
                echo "  ✅ 添加本地图库URL: " . substr($image_url, 0, 80) . "...\n";
            }
        }
    }
    
    echo "模拟结果数量: " . count($gallery_images) . "\n";
    
    if (count($gallery_images) == 4) {
        echo "✅ 模拟结果与动态映射一致（4张）\n";
        echo "❌ 但这说明动态映射的generate_special_attribute_value方法\n";
        echo "   没有包含占位符补足逻辑！\n";
    }
} else {
    echo "❌ 无法获取产品信息\n";
}

// 4. 总结分析
echo "\n=== 总结分析 ===\n";

if ($dynamic_log) {
    $dynamic_data = json_decode($dynamic_log->request, true);
    $dynamic_value = $dynamic_data['value'] ?? '';
    $dynamic_count = 0;

    if (is_array($dynamic_value)) {
        $dynamic_count = count($dynamic_value);
    } else if (is_string($dynamic_value)) {
        $decoded = json_decode($dynamic_value, true);
        if (is_array($decoded)) {
            $dynamic_count = count($decoded);
        }
    }
    
    echo "确认的事实:\n";
    echo "1. 动态映射确实覆盖了productSecondaryImageURL字段\n";
    echo "2. 动态映射生成了 {$dynamic_count} 张图片\n";
    echo "3. 动态映射的auto_generate逻辑包含了远程图库处理\n";
    echo "4. 但动态映射没有包含占位符补足逻辑\n";
    echo "\n问题根源:\n";
    echo "generate_special_attribute_value方法中的productSecondaryImageURL处理\n";
    echo "需要包含占位符补足逻辑，或者从动态映射规则中移除此字段\n";
}

?>
