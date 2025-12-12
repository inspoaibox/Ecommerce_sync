<?php
// 简单检查沃尔玛API的分页信息

// 加载WordPress
require_once '../../../wp-load.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== 检查沃尔玛API分页信息 ===\n\n";

// 测试Feed ID
$feed_id = '1861B138A35454308C110B242AFE82B5@AXkBCgA';

$api_auth = new Woo_Walmart_API_Key_Auth();
$endpoint = "/v3/feeds/{$feed_id}?includeDetails=true";
$result = $api_auth->make_request($endpoint);

if (!is_wp_error($result)) {
    echo "API响应的关键字段:\n";
    
    // 基本统计
    echo "itemsReceived: " . ($result['itemsReceived'] ?? '无') . "\n";
    echo "itemsSucceeded: " . ($result['itemsSucceeded'] ?? '无') . "\n";
    echo "itemsFailed: " . ($result['itemsFailed'] ?? '无') . "\n";
    echo "itemsProcessing: " . ($result['itemsProcessing'] ?? '无') . "\n";
    
    // 分页信息
    echo "\n分页相关字段:\n";
    echo "offset: " . ($result['offset'] ?? '无') . "\n";
    echo "limit: " . ($result['limit'] ?? '无') . "\n";
    
    // 检查是否有总数字段
    $possible_total_fields = ['total', 'totalCount', 'totalItems', 'count', 'size'];
    foreach ($possible_total_fields as $field) {
        if (isset($result[$field])) {
            echo "$field: " . $result[$field] . "\n";
        }
    }
    
    // 实际商品详情数量
    $actual_items = isset($result['itemDetails']['itemIngestionStatus']) ? count($result['itemDetails']['itemIngestionStatus']) : 0;
    echo "\n实际返回商品详情数量: $actual_items\n";
    
    // 判断是否需要分页
    $items_received = $result['itemsReceived'] ?? 0;
    echo "API说接收了: $items_received 个商品\n";
    echo "实际返回详情: $actual_items 个商品\n";
    
    if ($actual_items < $items_received) {
        echo "\n🎯 需要分页！缺少 " . ($items_received - $actual_items) . " 个商品详情\n";
        
        // 计算需要多少页
        $limit = $result['limit'] ?? 50;
        $total_pages = ceil($items_received / $limit);
        echo "每页限制: $limit\n";
        echo "需要总页数: $total_pages\n";
        
        echo "\n简单的解决方案:\n";
        echo "1. 第一次调用: ?includeDetails=true&limit=1000&offset=0\n";
        echo "2. 如果返回数量 < itemsReceived，继续调用下一页\n";
        echo "3. 直到获取所有数据\n";
    } else {
        echo "\n✅ 数据完整，无需分页\n";
    }
    
} else {
    echo "API调用失败: " . $result->get_error_message() . "\n";
}

echo "\n=== 检查完成 ===\n";
?>
