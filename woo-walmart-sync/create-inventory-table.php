<?php
/**
 * 手动创建库存同步表
 * 用于现有安装升级
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
    <title>创建库存同步表</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .button { 
            background: #0073aa; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
            margin: 10px 0;
        }
        .button:hover { background: #005a87; }
    </style>
</head>
<body>

<h1>创建库存同步表</h1>

<?php
global $wpdb;

$inventory_table = $wpdb->prefix . 'walmart_inventory_sync';

// 检查表是否已存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;

if ($table_exists) {
    echo "<p class='success'>✅ 库存同步表已存在！</p>";
    echo "<p>表名: <code>$inventory_table</code></p>";
    
    // 显示表结构
    $columns = $wpdb->get_results("DESCRIBE $inventory_table");
    echo "<h3>表结构:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>字段名</th><th>类型</th><th>空值</th><th>键</th><th>默认值</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 显示记录数
    $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $inventory_table");
    echo "<p>当前记录数: <strong>$record_count</strong></p>";
    
} else {
    echo "<p class='error'>❌ 库存同步表不存在，正在创建...</p>";
    
    // 创建表
    $charset_collate = $wpdb->get_charset_collate();
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    // 检查创建结果
    $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;
    
    if ($table_exists_after) {
        echo "<p class='success'>✅ 库存同步表创建成功！</p>";
        echo "<p>表名: <code>$inventory_table</code></p>";
        
        // 记录日志
        if (function_exists('woo_walmart_sync_log')) {
            woo_walmart_sync_log('手动表创建', '成功', [
                'table' => $inventory_table,
                'result' => $result
            ], '手动创建库存同步表成功');
        }
        
        echo "<h3>创建结果:</h3>";
        echo "<pre>" . print_r($result, true) . "</pre>";
        
    } else {
        echo "<p class='error'>❌ 库存同步表创建失败！</p>";
        if ($wpdb->last_error) {
            echo "<p class='error'>错误信息: " . $wpdb->last_error . "</p>";
        }
        echo "<h3>SQL语句:</h3>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";
        echo "<h3>dbDelta结果:</h3>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    }
}

// 触发表结构更新函数
if (function_exists('woo_walmart_sync_update_table_structure')) {
    echo "<hr>";
    echo "<h2>触发完整表结构更新</h2>";
    echo "<p class='info'>正在运行完整的表结构更新函数...</p>";
    
    try {
        woo_walmart_sync_update_table_structure();
        echo "<p class='success'>✅ 表结构更新函数执行完成！</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ 表结构更新函数执行失败: " . $e->getMessage() . "</p>";
    }
}

?>

<hr>
<h2>后续步骤</h2>
<p>库存同步表创建完成后，您可以：</p>
<ul>
<li>返回 <a href="<?php echo admin_url('admin.php?page=woo-walmart-inventory'); ?>" class="button">库存同步管理页面</a></li>
<li>查看 <a href="debug-inventory-status.php" class="button">库存状态调试页面</a></li>
<li>进行库存同步测试</li>
</ul>

<p><strong>注意：</strong>现在库存同步功能应该可以正常工作了。之前的同步状态问题已经解决。</p>

</body>
</html>
