<?php
/**
 * 更广泛地查找失败记录
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 更广泛地查找失败记录 ===\n\n";

global $wpdb;

// 1. 查看所有相关表
echo "1. 检查所有相关表:\n";

$tables = [
    'walmart_feeds',
    'walmart_batch_feeds', 
    'woo_walmart_sync_logs',
    'walmart_inventory_sync'
];

foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") == $full_table;
    echo "  {$table}: " . ($exists ? '✅ 存在' : '❌ 不存在') . "\n";
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_table}");
        echo "    总记录数: {$count}\n";
        
        // 查看最近的记录
        $recent = $wpdb->get_results("SELECT * FROM {$full_table} ORDER BY created_at DESC LIMIT 3");
        if (!empty($recent)) {
            echo "    最近3条记录:\n";
            foreach ($recent as $record) {
                $created_at = $record->created_at ?? '未知时间';
                $status = $record->status ?? '未知状态';
                $sku = $record->sku ?? $record->walmart_sku ?? '未知SKU';
                echo "      时间: {$created_at}, 状态: {$status}, SKU: {$sku}\n";
            }
        }
    }
    echo "\n";
}

// 2. 查找包含您提供SKU的任何记录
echo "2. 查找包含您提供SKU的记录:\n";

$sample_skus = ['W3041S00098', 'W1568P332410', 'W1825P332361', 'W714S00833', 'W1191S00043'];

foreach ($sample_skus as $sku) {
    echo "查找SKU: {$sku}\n";
    
    // 在feeds表中查找
    $feeds_table = $wpdb->prefix . 'walmart_feeds';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$feeds_table}'") == $feeds_table) {
        $feed_records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$feeds_table} WHERE sku = %s ORDER BY created_at DESC LIMIT 3",
            $sku
        ));
        
        if (!empty($feed_records)) {
            echo "  在feeds表中找到 " . count($feed_records) . " 条记录:\n";
            foreach ($feed_records as $record) {
                echo "    时间: {$record->created_at}, 状态: {$record->status}, Feed: {$record->feed_id}\n";
            }
        } else {
            echo "  在feeds表中未找到记录\n";
        }
    }
    
    // 在日志表中查找
    $logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'") == $logs_table) {
        $log_records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$logs_table} WHERE request LIKE %s OR response LIKE %s ORDER BY created_at DESC LIMIT 2",
            '%' . $sku . '%',
            '%' . $sku . '%'
        ));
        
        if (!empty($log_records)) {
            echo "  在日志表中找到 " . count($log_records) . " 条记录:\n";
            foreach ($log_records as $record) {
                echo "    时间: {$record->created_at}, 操作: {$record->action}, 状态: {$record->status}\n";
            }
        } else {
            echo "  在日志表中未找到记录\n";
        }
    }
    echo "\n";
}

// 3. 查找2025-08-25的所有记录
echo "3. 查找2025-08-25的所有相关记录:\n";

$feeds_table = $wpdb->prefix . 'walmart_feeds';
if ($wpdb->get_var("SHOW TABLES LIKE '{$feeds_table}'") == $feeds_table) {
    
    $daily_records = $wpdb->get_results("
        SELECT sku, status, feed_id, created_at, COUNT(*) as count
        FROM {$feeds_table} 
        WHERE DATE(created_at) = '2025-08-25'
        GROUP BY sku, status, feed_id
        ORDER BY created_at DESC
        LIMIT 20
    ");
    
    if (!empty($daily_records)) {
        echo "2025-08-25的Feed记录 (前20条):\n";
        foreach ($daily_records as $record) {
            echo "  SKU: {$record->sku}, 状态: {$record->status}, 数量: {$record->count}, 时间: {$record->created_at}\n";
        }
    } else {
        echo "❌ 2025-08-25没有Feed记录\n";
    }
}

// 4. 查找批量同步的日志
echo "\n4. 查找批量同步的日志:\n";

$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
if ($wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'") == $logs_table) {
    
    $batch_logs = $wpdb->get_results("
        SELECT * FROM {$logs_table} 
        WHERE DATE(created_at) = '2025-08-25'
        AND (action LIKE '%批量%' OR action LIKE '%batch%' OR action LIKE '%Feed%')
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    if (!empty($batch_logs)) {
        echo "找到批量相关日志:\n";
        foreach ($batch_logs as $log) {
            echo "  时间: {$log->created_at}\n";
            echo "  操作: {$log->action}\n";
            echo "  状态: {$log->status}\n";
            
            // 检查是否包含失败信息
            if (!empty($log->response)) {
                $response_data = json_decode($log->response, true);
                if ($response_data) {
                    if (isset($response_data['itemsFailed'])) {
                        echo "  失败数量: {$response_data['itemsFailed']}\n";
                    }
                    if (isset($response_data['itemsSucceeded'])) {
                        echo "  成功数量: {$response_data['itemsSucceeded']}\n";
                    }
                }
            }
            echo "  ---\n";
        }
    } else {
        echo "❌ 没有找到批量相关日志\n";
    }
}

// 5. 检查是否有其他可能存储失败信息的地方
echo "\n5. 检查WordPress选项表:\n";

$batch_options = $wpdb->get_results("
    SELECT option_name, option_value 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '%walmart%batch%' 
    OR option_name LIKE '%walmart%failed%'
    OR option_name LIKE '%walmart%queue%'
");

if (!empty($batch_options)) {
    echo "找到相关选项:\n";
    foreach ($batch_options as $option) {
        echo "  {$option->option_name}: " . substr($option->option_value, 0, 100) . "...\n";
    }
} else {
    echo "❌ 没有找到相关选项\n";
}

echo "\n=== 结论 ===\n";
echo "如果数据库中没有找到失败记录，可能的原因:\n";
echo "1. 失败信息存储在其他地方（如临时文件、缓存等）\n";
echo "2. 队列管理页面从API实时获取数据\n";
echo "3. 失败信息存储在不同的表结构中\n";
echo "4. 数据可能已经被清理或归档\n";
echo "\n建议直接检查队列管理页面的源代码来确定数据来源。\n";

?>
