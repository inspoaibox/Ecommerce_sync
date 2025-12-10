<?php
/**
 * 使用最近同步的产品ID测试AJAX功能
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 使用最近同步的产品ID测试 ===\n\n";

// 使用您提供的最近同步的产品ID
$recent_product_ids = [17801, 17800, 17799, 17798, 17797, 17796, 17795, 17794, 17793, 17792];

echo "测试产品ID: " . implode(', ', $recent_product_ids) . "\n\n";

// 1. 检查这些产品的基本信息
echo "1. 检查产品基本信息:\n";
foreach (array_slice($recent_product_ids, 0, 5) as $product_id) {
    $product = wc_get_product($product_id);
    if ($product) {
        echo "  产品 {$product_id}: ✅ 存在\n";
        echo "    名称: " . $product->get_name() . "\n";
        echo "    SKU: " . ($product->get_sku() ?: '无') . "\n";
        echo "    价格: " . $product->get_price() . "\n";
        echo "    状态: " . $product->get_status() . "\n";
        
        // 检查是否已有UPC
        global $wpdb;
        $existing_upc = $wpdb->get_var($wpdb->prepare(
            "SELECT upc_code FROM {$wpdb->prefix}walmart_upc_pool WHERE product_id = %d AND is_used = 1",
            $product_id
        ));
        echo "    UPC: " . ($existing_upc ?: '无') . "\n";
        
        // 检查分类
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
        echo "    分类: " . (empty($categories) ? '无' : implode(', ', $categories)) . "\n";
        echo "    ---\n";
    } else {
        echo "  产品 {$product_id}: ❌ 不存在\n";
    }
}

// 2. 测试批量同步AJAX（使用前3个产品）
echo "\n2. 测试批量同步AJAX (使用前3个产品):\n";

$test_ids = array_slice($recent_product_ids, 0, 3);
echo "  测试产品ID: " . implode(', ', $test_ids) . "\n";

// 设置POST数据
$_POST = [
    'action' => 'walmart_batch_sync_products',
    'product_ids' => $test_ids,
    'force_sync' => 1, // 强制同步
    'skip_validation' => 0,
    'nonce' => wp_create_nonce('sku_batch_sync_nonce')
];

echo "  POST数据: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n";

// 捕获输出
ob_start();
$error_occurred = false;
$start_time = microtime(true);

try {
    echo "  开始调用 handle_walmart_batch_sync_products...\n";
    handle_walmart_batch_sync_products();
    echo "  ✅ 函数调用完成\n";
} catch (Exception $e) {
    $error_occurred = true;
    echo "  ❌ 异常: " . $e->getMessage() . "\n";
    echo "  文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    $error_occurred = true;
    echo "  ❌ 错误: " . $e->getMessage() . "\n";
    echo "  文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$execution_time = round((microtime(true) - $start_time) * 1000, 2);
$output = ob_get_clean();

echo "  执行时间: {$execution_time}ms\n";
echo "  输出内容: " . ($output ? trim($output) : '无') . "\n";

if ($output && !$error_occurred) {
    // 尝试解析JSON
    $json_data = json_decode($output, true);
    if ($json_data) {
        echo "  JSON解析: ✅ 成功\n";
        echo "  响应类型: " . ($json_data['success'] ? '✅ 成功' : '❌ 失败') . "\n";
        
        if ($json_data['success']) {
            echo "  批次ID: " . ($json_data['data']['batch_id'] ?? '无') . "\n";
            echo "  Feed ID: " . ($json_data['data']['feed_id'] ?? '无') . "\n";
            echo "  消息: " . ($json_data['data']['message'] ?? '无') . "\n";
            echo "  产品数量: " . ($json_data['data']['product_count'] ?? '无') . "\n";
        } else {
            echo "  错误消息: " . ($json_data['data']['message'] ?? '无') . "\n";
        }
    } else {
        echo "  JSON解析: ❌ 失败\n";
        echo "  原始输出长度: " . strlen($output) . " 字符\n";
        echo "  输出前100字符: " . substr($output, 0, 100) . "\n";
    }
}

// 3. 检查最新的批次记录
echo "\n3. 检查最新的批次记录:\n";
global $wpdb;
$latest_batches = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}walmart_batch_feeds 
    ORDER BY created_at DESC 
    LIMIT 3
");

if ($latest_batches) {
    foreach ($latest_batches as $batch) {
        echo "  批次: {$batch->batch_id}\n";
        echo "    产品数量: {$batch->product_count}\n";
        echo "    状态: {$batch->status}\n";
        echo "    Feed ID: " . ($batch->feed_id ?: '无') . "\n";
        echo "    创建时间: {$batch->created_at}\n";
        echo "    错误消息: " . ($batch->error_message ?: '无') . "\n";
        
        // 检查产品ID
        $product_ids = json_decode($batch->product_ids, true);
        if ($product_ids) {
            echo "    产品ID: " . implode(', ', $product_ids) . "\n";
        }
        echo "    ---\n";
    }
} else {
    echo "  ❌ 没有找到批次记录\n";
}

// 4. 检查最新的同步日志
echo "\n4. 检查最新的同步日志:\n";
$latest_logs = $wpdb->get_results("
    SELECT action, level, message, created_at, data
    FROM {$wpdb->prefix}woo_walmart_sync_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    AND (action LIKE '%批量%' OR action LIKE '%batch%' OR action LIKE '%Feed%')
    ORDER BY created_at DESC 
    LIMIT 10
");

if ($latest_logs) {
    foreach ($latest_logs as $log) {
        echo "  [{$log->created_at}] {$log->level}: {$log->action}\n";
        echo "    消息: {$log->message}\n";
        if ($log->data) {
            $log_data = json_decode($log->data, true);
            if ($log_data && isset($log_data['feed_id'])) {
                echo "    Feed ID: {$log_data['feed_id']}\n";
            }
        }
        echo "    ---\n";
    }
} else {
    echo "  没有找到最近10分钟的相关日志\n";
}

// 5. 测试单个产品的完整流程
echo "\n5. 测试单个产品的完整流程:\n";
$single_product_id = $recent_product_ids[0];
echo "  测试产品ID: {$single_product_id}\n";

try {
    $batch_builder = new Walmart_Batch_Feed_Builder();
    $reflection = new ReflectionClass($batch_builder);
    
    // 测试build_batch_feed_data方法
    $build_method = $reflection->getMethod('build_batch_feed_data');
    $build_method->setAccessible(true);
    
    $feed_data = $build_method->invoke($batch_builder, [$single_product_id]);
    
    if ($feed_data && !empty($feed_data['MPItem'])) {
        echo "  ✅ 单个产品Feed构建成功\n";
        echo "  MPItem数量: " . count($feed_data['MPItem']) . "\n";
        echo "  数据大小: " . strlen(json_encode($feed_data)) . " 字节\n";
    } else {
        echo "  ❌ 单个产品Feed构建失败\n";
        if ($feed_data) {
            echo "  Feed数据: " . json_encode($feed_data, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "  ❌ 单个产品测试异常: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
echo "内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "峰值内存: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
