<?php
/**
 * 测试SKU筛选逻辑
 */

// WordPress 加载
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
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
    die("错误：无法找到 WordPress。\n");
}

echo "=== 测试SKU筛选逻辑 ===\n\n";

global $wpdb;

// 测试SKU
$test_sku = 'W714P357249';

echo "测试SKU: {$test_sku}\n\n";

// 步骤1: 通过SKU查找product_id
echo "【步骤1: 查找产品ID】\n";
echo str_repeat("-", 80) . "\n";

$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
    $test_sku
));

if ($product_id) {
    echo "✅ 找到产品ID: {$product_id}\n";
    
    // 获取产品信息
    $product = wc_get_product($product_id);
    if ($product) {
        echo "产品名称: {$product->get_name()}\n";
        echo "产品SKU: {$product->get_sku()}\n";
    }
} else {
    echo "❌ 未找到产品ID\n";
}

echo "\n";

// 步骤2: 查找日志表
echo "【步骤2: 检查日志表】\n";
echo str_repeat("-", 80) . "\n";

$logs_table = $wpdb->prefix . 'walmart_sync_logs';
$old_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$table_name = $logs_table;
if($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") != $logs_table) {
    if($wpdb->get_var("SHOW TABLES LIKE '$old_table'") == $old_table) {
        $table_name = $old_table;
        echo "使用旧表: {$old_table}\n";
    } else {
        echo "❌ 日志表不存在\n";
        exit;
    }
} else {
    echo "使用新表: {$logs_table}\n";
}

// 检查表结构
$columns = $wpdb->get_results("DESCRIBE {$table_name}");
echo "\n表结构:\n";
foreach ($columns as $column) {
    echo "  - {$column->Field} ({$column->Type})\n";
}

echo "\n";

// 步骤3: 测试筛选查询
echo "【步骤3: 测试筛选查询】\n";
echo str_repeat("-", 80) . "\n";

$where_conditions = ['1=1'];
$where_values = [];

if ($product_id) {
    // 如果找到product_id，优先使用product_id筛选
    $where_conditions[] = "(product_id = %d OR request LIKE %s OR response LIKE %s)";
    $where_values[] = $product_id;
    $where_values[] = '%' . $wpdb->esc_like($test_sku) . '%';
    $where_values[] = '%' . $wpdb->esc_like($test_sku) . '%';
    
    echo "筛选条件: product_id = {$product_id} OR request/response LIKE '%{$test_sku}%'\n\n";
} else {
    // 如果没有找到product_id，只在request和response中搜索
    $where_conditions[] = "(request LIKE %s OR response LIKE %s)";
    $where_values[] = '%' . $wpdb->esc_like($test_sku) . '%';
    $where_values[] = '%' . $wpdb->esc_like($test_sku) . '%';
    
    echo "筛选条件: request/response LIKE '%{$test_sku}%'\n\n";
}

$where_clause = implode(' AND ', $where_conditions);

// 获取总数
$total_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
$total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));

echo "找到日志总数: {$total_items}\n\n";

if ($total_items > 0) {
    // 获取前5条日志
    $logs_query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT 5";
    $logs = $wpdb->get_results($wpdb->prepare($logs_query, $where_values));
    
    echo "前5条日志:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($logs as $log) {
        echo "\n日志ID: {$log->id}\n";
        echo "时间: {$log->created_at}\n";
        
        // 根据表结构显示字段
        if (isset($log->level)) {
            echo "级别: {$log->level}\n";
        } elseif (isset($log->status)) {
            echo "状态: {$log->status}\n";
        }
        
        if (isset($log->operation)) {
            echo "操作: {$log->operation}\n";
        } elseif (isset($log->action)) {
            echo "操作: {$log->action}\n";
        }
        
        if (isset($log->message)) {
            echo "消息: " . substr($log->message, 0, 100) . (strlen($log->message) > 100 ? '...' : '') . "\n";
        }
        
        if (isset($log->product_id)) {
            echo "产品ID: {$log->product_id}\n";
        }
        
        // 检查request中是否包含SKU
        if (isset($log->request) && !empty($log->request)) {
            if (stripos($log->request, $test_sku) !== false) {
                echo "✅ Request中包含SKU\n";
            }
        }
        
        // 检查response中是否包含SKU
        if (isset($log->response) && !empty($log->response)) {
            if (stripos($log->response, $test_sku) !== false) {
                echo "✅ Response中包含SKU\n";
            }
        }
        
        echo str_repeat("-", 40) . "\n";
    }
} else {
    echo "⚠️ 没有找到相关日志\n";
    echo "\n可能的原因:\n";
    echo "1. 该SKU的产品还没有同步过\n";
    echo "2. 日志已被清除\n";
    echo "3. SKU不正确\n";
}

echo "\n";

// 步骤4: 测试其他SKU
echo "【步骤4: 测试其他SKU】\n";
echo str_repeat("-", 80) . "\n";

$other_skus = ['W487S00390', 'WF310165AAA', 'WY000387AAA'];

foreach ($other_skus as $sku) {
    $pid = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
        $sku
    ));
    
    if ($pid) {
        $where_cond = ['1=1'];
        $where_vals = [];
        $where_cond[] = "(product_id = %d OR request LIKE %s OR response LIKE %s)";
        $where_vals[] = $pid;
        $where_vals[] = '%' . $wpdb->esc_like($sku) . '%';
        $where_vals[] = '%' . $wpdb->esc_like($sku) . '%';
        
        $where_str = implode(' AND ', $where_cond);
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE {$where_str}",
            $where_vals
        ));
        
        echo "SKU: {$sku} → 产品ID: {$pid} → 日志数: {$count}\n";
    } else {
        echo "SKU: {$sku} → ❌ 未找到产品\n";
    }
}

echo "\n";

// 步骤5: 生成测试URL
echo "【步骤5: 生成测试URL】\n";
echo str_repeat("-", 80) . "\n";

$test_url = admin_url('admin.php?page=woo-walmart-sync-logs&sku_filter=' . urlencode($test_sku));
echo "测试URL:\n";
echo $test_url . "\n\n";

echo "在浏览器中访问此URL可以测试SKU筛选功能。\n";

echo "\n=== 测试完成 ===\n";
?>

