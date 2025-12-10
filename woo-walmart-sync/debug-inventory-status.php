<?php
/**
 * 调试库存同步状态
 * 检查库存同步记录和状态显示问题
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
    <title>库存同步状态调试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
    </style>
</head>
<body>

<h1>库存同步状态调试</h1>

<?php
global $wpdb;

$feeds_table = $wpdb->prefix . 'walmart_feeds';
$inventory_table = $wpdb->prefix . 'walmart_inventory_sync';

// 检查表是否存在
$feeds_exists = $wpdb->get_var("SHOW TABLES LIKE '$feeds_table'") === $feeds_table;
$inventory_exists = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;

echo "<h2>表状态检查</h2>";
echo "<p>Feeds表存在: " . ($feeds_exists ? '<span class="success">是</span>' : '<span class="error">否</span>') . "</p>";
echo "<p>库存同步表存在: " . ($inventory_exists ? '<span class="success">是</span>' : '<span class="error">否</span>') . "</p>";

if (!$feeds_exists) {
    echo "<p class='error'>Feeds表不存在，插件可能未正确安装。</p>";
    exit;
}

if (!$inventory_exists) {
    echo "<p class='warning'>库存同步表不存在，正在创建...</p>";

    // 创建库存同步表
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

    // 检查表是否创建成功
    $inventory_exists = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;

    if ($inventory_exists) {
        echo "<p class='success'>✅ 库存同步表创建成功！</p>";
        echo "<p class='info'>现在可以正常使用库存同步功能了。</p>";
    } else {
        echo "<p class='error'>❌ 库存同步表创建失败。</p>";
        echo "<p>SQL执行结果：</p><pre>" . print_r($result, true) . "</pre>";
        exit;
    }
}

// 检查特定商品的状态
$test_product_ids = [62, 61]; // 测试商品ID
$test_skus = ['02238142', '01702172']; // 测试SKU

echo "<h2>特定商品状态检查</h2>";

foreach ($test_product_ids as $product_id) {
    echo "<h3>商品ID: $product_id</h3>";
    
    // 检查feeds表中的记录
    $feed_records = $wpdb->get_results($wpdb->prepare("
        SELECT id, sku, status, wpid, created_at 
        FROM $feeds_table 
        WHERE product_id = %d 
        ORDER BY created_at DESC
    ", $product_id));
    
    echo "<h4>Feeds表记录:</h4>";
    if (!empty($feed_records)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>SKU</th><th>状态</th><th>WPID</th><th>创建时间</th></tr>";
        foreach ($feed_records as $record) {
            echo "<tr>";
            echo "<td>{$record->id}</td>";
            echo "<td>{$record->sku}</td>";
            echo "<td>{$record->status}</td>";
            echo "<td>{$record->wpid}</td>";
            echo "<td>{$record->created_at}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>没有找到feeds记录</p>";
    }
    
    // 检查库存同步表中的记录
    $inventory_records = $wpdb->get_results($wpdb->prepare("
        SELECT id, walmart_sku, status, quantity, last_sync_time, created_time
        FROM $inventory_table 
        WHERE product_id = %d 
        ORDER BY created_time DESC
    ", $product_id));
    
    echo "<h4>库存同步表记录:</h4>";
    if (!empty($inventory_records)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Walmart SKU</th><th>状态</th><th>数量</th><th>最后同步时间</th><th>创建时间</th></tr>";
        foreach ($inventory_records as $record) {
            echo "<tr>";
            echo "<td>{$record->id}</td>";
            echo "<td>{$record->walmart_sku}</td>";
            echo "<td>{$record->status}</td>";
            echo "<td>{$record->quantity}</td>";
            echo "<td>{$record->last_sync_time}</td>";
            echo "<td>{$record->created_time}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>没有找到库存同步记录</p>";
    }
}

echo "<h2>SKU匹配检查</h2>";

foreach ($test_skus as $sku) {
    echo "<h3>SKU: $sku</h3>";
    
    // 检查feeds表
    $feed_record = $wpdb->get_row($wpdb->prepare("
        SELECT product_id, status, wpid 
        FROM $feeds_table 
        WHERE sku = %s AND status = 'PROCESSED'
        ORDER BY created_at DESC LIMIT 1
    ", $sku));
    
    if ($feed_record) {
        echo "<p class='success'>Feeds表找到记录: 商品ID {$feed_record->product_id}, 状态 {$feed_record->status}</p>";
        
        // 检查对应的库存同步记录
        $inventory_record = $wpdb->get_row($wpdb->prepare("
            SELECT status, quantity, last_sync_time 
            FROM $inventory_table 
            WHERE product_id = %d AND walmart_sku = %s
        ", $feed_record->product_id, $sku));
        
        if ($inventory_record) {
            echo "<p class='success'>库存同步表找到匹配记录: 状态 {$inventory_record->status}, 数量 {$inventory_record->quantity}</p>";
        } else {
            echo "<p class='error'>库存同步表未找到匹配记录 (product_id={$feed_record->product_id}, walmart_sku={$sku})</p>";
            
            // 检查是否有该商品的其他SKU记录
            $other_records = $wpdb->get_results($wpdb->prepare("
                SELECT walmart_sku, status 
                FROM $inventory_table 
                WHERE product_id = %d
            ", $feed_record->product_id));
            
            if (!empty($other_records)) {
                echo "<p class='info'>该商品的其他库存同步记录:</p>";
                foreach ($other_records as $other) {
                    echo "<p>- SKU: {$other->walmart_sku}, 状态: {$other->status}</p>";
                }
            }
        }
    } else {
        echo "<p class='error'>Feeds表未找到PROCESSED状态的记录</p>";
    }
}

echo "<h2>统计查询测试</h2>";

// 测试原始查询
$old_query_result = $wpdb->get_var("
    SELECT COUNT(DISTINCT f.product_id)
    FROM $feeds_table f
    LEFT JOIN $inventory_table i ON f.product_id = i.product_id
    WHERE f.status = 'PROCESSED'
    AND i.product_id IS NULL
");

// 测试修复后的查询
$new_query_result = $wpdb->get_var("
    SELECT COUNT(DISTINCT f.product_id)
    FROM $feeds_table f
    LEFT JOIN $inventory_table i ON f.product_id = i.product_id AND f.sku = i.walmart_sku
    WHERE f.status = 'PROCESSED'
    AND i.product_id IS NULL
");

echo "<p>原始查询结果（只按product_id关联）: <strong>$old_query_result</strong></p>";
echo "<p>修复后查询结果（按product_id和SKU关联）: <strong>$new_query_result</strong></p>";

// 显示详细的未同步商品
echo "<h3>修复后查询的详细结果:</h3>";
$detailed_unsynced = $wpdb->get_results("
    SELECT f.product_id, f.sku, f.status as feed_status, f.created_at
    FROM $feeds_table f
    LEFT JOIN $inventory_table i ON f.product_id = i.product_id AND f.sku = i.walmart_sku
    WHERE f.status = 'PROCESSED'
    AND i.product_id IS NULL
    ORDER BY f.created_at DESC
    LIMIT 10
");

if (!empty($detailed_unsynced)) {
    echo "<table>";
    echo "<tr><th>商品ID</th><th>SKU</th><th>Feed状态</th><th>创建时间</th></tr>";
    foreach ($detailed_unsynced as $item) {
        echo "<tr>";
        echo "<td>{$item->product_id}</td>";
        echo "<td>{$item->sku}</td>";
        echo "<td>{$item->feed_status}</td>";
        echo "<td>{$item->created_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='success'>没有未同步的商品！</p>";
}

?>

<h2>总结</h2>
<p>这个调试页面帮助识别库存同步状态显示问题的根本原因。</p>
<p>主要检查点：</p>
<ul>
<li>Feeds表和库存同步表的记录是否存在</li>
<li>SKU是否在两个表中匹配</li>
<li>查询逻辑是否正确关联记录</li>
</ul>

</body>
</html>
