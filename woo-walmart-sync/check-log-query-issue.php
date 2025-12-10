<?php
/**
 * 检查日志查询问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查日志查询问题 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 检查刚写入的日志
echo "=== 检查刚写入的日志 ===\n";

$latest_log = $wpdb->get_row("
    SELECT * FROM {$logs_table} 
    WHERE id = 311705
");

if ($latest_log) {
    echo "✅ 找到ID 311705的日志:\n";
    echo "ID: {$latest_log->id}\n";
    echo "时间: {$latest_log->created_at}\n";
    echo "操作: {$latest_log->action}\n";
    echo "状态: {$latest_log->status}\n";
    echo "产品ID: {$latest_log->product_id}\n";
} else {
    echo "❌ 没有找到ID 311705的日志\n";
}

// 2. 查询最近的所有日志（不限制条件）
echo "\n=== 查询最近的所有日志 ===\n";

$all_recent_logs = $wpdb->get_results("
    SELECT id, action, status, created_at, product_id 
    FROM {$logs_table} 
    ORDER BY id DESC 
    LIMIT 10
");

if (!empty($all_recent_logs)) {
    echo "最近10条日志:\n";
    foreach ($all_recent_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->status} | 产品ID: {$log->product_id}\n";
    }
} else {
    echo "❌ 没有找到任何日志\n";
}

// 3. 检查产品13917的日志（使用正确的查询）
echo "\n=== 检查产品13917的日志 ===\n";

$product_logs = $wpdb->get_results($wpdb->prepare("
    SELECT id, action, status, created_at, product_id 
    FROM {$logs_table} 
    WHERE product_id = %d 
    ORDER BY id DESC 
    LIMIT 10
", 13917));

if (!empty($product_logs)) {
    echo "产品13917的日志:\n";
    foreach ($product_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->status}\n";
    }
} else {
    echo "❌ 没有找到产品13917的日志\n";
}

// 4. 查找包含"图片"关键词的日志
echo "\n=== 查找图片相关日志 ===\n";

$image_logs = $wpdb->get_results("
    SELECT id, action, status, created_at, product_id 
    FROM {$logs_table} 
    WHERE action LIKE '%图片%' 
    ORDER BY id DESC 
    LIMIT 10
");

if (!empty($image_logs)) {
    echo "图片相关日志:\n";
    foreach ($image_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->status} | 产品ID: {$log->product_id}\n";
    }
} else {
    echo "❌ 没有找到图片相关日志\n";
}

// 5. 查找今天的日志
echo "\n=== 查找今天的日志 ===\n";

$today_logs = $wpdb->get_results("
    SELECT id, action, status, created_at, product_id 
    FROM {$logs_table} 
    WHERE DATE(created_at) = CURDATE() 
    ORDER BY id DESC 
    LIMIT 20
");

if (!empty($today_logs)) {
    echo "今天的日志 (" . count($today_logs) . "条):\n";
    foreach ($today_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->status} | 产品ID: {$log->product_id}\n";
    }
} else {
    echo "❌ 没有找到今天的日志\n";
}

// 6. 检查表的总记录数
echo "\n=== 检查表的总记录数 ===\n";

$total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
echo "日志表总记录数: {$total_count}\n";

if ($total_count > 0) {
    $max_id = $wpdb->get_var("SELECT MAX(id) FROM {$logs_table}");
    $min_id = $wpdb->get_var("SELECT MIN(id) FROM {$logs_table}");
    echo "ID范围: {$min_id} - {$max_id}\n";
    
    // 检查最新的几条记录
    $latest_records = $wpdb->get_results("
        SELECT id, action, status, created_at, product_id 
        FROM {$logs_table} 
        WHERE id >= {$max_id} - 5
        ORDER BY id DESC
    ");
    
    echo "最新的几条记录:\n";
    foreach ($latest_records as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->status} | 产品ID: {$log->product_id}\n";
    }
}

// 7. 特别查找"产品图片获取"日志
echo "\n=== 查找产品图片获取日志 ===\n";

$image_get_logs = $wpdb->get_results("
    SELECT id, action, status, created_at, product_id, request 
    FROM {$logs_table} 
    WHERE action = '产品图片获取' 
    ORDER BY id DESC 
    LIMIT 5
");

if (!empty($image_get_logs)) {
    echo "产品图片获取日志:\n";
    foreach ($image_get_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | 产品ID: {$log->product_id}\n";
        
        // 解析请求数据
        $request_data = json_decode($log->request, true);
        if ($request_data && isset($request_data['additional_images_count'])) {
            echo "  副图数量: {$request_data['additional_images_count']}\n";
        }
    }
} else {
    echo "❌ 没有找到产品图片获取日志\n";
}

?>
