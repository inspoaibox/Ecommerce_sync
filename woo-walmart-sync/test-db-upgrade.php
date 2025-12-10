<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>测试数据库升级机制</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .button { 
            background-color: #4CAF50; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .button:hover { background-color: #45a049; }
        .button-secondary { background-color: #008CBA; }
        .button-secondary:hover { background-color: #007399; }
    </style>
</head>
<body>
    <h1>🔧 数据库升级机制测试</h1>
    <p>测试插件的自动数据库升级功能</p>
    <hr>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'walmart_categories';

echo "<h2>1️⃣ 当前状态检查</h2>";

// 检查数据库版本
$current_db_version = get_option('woo_walmart_sync_db_version', '未设置');
$plugin_db_version = defined('WOO_WALMART_SYNC_DB_VERSION') ? WOO_WALMART_SYNC_DB_VERSION : '未定义';

echo "<table>";
echo "<tr><th>项目</th><th>值</th></tr>";
echo "<tr><td>插件定义的数据库版本</td><td class='info'>{$plugin_db_version}</td></tr>";
echo "<tr><td>数据库中保存的版本</td><td class='info'>{$current_db_version}</td></tr>";
echo "</table>";

// 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if (!$table_exists) {
    echo "<p class='error'>❌ 表 {$table_name} 不存在</p>";
    echo "<p>请先激活插件以创建数据库表</p>";
    exit;
}

echo "<p class='success'>✅ 表 {$table_name} 存在</p>";

// 检查 market 字段
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
$market_field_exists = false;

echo "<h3>表字段列表:</h3>";
echo "<table>";
echo "<tr><th>字段名</th><th>类型</th><th>默认值</th><th>键</th></tr>";
foreach ($columns as $col) {
    if ($col->Field === 'market') {
        $market_field_exists = true;
        echo "<tr style='background-color: #d4edda;'>";
    } else {
        echo "<tr>";
    }
    echo "<td>{$col->Field}</td>";
    echo "<td>{$col->Type}</td>";
    echo "<td>{$col->Default}</td>";
    echo "<td>{$col->Key}</td>";
    echo "</tr>";
}
echo "</table>";

if ($market_field_exists) {
    echo "<p class='success'>✅ market 字段已存在</p>";
} else {
    echo "<p class='warning'>⚠️ market 字段不存在</p>";
}

// 检查索引
echo "<h3>表索引列表:</h3>";
$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
$index_list = [];
foreach ($indexes as $idx) {
    if (!isset($index_list[$idx->Key_name])) {
        $index_list[$idx->Key_name] = [];
    }
    $index_list[$idx->Key_name][] = $idx->Column_name;
}

echo "<table>";
echo "<tr><th>索引名</th><th>字段</th></tr>";
foreach ($index_list as $index_name => $columns) {
    $is_market_index = ($index_name === 'market' || $index_name === 'category_market');
    if ($is_market_index) {
        echo "<tr style='background-color: #d4edda;'>";
    } else {
        echo "<tr>";
    }
    echo "<td>{$index_name}</td>";
    echo "<td>" . implode(', ', $columns) . "</td>";
    echo "</tr>";
}
echo "</table>";

// 数据统计
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "<h3>数据统计:</h3>";
echo "<p>总记录数: <strong>{$total_count}</strong></p>";

if ($market_field_exists && $total_count > 0) {
    $market_stats = $wpdb->get_results("
        SELECT market, COUNT(*) as count 
        FROM {$table_name} 
        GROUP BY market
    ");
    
    echo "<table>";
    echo "<tr><th>市场</th><th>记录数</th></tr>";
    foreach ($market_stats as $stat) {
        echo "<tr><td>{$stat->market}</td><td>{$stat->count}</td></tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>2️⃣ 操作选项</h2>";

if (!$market_field_exists) {
    echo "<p class='warning'>⚠️ 需要升级数据库以添加 market 字段</p>";
    echo "<a href='?action=trigger_upgrade' class='button'>🔧 触发数据库升级</a>";
} else {
    echo "<p class='success'>✅ 数据库结构已是最新版本</p>";
}

echo "<a href='?' class='button button-secondary'>🔄 刷新页面</a>";

// 处理升级操作
if (isset($_GET['action']) && $_GET['action'] === 'trigger_upgrade') {
    echo "<hr>";
    echo "<h2>3️⃣ 执行升级</h2>";
    
    // 临时降低版本号以触发升级
    update_option('woo_walmart_sync_db_version', '1.0.0');
    
    echo "<p>已将数据库版本设置为 1.0.0</p>";
    echo "<p>正在调用升级函数...</p>";
    
    // 调用升级函数
    if (function_exists('woo_walmart_sync_upgrade_database')) {
        woo_walmart_sync_upgrade_database('1.0.0');
        echo "<p class='success'>✅ 升级函数执行完成</p>";
    } else {
        echo "<p class='error'>❌ 升级函数不存在</p>";
    }
    
    echo "<p><a href='?' class='button'>查看升级结果</a></p>";
}

?>

</body>
</html>

