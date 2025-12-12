<?php
/**
 * 测试SKU批量同步的AJAX调用
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试SKU批量同步AJAX调用 ===\n\n";

// 测试SKU
$test_sku = 'W1191S00043';

echo "测试SKU: {$test_sku}\n\n";

global $wpdb;

// 1. 查找产品
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $test_sku
));

if (!$product_id) {
    echo "❌ 产品不存在\n";
    exit;
}

echo "✅ 找到产品ID: {$product_id}\n";

$product = wc_get_product($product_id);
echo "产品名称: {$product->get_name()}\n";
echo "产品状态: {$product->get_status()}\n\n";

// 2. 模拟AJAX调用
echo "2. 模拟AJAX调用 walmart_sync_product:\n";

// 设置POST数据
$_POST = [
    'action' => 'walmart_sync_product',
    'product_id' => $product_id,
    'force_sync' => 1,
    'skip_validation' => 0
];

echo "POST数据:\n";
print_r($_POST);
echo "\n";

// 3. 直接调用AJAX处理函数
echo "3. 直接调用AJAX处理函数:\n";

try {
    // 捕获输出
    ob_start();
    
    // 调用AJAX处理函数
    handle_walmart_sync_product_ajax();
    
    // 获取输出
    $output = ob_get_clean();
    
    echo "AJAX输出:\n";
    echo $output . "\n";
    
    // 尝试解析JSON响应
    $response = json_decode($output, true);
    if ($response) {
        echo "解析后的响应:\n";
        echo "  success: " . ($response['success'] ? 'true' : 'false') . "\n";
        if (isset($response['data'])) {
            echo "  data: " . json_encode($response['data']) . "\n";
        }
        if (isset($response['message'])) {
            echo "  message: {$response['message']}\n";
        }
    } else {
        echo "❌ 无法解析JSON响应\n";
    }
    
} catch (Exception $e) {
    echo "❌ 调用异常: " . $e->getMessage() . "\n";
    echo "异常跟踪:\n" . $e->getTraceAsString() . "\n";
}

// 4. 测试同步类是否存在
echo "\n4. 测试同步类:\n";

$class_file = WOO_WALMART_SYNC_PATH . 'includes/class-product-sync.php';
echo "类文件路径: {$class_file}\n";
echo "文件存在: " . (file_exists($class_file) ? '是' : '否') . "\n";

if (file_exists($class_file)) {
    require_once $class_file;
    echo "类存在: " . (class_exists('Woo_Walmart_Product_Sync') ? '是' : '否') . "\n";
    
    if (class_exists('Woo_Walmart_Product_Sync')) {
        echo "尝试创建同步实例...\n";
        try {
            $sync = new Woo_Walmart_Product_Sync();
            echo "✅ 同步实例创建成功\n";
            
            // 测试initiate_sync方法
            echo "测试initiate_sync方法...\n";
            $result = $sync->initiate_sync($product_id);
            echo "同步结果:\n";
            print_r($result);
            
        } catch (Exception $e) {
            echo "❌ 创建同步实例失败: " . $e->getMessage() . "\n";
        }
    }
}

// 5. 检查日志
echo "\n5. 检查最近的同步日志:\n";

$log_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$recent_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$log_table} 
     WHERE product_id = %d 
     ORDER BY created_at DESC 
     LIMIT 5",
    $product_id
));

if ($recent_logs) {
    foreach ($recent_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "级别: {$log->level}\n";
        echo "消息: {$log->message}\n";
        if (!empty($log->context)) {
            echo "上下文: {$log->context}\n";
        }
        echo "---\n";
    }
} else {
    echo "没有找到相关日志\n";
}

echo "\n=== 测试完成 ===\n";

?>
