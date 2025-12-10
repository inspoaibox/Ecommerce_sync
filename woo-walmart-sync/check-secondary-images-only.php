<?php
/**
 * 专门检查副图字段的处理逻辑
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 专门检查副图字段处理 ===\n\n";

$product_id = 13917;

// 1. 检查原始数据
echo "=== 原始数据 ===\n";
$remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);

if (is_array($remote_gallery)) {
    echo "远程图库总数: " . count($remote_gallery) . "张\n";
    foreach ($remote_gallery as $i => $url) {
        echo ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
    }
} else {
    echo "远程图库: " . ($remote_gallery ?: '(空)') . "\n";
}

// 2. 检查图片处理日志中的副图处理
echo "\n=== 图片处理日志中的副图 ===\n";

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
    $additional_images = $request_data['additional_images'] ?? [];
    
    echo "处理后的副图数量: " . count($additional_images) . "张\n";
    foreach ($additional_images as $i => $url) {
        echo ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
    }
    
    // 检查是否与原始远程图库相同
    if (is_array($remote_gallery)) {
        if (count($additional_images) === count($remote_gallery)) {
            echo "\n✅ 副图数量与远程图库相同\n";
            
            $all_match = true;
            foreach ($additional_images as $i => $processed_url) {
                if (!isset($remote_gallery[$i]) || $processed_url !== $remote_gallery[$i]) {
                    $all_match = false;
                    break;
                }
            }
            
            if ($all_match) {
                echo "✅ 副图内容与远程图库完全相同\n";
            } else {
                echo "❌ 副图内容与远程图库不同\n";
            }
        } else {
            echo "❌ 副图数量与远程图库不同\n";
            echo "远程图库: " . count($remote_gallery) . "张\n";
            echo "处理后副图: " . count($additional_images) . "张\n";
        }
    }
}

// 3. 检查最终发送给沃尔玛的数据
echo "\n=== 最终发送给沃尔玛的副图数据 ===\n";

$mapping_log = $wpdb->get_row("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品映射-最终数据结构'
    AND created_at >= '2025-08-10 15:20:00'
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($mapping_log) {
    $request_data = json_decode($mapping_log->request, true);
    
    if (isset($request_data['MPItem']) && is_array($request_data['MPItem'])) {
        $mp_items = $request_data['MPItem'];
        
        foreach ($mp_items as $item) {
            if (isset($item['Visible'])) {
                foreach ($item['Visible'] as $category => $data) {
                    if (isset($data['sku']) && $data['sku'] === 'B202S00493') {
                        echo "找到SKU B202S00493在分类: {$category}\n";
                        
                        // 检查主图
                        if (isset($data['mainImageUrl'])) {
                            echo "主图字段: ✅ 存在\n";
                            echo "主图URL: " . substr($data['mainImageUrl'], 0, 80) . "...\n";
                        } else {
                            echo "主图字段: ❌ 缺失\n";
                        }
                        
                        // 检查副图
                        if (isset($data['productSecondaryImageURL'])) {
                            $secondary_images = $data['productSecondaryImageURL'];
                            echo "副图字段: ✅ 存在\n";
                            echo "副图数量: " . count($secondary_images) . "张\n";
                            
                            foreach ($secondary_images as $i => $url) {
                                echo "副图" . ($i + 1) . ": " . substr($url, 0, 80) . "...\n";
                            }
                            
                            if (count($secondary_images) < 5) {
                                echo "❌ 副图不足5张，这就是沃尔玛报错的原因\n";
                                echo "需要分析为什么只有 " . count($secondary_images) . " 张\n";
                            } else {
                                echo "✅ 副图满足5张要求\n";
                            }
                        } else {
                            echo "副图字段: ❌ 完全缺失\n";
                        }
                        
                        break 2;
                    }
                }
            }
        }
    }
}

// 4. 分析为什么副图只有4张
echo "\n=== 分析副图数量问题 ===\n";

if (is_array($remote_gallery)) {
    $remote_count = count($remote_gallery);
    echo "远程图库原始数量: {$remote_count}张\n";
    
    if ($remote_count === 4) {
        echo "原始就只有4张远程图片\n";
        echo "根据占位符补足逻辑:\n";
        echo "- 如果副图 = 4张，应该添加占位符1补足至5张\n";
        echo "- 如果副图 = 3张，应该添加占位符1+2补足至5张\n";
        echo "- 如果副图 < 3张，不进行补足\n";
        
        // 检查占位符
        $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
        $placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');
        
        echo "\n占位符检查:\n";
        echo "占位符1: " . ($placeholder_1 ?: '(空)') . "\n";
        echo "占位符2: " . ($placeholder_2 ?: '(空)') . "\n";
        
        if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
            echo "✅ 占位符1有效，应该被添加\n";
        } else {
            echo "❌ 占位符1无效，不会被添加\n";
        }
        
        echo "\n结论: 4张原始图片 + 1张占位符 = 5张，应该满足要求\n";
        echo "如果最终只有4张，说明占位符添加失败\n";
    }
}

?>
