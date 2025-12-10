<?php
/**
 * 数据库升级脚本：为 walmart_categories 表添加 market 字段
 * 
 * 用途：支持多市场分类数据区分
 */

require_once '../../../wp-load.php';

echo "=== 升级 walmart_categories 表结构 ===\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'walmart_categories';

// 1. 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if (!$table_exists) {
    echo "❌ 表 {$table_name} 不存在，请先激活插件创建表\n";
    exit;
}

echo "✅ 表 {$table_name} 存在\n\n";

// 2. 检查 market 字段是否已存在
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'market'");

if (!empty($column_exists)) {
    echo "✅ market 字段已存在，无需升级\n";
    
    // 显示字段信息
    echo "\n当前字段信息:\n";
    foreach ($column_exists as $col) {
        echo "  - 字段名: {$col->Field}\n";
        echo "  - 类型: {$col->Type}\n";
        echo "  - 默认值: {$col->Default}\n";
    }
    exit;
}

echo "⚠️ market 字段不存在，开始添加...\n\n";

// 3. 添加 market 字段
$sql = "ALTER TABLE {$table_name} 
        ADD COLUMN market varchar(10) DEFAULT 'US' AFTER attributes_data,
        ADD INDEX market (market)";

$result = $wpdb->query($sql);

if ($result === false) {
    echo "❌ 添加 market 字段失败\n";
    echo "错误信息: " . $wpdb->last_error . "\n";
    exit;
}

echo "✅ 成功添加 market 字段\n\n";

// 4. 修改 UNIQUE KEY 从 category_id 改为 (category_id, market)
echo "正在修改唯一索引...\n";

// 先删除旧的唯一索引
$wpdb->query("ALTER TABLE {$table_name} DROP INDEX category_id");

// 添加新的复合唯一索引
$wpdb->query("ALTER TABLE {$table_name} ADD UNIQUE KEY category_market (category_id, market)");

echo "✅ 成功修改唯一索引为 (category_id, market)\n\n";

// 5. 验证升级结果
echo "=== 验证升级结果 ===\n\n";

$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
echo "表字段列表:\n";
foreach ($columns as $col) {
    $marker = ($col->Field === 'market') ? '✅ ' : '   ';
    echo "{$marker}{$col->Field} ({$col->Type})\n";
}

echo "\n";

$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
echo "表索引列表:\n";
$index_names = [];
foreach ($indexes as $idx) {
    if (!in_array($idx->Key_name, $index_names)) {
        $index_names[] = $idx->Key_name;
        $marker = ($idx->Key_name === 'category_market' || $idx->Key_name === 'market') ? '✅ ' : '   ';
        echo "{$marker}{$idx->Key_name}\n";
    }
}

echo "\n";

// 6. 统计现有数据
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$us_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE market = 'US'");

echo "=== 数据统计 ===\n";
echo "总记录数: {$total_count}\n";
echo "US市场记录数: {$us_count}\n";
echo "\n";

if ($total_count > 0) {
    echo "⚠️ 注意：现有的 {$total_count} 条记录已自动设置为 US 市场\n";
    echo "如果您需要为其他市场（CA/MX/CL）获取分类数据，请：\n";
    echo "1. 在设置页面切换到对应市场\n";
    echo "2. 点击"从沃尔玛更新分类列表"按钮\n";
}

echo "\n✅ 升级完成！\n";

