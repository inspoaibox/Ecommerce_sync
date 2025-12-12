<?php
/**
 * 直接查询数据库中这些SKU的图片处理日志
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查失败SKU的图片处理日志 ===\n\n";

$failed_skus = [
    'B202P222191',
    'B202S00513', 
    'B202S00514',
    'B202S00492',
    'B202S00493'
];

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

foreach ($failed_skus as $sku) {
    echo "=== SKU: {$sku} ===\n";
    
    // 1. 查找产品ID
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if (!$product_id) {
        echo "❌ 未找到产品ID\n\n";
        continue;
    }
    
    echo "产品ID: {$product_id}\n";
    
    // 2. 获取产品实际图片情况
    $product = wc_get_product($product_id);
    if ($product) {
        $main_image_id = $product->get_image_id();
        $gallery_image_ids = $product->get_gallery_image_ids();
        $remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
        
        echo "主图: " . ($main_image_id ? "有" : "无") . "\n";
        echo "图库图片数量: " . count($gallery_image_ids) . "\n";
        echo "远程图库数量: " . (is_array($remote_gallery) ? count($remote_gallery) : 0) . "\n";
        
        // 计算总的副图数量
        $total_additional = count($gallery_image_ids);
        if (is_array($remote_gallery)) {
            $total_additional += count($remote_gallery);
        }
        echo "总副图数量: {$total_additional}\n";
    }
    
    // 3. 查找图片处理相关日志
    echo "\n【图片处理日志】:\n";
    
    $image_logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$logs_table} 
        WHERE (action LIKE '%图片%' OR action = '产品图片字段') 
        AND (request LIKE %s OR request LIKE %s)
        ORDER BY created_at DESC 
        LIMIT 5
    ", '%' . $product_id . '%', '%' . $sku . '%'));
    
    if (!empty($image_logs)) {
        foreach ($image_logs as $log) {
            echo "时间: {$log->created_at}\n";
            echo "操作: {$log->action}\n";
            echo "状态: {$log->status}\n";
            
            $request_data = json_decode($log->request, true);
            if ($request_data) {
                if (isset($request_data['original_images_count'])) {
                    echo "原始图片数量: {$request_data['original_images_count']}\n";
                }
                if (isset($request_data['final_images_count'])) {
                    echo "最终图片数量: {$request_data['final_images_count']}\n";
                }
                if (isset($request_data['meets_walmart_requirement'])) {
                    echo "满足沃尔玛要求: " . ($request_data['meets_walmart_requirement'] ? '是' : '否') . "\n";
                }
                if (isset($request_data['placeholder_used'])) {
                    echo "使用占位符: " . ($request_data['placeholder_used'] ? '是' : '否') . "\n";
                }
                if (isset($request_data['additionalImages'])) {
                    $additional_images = $request_data['additionalImages'];
                    echo "副图URL数量: " . count($additional_images) . "\n";
                    if (!empty($additional_images)) {
                        echo "副图URLs:\n";
                        foreach ($additional_images as $i => $url) {
                            echo "  " . ($i + 1) . ". {$url}\n";
                        }
                    }
                }
            }
            echo "消息: {$log->message}\n";
            echo "---\n";
        }
    } else {
        echo "未找到图片处理日志\n";
    }
    
    // 4. 查找产品映射日志
    echo "\n【产品映射日志】:\n";
    
    $mapping_logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$logs_table} 
        WHERE action LIKE '%产品映射%' 
        AND request LIKE %s
        ORDER BY created_at DESC 
        LIMIT 3
    ", '%' . $sku . '%'));
    
    if (!empty($mapping_logs)) {
        foreach ($mapping_logs as $log) {
            echo "时间: {$log->created_at}\n";
            echo "操作: {$log->action}\n";
            
            // 检查请求数据中是否包含图片信息
            if (strpos($log->request, 'productSecondaryImageURL') !== false) {
                echo "✅ 包含productSecondaryImageURL字段\n";
                
                // 尝试解析JSON
                $request_data = json_decode($log->request, true);
                if ($request_data && isset($request_data['MPItem']['Visible'])) {
                    $visible_data = $request_data['MPItem']['Visible'];
                    foreach ($visible_data as $category => $data) {
                        if (isset($data['productSecondaryImageURL'])) {
                            $secondary_images = $data['productSecondaryImageURL'];
                            echo "分类 {$category} 的副图数量: " . count($secondary_images) . "\n";
                        }
                    }
                }
            } else {
                echo "❌ 不包含productSecondaryImageURL字段\n";
            }
            echo "---\n";
        }
    } else {
        echo "未找到产品映射日志\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

// 检查占位符配置
echo "=== 占位符配置 ===\n";
$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "占位符1: " . ($placeholder_1 ?: '未设置') . "\n";
echo "占位符2: " . ($placeholder_2 ?: '未设置') . "\n";

if (!empty($placeholder_1)) {
    echo "占位符1 URL验证: " . (filter_var($placeholder_1, FILTER_VALIDATE_URL) ? '有效' : '无效') . "\n";
}
if (!empty($placeholder_2)) {
    echo "占位符2 URL验证: " . (filter_var($placeholder_2, FILTER_VALIDATE_URL) ? '有效' : '无效') . "\n";
}

?>
