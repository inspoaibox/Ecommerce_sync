<?php
/**
 * 检查Feed状态表
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查Feed状态表 ===\n\n";

global $wpdb;
$feed_table = $wpdb->prefix . 'walmart_feed_status';

echo "表名: {$feed_table}\n";

// 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$feed_table}'") == $feed_table;
echo "表存在: " . ($table_exists ? '是' : '否') . "\n";

if ($table_exists) {
    echo "\n表结构:\n";
    $columns = $wpdb->get_results("DESCRIBE {$feed_table}");
    
    foreach ($columns as $column) {
        echo "  {$column->Field} - {$column->Type} - " . ($column->Null == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
    echo "\n记录数量: " . $wpdb->get_var("SELECT COUNT(*) FROM {$feed_table}") . "\n";
    
    // 显示最近的几条记录
    $recent_records = $wpdb->get_results("SELECT * FROM {$feed_table} ORDER BY submitted_at DESC LIMIT 5");
    
    if (!empty($recent_records)) {
        echo "\n最近的记录:\n";
        foreach ($recent_records as $record) {
            echo "  Feed ID: {$record->feed_id}\n";
            echo "  Product ID: {$record->product_id}\n";
            echo "  Status: {$record->status}\n";
            echo "  Submitted: {$record->submitted_at}\n";
            echo "  ---\n";
        }
    }
} else {
    echo "\n❌ 表不存在，需要创建\n";
    echo "这个表应该在插件激活时自动创建。\n";
    echo "请检查插件的数据库创建逻辑。\n";
}

?>
