<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 检查API端点配置 ===\n\n";

// 1. 检查当前市场配置
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);

echo "1. 当前市场配置:\n";
echo "业务单元: {$business_unit}\n";
echo "市场代码: {$market_code}\n\n";

// 2. 检查Feed类型配置
require_once 'includes/class-multi-market-config.php';
$feed_type = Woo_Walmart_Multi_Market_Config::get_market_feed_type($market_code, 'item');

echo "2. Feed类型配置:\n";
echo "Feed类型: {$feed_type}\n\n";

// 3. 检查API端点构建
$endpoint = "/v3/feeds?feedType={$feed_type}";
$full_url = Woo_Walmart_Multi_Market_Config::get_market_api_endpoint($market_code, $endpoint);

echo "3. API端点构建:\n";
echo "基础端点: {$endpoint}\n";
echo "完整URL: {$full_url}\n\n";

// 4. 测试API端点是否存在
echo "4. 测试API端点可访问性:\n";

require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();
$access_token = $api_auth->get_access_token();

if (!$access_token) {
    echo "❌ 无法获取访问令牌\n";
    exit;
}

echo "✅ 获取访问令牌成功\n";

// 测试不同的端点
$test_endpoints = [
    "/v3/ca/feeds?feedType=MP_ITEM_INTL",
    "/v3/feeds?feedType=MP_ITEM_INTL", 
    "/v3/ca/feeds?feedType=MP_ITEM",
    "/v3/feeds?feedType=MP_ITEM"
];

foreach ($test_endpoints as $test_endpoint) {
    echo "\n测试端点: {$test_endpoint}\n";
    
    $test_url = 'https://marketplace.walmartapis.com' . $test_endpoint;
    
    $headers = [
        'WM_SEC.ACCESS_TOKEN: ' . $access_token,
        'WM_SVC.NAME: Walmart Marketplace',
        'WM_QOS.CORRELATION_ID: ' . uniqid(),
        'WM_CONSUMER.CHANNEL.TYPE: ' . $business_unit,
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); // 使用GET测试端点存在性
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ CURL错误: {$error}\n";
    } else {
        echo "  HTTP状态码: {$http_code}\n";
        
        if ($http_code == 404) {
            echo "  ❌ 端点不存在 (404 Not Found)\n";
        } elseif ($http_code == 405) {
            echo "  ✅ 端点存在但不支持GET方法 (405 Method Not Allowed)\n";
        } elseif ($http_code == 400) {
            echo "  ✅ 端点存在但请求格式错误 (400 Bad Request)\n";
        } elseif ($http_code == 200) {
            echo "  ✅ 端点存在且可访问 (200 OK)\n";
        } else {
            echo "  ⚠️ 其他状态码: {$http_code}\n";
        }
        
        // 解析响应
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['error'])) {
            foreach ($response_data['error'] as $error_info) {
                if (isset($error_info['code'])) {
                    echo "  错误代码: {$error_info['code']}\n";
                    if (isset($error_info['description'])) {
                        echo "  错误描述: {$error_info['description']}\n";
                    }
                }
            }
        }
    }
}

// 5. 检查官方文档推荐的端点
echo "\n\n5. 根据官方文档检查:\n";
echo "加拿大市场应该使用: /v3/ca/feeds?feedType=MP_ITEM_INTL\n";
echo "这是基于官方文档 https://developer.walmart.com/ca-marketplace/docs/item-management-api-overview\n";

// 6. 检查当前代码实际使用的端点
echo "\n6. 当前代码实际使用的端点:\n";
echo "配置的端点: {$full_url}\n";

if (strpos($full_url, '/v3/ca/feeds') !== false && strpos($full_url, 'MP_ITEM_INTL') !== false) {
    echo "✅ 端点配置正确\n";
} else {
    echo "❌ 端点配置可能有问题\n";
    echo "应该是: https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL\n";
}

echo "\n=== 检查完成 ===\n";
