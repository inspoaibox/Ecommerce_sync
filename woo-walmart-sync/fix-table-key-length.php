<?php
/**
 * 修复表索引键长度问题
 * 删除有问题的表并重新创建
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
    <title>修复表索引键长度问题</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
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
            margin: 10px 5px;
        }
        .button:hover { background: #005a87; }
        .button.danger { background: #dc3232; }
        .button.danger:hover { background: #a02622; }
    </style>
</head>
<body>

<h1>修复表索引键长度问题</h1>

<div class="warning">
<h3>⚠️ 注意</h3>
<p>这个操作会删除有问题的表并重新创建。如果表中有重要数据，请先备份。</p>
<p>受影响的表：</p>
<ul>
<li>wp_walmart_inventory_sync (库存同步表)</li>
<li>wp_walmart_batch_feeds (批量Feed表)</li>
<li>wp_walmart_products (沃尔玛商品表)</li>
<li>wp_walmart_local_cache (本地缓存表)</li>
</ul>
</div>

<?php
global $wpdb;

if (isset($_POST['fix_tables'])) {
    echo "<hr>";
    echo "<h2>开始修复表...</h2>";
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // 需要修复的表定义（使用varchar(191)而不是varchar(255)）
    $tables_to_fix = [
        'walmart_inventory_sync' => "CREATE TABLE {$wpdb->prefix}walmart_inventory_sync (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            walmart_sku varchar(191) NOT NULL,
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
        ) {$charset_collate};",
        
        'walmart_batch_feeds' => "CREATE TABLE {$wpdb->prefix}walmart_batch_feeds (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_id varchar(191) NOT NULL,
            feed_type varchar(50) NOT NULL,
            status varchar(50) NOT NULL,
            total_items int(11) NOT NULL DEFAULT 0,
            processed_items int(11) NOT NULL DEFAULT 0,
            successful_items int(11) NOT NULL DEFAULT 0,
            failed_items int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            response_data longtext,
            PRIMARY KEY (id),
            UNIQUE KEY feed_id (feed_id),
            KEY status (status),
            KEY feed_type (feed_type)
        ) {$charset_collate};",
        
        'walmart_products' => "CREATE TABLE {$wpdb->prefix}walmart_products (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            walmart_sku varchar(191) NOT NULL,
            wpid varchar(191),
            status varchar(50) NOT NULL,
            sync_status varchar(50) DEFAULT 'pending',
            last_sync_time datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_sku (product_id, walmart_sku),
            KEY status (status),
            KEY sync_status (sync_status)
        ) {$charset_collate};",
        
        'walmart_local_cache' => "CREATE TABLE {$wpdb->prefix}walmart_local_cache (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sku varchar(191) NOT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            product_name varchar(500) NOT NULL,
            price decimal(10,2) DEFAULT 0.00,
            inventory_count int(11) DEFAULT 0,
            category varchar(191) DEFAULT '',
            status varchar(20) DEFAULT 'active',
            last_sync_time datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sku (sku),
            KEY product_id (product_id),
            KEY status (status),
            KEY last_sync_time (last_sync_time)
        ) {$charset_collate};"
    ];
    
    $success_count = 0;
    $total_count = count($tables_to_fix);
    
    foreach ($tables_to_fix as $table_suffix => $sql) {
        $table_name = $wpdb->prefix . $table_suffix;
        
        echo "<h3>处理表: {$table_name}</h3>";
        
        // 1. 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            echo "<p class='info'>表已存在，先删除...</p>";
            
            // 2. 删除现有表
            $drop_result = $wpdb->query("DROP TABLE IF EXISTS $table_name");
            
            if ($drop_result !== false) {
                echo "<p class='success'>✅ 表删除成功</p>";
            } else {
                echo "<p class='error'>❌ 表删除失败: " . $wpdb->last_error . "</p>";
                continue;
            }
        }
        
        // 3. 创建新表
        echo "<p class='info'>创建新表...</p>";
        $create_result = $wpdb->query($sql);
        
        if ($create_result !== false) {
            // 验证表是否创建成功
            $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if ($table_exists_after) {
                echo "<p class='success'>✅ 表创建成功</p>";
                $success_count++;
            } else {
                echo "<p class='error'>❌ 表创建失败：表不存在</p>";
            }
        } else {
            echo "<p class='error'>❌ SQL执行失败: " . $wpdb->last_error . "</p>";
        }
        
        echo "<hr>";
    }
    
    echo "<h2>修复完成</h2>";
    echo "<p class='success'>成功修复了 {$success_count}/{$total_count} 个表。</p>";
    
    if ($success_count === $total_count) {
        echo "<p class='success'>🎉 所有表都已成功创建！现在可以正常使用库存同步功能了。</p>";
        echo "<p><a href='check-table-creation.php' class='button'>查看表状态</a></p>";
    } else {
        echo "<p class='warning'>⚠️ 部分表创建失败，请检查数据库权限或联系管理员。</p>";
    }
    
} else {
    echo "<h2>准备修复</h2>";
    echo "<p>这个操作将：</p>";
    echo "<ol>";
    echo "<li>删除有索引键长度问题的表</li>";
    echo "<li>使用正确的字段长度重新创建表</li>";
    echo "<li>验证表创建是否成功</li>";
    echo "</ol>";
    
    echo "<form method='post'>";
    echo "<p><input type='checkbox' id='confirm' name='confirm' required> <label for='confirm'>我确认要删除并重新创建这些表</label></p>";
    echo "<button type='submit' name='fix_tables' value='1' class='button danger'>开始修复</button>";
    echo "</form>";
}
?>

<hr>
<h2>说明</h2>
<div class="info">
<p><strong>为什么会出现这个问题？</strong></p>
<ul>
<li>MySQL的索引键长度限制是1000字节</li>
<li>使用utf8mb4字符集时，varchar(255)占用255×4=1020字节，超过限制</li>
<li>解决方案是将varchar(255)改为varchar(191)，占用191×4=764字节</li>
</ul>

<p><strong>修复后的变化：</strong></p>
<ul>
<li>walmart_sku字段从varchar(255)改为varchar(191)</li>
<li>其他相关字符串字段也相应调整</li>
<li>功能不受影响，191个字符足够存储SKU和其他标识符</li>
</ul>
</div>

</body>
</html>
