<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 检查Channel Type问题 ===\n\n";

// 1. 检查当前发送的Channel Type值
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
echo "1. 当前发送的Channel Type值:\n";
echo "业务单元: {$business_unit}\n";
echo "作为Channel Type发送: {$business_unit}\n\n";

// 2. 查看最近的API请求日志中的实际头部
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

echo "2. 最近API请求中的实际头部:\n";

$recent_api_log = $wpdb->get_row("
    SELECT * FROM {$logs_table} 
    WHERE action = 'API请求-文件上传' 
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($recent_api_log && !empty($recent_api_log->request)) {
    $request_data = json_decode($recent_api_log->request, true);
    if ($request_data && isset($request_data['headers'])) {
        echo "请求头部:\n";
        foreach ($request_data['headers'] as $key => $value) {
            if (strpos($key, 'CHANNEL.TYPE') !== false) {
                echo "  ❌ {$key}: {$value}\n";
            } else {
                echo "  {$key}: " . (strpos($key, 'TOKEN') !== false ? '[TOKEN]' : $value) . "\n";
            }
        }
    }
} else {
    echo "❌ 没有找到最近的API请求日志\n";
}

// 3. 测试不同的Channel Type值
echo "\n3. 测试不同的Channel Type值:\n";

require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();
$access_token = $api_auth->get_access_token();

if (!$access_token) {
    echo "❌ 无法获取访问令牌\n";
    exit;
}

$test_channel_types = [
    'WALMART_CA',
    'CANADA',
    'CA',
    'WALMART_CANADA',
    'precisedelivery',
    'SELLER_FULFILLED',
    'WCS',
    'MARKETPLACE_CA'
];

$test_url = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL';

foreach ($test_channel_types as $channel_type) {
    echo "\n测试Channel Type: {$channel_type}\n";
    
    $headers = [
        'WM_SEC.ACCESS_TOKEN: ' . $access_token,
        'WM_SVC.NAME: Walmart Marketplace',
        'WM_QOS.CORRELATION_ID: ' . uniqid(),
        'WM_CONSUMER.CHANNEL.TYPE: ' . $channel_type,
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
                if (isset($error['field']) && $error['field'] == 'WM_CONSUMER.CHANNEL.TYPE') {
                    echo "  ❌ Channel Type无效: {$error['description']}\n";
                } else {
                    echo "  ⚠️ 其他错误: {$error['code']}\n";
                }
            }
        }
    } elseif ($http_code == 405) {
        echo "  ✅ Channel Type有效！(405 Method Not Allowed - 端点存在但不支持GET)\n";
    } elseif ($http_code == 200) {
        echo "  ✅ Channel Type有效！(200 OK)\n";
    } else {
        echo "  ⚠️ 其他状态码: {$http_code}\n";
    }
}

// 4. 检查是否需要从Seller Center获取Channel Type
echo "\n\n4. Channel Type获取建议:\n";
echo "根据Walmart官方文档，Channel Type值需要从Seller Center获取。\n";
echo "请登录 Walmart Canada Seller Center -> API Settings 查找正确的值。\n";
echo "如果找不到，请联系Walmart支持获取您账户的正确Channel Type值。\n";

// 5. 临时解决方案
echo "\n5. 临时解决方案:\n";
echo "如果无法获取正确的Channel Type，可以尝试使用美国端点：\n";
echo "将API端点从 /v3/ca/feeds 改为 /v3/feeds\n";
echo "这样可以绕过Channel Type验证，但可能影响加拿大市场特定功能。\n";

echo "\n=== 检查完成 ===\n";
