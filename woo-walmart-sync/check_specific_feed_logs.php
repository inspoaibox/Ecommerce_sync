<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 检查特定Feed ID的日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找Feed ID为18584710A6BC55D08EC799638E8C0C70@AXkBCgA的相关日志
$feed_id = '18584710A6BC55D08EC799638E8C0C70@AXkBCgA';
echo "查找Feed ID: {$feed_id}\n\n";

$feed_logs = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $logs_table 
    WHERE request LIKE %s OR response LIKE %s
    ORDER BY created_at DESC 
    LIMIT 10
", '%' . $feed_id . '%', '%' . $feed_id . '%'));

echo "找到 " . count($feed_logs) . " 条相关日志:\n\n";

foreach ($feed_logs as $log) {
    echo "=== 日志 #{$log->id} ===\n";
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "状态: {$log->status}\n";
    
    // 检查是否包含实际发送的数据
    if (strpos($log->request, 'MPItemFeedHeader') !== false) {
        echo "✅ 包含MPItemFeedHeader数据\n";
        $request_data = json_decode($log->request, true);
        if ($request_data && isset($request_data['MPItemFeedHeader'])) {
            $header = $request_data['MPItemFeedHeader'];
            echo "实际发送的Header:\n";
            echo json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // 检查问题字段
            if (isset($header['subset'])) {
                echo "⚠️ 发现subset字段: " . json_encode($header['subset']) . "\n";
            } else {
                echo "✅ 没有subset字段\n";
            }
            
            if (!isset($header['businessUnit'])) {
                echo "⚠️ 缺少businessUnit字段\n";
            } else {
                echo "✅ businessUnit: " . $header['businessUnit'] . "\n";
            }
            
            if (isset($header['version'])) {
                echo "✅ version: " . $header['version'] . "\n";
            } else {
                echo "⚠️ 缺少version字段\n";
            }
            
            if (isset($header['locale'])) {
                echo "✅ locale: " . $header['locale'] . "\n";
            } else {
                echo "⚠️ 缺少locale字段\n";
            }
        }
    }
    
    // 检查是否是文件上传相关日志
    if ($log->action === '文件上传方法-开始') {
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "文件上传信息:\n";
            echo "  端点: " . ($request_data['endpoint'] ?? '未知') . "\n";
            echo "  文件名: " . ($request_data['filename'] ?? '未知') . "\n";
            echo "  数据大小: " . ($request_data['data_size'] ?? '未知') . " 字节\n";
        }
    }
    
    echo "\n";
}

// 如果没有找到相关日志，查找时间范围内的所有批量同步日志
if (empty($feed_logs)) {
    echo "没有找到该Feed ID的直接日志，查找最近的批量同步日志:\n\n";
    
    $recent_batch_logs = $wpdb->get_results("
        SELECT * FROM $logs_table 
        WHERE (action LIKE '%批量%' OR action LIKE '%文件上传%' OR action LIKE '%Feed%')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    foreach ($recent_batch_logs as $log) {
        echo "时间: {$log->created_at} - {$log->action} ({$log->status})\n";
        
        // 检查是否包含MPItemFeedHeader
        if (strpos($log->request, 'MPItemFeedHeader') !== false) {
            echo "  ✅ 包含MPItemFeedHeader\n";
            $request_data = json_decode($log->request, true);
            if ($request_data && isset($request_data['MPItemFeedHeader'])) {
                $header = $request_data['MPItemFeedHeader'];
                echo "  Header: " . json_encode($header, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        
        // 检查是否包含Feed ID
        if (strpos($log->request, 'feedId') !== false || strpos($log->response, 'feedId') !== false) {
            echo "  📋 包含Feed ID信息\n";
            
            // 尝试提取Feed ID
            if (preg_match('/feedId["\']?\s*:\s*["\']?([^"\'}\s,]+)/', $log->request . $log->response, $matches)) {
                echo "  Feed ID: {$matches[1]}\n";
            }
        }
        
        echo "\n";
    }
}

// 检查是否有其他批量同步方法被调用
echo "\n=== 检查其他可能的批量同步入口 ===\n";

// 查找所有可能的批量同步相关日志
$all_batch_logs = $wpdb->get_results("
    SELECT DISTINCT action FROM $logs_table 
    WHERE action LIKE '%批量%' OR action LIKE '%batch%' OR action LIKE '%bulk%'
    ORDER BY action
");

echo "发现的批量同步操作类型:\n";
foreach ($all_batch_logs as $log) {
    echo "  - {$log->action}\n";
}

echo "\n=== 检查完成 ===\n";
echo "如果找到了实际发送的MPItemFeedHeader数据，可以确定问题的具体原因\n";
?>
