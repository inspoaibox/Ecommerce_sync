<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 测试使用Client ID作为Channel Type ===\n\n";

// 1. 获取当前的Client ID
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);

echo "1. 当前市场配置:\n";
echo "业务单元: {$business_unit}\n";
echo "市场代码: {$market_code}\n\n";

// 获取市场特定的认证配置
require_once 'includes/class-multi-market-config.php';
$auth_config = Woo_Walmart_Multi_Market_Config::get_market_auth_config($market_code);

if (empty($auth_config['client_id'])) {
    echo "❌ 没有找到{$market_code}市场的Client ID配置\n";
    exit;
}

$client_id = $auth_config['client_id'];
echo "2. Client ID信息:\n";
echo "Client ID: " . substr($client_id, 0, 10) . "...[隐藏]\n";
echo "Client ID长度: " . strlen($client_id) . " 字符\n\n";

// 3. 获取访问令牌
require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();
$access_token = $api_auth->get_access_token();

if (!$access_token) {
    echo "❌ 无法获取访问令牌\n";
    exit;
}

echo "✅ 获取访问令牌成功\n\n";

// 4. 测试使用Client ID作为Channel Type
echo "3. 测试使用Client ID作为Channel Type:\n";

$test_url = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL';

$headers_with_client_id = [
    'WM_SEC.ACCESS_TOKEN: ' . $access_token,
    'WM_SVC.NAME: Walmart Marketplace',
    'WM_QOS.CORRELATION_ID: ' . uniqid(),
    'WM_CONSUMER.CHANNEL.TYPE: ' . $client_id,  // 使用Client ID
    'Accept: application/json'
];

echo "使用Client ID作为Channel Type: " . substr($client_id, 0, 10) . "...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_with_client_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP状态码: {$http_code}\n";

if ($http_code == 400) {
    $response_data = json_decode($response, true);
    if ($response_data && isset($response_data['error'])) {
        foreach ($response_data['error'] as $error) {
            if (isset($error['field']) && $error['field'] == 'WM_CONSUMER.CHANNEL.TYPE') {
                echo "❌ Client ID作为Channel Type无效: {$error['description']}\n";
            } else {
                echo "⚠️ 其他错误: {$error['code']} - " . ($error['description'] ?? $error['info'] ?? '') . "\n";
            }
        }
    }
} elseif ($http_code == 405) {
    echo "✅ 成功！Client ID作为Channel Type有效 (405 Method Not Allowed)\n";
} elseif ($http_code == 200) {
    echo "✅ 成功！Client ID作为Channel Type有效 (200 OK)\n";
} else {
    echo "⚠️ 其他状态码: {$http_code}\n";
    if (!empty($response)) {
        echo "响应: " . substr($response, 0, 200) . "...\n";
    }
}

// 5. 如果Client ID有效，测试实际的Feed提交
if ($http_code == 200 || $http_code == 405) {
    echo "\n4. 测试实际的Feed提交（使用Client ID作为Channel Type）:\n";
    
    // 创建一个最小的测试Feed数据
    $test_feed_data = [
        'MPItemFeedHeader' => [
            'businessUnit' => $business_unit,
            'locale' => 'en',
            'version' => '5.0.20241118-04_39_24-api'
        ],
        'MPItem' => []
    ];
    
    $json_data = json_encode($test_feed_data, JSON_UNESCAPED_UNICODE);
    
    // 构建multipart数据
    $boundary = wp_generate_uuid4();
    $multipart_data = "--$boundary\r\n";
    $multipart_data .= "Content-Disposition: form-data; name=\"file\"; filename=\"test_feed.json\"\r\n";
    $multipart_data .= "Content-Type: application/json\r\n\r\n";
    $multipart_data .= $json_data . "\r\n";
    $multipart_data .= "--$boundary--\r\n";
    
    $headers_feed = [
        'WM_SEC.ACCESS_TOKEN: ' . $access_token,
        'WM_SVC.NAME: Walmart Marketplace',
        'WM_QOS.CORRELATION_ID: ' . uniqid(),
        'WM_CONSUMER.CHANNEL.TYPE: ' . $client_id,  // 使用Client ID
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_feed);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Feed提交HTTP状态码: {$http_code}\n";
    
    if ($http_code == 200 || $http_code == 202) {
        echo "🎉 成功！Feed提交成功\n";
        
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['feedId'])) {
            echo "Feed ID: {$response_data['feedId']}\n";
        }
        
        echo "\n✅ 解决方案确认：使用Client ID作为Channel Type可以成功！\n";
        
    } elseif ($http_code == 400) {
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['error'])) {
            foreach ($response_data['error'] as $error) {
                if (strpos($error['description'], 'CHANNEL.TYPE') !== false) {
                    echo "❌ 仍然是Channel Type问题\n";
                } else {
                    echo "⚠️ 其他错误: {$error['code']} - " . ($error['description'] ?? $error['info'] ?? '') . "\n";
                }
            }
        }
    } else {
        echo "⚠️ 其他状态码: {$http_code}\n";
        if (!empty($response)) {
            echo "响应: " . substr($response, 0, 300) . "...\n";
        }
    }
}

// 6. 测试其他可能的值
echo "\n5. 测试其他可能的Channel Type值:\n";

$other_test_values = [
    substr($client_id, 0, 8),  // Client ID前8位
    substr($client_id, -8),    // Client ID后8位
    strtoupper($client_id),    // 大写Client ID
    strtolower($client_id),    // 小写Client ID
];

foreach ($other_test_values as $i => $test_value) {
    echo "\n测试值 " . ($i + 1) . ": " . substr($test_value, 0, 10) . "...\n";
    
    $headers_test = [
        'WM_SEC.ACCESS_TOKEN: ' . $access_token,
        'WM_SVC.NAME: Walmart Marketplace',
        'WM_QOS.CORRELATION_ID: ' . uniqid(),
        'WM_CONSUMER.CHANNEL.TYPE: ' . $test_value,
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_test);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP状态码: {$http_code}\n";
    
    if ($http_code == 200 || $http_code == 405) {
        echo "✅ 这个值有效！\n";
    } elseif ($http_code == 400) {
        echo "❌ 无效\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "\n总结：\n";
echo "如果Client ID作为Channel Type有效，我们可以修改代码使用Client ID替代WALMART_CA\n";
