<?php
// 检查日志表的实际结构

// 尝试加载WordPress
$wp_load_paths = [
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '../wp-load.php'
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!function_exists('get_option')) {
    die('请通过WordPress环境访问此脚本');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== 检查日志表结构 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'");

if ($table_exists) {
    echo "✅ 日志表存在: $logs_table\n\n";
    
    // 2. 检查表结构
    echo "表结构:\n";
    $columns = $wpdb->get_results("DESCRIBE $logs_table");
    
    foreach ($columns as $column) {
        echo "  {$column->Field}: {$column->Type}";
        if ($column->Null === 'NO') {
            echo " (NOT NULL)";
        }
        if (!empty($column->Default)) {
            echo " DEFAULT {$column->Default}";
        }
        echo "\n";
    }
    
    // 3. 检查记录数量
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
    echo "\n总记录数: $count\n";
    
    // 4. 检查最近的记录
    echo "\n最近5条记录:\n";
    $recent_logs = $wpdb->get_results("SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT 5");
    
    foreach ($recent_logs as $log) {
        echo "  ID: {$log->id}\n";
        echo "  时间: {$log->created_at}\n";
        echo "  操作: {$log->action}\n";
        echo "  状态: {$log->status}\n";
        
        // 检查是否有message字段
        if (property_exists($log, 'message')) {
            echo "  消息: " . substr($log->message, 0, 50) . "...\n";
        } else {
            echo "  ❌ 没有message字段\n";
        }
        echo "\n";
    }
    
    // 5. 测试查询
    echo "测试查询:\n";
    
    // 测试不同的查询方式
    $test_queries = [
        "SELECT COUNT(*) FROM $logs_table WHERE action LIKE '%Feed%'",
        "SELECT COUNT(*) FROM $logs_table WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    ];
    
    foreach ($test_queries as $query) {
        echo "  查询: $query\n";
        $result = $wpdb->get_var($query);
        if ($result !== null) {
            echo "  结果: $result\n";
        } else {
            echo "  错误: " . $wpdb->last_error . "\n";
        }
        echo "\n";
    }
    
} else {
    echo "❌ 日志表不存在: $logs_table\n";
    
    // 检查可能的其他日志表名
    $possible_tables = [
        $wpdb->prefix . 'walmart_sync_logs',
        $wpdb->prefix . 'woo_walmart_logs',
        $wpdb->prefix . 'walmart_logs'
    ];
    
    echo "\n检查其他可能的日志表:\n";
    foreach ($possible_tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($exists) {
            echo "  ✅ 找到表: $table\n";
        } else {
            echo "  ❌ 不存在: $table\n";
        }
    }
}

echo "\n=== 检查完成 ===\n";
?>
