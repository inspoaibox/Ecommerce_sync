<?php
/**
 * 检查数据库表结构并修复查询
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 检查数据库表结构 ===\n\n";

global $wpdb;

// 1. 检查日志表结构
echo "1. 检查日志表结构:\n";
$log_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$log_columns = $wpdb->get_results("DESCRIBE {$log_table}");

if ($log_columns) {
    echo "  日志表字段:\n";
    foreach ($log_columns as $column) {
        echo "    - {$column->Field} ({$column->Type})\n";
    }
} else {
    echo "  ❌ 日志表不存在或无法访问\n";
}

// 2. 检查批次表结构
echo "\n2. 检查批次表结构:\n";
$batch_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_columns = $wpdb->get_results("DESCRIBE {$batch_table}");

if ($batch_columns) {
    echo "  批次表字段:\n";
    foreach ($batch_columns as $column) {
        echo "    - {$column->Field} ({$column->Type})\n";
    }
} else {
    echo "  ❌ 批次表不存在或无法访问\n";
}

// 3. 使用正确的字段名查询日志
echo "\n3. 查询最近的日志记录:\n";
if ($log_columns) {
    // 构建正确的查询
    $available_fields = array_column($log_columns, 'Field');
    
    $select_fields = ['action', 'message', 'created_at'];
    if (in_array('level', $available_fields)) {
        $select_fields[] = 'level';
    }
    if (in_array('data', $available_fields)) {
        $select_fields[] = 'data';
    }
    
    $select_sql = implode(', ', $select_fields);
    
    $recent_logs = $wpdb->get_results("
        SELECT {$select_sql}
        FROM {$log_table} 
        WHERE (action LIKE '%API%' OR action LIKE '%Feed%' OR action LIKE '%批量%')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    if ($recent_logs) {
        foreach ($recent_logs as $log) {
            echo "  [{$log->created_at}] {$log->action}\n";
            echo "    消息: {$log->message}\n";
            
            if (isset($log->level)) {
                echo "    级别: {$log->level}\n";
            }
            
            if (isset($log->data) && $log->data) {
                $log_data = json_decode($log->data, true);
                if ($log_data) {
                    if (isset($log_data['feedId'])) {
                        echo "    ✅ Feed ID: {$log_data['feedId']}\n";
                    }
                    if (isset($log_data['feed_id'])) {
                        echo "    ✅ Feed ID: {$log_data['feed_id']}\n";
                    }
                    if (isset($log_data['batch_id'])) {
                        echo "    批次ID: {$log_data['batch_id']}\n";
                    }
                    if (isset($log_data['error'])) {
                        echo "    ❌ 错误: {$log_data['error']}\n";
                    }
                }
            }
            echo "    ---\n";
        }
    } else {
        echo "  没有找到最近2小时的相关日志\n";
    }
}

// 4. 使用正确的字段名查询批次记录
echo "\n4. 查询最近的批次记录:\n";
if ($batch_columns) {
    $available_fields = array_column($batch_columns, 'Field');
    
    $select_fields = ['batch_id', 'status', 'product_count', 'created_at'];
    if (in_array('feed_id', $available_fields)) {
        $select_fields[] = 'feed_id';
    }
    if (in_array('error_message', $available_fields)) {
        $select_fields[] = 'error_message';
    }
    if (in_array('message', $available_fields)) {
        $select_fields[] = 'message';
    }
    
    $select_sql = implode(', ', $select_fields);
    
    $recent_batches = $wpdb->get_results("
        SELECT {$select_sql}
        FROM {$batch_table} 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    if ($recent_batches) {
        foreach ($recent_batches as $batch) {
            echo "  批次: {$batch->batch_id}\n";
            echo "    状态: {$batch->status}\n";
            echo "    产品数量: {$batch->product_count}\n";
            echo "    创建时间: {$batch->created_at}\n";
            
            if (isset($batch->feed_id)) {
                echo "    Feed ID: " . ($batch->feed_id ?: '无') . "\n";
            }
            
            if (isset($batch->error_message)) {
                echo "    错误消息: " . ($batch->error_message ?: '无') . "\n";
            } elseif (isset($batch->message)) {
                echo "    消息: " . ($batch->message ?: '无') . "\n";
            }
            
            // 检查矛盾情况
            if ($batch->status === 'ERROR' && isset($batch->feed_id) && !empty($batch->feed_id)) {
                echo "    ⚠️  关键发现: 状态为ERROR但有Feed ID！这就是问题所在！\n";
            }
            
            echo "    ---\n";
        }
    } else {
        echo "  没有找到最近4小时的批次记录\n";
    }
}

// 5. 检查所有表
echo "\n5. 检查所有相关表:\n";
$tables = $wpdb->get_results("SHOW TABLES LIKE '%walmart%'");
foreach ($tables as $table) {
    $table_name = array_values((array)$table)[0];
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "  {$table_name}: {$count} 条记录\n";
}

// 6. 查找最近的任何批次记录（不限时间）
echo "\n6. 查找最近的任何批次记录:\n";
$any_recent = $wpdb->get_results("
    SELECT batch_id, status, product_count, created_at 
    FROM {$batch_table} 
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($any_recent) {
    echo "  最近的批次记录:\n";
    foreach ($any_recent as $batch) {
        echo "    {$batch->batch_id} - {$batch->status} - {$batch->product_count}个产品 - {$batch->created_at}\n";
    }
} else {
    echo "  ❌ 完全没有批次记录\n";
}

echo "\n=== 检查完成 ===\n";
