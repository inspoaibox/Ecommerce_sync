<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 检查日志表结构 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'");

if ($table_exists) {
    echo "✅ 日志表存在: {$logs_table}\n\n";
    
    // 2. 检查表结构
    echo "表结构:\n";
    $table_structure = $wpdb->get_results("DESCRIBE {$logs_table}");
    foreach ($table_structure as $column) {
        echo "  {$column->Field}: {$column->Type} ({$column->Null}, {$column->Key}, {$column->Default})\n";
    }
    
    // 3. 检查记录数量
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
    echo "\n总记录数: {$count}\n";
    
    // 4. 检查最近的记录（使用正确的字段名）
    echo "\n最近5条记录:\n";
    $recent_logs = $wpdb->get_results("SELECT * FROM {$logs_table} ORDER BY created_at DESC LIMIT 5");
    
    foreach ($recent_logs as $log) {
        echo "ID: {$log->id}\n";
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        
        // 检查所有可能的消息字段
        $message_fields = ['message', 'log_message', 'description', 'error_message'];
        foreach ($message_fields as $field) {
            if (property_exists($log, $field) && !empty($log->$field)) {
                echo "消息({$field}): " . substr($log->$field, 0, 100) . "...\n";
                break;
            }
        }
        
        if (!empty($log->response)) {
            echo "响应: " . substr($log->response, 0, 100) . "...\n";
        }
        
        echo str_repeat('-', 50) . "\n";
    }
    
} else {
    echo "❌ 日志表不存在: {$logs_table}\n";
}

// 5. 查找最近的Channel Type错误
echo "\n=== 查找Channel Type错误 ===\n";

if ($table_exists) {
    $channel_type_errors = $wpdb->get_results("
        SELECT * FROM {$logs_table} 
        WHERE response LIKE '%WM_CONSUMER.CHANNEL.TYPE%'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    if (!empty($channel_type_errors)) {
        foreach ($channel_type_errors as $log) {
            echo "时间: {$log->created_at}\n";
            echo "操作: {$log->action}\n";
            echo "状态: {$log->status}\n";
            
            if (!empty($log->response)) {
                $response_data = json_decode($log->response, true);
                if ($response_data && isset($response_data['error'])) {
                    echo "错误详情: " . json_encode($response_data['error'], JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            
            echo str_repeat('-', 50) . "\n";
        }
    } else {
        echo "❌ 没有找到Channel Type错误\n";
    }
}

// 6. 查找最近的API错误
echo "\n=== 查找最近的API错误 ===\n";

if ($table_exists) {
    $api_errors = $wpdb->get_results("
        SELECT * FROM {$logs_table} 
        WHERE status IN ('Bad Request', 'Forbidden', 'Not Found', 'error', '错误')
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    if (!empty($api_errors)) {
        foreach ($api_errors as $log) {
            echo "时间: {$log->created_at}\n";
            echo "操作: {$log->action}\n";
            echo "状态: {$log->status}\n";
            
            if (!empty($log->response)) {
                $response_data = json_decode($log->response, true);
                if ($response_data) {
                    if (isset($response_data['error'])) {
                        echo "API错误: " . json_encode($response_data['error'], JSON_UNESCAPED_UNICODE) . "\n";
                    } elseif (isset($response_data['errors'])) {
                        echo "API错误: " . json_encode($response_data['errors'], JSON_UNESCAPED_UNICODE) . "\n";
                    }
                } else {
                    echo "响应: " . substr($log->response, 0, 200) . "...\n";
                }
            }
            
            echo str_repeat('-', 50) . "\n";
        }
    } else {
        echo "❌ 没有找到API错误\n";
    }
}

echo "\n=== 检查完成 ===\n";
