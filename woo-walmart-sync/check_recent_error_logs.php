<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 检查最近的错误日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 查找包含"批量同步失败"的日志
echo "1. 查找批量同步失败的日志:\n";

$batch_error_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE (message LIKE '%批量同步失败%' OR message LIKE '%Feed提交失败%' OR message LIKE '%未知错误%')
    ORDER BY created_at DESC 
    LIMIT 10
");

if (!empty($batch_error_logs)) {
    foreach ($batch_error_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->request)) {
            echo "请求数据: " . substr($log->request, 0, 200) . "...\n";
        }
        
        if (!empty($log->response)) {
            echo "响应数据: " . substr($log->response, 0, 200) . "...\n";
        }
        
        echo str_repeat('-', 50) . "\n";
    }
} else {
    echo "❌ 没有找到批量同步失败的日志\n";
}

// 2. 查找最近的API请求日志
echo "\n2. 查找最近的API请求日志:\n";

$api_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action LIKE '%API%' OR action LIKE '%请求%'
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($api_logs)) {
    foreach ($api_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->response)) {
            $response_data = json_decode($log->response, true);
            if ($response_data && isset($response_data['error'])) {
                echo "API错误: " . json_encode($response_data['error'], JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        
        echo str_repeat('-', 50) . "\n";
    }
} else {
    echo "❌ 没有找到API请求日志\n";
}

// 3. 查找最近的Feed相关日志
echo "\n3. 查找最近的Feed相关日志:\n";

$feed_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action LIKE '%Feed%' OR message LIKE '%Feed%'
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($feed_logs)) {
    foreach ($feed_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->response)) {
            echo "响应: " . substr($log->response, 0, 300) . "...\n";
        }
        
        echo str_repeat('-', 50) . "\n";
    }
} else {
    echo "❌ 没有找到Feed相关日志\n";
}

// 4. 查找今天的所有错误日志
echo "\n4. 查找今天的所有错误日志:\n";

$today_error_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE DATE(created_at) = CURDATE() 
    AND (status = '错误' OR status = 'error' OR message LIKE '%错误%' OR message LIKE '%失败%')
    ORDER BY created_at DESC 
    LIMIT 10
");

if (!empty($today_error_logs)) {
    foreach ($today_error_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->response)) {
            // 尝试解析JSON响应
            $response_data = json_decode($log->response, true);
            if ($response_data) {
                if (isset($response_data['error'])) {
                    echo "错误详情: " . json_encode($response_data['error'], JSON_UNESCAPED_UNICODE) . "\n";
                }
            } else {
                echo "响应: " . substr($log->response, 0, 200) . "...\n";
            }
        }
        
        echo str_repeat('-', 50) . "\n";
    }
} else {
    echo "❌ 没有找到今天的错误日志\n";
}

// 5. 查找最近修改履行中心ID后的日志
echo "\n5. 查找最近修改履行中心ID后的日志:\n";

$fc_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE (message LIKE '%SELLER_FULFILLED%' OR message LIKE '%履行中心%' OR message LIKE '%fulfillmentCenterID%')
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($fc_logs)) {
    foreach ($fc_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->request)) {
            // 检查请求中的履行中心ID
            if (strpos($log->request, 'fulfillmentCenterID') !== false) {
                echo "包含履行中心ID的请求\n";
            }
        }
        
        echo str_repeat('-', 50) . "\n";
    }
} else {
    echo "❌ 没有找到履行中心相关日志\n";
}

echo "\n=== 检查完成 ===\n";
