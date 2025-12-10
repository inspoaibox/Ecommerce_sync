<?php
/**
 * AJAX调试测试脚本
 * 用于测试批量同步的AJAX功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../wp-config.php';
}

echo "=== AJAX功能调试测试 ===\n\n";

// 1. 测试SKU转换功能
echo "1. 测试SKU转换功能:\n";

// 模拟POST数据
$_POST = [
    'action' => 'convert_skus_to_product_ids',
    'sku_list' => ['TEST001', 'TEST002', 'NONEXISTENT'],
    'nonce' => wp_create_nonce('sku_batch_sync_nonce')
];

echo "  模拟POST数据: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n";

// 捕获输出
ob_start();
$error_occurred = false;

try {
    handle_convert_skus_to_product_ids();
} catch (Exception $e) {
    $error_occurred = true;
    echo "  ❌ 异常: " . $e->getMessage() . "\n";
} catch (Error $e) {
    $error_occurred = true;
    echo "  ❌ 错误: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

if (!$error_occurred) {
    echo "  ✅ 函数执行完成\n";
    if (!empty($output)) {
        echo "  输出内容: " . trim($output) . "\n";
        
        // 尝试解析JSON
        $json_data = json_decode($output, true);
        if ($json_data) {
            echo "  JSON解析成功: " . json_encode($json_data, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "  ⚠️ JSON解析失败\n";
        }
    } else {
        echo "  ⚠️ 没有输出内容\n";
    }
}

// 2. 测试批量同步功能（使用少量测试数据）
echo "\n2. 测试批量同步功能:\n";

// 获取一些测试产品ID
global $wpdb;
$test_product_ids = $wpdb->get_col("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    LIMIT 3
");

if (empty($test_product_ids)) {
    echo "  ❌ 没有找到测试产品\n";
} else {
    echo "  找到测试产品ID: " . implode(', ', $test_product_ids) . "\n";
    
    // 模拟POST数据
    $_POST = [
        'action' => 'walmart_batch_sync_products',
        'product_ids' => $test_product_ids,
        'force_sync' => 0,
        'skip_validation' => 0,
        'nonce' => wp_create_nonce('sku_batch_sync_nonce')
    ];
    
    echo "  模拟POST数据: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n";
    
    // 捕获输出
    ob_start();
    $error_occurred = false;
    
    try {
        handle_walmart_batch_sync_products();
    } catch (Exception $e) {
        $error_occurred = true;
        echo "  ❌ 异常: " . $e->getMessage() . "\n";
        echo "  异常文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    } catch (Error $e) {
        $error_occurred = true;
        echo "  ❌ 错误: " . $e->getMessage() . "\n";
        echo "  错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    $output = ob_get_clean();
    
    if (!$error_occurred) {
        echo "  ✅ 函数执行完成\n";
        if (!empty($output)) {
            echo "  输出内容: " . trim($output) . "\n";
            
            // 尝试解析JSON
            $json_data = json_decode($output, true);
            if ($json_data) {
                echo "  JSON解析成功: " . json_encode($json_data, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "  ⚠️ JSON解析失败\n";
            }
        } else {
            echo "  ⚠️ 没有输出内容\n";
        }
    }
}

// 3. 检查最近的日志
echo "\n3. 检查最近的同步日志:\n";
$recent_logs = $wpdb->get_results("
    SELECT action, level, message, created_at 
    FROM {$wpdb->prefix}woo_walmart_sync_logs 
    WHERE action LIKE '%批量%' OR action LIKE '%batch%'
    ORDER BY created_at DESC 
    LIMIT 10
");

if ($recent_logs) {
    foreach ($recent_logs as $log) {
        echo "  [{$log->created_at}] {$log->level}: {$log->action} - {$log->message}\n";
    }
} else {
    echo "  没有找到相关日志\n";
}

// 4. 检查最近的批次记录
echo "\n4. 检查最近的批次记录:\n";
$recent_batches = $wpdb->get_results("
    SELECT batch_id, product_count, status, feed_id, created_at, error_message
    FROM {$wpdb->prefix}walmart_batch_feeds 
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($recent_batches) {
    foreach ($recent_batches as $batch) {
        echo "  批次: {$batch->batch_id}\n";
        echo "    产品数: {$batch->product_count}\n";
        echo "    状态: {$batch->status}\n";
        echo "    Feed ID: " . ($batch->feed_id ?: '无') . "\n";
        echo "    时间: {$batch->created_at}\n";
        if ($batch->error_message) {
            echo "    错误: {$batch->error_message}\n";
        }
        echo "    ---\n";
    }
} else {
    echo "  没有找到批次记录\n";
}

// 5. 内存和性能检查
echo "\n5. 内存和性能检查:\n";
echo "  当前内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "  峰值内存使用: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
echo "  内存限制: " . ini_get('memory_limit') . "\n";
echo "  执行时间限制: " . ini_get('max_execution_time') . "秒\n";

echo "\n=== 调试测试完成 ===\n";
