<?php
/**
 * 检查插件表创建状态
 * 显示激活时的表创建结果
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
    <title>插件表创建状态检查</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
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

<h1>插件表创建状态检查</h1>

<?php
global $wpdb;

// 获取表创建结果
$creation_results = get_option('walmart_sync_table_creation_results', []);

echo "<h2>插件激活时的表创建结果</h2>";

if (empty($creation_results)) {
    echo "<p class='warning'>⚠️ 没有找到表创建结果记录。这可能意味着：</p>";
    echo "<ul>";
    echo "<li>插件是在添加验证机制之前激活的</li>";
    echo "<li>激活钩子没有正确执行</li>";
    echo "<li>表创建过程中出现了严重错误</li>";
    echo "</ul>";
} else {
    echo "<table>";
    echo "<tr><th>表类型</th><th>表名</th><th>状态</th><th>dbDelta结果</th><th>错误信息</th><th>操作</th></tr>";
    
    foreach ($creation_results as $table_key => $result) {
        $status = $result['created'] ? '<span class="success">✅ 已创建</span>' : '<span class="error">❌ 未创建</span>';
        $table_name = $result['table_name'];
        $error = !empty($result['error']) ? $result['error'] : '';
        $dbdelta_result = !empty($result['dbdelta_result']) ? implode(', ', $result['dbdelta_result']) : '无';
        
        echo "<tr>";
        echo "<td>{$table_key}</td>";
        echo "<td>{$table_name}</td>";
        echo "<td>{$status}</td>";
        echo "<td>" . esc_html($dbdelta_result) . "</td>";
        echo "<td class='error'>" . esc_html($error) . "</td>";
        
        // 操作按钮
        if (!$result['created']) {
            echo "<td><a href='?create_table={$table_key}' class='button'>创建此表</a></td>";
        } else {
            echo "<td><span class='success'>已存在</span></td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// 处理单个表创建请求
if (isset($_GET['create_table']) && !empty($creation_results)) {
    $table_key = sanitize_text_field($_GET['create_table']);
    
    if (isset($creation_results[$table_key])) {
        echo "<hr>";
        echo "<h3>创建表: {$table_key}</h3>";
        
        $table_info = $creation_results[$table_key];
        $table_name = $table_info['table_name'];
        
        // 重新定义SQL（这里需要根据表类型定义相应的SQL）
        $charset_collate = $wpdb->get_charset_collate();
        $sql_definitions = [
            'inventory_sync' => "CREATE TABLE IF NOT EXISTS {$table_name} (
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
            ) {$charset_collate};",

            'cat_map' => "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                wc_category_id bigint(20) UNSIGNED NOT NULL,
                walmart_category_id varchar(255) NOT NULL,
                walmart_category_name varchar(500) NOT NULL,
                mapping_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY wc_category_id (wc_category_id),
                KEY walmart_category_id (walmart_category_id)
            ) {$charset_collate};",

            'batch_feeds' => "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                feed_id varchar(255) NOT NULL,
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

            'walmart_products' => "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id bigint(20) UNSIGNED NOT NULL,
                walmart_sku varchar(255) NOT NULL,
                wpid varchar(255),
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

            'walmart_notifications' => "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                message longtext NOT NULL,
                status varchar(20) DEFAULT 'unread',
                priority varchar(20) DEFAULT 'normal',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                read_at datetime NULL,
                PRIMARY KEY (id),
                KEY type (type),
                KEY status (status),
                KEY priority (priority)
            ) {$charset_collate};",

            'walmart_local_cache' => "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                sku varchar(255) NOT NULL,
                product_id bigint(20) UNSIGNED NOT NULL,
                product_name varchar(500) NOT NULL,
                price decimal(10,2) DEFAULT 0.00,
                inventory_count int(11) DEFAULT 0,
                category varchar(255) DEFAULT '',
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
        
        if (isset($sql_definitions[$table_key])) {
            $sql = $sql_definitions[$table_key];
            
            echo "<p><strong>执行SQL:</strong></p>";
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 3px;'>" . esc_html($sql) . "</pre>";
            
            // 执行SQL
            $result = $wpdb->query($sql);
            
            if ($result !== false) {
                // 验证表是否创建成功
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                
                if ($table_exists) {
                    echo "<p class='success'>✅ 表创建成功！</p>";
                    
                    // 更新创建结果
                    $creation_results[$table_key]['created'] = true;
                    $creation_results[$table_key]['manual_creation'] = true;
                    update_option('walmart_sync_table_creation_results', $creation_results);
                    
                    echo "<p><a href='?' class='button'>刷新页面</a></p>";
                } else {
                    echo "<p class='error'>❌ 表创建失败：表不存在</p>";
                }
            } else {
                echo "<p class='error'>❌ SQL执行失败</p>";
                if ($wpdb->last_error) {
                    echo "<p class='error'>错误信息: " . $wpdb->last_error . "</p>";
                }
            }
        } else {
            echo "<p class='error'>❌ 未找到表 {$table_key} 的SQL定义</p>";
        }
    }
}

// 当前表状态检查
echo "<hr>";
echo "<h2>当前数据库表状态</h2>";

$required_tables = [
    'woo_walmart_sync_logs' => '日志表',
    'walmart_upc_pool' => 'UPC池',
    'walmart_category_map' => '分类映射',
    'walmart_feeds' => 'Feed记录',
    'walmart_batch_feeds' => '批量Feed',
    'walmart_batch_items' => '批量商品',
    'walmart_products_cache' => '沃尔玛商品',
    'walmart_sync_notifications' => '通知记录',
    'walmart_local_cache' => '本地缓存',
    'walmart_inventory_sync' => '库存同步'
];

echo "<table>";
echo "<tr><th>表名</th><th>描述</th><th>状态</th><th>记录数</th></tr>";

foreach ($required_tables as $table_suffix => $description) {
    $table_name = $wpdb->prefix . $table_suffix;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    $status = $table_exists ? '<span class="success">✅ 存在</span>' : '<span class="error">❌ 不存在</span>';
    $record_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 'N/A';
    
    echo "<tr>";
    echo "<td>{$table_name}</td>";
    echo "<td>{$description}</td>";
    echo "<td>{$status}</td>";
    echo "<td>{$record_count}</td>";
    echo "</tr>";
}
echo "</table>";

?>

<hr>
<h2>操作选项</h2>
<div>
    <a href="?fix_all=1" class="button" style="background: #00a32a;">一键修复所有缺失表</a>
    <a href="force-create-inventory-table.php" class="button">强制创建库存同步表</a>
    <a href="?reactivate=1" class="button">重新触发激活钩子</a>
    <a href="?clear_results=1" class="button danger">清除创建结果记录</a>
</div>

<?php
// 处理一键修复所有缺失表
if (isset($_GET['fix_all'])) {
    echo "<hr>";
    echo "<h3>一键修复所有缺失表</h3>";

    $charset_collate = $wpdb->get_charset_collate();
    $sql_definitions = [
        'inventory_sync' => [
            'table_name' => $wpdb->prefix . 'walmart_inventory_sync',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}walmart_inventory_sync (
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
            ) {$charset_collate};"
        ],
        'cat_map' => [
            'table_name' => $wpdb->prefix . 'walmart_category_mapping',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}walmart_category_mapping (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                wc_category_id bigint(20) UNSIGNED NOT NULL,
                walmart_category_id varchar(191) NOT NULL,
                walmart_category_name varchar(500) NOT NULL,
                mapping_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY wc_category_id (wc_category_id),
                KEY walmart_category_id (walmart_category_id)
            ) {$charset_collate};"
        ],
        'batch_feeds' => [
            'table_name' => $wpdb->prefix . 'walmart_batch_feeds',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}walmart_batch_feeds (
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
            ) {$charset_collate};"
        ],
        'walmart_products' => [
            'table_name' => $wpdb->prefix . 'walmart_products',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}walmart_products (
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
            ) {$charset_collate};"
        ],
        'walmart_notifications' => [
            'table_name' => $wpdb->prefix . 'walmart_notifications',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}walmart_notifications (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                message longtext NOT NULL,
                status varchar(20) DEFAULT 'unread',
                priority varchar(20) DEFAULT 'normal',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                read_at datetime NULL,
                PRIMARY KEY (id),
                KEY type (type),
                KEY status (status),
                KEY priority (priority)
            ) {$charset_collate};"
        ],
        'walmart_local_cache' => [
            'table_name' => $wpdb->prefix . 'walmart_local_cache',
            'sql' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}walmart_local_cache (
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
        ]
    ];

    $fixed_count = 0;
    $total_count = 0;

    foreach ($sql_definitions as $table_key => $table_info) {
        $table_name = $table_info['table_name'];
        $sql = $table_info['sql'];
        $total_count++;

        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            echo "<p><strong>创建表:</strong> {$table_name}</p>";

            // 执行SQL
            $result = $wpdb->query($sql);

            if ($result !== false) {
                // 验证表是否创建成功
                $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

                if ($table_exists_after) {
                    echo "<p class='success'>✅ {$table_name} 创建成功</p>";
                    $fixed_count++;
                } else {
                    echo "<p class='error'>❌ {$table_name} 创建失败：表不存在</p>";
                }
            } else {
                echo "<p class='error'>❌ {$table_name} SQL执行失败</p>";
                if ($wpdb->last_error) {
                    echo "<p class='error'>错误: " . $wpdb->last_error . "</p>";
                }
            }
        } else {
            echo "<p class='info'>ℹ️ {$table_name} 已存在，跳过</p>";
        }
    }

    echo "<hr>";
    echo "<p class='success'><strong>修复完成！</strong></p>";
    echo "<p>总共检查了 {$total_count} 个表，成功创建了 {$fixed_count} 个缺失的表。</p>";
    echo "<p><a href='?' class='button'>刷新页面查看结果</a></p>";
}

// 处理重新激活
if (isset($_GET['reactivate'])) {
    echo "<hr>";
    echo "<h3>重新触发激活钩子</h3>";
    
    // 删除旧的结果记录
    delete_option('walmart_sync_table_creation_results');
    
    // 手动触发激活钩子
    do_action('activate_' . plugin_basename(__FILE__));
    
    echo "<p class='info'>激活钩子已重新触发。</p>";
    echo "<p><a href='?' class='button'>查看结果</a></p>";
}

// 处理清除结果
if (isset($_GET['clear_results'])) {
    delete_option('walmart_sync_table_creation_results');
    echo "<p class='success'>创建结果记录已清除。</p>";
    echo "<p><a href='?' class='button'>刷新页面</a></p>";
}
?>

<hr>
<h2>说明</h2>
<div class="info">
<p><strong>这个页面的作用：</strong></p>
<ul>
<li>显示插件激活时的表创建结果</li>
<li>检查当前数据库中所有必需表的状态</li>
<li>提供手动创建缺失表的功能</li>
<li>帮助诊断表创建问题</li>
</ul>

<p><strong>如果表创建失败：</strong></p>
<ul>
<li>检查数据库用户权限</li>
<li>查看错误信息</li>
<li>使用手动创建功能</li>
<li>联系服务器管理员</li>
</ul>
</div>

</body>
</html>
