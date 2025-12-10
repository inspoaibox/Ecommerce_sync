<?php
/**
 * 找到API调用失败的确切阈值
 * 逐步增加产品数量，找到失败点
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 寻找API调用失败阈值 ===\n\n";

// 逐步测试不同的产品数量
$test_sizes = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50];

$results = [];

foreach ($test_sizes as $size) {
    echo "=== 测试 {$size} 个产品 ===\n";
    
    // 获取产品
    global $wpdb;
    $products = $wpdb->get_results($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status = 'publish' 
        ORDER BY ID DESC 
        LIMIT %d
    ", $size));
    
    if (count($products) < $size) {
        echo "⚠️  只找到 " . count($products) . " 个产品，跳过\n\n";
        continue;
    }
    
    $product_ids = array_column($products, 'ID');
    
    // 构建Feed数据
    try {
        $batch_builder = new Walmart_Batch_Feed_Builder();
        $reflection = new ReflectionClass($batch_builder);
        $build_method = $reflection->getMethod('build_batch_feed_data');
        $build_method->setAccessible(true);
        
        $feed_data = $build_method->invoke($batch_builder, $product_ids);
        
        if (empty($feed_data['MPItem'])) {
            echo "❌ Feed构建失败\n\n";
            $results[$size] = ['status' => 'build_failed', 'reason' => 'MPItem为空'];
            continue;
        }
        
        $data_size = strlen(json_encode($feed_data));
        $data_size_kb = round($data_size / 1024, 1);
        $successful_items = count($feed_data['MPItem']);
        
        echo "Feed数据: {$successful_items}个产品, {$data_size_kb}KB\n";
        
        // API调用测试
        $api_auth = new Woo_Walmart_API_Key_Auth();
        
        $start_time = microtime(true);
        $response = $api_auth->make_file_upload_request('/v3/feeds?feedType=MP_ITEM', $feed_data, 'threshold_test.json');
        $api_time = round((microtime(true) - $start_time) * 1000, 2);
        
        echo "API调用时间: {$api_time}ms\n";
        
        // 分析响应
        if (is_wp_error($response)) {
            echo "❌ WP_Error: " . $response->get_error_message() . "\n";
            $results[$size] = [
                'status' => 'wp_error',
                'reason' => $response->get_error_message(),
                'data_size_kb' => $data_size_kb,
                'api_time_ms' => $api_time,
                'items' => $successful_items
            ];
            
        } elseif (is_array($response) && !empty($response['feedId'])) {
            echo "✅ 成功: Feed ID = " . $response['feedId'] . "\n";
            $results[$size] = [
                'status' => 'success',
                'feed_id' => $response['feedId'],
                'data_size_kb' => $data_size_kb,
                'api_time_ms' => $api_time,
                'items' => $successful_items
            ];
            
        } else {
            echo "❌ 失败: 响应格式异常\n";
            echo "响应类型: " . gettype($response) . "\n";
            if (is_string($response)) {
                echo "响应内容: " . substr($response, 0, 200) . "...\n";
            } elseif (is_array($response)) {
                echo "响应键: " . implode(', ', array_keys($response)) . "\n";
            }
            
            $results[$size] = [
                'status' => 'format_error',
                'reason' => '响应格式异常',
                'response_type' => gettype($response),
                'data_size_kb' => $data_size_kb,
                'api_time_ms' => $api_time,
                'items' => $successful_items
            ];
        }
        
    } catch (Exception $e) {
        echo "❌ 异常: " . $e->getMessage() . "\n";
        $results[$size] = [
            'status' => 'exception',
            'reason' => $e->getMessage(),
            'items' => $size
        ];
    }
    
    echo "内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    echo "---\n\n";
    
    // 如果连续失败，提前结束
    if (isset($results[$size]) && $results[$size]['status'] !== 'success') {
        $recent_failures = 0;
        $recent_sizes = array_slice($test_sizes, max(0, array_search($size, $test_sizes) - 2), 3);
        foreach ($recent_sizes as $recent_size) {
            if (isset($results[$recent_size]) && $results[$recent_size]['status'] !== 'success') {
                $recent_failures++;
            }
        }
        
        if ($recent_failures >= 2) {
            echo "⚠️  连续失败，提前结束测试\n";
            break;
        }
    }
    
    // 清理内存
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // 避免过于频繁的API调用
    sleep(1);
}

// 分析结果
echo "=== 结果分析 ===\n";

$success_sizes = [];
$failure_sizes = [];

foreach ($results as $size => $result) {
    if ($result['status'] === 'success') {
        $success_sizes[] = $size;
        echo "✅ {$size}个产品: 成功 ({$result['data_size_kb']}KB, {$result['api_time_ms']}ms)\n";
    } else {
        $failure_sizes[] = $size;
        echo "❌ {$size}个产品: {$result['status']} - {$result['reason']}\n";
        if (isset($result['data_size_kb'])) {
            echo "   数据大小: {$result['data_size_kb']}KB\n";
        }
    }
}

echo "\n=== 阈值分析 ===\n";

if (!empty($success_sizes) && !empty($failure_sizes)) {
    $max_success = max($success_sizes);
    $min_failure = min($failure_sizes);
    
    echo "最大成功: {$max_success}个产品\n";
    echo "最小失败: {$min_failure}个产品\n";
    
    if ($min_failure > $max_success) {
        echo "🎯 失败阈值在 {$max_success} - {$min_failure} 个产品之间\n";
        
        // 计算对应的数据大小
        if (isset($results[$max_success]['data_size_kb']) && isset($results[$min_failure]['data_size_kb'])) {
            echo "对应数据大小: {$results[$max_success]['data_size_kb']}KB - {$results[$min_failure]['data_size_kb']}KB\n";
        }
    }
} elseif (empty($failure_sizes)) {
    echo "✅ 所有测试都成功，阈值可能更高\n";
} else {
    echo "❌ 所有测试都失败，阈值可能更低\n";
}

echo "\n=== 建议 ===\n";
if (!empty($success_sizes)) {
    $recommended_size = max($success_sizes);
    echo "建议的批次大小: {$recommended_size}个产品\n";
    echo "这样可以确保稳定的成功率\n";
} else {
    echo "建议进一步测试更小的批次大小\n";
}

echo "\n=== 测试完成 ===\n";
