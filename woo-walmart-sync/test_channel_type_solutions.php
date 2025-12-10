<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 测试Channel Type解决方案 ===\n\n";

require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();
$access_token = $api_auth->get_access_token();

if (!$access_token) {
    echo "❌ 无法获取访问令牌\n";
    exit;
}

echo "✅ 获取访问令牌成功\n\n";

$test_url = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL';

// 解决方案1：完全移除Channel Type头部（因为文档说它不是必需的）
echo "1. 测试移除Channel Type头部:\n";

$headers_without_channel = [
    'WM_SEC.ACCESS_TOKEN: ' . $access_token,
    'WM_SVC.NAME: Walmart Marketplace',
    'WM_QOS.CORRELATION_ID: ' . uniqid(),
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_without_channel);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  HTTP状态码: {$http_code}\n";

if ($http_code == 400) {
    $response_data = json_decode($response, true);
    if ($response_data && isset($response_data['error'])) {
        foreach ($response_data['error'] as $error) {
            echo "  错误: {$error['code']} - {$error['description']}\n";
        }
    }
} elseif ($http_code == 405) {
    echo "  ✅ 成功！端点可访问 (405 Method Not Allowed)\n";
} elseif ($http_code == 200) {
    echo "  ✅ 成功！端点可访问 (200 OK)\n";
} else {
    echo "  状态码: {$http_code}\n";
}

// 解决方案2：使用UUID格式的Channel Type
echo "\n2. 测试UUID格式的Channel Type:\n";

// 生成一个测试UUID
$test_uuid = wp_generate_uuid4();
echo "  测试UUID: {$test_uuid}\n";

$headers_with_uuid = [
    'WM_SEC.ACCESS_TOKEN: ' . $access_token,
    'WM_SVC.NAME: Walmart Marketplace',
    'WM_QOS.CORRELATION_ID: ' . uniqid(),
    'WM_CONSUMER.CHANNEL.TYPE: ' . $test_uuid,
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_with_uuid);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  HTTP状态码: {$http_code}\n";

if ($http_code == 400) {
    $response_data = json_decode($response, true);
    if ($response_data && isset($response_data['error'])) {
        foreach ($response_data['error'] as $error) {
            echo "  错误: {$error['code']} - {$error['description']}\n";
        }
    }
} elseif ($http_code == 405) {
    echo "  ✅ 成功！UUID格式有效 (405 Method Not Allowed)\n";
} elseif ($http_code == 200) {
    echo "  ✅ 成功！UUID格式有效 (200 OK)\n";
} else {
    echo "  状态码: {$http_code}\n";
}

// 解决方案3：使用美国端点绕过Channel Type验证
echo "\n3. 测试使用美国端点:\n";

$us_url = 'https://marketplace.walmartapis.com/v3/feeds?feedType=MP_ITEM_INTL';

$headers_us = [
    'WM_SEC.ACCESS_TOKEN: ' . $access_token,
    'WM_SVC.NAME: Walmart Marketplace',
    'WM_QOS.CORRELATION_ID: ' . uniqid(),
    'WM_CONSUMER.CHANNEL.TYPE: WALMART_CA',
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $us_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_us);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  HTTP状态码: {$http_code}\n";

if ($http_code == 200 || $http_code == 405) {
    echo "  ✅ 美国端点可以绕过Channel Type验证\n";
} else {
    echo "  ❌ 美国端点也有问题\n";
}

// 解决方案4：测试实际的Feed提交
echo "\n4. 测试实际的Feed提交（使用最佳方案）:\n";

// 创建一个最小的测试Feed数据
$test_feed_data = [
    'MPItemFeedHeader' => [
        'businessUnit' => 'WALMART_CA',
        'locale' => 'en',
        'version' => '5.0.20241118-04_39_24-api'
    ],
    'MPItem' => []
];

$json_data = json_encode($test_feed_data, JSON_UNESCAPED_UNICODE);

// 测试不同的方案
$test_scenarios = [
    [
        'name' => '移除Channel Type + 加拿大端点',
        'url' => 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL',
        'include_channel_type' => false
    ],
    [
        'name' => 'UUID Channel Type + 加拿大端点',
        'url' => 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL',
        'include_channel_type' => true,
        'channel_type' => $test_uuid
    ],
    [
        'name' => '原Channel Type + 美国端点',
        'url' => 'https://marketplace.walmartapis.com/v3/feeds?feedType=MP_ITEM_INTL',
        'include_channel_type' => true,
        'channel_type' => 'WALMART_CA'
    ]
];

foreach ($test_scenarios as $scenario) {
    echo "\n  测试方案: {$scenario['name']}\n";
    
    // 构建multipart数据
    $boundary = wp_generate_uuid4();
    $multipart_data = "--$boundary\r\n";
    $multipart_data .= "Content-Disposition: form-data; name=\"file\"; filename=\"test_feed.json\"\r\n";
    $multipart_data .= "Content-Type: application/json\r\n\r\n";
    $multipart_data .= $json_data . "\r\n";
    $multipart_data .= "--$boundary--\r\n";
    
    $headers = [
        'WM_SEC.ACCESS_TOKEN: ' . $access_token,
        'WM_SVC.NAME: Walmart Marketplace',
        'WM_QOS.CORRELATION_ID: ' . uniqid(),
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Accept: application/json'
    ];
    
    if ($scenario['include_channel_type']) {
        $headers[] = 'WM_CONSUMER.CHANNEL.TYPE: ' . $scenario['channel_type'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $scenario['url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "    HTTP状态码: {$http_code}\n";
    
    if ($http_code == 200 || $http_code == 202) {
        echo "    ✅ 成功！这个方案可行\n";
        
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['feedId'])) {
            echo "    Feed ID: {$response_data['feedId']}\n";
        }
    } elseif ($http_code == 400) {
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['error'])) {
            foreach ($response_data['error'] as $error) {
                if (strpos($error['description'], 'CHANNEL.TYPE') !== false) {
                    echo "    ❌ 仍然是Channel Type问题\n";
                } else {
                    echo "    ⚠️ 其他错误: {$error['code']}\n";
                }
            }
        }
    } else {
        echo "    ⚠️ 其他状态码: {$http_code}\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "\n建议：\n";
echo "1. 如果方案1成功，修改代码移除Channel Type头部\n";
echo "2. 如果方案2成功，需要获取您账户的真实UUID\n";
echo "3. 如果方案3成功，可以临时使用美国端点\n";
