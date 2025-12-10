<?php
/**
 * 检查商品175的库存同步状态
 * 诊断为什么显示"未同步"
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
    <title>检查商品175库存同步状态</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>

<h1>检查商品175库存同步状态</h1>

<?php
global $wpdb;

$product_id = 175;
$sku = '02238142';

$feeds_table = $wpdb->prefix . 'walmart_feeds';
$inventory_table = $wpdb->prefix . 'walmart_inventory_sync';

echo "<h2>基本信息</h2>";
echo "<p><strong>商品ID:</strong> $product_id</p>";
echo "<p><strong>SKU:</strong> $sku</p>";

// 检查WooCommerce商品
$product = wc_get_product($product_id);
if ($product) {
    echo "<p class='success'>✅ WooCommerce商品存在</p>";
    echo "<p><strong>商品名称:</strong> " . esc_html($product->get_name()) . "</p>";
    echo "<p><strong>商品SKU:</strong> " . esc_html($product->get_sku()) . "</p>";
    echo "<p><strong>库存数量:</strong> " . $product->get_stock_quantity() . "</p>";
} else {
    echo "<p class='error'>❌ WooCommerce商品不存在</p>";
}

// 检查Feeds表记录
echo "<h2>Feeds表记录</h2>";
$feed_records = $wpdb->get_results($wpdb->prepare("
    SELECT id, feed_id, sku, status, wpid, created_at, updated_at
    FROM $feeds_table 
    WHERE product_id = %d 
    ORDER BY created_at DESC
", $product_id));

if (!empty($feed_records)) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Feed ID</th><th>SKU</th><th>状态</th><th>WPID</th><th>创建时间</th><th>更新时间</th></tr>";
    foreach ($feed_records as $record) {
        echo "<tr>";
        echo "<td>{$record->id}</td>";
        echo "<td>{$record->feed_id}</td>";
        echo "<td>{$record->sku}</td>";
        echo "<td>{$record->status}</td>";
        echo "<td>{$record->wpid}</td>";
        echo "<td>{$record->created_at}</td>";
        echo "<td>{$record->updated_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>没有找到Feeds记录</p>";
}

// 检查库存同步表
echo "<h2>库存同步表记录</h2>";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;

if (!$table_exists) {
    echo "<p class='error'>❌ 库存同步表不存在！</p>";
} else {
    echo "<p class='success'>✅ 库存同步表存在</p>";
    
    // 查询所有相关记录
    $inventory_records = $wpdb->get_results($wpdb->prepare("
        SELECT id, walmart_sku, status, quantity, retry_count, last_sync_time, created_time, response_data
        FROM $inventory_table 
        WHERE product_id = %d 
        ORDER BY created_time DESC
    ", $product_id));
    
    if (!empty($inventory_records)) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Walmart SKU</th><th>状态</th><th>数量</th><th>重试次数</th><th>最后同步时间</th><th>创建时间</th><th>响应数据</th></tr>";
        foreach ($inventory_records as $record) {
            $status_color = '';
            switch ($record->status) {
                case 'success':
                    $status_color = 'color: green;';
                    break;
                case 'failed':
                    $status_color = 'color: red;';
                    break;
                case 'pending':
                    $status_color = 'color: orange;';
                    break;
            }
            
            echo "<tr>";
            echo "<td>{$record->id}</td>";
            echo "<td>{$record->walmart_sku}</td>";
            echo "<td style='$status_color'><strong>{$record->status}</strong></td>";
            echo "<td>{$record->quantity}</td>";
            echo "<td>{$record->retry_count}</td>";
            echo "<td>{$record->last_sync_time}</td>";
            echo "<td>{$record->created_time}</td>";
            echo "<td>" . esc_html(substr($record->response_data, 0, 100)) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 检查特定SKU的记录
        $specific_record = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $inventory_table 
            WHERE product_id = %d AND walmart_sku = %s
        ", $product_id, $sku));
        
        if ($specific_record) {
            echo "<h3>SKU {$sku} 的具体记录：</h3>";
            echo "<div class='code'>";
            echo "<strong>状态:</strong> {$specific_record->status}<br>";
            echo "<strong>数量:</strong> {$specific_record->quantity}<br>";
            echo "<strong>最后同步时间:</strong> {$specific_record->last_sync_time}<br>";
            echo "<strong>响应数据:</strong><br>";
            echo "<pre>" . esc_html($specific_record->response_data) . "</pre>";
            echo "</div>";
        } else {
            echo "<p class='error'>❌ 没有找到SKU {$sku} 的库存同步记录</p>";
        }
        
    } else {
        echo "<p class='warning'>没有找到库存同步记录</p>";
    }
}

// 测试库存状态查询逻辑
echo "<h2>库存状态查询测试</h2>";

if ($table_exists) {
    // 模拟库存同步管理页面的查询逻辑
    $sync_record = $wpdb->get_row($wpdb->prepare("
        SELECT status, last_sync_time
        FROM $inventory_table
        WHERE product_id = %d AND walmart_sku = %s
    ", $product_id, $sku));
    
    if ($sync_record) {
        $status_labels = [
            'success' => '✅ 已同步',
            'failed' => '❌ 失败',
            'pending' => '⏳ 待处理',
            'retrying' => '🔄 重试中'
        ];
        $inventory_status = $status_labels[$sync_record->status] ?? $sync_record->status;
        echo "<p class='success'>查询结果: <strong>$inventory_status</strong></p>";
        echo "<p>最后同步时间: {$sync_record->last_sync_time}</p>";
    } else {
        echo "<p class='warning'>查询结果: <strong>⚪ 未同步</strong></p>";
        echo "<p>原因: 没有找到匹配的库存同步记录</p>";
    }
}

// 手动触发库存同步测试
echo "<h2>手动触发库存同步测试</h2>";
if (isset($_POST['test_sync'])) {
    if (!defined('WOO_WALMART_SYNC_PATH')) {
        define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
    }
    require_once WOO_WALMART_SYNC_PATH . 'includes/class-inventory-manager.php';
    
    $inventory_manager = new WooWalmartSync_Inventory_Manager();
    $result = $inventory_manager->sync_single_inventory($product_id, $sku);
    
    if ($result) {
        echo "<p class='success'>✅ 库存同步测试成功</p>";
    } else {
        echo "<p class='error'>❌ 库存同步测试失败</p>";
    }
    
    echo "<p><a href='?'>刷新页面查看结果</a></p>";
} else {
    echo "<form method='post'>";
    echo "<button type='submit' name='test_sync' value='1'>测试库存同步</button>";
    echo "</form>";
}

?>

<hr>
<h2>诊断总结</h2>
<div class="info">
<p><strong>可能的问题原因：</strong></p>
<ul>
<li>库存同步表不存在或记录未正确插入</li>
<li>SKU不匹配（查询使用的SKU与记录中的SKU不一致）</li>
<li>数据库操作失败但没有错误提示</li>
<li>缓存问题导致页面显示过期数据</li>
</ul>

<p><strong>解决建议：</strong></p>
<ul>
<li>检查数据库操作日志</li>
<li>确认库存同步表结构正确</li>
<li>手动触发库存同步并观察日志</li>
<li>清除可能的缓存</li>
</ul>
</div>

</body>
</html>
