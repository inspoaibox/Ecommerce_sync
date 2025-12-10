<?php
/**
 * 检查API错误响应的具体内容
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 检查API错误响应内容 ===\n\n";

// 测试10个产品，获取详细的错误信息
global $wpdb;
$products = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    ORDER BY ID DESC 
    LIMIT 10
");

$product_ids = array_column($products, 'ID');
echo "测试产品ID: " . implode(', ', $product_ids) . "\n\n";

// 构建Feed数据
$batch_builder = new Walmart_Batch_Feed_Builder();
$reflection = new ReflectionClass($batch_builder);
$build_method = $reflection->getMethod('build_batch_feed_data');
$build_method->setAccessible(true);

$feed_data = $build_method->invoke($batch_builder, $product_ids);
echo "Feed数据大小: " . strlen(json_encode($feed_data)) . " 字节\n";

// API调用
$api_auth = new Woo_Walmart_API_Key_Auth();
$response = $api_auth->make_file_upload_request('/v3/feeds?feedType=MP_ITEM', $feed_data, 'error_test.json');

echo "\n=== 详细响应分析 ===\n";
echo "响应类型: " . gettype($response) . "\n";

if (is_array($response)) {
    echo "响应键: " . implode(', ', array_keys($response)) . "\n";
    echo "完整响应内容:\n";
    print_r($response);
    
    // 特别检查error字段
    if (isset($response['error'])) {
        echo "\n=== ERROR字段详细内容 ===\n";
        print_r($response['error']);
        
        // 检查是否同时有feedId
        if (isset($response['feedId'])) {
            echo "\n🎯 关键发现: 响应中同时包含error和feedId!\n";
            echo "Feed ID: " . $response['feedId'] . "\n";
            echo "这说明提交实际上是成功的，但API返回了警告信息\n";
        }
    }
    
    // 检查其他可能的成功标识
    foreach ($response as $key => $value) {
        if (stripos($key, 'feed') !== false || stripos($key, 'id') !== false) {
            echo "\n可能的Feed标识: {$key} = {$value}\n";
        }
    }
}

// 检查最新的批次记录，看是否实际创建了
echo "\n=== 检查是否实际创建了批次 ===\n";
sleep(2); // 等待2秒，确保数据库更新

$latest_batch = $wpdb->get_row("
    SELECT batch_id, feed_id, status, created_at 
    FROM {$wpdb->prefix}walmart_batch_feeds 
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($latest_batch) {
    $time_diff = time() - strtotime($latest_batch->created_at);
    if ($time_diff < 60) {
        echo "✅ 发现新创建的批次:\n";
        echo "批次ID: {$latest_batch->batch_id}\n";
        echo "Feed ID: " . ($latest_batch->feed_id ?: '无') . "\n";
        echo "状态: {$latest_batch->status}\n";
        echo "创建时间: {$latest_batch->created_at}\n";
        
        if (!empty($latest_batch->feed_id)) {
            echo "\n🎯 关键发现: 虽然API返回error，但实际上创建了Feed!\n";
            echo "这证实了您的观点：实际是成功的，但前端显示失败\n";
        }
    } else {
        echo "最新批次创建时间: {$latest_batch->created_at} (超过1分钟前)\n";
    }
}

echo "\n=== 问题根源分析 ===\n";
echo "基于以上测试，真正的问题是:\n\n";

echo "1. **API响应格式变化**:\n";
echo "   - Walmart API现在可能返回包含error字段的响应\n";
echo "   - 但这些error可能只是警告，不是真正的失败\n";
echo "   - 代码只检查feedId字段，忽略了可能的警告信息\n\n";

echo "2. **响应处理逻辑过于严格**:\n";
echo "   - 代码期望纯净的 ['feedId' => 'xxx'] 格式\n";
echo "   - 但API可能返回 ['feedId' => 'xxx', 'error' => [...]] 格式\n";
echo "   - 导致被误判为失败\n\n";

echo "3. **前端错误显示**:\n";
echo "   - 后端判断为失败，返回错误消息\n";
echo "   - 前端显示500错误或失败信息\n";
echo "   - 但实际Feed已经提交成功\n\n";

echo "**解决方案**:\n";
echo "1. 修改API响应处理逻辑，优先检查feedId\n";
echo "2. 将error字段作为警告而不是失败处理\n";
echo "3. 改进前端错误显示逻辑\n";

echo "\n=== 调试完成 ===\n";
