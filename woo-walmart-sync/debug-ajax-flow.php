<?php
/**
 * 调试完整的AJAX调用流程
 * 模拟前端AJAX请求，找出500错误的真正原因
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 调试完整AJAX调用流程 ===\n\n";

// 1. 模拟不同大小的批次
$test_cases = [
    ['size' => 50, 'description' => '小批次'],
    ['size' => 100, 'description' => '中批次'],
    ['size' => 150, 'description' => '大批次（会分批）']
];

foreach ($test_cases as $test_case) {
    $size = $test_case['size'];
    $description = $test_case['description'];
    
    echo "=== 测试 {$description} ({$size}个产品) ===\n";
    
    // 获取产品ID
    global $wpdb;
    $products = $wpdb->get_results($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status = 'publish' 
        ORDER BY ID DESC 
        LIMIT %d
    ", $size));
    
    if (count($products) < $size) {
        echo "⚠️  只找到 " . count($products) . " 个产品，跳过测试\n\n";
        continue;
    }
    
    $product_ids = array_column($products, 'ID');
    
    // 模拟AJAX环境
    $_POST = [
        'action' => 'walmart_batch_sync_products',
        'product_ids' => $product_ids,
        'force_sync' => 1,
        'skip_validation' => 0,
        'nonce' => wp_create_nonce('sku_batch_sync_nonce')
    ];
    
    echo "开始模拟AJAX调用...\n";
    $start_time = microtime(true);
    
    // 捕获所有输出
    ob_start();
    $ajax_success = false;
    $ajax_response = null;
    $ajax_error = null;
    
    try {
        // 直接调用AJAX处理函数
        handle_walmart_batch_sync_products();
        $ajax_success = true;
        
    } catch (Exception $e) {
        $ajax_error = $e->getMessage();
        echo "❌ AJAX异常: " . $e->getMessage() . "\n";
        echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    } catch (Error $e) {
        $ajax_error = $e->getMessage();
        echo "❌ PHP错误: " . $e->getMessage() . "\n";
        echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    $output = ob_get_clean();
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    
    echo "执行时间: {$execution_time}ms\n";
    echo "内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    
    // 分析输出
    if ($ajax_success && $output) {
        echo "✅ AJAX调用完成\n";
        echo "输出长度: " . strlen($output) . " 字符\n";
        
        // 尝试解析JSON
        $json_data = json_decode($output, true);
        if ($json_data) {
            echo "✅ JSON解析成功\n";
            
            if (isset($json_data['success'])) {
                if ($json_data['success']) {
                    echo "✅ 业务逻辑成功\n";
                    echo "批次ID: " . ($json_data['data']['batch_id'] ?? '无') . "\n";
                    echo "Feed ID: " . ($json_data['data']['feed_id'] ?? '无') . "\n";
                    echo "消息: " . ($json_data['data']['message'] ?? '无') . "\n";
                } else {
                    echo "❌ 业务逻辑失败\n";
                    echo "错误消息: " . ($json_data['data']['message'] ?? '无') . "\n";
                }
            } else {
                echo "⚠️  响应格式异常，没有success字段\n";
                echo "响应内容: " . substr($output, 0, 200) . "...\n";
            }
        } else {
            echo "❌ JSON解析失败\n";
            echo "JSON错误: " . json_last_error_msg() . "\n";
            echo "原始输出前200字符: " . substr($output, 0, 200) . "\n";
            
            // 检查是否包含HTML或其他非JSON内容
            if (strpos($output, '<') !== false) {
                echo "⚠️  输出包含HTML内容，可能有PHP错误或警告\n";
            }
        }
    } elseif ($ajax_error) {
        echo "❌ AJAX调用失败: {$ajax_error}\n";
    } else {
        echo "❌ AJAX调用无输出\n";
    }
    
    // 检查最新的批次记录
    echo "\n检查最新批次记录:\n";
    $latest_batch = $wpdb->get_row("
        SELECT batch_id, status, feed_id, product_count, created_at 
        FROM {$wpdb->prefix}walmart_batch_feeds 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    if ($latest_batch) {
        echo "最新批次: {$latest_batch->batch_id}\n";
        echo "状态: {$latest_batch->status}\n";
        echo "Feed ID: " . ($latest_batch->feed_id ?: '无') . "\n";
        echo "产品数量: {$latest_batch->product_count}\n";
        echo "创建时间: {$latest_batch->created_at}\n";
        
        // 检查是否是刚才创建的
        $time_diff = time() - strtotime($latest_batch->created_at);
        if ($time_diff < 60) {
            echo "✅ 这是刚才创建的批次\n";
            
            // 检查矛盾情况
            if ($ajax_success && $latest_batch->status === 'ERROR') {
                echo "⚠️  矛盾: AJAX成功但批次状态为ERROR\n";
            } elseif (!$ajax_success && $latest_batch->status === 'SUBMITTED') {
                echo "⚠️  矛盾: AJAX失败但批次状态为SUBMITTED\n";
            }
        }
    }
    
    echo "---\n\n";
    
    // 清理内存
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // 避免测试过多导致超时
    if ($size >= 100) {
        echo "⚠️  为避免超时，跳过更大的测试\n";
        break;
    }
}

echo "=== 总结分析 ===\n";
echo "基于AJAX流程测试，可能的500错误原因:\n\n";

echo "1. **输出缓冲问题**: PHP错误或警告混入JSON输出\n";
echo "2. **内存/时间限制**: 大批量处理触发PHP限制\n";
echo "3. **异常未捕获**: 某些异常没有被正确处理\n";
echo "4. **数据库连接问题**: 大量查询导致连接超时\n";
echo "5. **API调用超时**: Walmart API响应超时\n\n";

echo "**前端看到500错误的可能情况:**\n";
echo "- 服务器返回了非JSON内容（HTML错误页面）\n";
echo "- 响应被截断或损坏\n";
echo "- 网络连接在处理过程中断开\n";
echo "- PHP Fatal Error导致脚本终止\n";

echo "\n=== 调试完成 ===\n";
