<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>升级 walmart_categories 表</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>升级 walmart_categories 表结构</h1>
    <p>添加 market 字段以支持多市场分类数据</p>
    <hr>

<?php
// 开启错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查wp-load.php路径
$wp_load_path = __DIR__ . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    echo "<p class='error'>❌ 找不到 wp-load.php 文件</p>";
    echo "<p>查找路径: " . $wp_load_path . "</p>";
    exit;
}

try {
    require_once $wp_load_path;
} catch (Exception $e) {
    echo "<p class='error'>❌ 加载 WordPress 失败</p>";
    echo "<p>错误信息: " . $e->getMessage() . "</p>";
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'walmart_categories';

// 1. 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if (!$table_exists) {
    echo "<p class='error'>❌ 表 {$table_name} 不存在，请先激活插件创建表</p>";
    exit;
}

echo "<p class='success'>✅ 表 {$table_name} 存在</p>";

// 2. 检查 market 字段是否已存在
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'market'");

if (!empty($column_exists)) {
    echo "<p class='success'>✅ market 字段已存在，无需升级</p>";
    
    echo "<h3>当前字段信息:</h3>";
    echo "<pre>";
    foreach ($column_exists as $col) {
        echo "字段名: {$col->Field}\n";
        echo "类型: {$col->Type}\n";
        echo "默认值: {$col->Default}\n";
    }
    echo "</pre>";
    exit;
}

echo "<p class='warning'>⚠️ market 字段不存在，开始添加...</p>";

// 3. 添加 market 字段
$sql = "ALTER TABLE {$table_name} 
        ADD COLUMN market varchar(10) DEFAULT 'US' AFTER attributes_data,
        ADD INDEX market (market)";

$result = $wpdb->query($sql);

if ($result === false) {
    echo "<p class='error'>❌ 添加 market 字段失败</p>";
    echo "<p class='error'>错误信息: " . $wpdb->last_error . "</p>";
    exit;
}

echo "<p class='success'>✅ 成功添加 market 字段</p>";

// 4. 修改 UNIQUE KEY
echo "<p>正在修改唯一索引...</p>";

$wpdb->query("ALTER TABLE {$table_name} DROP INDEX category_id");
$wpdb->query("ALTER TABLE {$table_name} ADD UNIQUE KEY category_market (category_id, market)");

echo "<p class='success'>✅ 成功修改唯一索引为 (category_id, market)</p>";

// 5. 验证结果
echo "<h3>验证升级结果</h3>";

$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
echo "<h4>表字段列表:</h4><pre>";
foreach ($columns as $col) {
    $marker = ($col->Field === 'market') ? '✅ ' : '   ';
    echo "{$marker}{$col->Field} ({$col->Type})\n";
}
echo "</pre>";

$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
echo "<h4>表索引列表:</h4><pre>";
$index_names = [];
foreach ($indexes as $idx) {
    if (!in_array($idx->Key_name, $index_names)) {
        $index_names[] = $idx->Key_name;
        $marker = ($idx->Key_name === 'category_market' || $idx->Key_name === 'market') ? '✅ ' : '   ';
        echo "{$marker}{$idx->Key_name}\n";
    }
}
echo "</pre>";

// 6. 数据统计
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$us_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE market = 'US'");

echo "<h3>数据统计</h3>";
echo "<p>总记录数: <strong>{$total_count}</strong></p>";
echo "<p>US市场记录数: <strong>{$us_count}</strong></p>";

if ($total_count > 0) {
    echo "<div class='warning'>";
    echo "<p>⚠️ 注意：现有的 {$total_count} 条记录已自动设置为 US 市场</p>";
    echo "<p>如果您需要为其他市场（CA/MX/CL）获取分类数据，请：</p>";
    echo "<ol>";
    echo "<li>在设置页面切换到对应市场</li>";
    echo "<li>点击 \"从沃尔玛更新分类列表\" 按钮</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<h2 class='success'>✅ 升级完成！</h2>";
?>

</body>
</html>

