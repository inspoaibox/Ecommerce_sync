<?php
/**
 * 强制创建库存同步表
 * 调试表创建问题
 */

// 加载WordPress环境
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php', 
    __DIR__ . '/../../../../../wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('无法找到WordPress。');
}

// 检查权限
if (!current_user_can('manage_options')) {
    wp_die(__('您没有权限执行此操作。'));
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>强制创建库存同步表</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>

<h1>强制创建库存同步表</h1>

<?php
global $wpdb;

$inventory_table = $wpdb->prefix . 'walmart_inventory_sync';

echo "<h2>当前状态检查</h2>";

// 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;
echo "<p>表是否存在: " . ($table_exists ? '<span class="success">是</span>' : '<span class="error">否</span>') . "</p>";

// 显示数据库信息
echo "<p><strong>数据库名:</strong> " . DB_NAME . "</p>";
echo "<p><strong>表前缀:</strong> " . $wpdb->prefix . "</p>";
echo "<p><strong>完整表名:</strong> " . $inventory_table . "</p>";

// 检查数据库连接
if ($wpdb->last_error) {
    echo "<p class='error'>数据库错误: " . $wpdb->last_error . "</p>";
}

echo "<h2>手动创建表</h2>";

if (isset($_POST['create_table'])) {
    echo "<h3>开始创建表...</h3>";
    
    // 获取字符集
    $charset_collate = $wpdb->get_charset_collate();
    echo "<p><strong>字符集:</strong> " . esc_html($charset_collate) . "</p>";
    
    // 构建SQL
    $sql = "CREATE TABLE IF NOT EXISTS {$inventory_table} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id bigint(20) UNSIGNED NOT NULL,
        walmart_sku varchar(255) NOT NULL,
        status varchar(20) NOT NULL,
        quantity int(11) NOT NULL DEFAULT 0,
        retry_count int(11) NOT NULL DEFAULT 0,
        last_sync_time datetime NOT NULL,
        created_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        response_data longtext,
        PRIMARY KEY (id),
        UNIQUE KEY product_sku (product_id, walmart_sku),
        KEY status (status),
        KEY last_sync_time (last_sync_time)
    ) {$charset_collate};";
    
    echo "<h4>SQL语句:</h4>";
    echo "<div class='code'>" . esc_html($sql) . "</div>";
    
    // 方法1: 使用dbDelta
    echo "<h4>方法1: 使用dbDelta</h4>";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    echo "<p><strong>dbDelta结果:</strong></p>";
    echo "<div class='code'>" . print_r($result, true) . "</div>";
    
    // 检查是否创建成功
    $table_exists_after_dbdelta = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;
    echo "<p>dbDelta后表是否存在: " . ($table_exists_after_dbdelta ? '<span class="success">是</span>' : '<span class="error">否</span>') . "</p>";
    
    if (!$table_exists_after_dbdelta) {
        // 方法2: 直接执行SQL
        echo "<h4>方法2: 直接执行SQL</h4>";
        $direct_result = $wpdb->query($sql);
        
        echo "<p><strong>直接执行结果:</strong> " . ($direct_result !== false ? '<span class="success">成功</span>' : '<span class="error">失败</span>') . "</p>";
        
        if ($wpdb->last_error) {
            echo "<p class='error'>SQL错误: " . $wpdb->last_error . "</p>";
        }
        
        // 再次检查
        $table_exists_after_direct = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;
        echo "<p>直接执行后表是否存在: " . ($table_exists_after_direct ? '<span class="success">是</span>' : '<span class="error">否</span>') . "</p>";
    }
    
    // 最终检查
    echo "<h4>最终检查</h4>";
    $final_check = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;
    
    if ($final_check) {
        echo "<p class='success'>✅ 表创建成功！</p>";
        
        // 显示表结构
        $columns = $wpdb->get_results("DESCRIBE $inventory_table");
        echo "<h4>表结构:</h4>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>字段名</th><th>类型</th><th>空值</th><th>键</th><th>默认值</th><th>额外</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column->Field}</td>";
            echo "<td>{$column->Type}</td>";
            echo "<td>{$column->Null}</td>";
            echo "<td>{$column->Key}</td>";
            echo "<td>{$column->Default}</td>";
            echo "<td>{$column->Extra}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 测试插入一条记录
        echo "<h4>测试插入记录</h4>";
        $test_data = [
            'product_id' => 175,
            'walmart_sku' => '02238142',
            'status' => 'success',
            'quantity' => 1073,
            'last_sync_time' => current_time('mysql'),
            'created_time' => current_time('mysql'),
            'response_data' => '{"sku":"02238142","quantity":{"unit":"EACH","amount":1073}}'
        ];
        
        $insert_result = $wpdb->insert($inventory_table, $test_data);
        
        if ($insert_result !== false) {
            echo "<p class='success'>✅ 测试记录插入成功！插入ID: " . $wpdb->insert_id . "</p>";
        } else {
            echo "<p class='error'>❌ 测试记录插入失败</p>";
            if ($wpdb->last_error) {
                echo "<p class='error'>错误: " . $wpdb->last_error . "</p>";
            }
        }
        
    } else {
        echo "<p class='error'>❌ 表创建失败</p>";
        
        // 显示所有表
        echo "<h4>当前数据库中的所有表:</h4>";
        $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        echo "<ul>";
        foreach ($all_tables as $table) {
            $table_name = $table[0];
            $highlight = (strpos($table_name, 'walmart') !== false) ? 'style="color: blue; font-weight: bold;"' : '';
            echo "<li $highlight>$table_name</li>";
        }
        echo "</ul>";
    }
    
} else {
    echo "<form method='post'>";
    echo "<button type='submit' name='create_table' value='1' style='background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;'>立即创建表</button>";
    echo "</form>";
}

?>

<hr>
<h2>诊断信息</h2>
<div class="info">
<p><strong>可能的问题原因：</strong></p>
<ul>
<li>数据库权限不足</li>
<li>SQL语法错误</li>
<li>字符集问题</li>
<li>表名冲突</li>
<li>dbDelta函数问题</li>
</ul>

<p><strong>解决方案：</strong></p>
<ul>
<li>检查数据库用户权限</li>
<li>使用直接SQL执行</li>
<li>检查错误日志</li>
<li>手动在数据库中创建表</li>
</ul>
</div>

</body>
</html>
