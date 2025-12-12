<?php
/**
 * 检查walmart_feeds表的实际结构
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查walmart_feeds表的实际结构 ===\n\n";

global $wpdb;
$feeds_table = $wpdb->prefix . 'walmart_feeds';

// 1. 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$feeds_table}'") == $feeds_table;
echo "表存在: " . ($table_exists ? '是' : '否') . "\n\n";

if ($table_exists) {
    // 2. 显示表结构
    echo "=== 实际表结构 ===\n";
    $columns = $wpdb->get_results("DESCRIBE {$feeds_table}");
    
    foreach ($columns as $column) {
        echo "字段: {$column->Field}\n";
        echo "  类型: {$column->Type}\n";
        echo "  NULL: {$column->Null}\n";
        echo "  默认值: " . ($column->Default ?? 'NULL') . "\n";
        echo "  额外: {$column->Extra}\n";
        echo "---\n";
    }
    
    // 3. 检查我在批量同步中使用的字段
    echo "\n=== 检查批量同步使用的字段 ===\n";
    $column_names = array_column($columns, 'Field');
    
    $used_fields = [
        'feed_id' => '必需',
        'product_id' => '必需', 
        'sku' => '我添加的？',
        'upc' => '我添加的？',
        'status' => '必需',
        'submitted_at' => '我添加的？',
        'created_at' => '我添加的？',
        'updated_at' => '我添加的？',
        'api_response' => '我添加的？'
    ];
    
    foreach ($used_fields as $field => $note) {
        $exists = in_array($field, $column_names);
        echo "{$field}: " . ($exists ? '✅ 存在' : '❌ 不存在') . " ({$note})\n";
    }
    
    // 4. 显示现有记录的示例
    echo "\n=== 现有记录示例 ===\n";
    $sample_records = $wpdb->get_results("SELECT * FROM {$feeds_table} ORDER BY id DESC LIMIT 3");
    
    if (!empty($sample_records)) {
        foreach ($sample_records as $record) {
            echo "记录ID: {$record->id}\n";
            foreach ($record as $field => $value) {
                if ($field != 'id') {
                    echo "  {$field}: " . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) . "\n";
                }
            }
            echo "---\n";
        }
    } else {
        echo "没有现有记录\n";
    }
    
} else {
    echo "❌ 表不存在，无法检查结构\n";
}

// 5. 检查单个产品同步使用的字段
echo "\n=== 检查单个产品同步的record_feed_status方法 ===\n";

// 查看单个产品同步实际使用的字段
$sync_file = plugin_dir_path(__FILE__) . 'includes/class-product-sync.php';
if (file_exists($sync_file)) {
    $content = file_get_contents($sync_file);
    
    // 查找record_feed_status方法
    if (preg_match('/private function record_feed_status.*?\{(.*?)\}/s', $content, $matches)) {
        echo "找到record_feed_status方法\n";
        
        // 查找wpdb->insert调用
        if (preg_match('/\$wpdb->insert\s*\(\s*\$feeds_table,\s*\[(.*?)\]/s', $matches[1], $insert_match)) {
            echo "单个产品同步使用的字段:\n";
            echo $insert_match[1] . "\n";
        }
    } else {
        echo "❌ 没有找到record_feed_status方法\n";
    }
} else {
    echo "❌ 产品同步文件不存在\n";
}

?>
