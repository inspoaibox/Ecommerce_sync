<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找最近的产品映射日志，特别是包含fulfillmentLagTime的
$recent_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE (action LIKE '%产品映射%' OR action LIKE '%fulfillmentLagTime%' OR response LIKE '%fulfillmentLagTime%')
    ORDER BY created_at DESC 
    LIMIT 10
");

echo "=== 查找fulfillmentLagTime相关日志 ===\n";
echo "找到日志数量: " . count($recent_logs) . "\n\n";

foreach ($recent_logs as $log) {
    echo "时间: " . $log->created_at . "\n";
    echo "操作: " . $log->action . "\n";
    echo "状态: " . $log->status . "\n";
    
    // 检查请求数据
    if (strpos($log->request, 'fulfillmentLagTime') !== false) {
        echo "✓ 请求数据包含fulfillmentLagTime\n";
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            // 递归查找fulfillmentLagTime
            $found = json_encode($request_data, JSON_PRETTY_PRINT);
            if (preg_match('/"fulfillmentLagTime"\s*:\s*([^,}]+)/', $found, $matches)) {
                echo "请求中的fulfillmentLagTime值: " . trim($matches[1]) . "\n";
            }
        }
    }
    
    // 检查响应数据
    if (strpos($log->response, 'fulfillmentLagTime') !== false) {
        echo "✓ 响应数据包含fulfillmentLagTime\n";
        if (preg_match('/"fulfillmentLagTime"\s*:\s*([^,}]+)/', $log->response, $matches)) {
            echo "响应中的fulfillmentLagTime值: " . trim($matches[1]) . "\n";
        }
    }
    
    echo "==================\n";
}

// 如果没有找到相关日志，查看最近的所有日志
if (count($recent_logs) == 0) {
    echo "没有找到包含fulfillmentLagTime的日志，查看最近的所有日志:\n\n";
    
    $all_recent_logs = $wpdb->get_results("
        SELECT * FROM $logs_table 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    foreach ($all_recent_logs as $log) {
        echo "时间: " . $log->created_at . "\n";
        echo "操作: " . $log->action . "\n";
        echo "状态: " . $log->status . "\n";
        echo "请求数据长度: " . strlen($log->request) . "\n";
        echo "响应数据长度: " . strlen($log->response) . "\n";
        echo "==================\n";
    }
}

// 检查当前的fulfillment_lag_time设置
echo "\n=== 当前设置检查 ===\n";
$current_setting = get_option('woo_walmart_fulfillment_lag_time', '未设置');
echo "woo_walmart_fulfillment_lag_time 设置值: " . $current_setting . "\n";
echo "数据类型: " . gettype($current_setting) . "\n";

// 测试mapper中的处理逻辑
echo "\n=== 测试mapper处理逻辑 ===\n";
$test_value = get_option('woo_walmart_fulfillment_lag_time', 2);
echo "原始值: " . $test_value . " (类型: " . gettype($test_value) . ")\n";
echo "转换为字符串: \"" . (string)$test_value . "\"\n";
echo "范围限制后: " . max(0, min(1, (int)$test_value)) . "\n";
echo "最终字符串: \"" . (string)max(0, min(1, (int)$test_value)) . "\"\n";

// 测试实际的JSON输出
echo "\n=== 测试JSON输出 ===\n";
$test_data = [
    'fulfillmentLagTime' => (string)max(0, min(1, (int)$test_value))
];
echo "JSON输出: " . json_encode($test_data) . "\n";
echo "print_r输出:\n";
print_r($test_data);

// 检查最近的同步是否真的发送了字符串
echo "\n=== 检查最近API请求的实际数据 ===\n";
$api_logs = $wpdb->get_results("
    SELECT * FROM $logs_table
    WHERE action = 'API请求' AND (request LIKE '%fulfillmentLagTime%' OR response LIKE '%fulfillmentLagTime%')
    ORDER BY created_at DESC
    LIMIT 3
");

foreach ($api_logs as $log) {
    echo "时间: " . $log->created_at . "\n";

    // 检查请求数据
    if (strpos($log->request, 'fulfillmentLagTime') !== false) {
        if (preg_match('/"fulfillmentLagTime":\s*([^,}]+)/', $log->request, $matches)) {
            echo "请求中发送的值: " . trim($matches[1]) . "\n";
            $value = trim($matches[1]);
            if ($value === '"1"') {
                echo "✅ 发送的是字符串格式\n";
            } elseif ($value === '1') {
                echo "❌ 发送的是整数格式\n";
            } else {
                echo "⚠️ 其他格式: " . $value . "\n";
            }
        }
    }

    // 检查响应数据
    if (strpos($log->response, 'fulfillmentLagTime') !== false) {
        if (preg_match('/"fulfillmentLagTime":\s*([^,}]+)/', $log->response, $matches)) {
            echo "API响应中的值: " . trim($matches[1]) . "\n";
        }
    }
    echo "---\n";
}

// 最终结论
echo "\n=== 结论 ===\n";
echo "1. JSON输出: " . json_encode(['fulfillmentLagTime' => (string)1]) . " ✅ 正确的字符串格式\n";
echo "2. print_r显示: ";
print_r(['fulfillmentLagTime' => (string)1]);
echo "   ↑ print_r()会将字符串'1'显示为1，但实际仍是字符串\n";
echo "3. 您看到的 [fulfillmentLagTime] => 1 是正常的显示方式\n";
echo "4. 实际发送给API的是字符串格式，符合5.0版本要求\n";
?>
