<?php
/**
 * 检查占位符补足的日志记录
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查占位符补足日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$product_id = 13917;

// 1. 查找图片补足相关的日志
echo "=== 查找图片补足日志 ===\n";

$placeholder_logs = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE (action LIKE '%图片补足%' OR action LIKE '%placeholder%')
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 5
", '%' . $product_id . '%'));

if (!empty($placeholder_logs)) {
    foreach ($placeholder_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            foreach ($request_data as $key => $value) {
                echo "{$key}: {$value}\n";
            }
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到图片补足日志\n";
}

// 2. 查找所有与该产品相关的日志，看看图片处理流程
echo "\n=== 查找该产品的所有处理日志 ===\n";

$all_logs = $wpdb->get_results($wpdb->prepare("
    SELECT action, status, created_at FROM {$logs_table} 
    WHERE request LIKE %s
    AND created_at >= '2025-08-10 15:20:00'
    ORDER BY created_at ASC
", '%' . $product_id . '%'));

echo "处理流程:\n";
foreach ($all_logs as $log) {
    echo "{$log->created_at} - {$log->action} ({$log->status})\n";
}

// 3. 检查图片字段的最终状态日志
echo "\n=== 检查图片字段最终状态 ===\n";

$final_image_log = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品图片字段'
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 1
", '%' . $product_id . '%'));

if ($final_image_log) {
    echo "时间: {$final_image_log->created_at}\n";
    echo "消息: {$final_image_log->message}\n";
    
    $request_data = json_decode($final_image_log->request, true);
    if ($request_data) {
        echo "原始图片数量: " . ($request_data['original_images_count'] ?? '未知') . "\n";
        echo "最终图片数量: " . ($request_data['final_images_count'] ?? '未知') . "\n";
        echo "使用占位符: " . ($request_data['placeholder_used'] ? '是' : '否') . "\n";
        echo "满足沃尔玛要求: " . ($request_data['meets_walmart_requirement'] ? '是' : '否') . "\n";
        
        if (isset($request_data['additionalImages'])) {
            $images = $request_data['additionalImages'];
            echo "最终副图列表 (" . count($images) . "张):\n";
            foreach ($images as $i => $url) {
                echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
            }
        }
    }
} else {
    echo "❌ 没有找到图片字段最终状态日志\n";
}

// 4. 手动测试占位符逻辑
echo "\n=== 手动测试占位符逻辑 ===\n";

$remote_gallery = get_post_meta($product_id, '_remote_gallery_urls', true);
$original_count = is_array($remote_gallery) ? count($remote_gallery) : 0;

echo "原始图片数量: {$original_count}\n";

if ($original_count == 4) {
    echo "✅ 符合4张图片的补足条件\n";
    
    $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
    echo "占位符1: " . ($placeholder_1 ?: '(空)') . "\n";
    
    if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
        echo "✅ 占位符1有效，应该被添加\n";
        
        // 模拟添加过程
        $test_images = $remote_gallery;
        $test_images[] = $placeholder_1;
        
        echo "模拟添加后数量: " . count($test_images) . "\n";
        echo "应该满足5张要求: " . (count($test_images) >= 5 ? '是' : '否') . "\n";
    } else {
        echo "❌ 占位符1无效\n";
    }
} else {
    echo "不符合4张图片的补足条件\n";
}

?>
