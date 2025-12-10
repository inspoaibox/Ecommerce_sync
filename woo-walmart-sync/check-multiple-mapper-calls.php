<?php
/**
 * 检查是否存在多次映射器调用
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查是否存在多次映射器调用 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 检查15:20:33到15:20:34之间的所有日志
echo "=== 检查关键时间段的日志 ===\n";

$critical_logs = $wpdb->get_results("
    SELECT id, created_at, action, status, product_id 
    FROM {$logs_table} 
    WHERE created_at BETWEEN '2025-08-10 15:20:33' AND '2025-08-10 15:20:34'
    AND (
        action LIKE '%图片%' OR 
        action LIKE '%映射%' OR 
        action LIKE '%产品%' OR
        action = '产品映射-最终数据结构'
    )
    ORDER BY id ASC
");

if (!empty($critical_logs)) {
    echo "关键时间段的相关日志:\n";
    foreach ($critical_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | 产品ID: {$log->product_id}\n";
    }
} else {
    echo "❌ 没有找到关键时间段的日志\n";
}

// 2. 检查是否有多个"产品图片获取"日志
echo "\n=== 检查产品图片获取的调用次数 ===\n";

$image_get_logs = $wpdb->get_results("
    SELECT id, created_at, action, product_id, request 
    FROM {$logs_table} 
    WHERE action = '产品图片获取' 
    AND created_at BETWEEN '2025-08-10 15:20:30' AND '2025-08-10 15:20:35'
    ORDER BY id ASC
");

if (!empty($image_get_logs)) {
    echo "产品图片获取调用次数: " . count($image_get_logs) . "\n";
    foreach ($image_get_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | 产品ID: {$log->product_id}\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data && isset($request_data['additional_images_count'])) {
            echo "  副图数量: {$request_data['additional_images_count']}\n";
        }
    }
} else {
    echo "❌ 没有找到产品图片获取日志\n";
}

// 3. 检查是否有多个"图片补足-4张"日志
echo "\n=== 检查图片补足的调用次数 ===\n";

$placeholder_logs = $wpdb->get_results("
    SELECT id, created_at, action, product_id, request 
    FROM {$logs_table} 
    WHERE action = '图片补足-4张' 
    AND created_at BETWEEN '2025-08-10 15:20:30' AND '2025-08-10 15:20:35'
    ORDER BY id ASC
");

if (!empty($placeholder_logs)) {
    echo "图片补足调用次数: " . count($placeholder_logs) . "\n";
    foreach ($placeholder_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | 产品ID: {$log->product_id}\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "  原始数量: {$request_data['original_count']}\n";
            echo "  最终数量: {$request_data['final_count']}\n";
        }
    }
} else {
    echo "❌ 没有找到图片补足日志\n";
}

// 4. 检查是否有多个"产品映射-最终数据结构"日志
echo "\n=== 检查最终数据结构的调用次数 ===\n";

$final_data_logs = $wpdb->get_results("
    SELECT id, created_at, action, product_id 
    FROM {$logs_table} 
    WHERE action = '产品映射-最终数据结构' 
    AND created_at BETWEEN '2025-08-10 15:20:30' AND '2025-08-10 15:20:35'
    ORDER BY id ASC
");

if (!empty($final_data_logs)) {
    echo "最终数据结构调用次数: " . count($final_data_logs) . "\n";
    foreach ($final_data_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | 产品ID: {$log->product_id}\n";
    }
} else {
    echo "❌ 没有找到最终数据结构日志\n";
}

// 5. 分析映射器调用模式
echo "\n=== 分析映射器调用模式 ===\n";

if (!empty($image_get_logs) && !empty($placeholder_logs) && !empty($final_data_logs)) {
    $image_count = count($image_get_logs);
    $placeholder_count = count($placeholder_logs);
    $final_count = count($final_data_logs);
    
    echo "调用次数统计:\n";
    echo "- 产品图片获取: {$image_count}次\n";
    echo "- 图片补足: {$placeholder_count}次\n";
    echo "- 最终数据结构: {$final_count}次\n";
    
    if ($image_count == $placeholder_count && $placeholder_count == $final_count) {
        echo "✅ 调用次数一致，每个产品都执行了完整的映射流程\n";
        echo "❌ 但最终数据仍然只有4张图片，说明问题在数据传递过程中\n";
    } else {
        echo "❌ 调用次数不一致！\n";
        echo "可能的问题:\n";
        echo "1. 某些映射器调用没有执行完整流程\n";
        echo "2. 最终使用的数据来自不完整的映射器调用\n";
        echo "3. 批量处理中存在数据覆盖问题\n";
    }
} else {
    echo "❌ 缺少关键日志，无法分析调用模式\n";
}

// 6. 检查批量处理的特殊逻辑
echo "\n=== 检查批量处理日志 ===\n";

$batch_logs = $wpdb->get_results("
    SELECT id, created_at, action, status 
    FROM {$logs_table} 
    WHERE (
        action LIKE '%批量%' OR 
        action LIKE '%Feed%' OR
        action LIKE '%文件上传%'
    )
    AND created_at BETWEEN '2025-08-10 15:20:30' AND '2025-08-10 15:20:40'
    ORDER BY id ASC
");

if (!empty($batch_logs)) {
    echo "批量处理相关日志:\n";
    foreach ($batch_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->status}\n";
    }
} else {
    echo "❌ 没有找到批量处理日志\n";
}

?>
