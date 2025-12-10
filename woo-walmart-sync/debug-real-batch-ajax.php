<?php
/**
 * 模拟真实的200个产品批量AJAX调用
 * 找出前端显示失败的真正原因
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 模拟真实批量AJAX调用 ===\n\n";

// 获取200个产品
global $wpdb;
$products = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    ORDER BY ID DESC 
    LIMIT 200
");

if (count($products) < 200) {
    echo "只找到 " . count($products) . " 个产品，继续测试\n";
}

$product_ids = array_column($products, 'ID');
echo "测试产品数量: " . count($product_ids) . "\n";
echo "产品ID范围: " . min($product_ids) . " - " . max($product_ids) . "\n\n";

// 模拟AJAX环境
$_POST = [
    'action' => 'walmart_batch_sync_products',
    'product_ids' => $product_ids,
    'force_sync' => 1,
    'skip_validation' => 0,
    'nonce' => wp_create_nonce('sku_batch_sync_nonce')
];

echo "开始模拟完整的AJAX调用流程...\n";
$start_time = microtime(true);

// 捕获所有输出
ob_start();
$ajax_success = false;
$ajax_error = null;

try {
    // 直接调用AJAX处理函数
    handle_walmart_batch_sync_products();
    $ajax_success = true;
    
} catch (Exception $e) {
    $ajax_error = "Exception: " . $e->getMessage();
    echo "❌ AJAX异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
} catch (Error $e) {
    $ajax_error = "Error: " . $e->getMessage();
    echo "❌ PHP错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$output = ob_get_clean();
$execution_time = round((microtime(true) - $start_time) * 1000, 2);

echo "AJAX执行完成\n";
echo "执行时间: {$execution_time}ms\n";
echo "内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "输出长度: " . strlen($output) . " 字符\n\n";

// 分析AJAX输出
echo "=== AJAX输出分析 ===\n";

if ($ajax_success && $output) {
    // 尝试解析JSON
    $json_data = json_decode($output, true);
    
    if ($json_data) {
        echo "✅ JSON解析成功\n";
        
        if (isset($json_data['success'])) {
            if ($json_data['success']) {
                echo "✅ AJAX返回成功\n";
                echo "批次ID: " . ($json_data['data']['batch_id'] ?? '无') . "\n";
                echo "Feed ID: " . ($json_data['data']['feed_id'] ?? '无') . "\n";
                echo "消息: " . ($json_data['data']['message'] ?? '无') . "\n";
                
                if (isset($json_data['data']['sub_batches'])) {
                    echo "子批次数量: " . $json_data['data']['sub_batches'] . "\n";
                    echo "成功子批次: " . ($json_data['data']['successful_sub_batches'] ?? '无') . "\n";
                }
                
            } else {
                echo "❌ AJAX返回失败\n";
                echo "错误消息: " . ($json_data['data']['message'] ?? '无') . "\n";
                
                // 这里就是前端看到失败的原因！
                echo "\n🎯 这就是前端显示失败的原因！\n";
            }
        }
        
    } else {
        echo "❌ JSON解析失败\n";
        echo "JSON错误: " . json_last_error_msg() . "\n";
        echo "原始输出: " . substr($output, 0, 500) . "...\n";
    }
    
} elseif ($ajax_error) {
    echo "❌ AJAX调用失败: {$ajax_error}\n";
} else {
    echo "❌ AJAX无输出\n";
}

// 检查数据库中的实际结果
echo "\n=== 检查数据库实际结果 ===\n";
sleep(3); // 等待数据库更新

// 查找最近创建的批次
$recent_batches = $wpdb->get_results("
    SELECT batch_id, status, feed_id, product_count, created_at 
    FROM {$wpdb->prefix}walmart_batch_feeds 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($recent_batches) {
    echo "最近5分钟创建的批次:\n";
    foreach ($recent_batches as $batch) {
        echo "  {$batch->batch_id} - {$batch->status} - {$batch->product_count}个产品";
        if ($batch->feed_id) {
            echo " - Feed ID: {$batch->feed_id}";
        }
        echo " - {$batch->created_at}\n";
    }
    
    // 检查是否有刚才创建的批次
    $latest_batch = $recent_batches[0];
    $time_diff = time() - strtotime($latest_batch->created_at);
    
    if ($time_diff < 300) { // 5分钟内
        echo "\n✅ 发现刚才创建的批次: {$latest_batch->batch_id}\n";
        echo "状态: {$latest_batch->status}\n";
        echo "产品数量: {$latest_batch->product_count}\n";
        
        if ($latest_batch->feed_id) {
            echo "Feed ID: {$latest_batch->feed_id}\n";
            echo "🎯 关键发现: 批次实际创建成功了！\n";
            
            if (!$ajax_success || (isset($json_data) && !$json_data['success'])) {
                echo "⚠️  矛盾: 数据库显示成功，但AJAX返回失败\n";
                echo "这就是您说的问题：实际成功但前端显示失败！\n";
            }
        }
        
        // 检查子批次
        $sub_batches = $wpdb->get_results($wpdb->prepare("
            SELECT batch_id, status, feed_id, product_count 
            FROM {$wpdb->prefix}walmart_batch_feeds 
            WHERE parent_batch_id = %s 
            ORDER BY chunk_index
        ", $latest_batch->batch_id));
        
        if ($sub_batches) {
            echo "\n子批次详情:\n";
            $successful_subs = 0;
            foreach ($sub_batches as $sub) {
                echo "  {$sub->batch_id} - {$sub->status} - {$sub->product_count}个产品";
                if ($sub->feed_id) {
                    echo " - Feed ID: {$sub->feed_id}";
                    $successful_subs++;
                }
                echo "\n";
            }
            
            echo "成功的子批次: {$successful_subs}/" . count($sub_batches) . "\n";
            
            if ($successful_subs == count($sub_batches)) {
                echo "✅ 所有子批次都成功！\n";
            } elseif ($successful_subs > 0) {
                echo "⚠️  部分子批次成功\n";
            } else {
                echo "❌ 所有子批次都失败\n";
            }
        }
    }
}

echo "\n=== 结论 ===\n";
echo "基于这次完整的测试，我们可以确定:\n";
echo "1. AJAX调用的实际结果\n";
echo "2. 数据库中的实际记录\n";
echo "3. 前端显示失败的具体原因\n";
echo "4. 后端处理与前端显示的差异\n";

echo "\n=== 测试完成 ===\n";
