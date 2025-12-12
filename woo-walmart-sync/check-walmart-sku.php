<?php
/**
 * 检查SKU在沃尔玛系统中的状态
 * 用于诊断库存同步问题
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

// 引入必要的类
if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}
require_once WOO_WALMART_SYNC_PATH . 'includes/class-api-key-auth.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>检查沃尔玛SKU状态</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-section { background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>

<h1>检查沃尔玛SKU状态</h1>

<div class="form-section">
    <h3>检查特定SKU</h3>
    <form method="post">
        <label for="check_sku">输入SKU:</label>
        <input type="text" id="check_sku" name="check_sku" value="<?php echo isset($_POST['check_sku']) ? esc_attr($_POST['check_sku']) : '07952289'; ?>" style="width: 200px;">
        <button type="submit" name="action" value="check_sku">检查SKU</button>
    </form>
</div>

<?php
$api_client = new Woo_Walmart_API_Key_Auth();

// 处理SKU检查请求
if (isset($_POST['action']) && $_POST['action'] === 'check_sku' && !empty($_POST['check_sku'])) {
    $sku = sanitize_text_field($_POST['check_sku']);
    
    echo "<h2>检查SKU: $sku</h2>";
    
    // 1. 检查商品是否存在
    echo "<h3>1. 检查商品信息</h3>";
    $item_result = $api_client->make_request("/v3/items/{$sku}");
    
    if (is_wp_error($item_result)) {
        echo "<p class='error'>❌ 商品API调用失败: " . $item_result->get_error_message() . "</p>";
    } elseif (isset($item_result['error'])) {
        echo "<p class='error'>❌ 商品不存在或无法访问</p>";
        echo "<pre>" . print_r($item_result['error'], true) . "</pre>";
    } else {
        echo "<p class='success'>✅ 商品存在于沃尔玛系统中</p>";
        if (isset($item_result['sku'])) {
            echo "<p><strong>SKU:</strong> {$item_result['sku']}</p>";
        }
        if (isset($item_result['wpid'])) {
            echo "<p><strong>WPID:</strong> {$item_result['wpid']}</p>";
        }
        if (isset($item_result['productName'])) {
            echo "<p><strong>商品名称:</strong> {$item_result['productName']}</p>";
        }
        if (isset($item_result['publishedStatus'])) {
            echo "<p><strong>发布状态:</strong> {$item_result['publishedStatus']}</p>";
        }
    }
    
    // 2. 检查库存信息
    echo "<h3>2. 检查库存信息</h3>";
    $inventory_result = $api_client->make_request("/v3/inventory?sku=" . urlencode($sku));
    
    if (is_wp_error($inventory_result)) {
        echo "<p class='error'>❌ 库存API调用失败: " . $inventory_result->get_error_message() . "</p>";
    } elseif (isset($inventory_result['error'])) {
        echo "<p class='error'>❌ 库存信息不存在</p>";
        $error_details = $inventory_result['error'][0] ?? $inventory_result['error'];
        $error_code = $error_details['code'] ?? 'UNKNOWN';
        $error_desc = $error_details['description'] ?? 'Unknown error';
        echo "<p><strong>错误代码:</strong> {$error_code}</p>";
        echo "<p><strong>错误描述:</strong> {$error_desc}</p>";
        
        if ($error_code === 'CONTENT_NOT_FOUND.GMP_INVENTORY_API') {
            echo "<div class='warning'>";
            echo "<h4>⚠️ 诊断建议：</h4>";
            echo "<ul>";
            echo "<li>商品可能刚刚发布，库存系统还未同步（通常需要几分钟到几小时）</li>";
            echo "<li>商品可能处于审核状态，尚未完全激活</li>";
            echo "<li>建议使用批量库存同步，它更容错</li>";
            echo "<li>可以稍后再试单个库存同步</li>";
            echo "</ul>";
            echo "</div>";
        }
    } else {
        echo "<p class='success'>✅ 库存信息存在</p>";
        if (isset($inventory_result['sku'])) {
            echo "<p><strong>SKU:</strong> {$inventory_result['sku']}</p>";
        }
        if (isset($inventory_result['quantity']['amount'])) {
            echo "<p><strong>当前库存:</strong> {$inventory_result['quantity']['amount']}</p>";
        }
        if (isset($inventory_result['quantity']['unit'])) {
            echo "<p><strong>库存单位:</strong> {$inventory_result['quantity']['unit']}</p>";
        }
    }
    
    // 3. 测试库存更新
    echo "<h3>3. 测试库存更新</h3>";
    $test_inventory_data = [
        'sku' => $sku,
        'quantity' => [
            'unit' => 'EACH',
            'amount' => 10
        ]
    ];
    
    $update_result = $api_client->update_inventory($test_inventory_data);
    
    if (is_wp_error($update_result)) {
        echo "<p class='error'>❌ 库存更新测试失败: " . $update_result->get_error_message() . "</p>";
    } elseif (isset($update_result['error'])) {
        echo "<p class='error'>❌ 库存更新失败</p>";
        echo "<pre>" . print_r($update_result['error'], true) . "</pre>";
    } else {
        echo "<p class='success'>✅ 库存更新测试成功</p>";
        echo "<pre>" . print_r($update_result, true) . "</pre>";
    }
}

// 显示最近的库存同步失败记录
echo "<hr>";
echo "<h2>最近的库存同步失败记录</h2>";

global $wpdb;
$inventory_table = $wpdb->prefix . 'walmart_inventory_sync';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") === $inventory_table;

if ($table_exists) {
    $failed_records = $wpdb->get_results("
        SELECT product_id, walmart_sku, status, quantity, last_sync_time, response_data
        FROM $inventory_table 
        WHERE status = 'failed' 
        ORDER BY last_sync_time DESC 
        LIMIT 10
    ");
    
    if (!empty($failed_records)) {
        echo "<table>";
        echo "<tr><th>商品ID</th><th>SKU</th><th>状态</th><th>数量</th><th>时间</th><th>错误信息</th></tr>";
        foreach ($failed_records as $record) {
            echo "<tr>";
            echo "<td>{$record->product_id}</td>";
            echo "<td>{$record->walmart_sku}</td>";
            echo "<td>{$record->status}</td>";
            echo "<td>{$record->quantity}</td>";
            echo "<td>{$record->last_sync_time}</td>";
            echo "<td>" . esc_html(substr($record->response_data, 0, 100)) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>没有失败的库存同步记录。</p>";
    }
} else {
    echo "<p class='warning'>库存同步表不存在。</p>";
}

?>

<hr>
<h2>解决建议</h2>
<div class="info">
<h3>如果SKU不存在：</h3>
<ul>
<li><strong>等待商品生效：</strong>新发布的商品可能需要几分钟到几小时才能在库存系统中生效</li>
<li><strong>检查商品状态：</strong>确认商品已成功发布且状态为"PUBLISHED"</li>
<li><strong>使用批量同步：</strong>批量库存同步更容错，建议优先使用</li>
<li><strong>检查Feed状态：</strong>确认商品发布的Feed已处理完成</li>
</ul>

<h3>如果需要立即同步：</h3>
<ul>
<li>使用批量库存同步功能</li>
<li>等待一段时间后重试单个同步</li>
<li>检查商品是否需要重新发布</li>
</ul>
</div>

</body>
</html>
