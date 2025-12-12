<?php
// 测试沃尔玛API的limit参数实际限制

require_once '../../../wp-load.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== 测试沃尔玛API的limit参数 ===\n\n";

$feed_id = '1861B138A35454308C110B242AFE82B5@AXkBCgA';
$api_auth = new Woo_Walmart_API_Key_Auth();

// 测试不同的limit值
$test_limits = [50, 100, 500, 1000, 2000];

foreach ($test_limits as $limit) {
    echo "测试 limit=$limit:\n";
    
    $endpoint = "/v3/feeds/{$feed_id}?includeDetails=true&limit={$limit}";
    $result = $api_auth->make_request($endpoint);
    
    if (is_wp_error($result)) {
        echo "  ❌ 错误: " . $result->get_error_message() . "\n";
    } else {
        $items_received = $result['itemsReceived'] ?? 0;
        $actual_items = isset($result['itemDetails']['itemIngestionStatus']) ? count($result['itemDetails']['itemIngestionStatus']) : 0;
        $returned_limit = $result['limit'] ?? '未知';
        
        echo "  API说接收: $items_received 个商品\n";
        echo "  实际返回: $actual_items 个商品详情\n";
        echo "  返回的limit: $returned_limit\n";
        
        if ($actual_items >= $items_received) {
            echo "  ✅ 获取完整数据\n";
        } else {
            echo "  ⚠️ 数据不完整，缺少 " . ($items_received - $actual_items) . " 个\n";
        }
    }
    echo "\n";
}

// 测试官方文档建议
echo "查看API响应中是否有关于分页的提示:\n";
$endpoint = "/v3/feeds/{$feed_id}?includeDetails=true&limit=50";
$result = $api_auth->make_request($endpoint);

if (!is_wp_error($result)) {
    echo "完整API响应的所有字段:\n";
    foreach ($result as $key => $value) {
        if (is_array($value)) {
            echo "  $key: [数组，" . count($value) . "个元素]\n";
        } else {
            echo "  $key: $value\n";
        }
    }
}

echo "\n=== 测试完成 ===\n";
?>
