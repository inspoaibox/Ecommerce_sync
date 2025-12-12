<?php
/**
 * 测试SSL修复是否解决了连接问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试SSL修复 ===\n\n";

// 测试SKU
$test_sku = 'W1191S00043';

echo "测试SKU: {$test_sku}\n\n";

global $wpdb;

// 1. 查找产品
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $test_sku
));

if (!$product_id) {
    echo "❌ 产品不存在\n";
    exit;
}

echo "✅ 找到产品ID: {$product_id}\n";

$product = wc_get_product($product_id);
echo "产品名称: {$product->get_name()}\n";
echo "产品状态: {$product->get_status()}\n\n";

// 2. 测试SSL连接
echo "2. 测试SSL连接到Walmart API:\n";

$test_url = 'https://marketplace.walmartapis.com/';

// 测试不同的SSL配置
$ssl_configs = [
    'default' => [],
    'sslverify_false' => ['sslverify' => false],
    'enhanced' => [
        'sslverify' => false,
        'user-agent' => 'WooCommerce-Walmart-Sync/1.0',
        'httpversion' => '1.1',
        'redirection' => 5,
        'timeout' => 30
    ]
];

foreach ($ssl_configs as $config_name => $config) {
    echo "测试配置: {$config_name}\n";
    
    $args = array_merge([
        'method' => 'GET',
        'timeout' => 10
    ], $config);
    
    $start_time = microtime(true);
    $response = wp_remote_request($test_url, $args);
    $end_time = microtime(true);
    
    $duration = round(($end_time - $start_time) * 1000, 2);
    
    if (is_wp_error($response)) {
        echo "  ❌ 失败: " . $response->get_error_message() . " (耗时: {$duration}ms)\n";
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        echo "  ✅ 成功: HTTP {$status_code} (耗时: {$duration}ms)\n";
    }
}

// 3. 测试修复后的同步
echo "\n3. 测试修复后的同步:\n";

try {
    // 直接调用同步服务
    require_once 'includes/class-product-sync.php';
    $sync = new Woo_Walmart_Product_Sync();
    
    echo "开始同步...\n";
    $sync_result = $sync->initiate_sync($product_id);
    
    echo "同步结果:\n";
    echo "  成功: " . ($sync_result['success'] ? 'true' : 'false') . "\n";
    echo "  消息: {$sync_result['message']}\n";
    
    if (!$sync_result['success']) {
        // 检查错误类型
        $error_message = $sync_result['message'];
        
        if (strpos($error_message, 'SSL_ERROR_SYSCALL') !== false) {
            echo "  ❌ 仍然是SSL错误，需要进一步调试\n";
        } elseif (strpos($error_message, 'cURL error') !== false) {
            echo "  ❌ 仍然是cURL错误，需要检查网络配置\n";
        } elseif (strpos($error_message, '产品名称过长') !== false) {
            echo "  ✅ SSL问题已解决！现在是业务逻辑错误\n";
        } else {
            echo "  ℹ️ 其他类型的错误\n";
        }
    } else {
        echo "  ✅ 同步成功！SSL问题已解决\n";
    }
    
} catch (Exception $e) {
    echo "❌ 同步异常: " . $e->getMessage() . "\n";
}

// 4. 检查API认证配置
echo "\n4. 检查API认证配置:\n";

$client_id = get_option('woo_walmart_client_id', '');
$client_secret = get_option('woo_walmart_client_secret', '');

echo "Client ID: " . (empty($client_id) ? '未设置' : '已设置 (' . strlen($client_id) . ' 字符)') . "\n";
echo "Client Secret: " . (empty($client_secret) ? '未设置' : '已设置 (' . strlen($client_secret) . ' 字符)') . "\n";

if (empty($client_id) || empty($client_secret)) {
    echo "⚠️ API认证信息不完整，这可能导致认证失败\n";
} else {
    echo "✅ API认证信息已配置\n";
}

// 5. 测试获取访问令牌
echo "\n5. 测试获取访问令牌:\n";

try {
    require_once 'includes/class-api-key-auth.php';
    $api_auth = new Woo_Walmart_API_Key_Auth();
    
    // 使用反射访问私有方法
    $reflection = new ReflectionClass($api_auth);
    $method = $reflection->getMethod('get_access_token');
    $method->setAccessible(true);
    
    $access_token = $method->invoke($api_auth);
    
    if ($access_token) {
        echo "✅ 成功获取访问令牌 (长度: " . strlen($access_token) . " 字符)\n";
    } else {
        echo "❌ 无法获取访问令牌\n";
    }
    
} catch (Exception $e) {
    echo "❌ 获取访问令牌异常: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";

// 总结
echo "\n=== 修复总结 ===\n";
echo "1. 已为API请求添加SSL配置：sslverify => false\n";
echo "2. 已为文件上传请求添加SSL配置\n";
echo "3. 已添加用户代理和HTTP版本配置\n";
echo "4. 如果仍有SSL错误，可能需要检查服务器的OpenSSL配置\n";

?>
