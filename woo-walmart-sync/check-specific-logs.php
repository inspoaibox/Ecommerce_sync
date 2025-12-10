<?php
/**
 * 检查特定的日志记录
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查特定日志记录 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$product_id = 13917;

// 1. 检查是否有"产品图片获取"日志
echo "=== 检查产品图片获取日志 ===\n";

$image_log = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品图片获取' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 1
", '%' . $product_id . '%'));

if ($image_log) {
    echo "✅ 找到产品图片获取日志\n";
    echo "时间: {$image_log->created_at}\n";
    
    $request_data = json_decode($image_log->request, true);
    if ($request_data) {
        echo "additional_images_count: " . ($request_data['additional_images_count'] ?? '未知') . "\n";
        
        if (isset($request_data['additional_images'])) {
            $additional_images = $request_data['additional_images'];
            $count = count($additional_images);
            echo "实际additional_images数量: {$count}\n";
            
            // 根据代码第287行，如果$original_count == 4，应该执行补足逻辑
            if ($count == 4) {
                echo "✅ 数量为4，应该触发补足逻辑\n";
            } else {
                echo "❌ 数量不是4，不会触发补足逻辑\n";
            }
        }
    }
} else {
    echo "❌ 没有找到产品图片获取日志\n";
    echo "这说明第185行的日志记录没有执行\n";
}

// 2. 检查是否有"图片补足-4张"日志
echo "\n=== 检查图片补足日志 ===\n";

$补足日志 = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '图片补足-4张' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 3
", '%' . $product_id . '%'));

if (!empty($补足日志)) {
    echo "✅ 找到图片补足日志:\n";
    foreach ($补足日志 as $log) {
        echo "时间: {$log->created_at}\n";
        echo "消息: {$log->message}\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "原始数量: " . ($request_data['original_count'] ?? '未知') . "\n";
            echo "最终数量: " . ($request_data['final_count'] ?? '未知') . "\n";
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到图片补足日志\n";
    echo "这说明第293行的日志记录没有执行\n";
}

// 3. 检查是否有"产品图片字段"日志
echo "\n=== 检查产品图片字段日志 ===\n";

$字段日志 = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品图片字段' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 1
", '%' . $product_id . '%'));

if ($字段日志) {
    echo "✅ 找到产品图片字段日志\n";
    echo "时间: {$字段日志->created_at}\n";
    
    $request_data = json_decode($字段日志->request, true);
    if ($request_data) {
        echo "原始图片数量: " . ($request_data['original_images_count'] ?? '未知') . "\n";
        echo "最终图片数量: " . ($request_data['final_images_count'] ?? '未知') . "\n";
        echo "使用占位符: " . ($request_data['placeholder_used'] ? '是' : '否') . "\n";
        echo "满足沃尔玛要求: " . ($request_data['meets_walmart_requirement'] ? '是' : '否') . "\n";
    }
} else {
    echo "❌ 没有找到产品图片字段日志\n";
    echo "这说明第342行的日志记录没有执行\n";
}

// 4. 如果都没有找到，检查映射器是否被调用
echo "\n=== 检查映射器调用情况 ===\n";

$mapping_logs = $wpdb->get_results($wpdb->prepare("
    SELECT action, created_at, status FROM {$logs_table} 
    WHERE request LIKE %s
    AND created_at >= '2025-08-10 15:00:00'
    ORDER BY created_at DESC 
    LIMIT 10
", '%' . $product_id . '%'));

if (!empty($mapping_logs)) {
    echo "找到相关日志:\n";
    foreach ($mapping_logs as $log) {
        echo "{$log->created_at} - {$log->action} ({$log->status})\n";
    }
} else {
    echo "❌ 没有找到任何相关日志\n";
    echo "这说明映射器可能根本没有被调用\n";
}

// 5. 检查占位符配置
echo "\n=== 检查占位符配置 ===\n";

$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
echo "占位符1: " . ($placeholder_1 ?: '(空)') . "\n";

if ($placeholder_1) {
    $is_valid = filter_var($placeholder_1, FILTER_VALIDATE_URL);
    echo "URL有效性: " . ($is_valid ? '有效' : '无效') . "\n";
    
    if (!$is_valid) {
        echo "❌ 占位符URL无效，这会导致第290行条件不成立\n";
    }
}

// 6. 根据日志情况分析问题
echo "\n=== 问题分析 ===\n";

$has_image_log = !is_null($image_log);
$has_补足_log = !empty($补足日志);
$has_字段_log = !is_null($字段日志);

if (!$has_image_log && !$has_补足_log && !$has_字段_log) {
    echo "❌ 关键发现：映射器的图片处理部分完全没有执行\n";
    echo "可能原因：\n";
    echo "1. 映射器的map()方法没有被调用\n";
    echo "2. map()方法在图片处理之前就异常退出\n";
    echo "3. 使用了不同的代码路径（如批量处理的特殊逻辑）\n";
} else if ($has_image_log && !$has_补足_log) {
    echo "❌ 有图片获取日志，但没有补足日志\n";
    echo "说明第287行的条件判断($original_count == 4)没有成立\n";
    echo "需要检查$original_count的实际值\n";
} else if ($has_image_log && $has_补足_log && !$has_字段_log) {
    echo "❌ 有图片获取和补足日志，但没有字段日志\n";
    echo "说明代码在第342行之前就退出了\n";
}

?>
