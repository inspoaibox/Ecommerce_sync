<?php
/**
 * 查看最近的批量同步日志
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 最近的批量同步日志 ===\n\n";

global $wpdb;
$logs = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs 
    WHERE action LIKE '%批量%' OR action LIKE '%Feed%' OR action LIKE '%文件上传%'
    ORDER BY created_at DESC 
    LIMIT 15
");

if (empty($logs)) {
    echo "❌ 没有找到相关日志\n";
    exit;
}

foreach ($logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "级别: {$log->level}\n";
    echo "消息: {$log->message}\n";
    
    if (!empty($log->context)) {
        $context = json_decode($log->context, true);
        if ($context) {
            echo "上下文:\n";
            echo json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    if (!empty($log->details)) {
        echo "详情: " . substr($log->details, 0, 1000) . "\n";
    }
    
    echo "---\n\n";
}

echo "=== 日志查看完成 ===\n";

