<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>测试 OpenSSL 弃用警告修复</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h1>🔧 测试 OpenSSL 弃用警告修复</h1>
    <p>验证 openssl_free_key() 弃用警告是否已修复</p>
    <hr>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../wp-load.php';
require_once __DIR__ . '/includes/class-api-key-auth.php';

echo "<h2>1️⃣ PHP 环境信息</h2>";

echo "<table>";
echo "<tr><th>项目</th><th>值</th></tr>";
echo "<tr><td>PHP 版本</td><td class='info'>" . PHP_VERSION . "</td></tr>";
echo "<tr><td>PHP_VERSION_ID</td><td class='info'>" . PHP_VERSION_ID . "</td></tr>";
echo "<tr><td>OpenSSL 版本</td><td class='info'>" . OPENSSL_VERSION_TEXT . "</td></tr>";

if (PHP_VERSION_ID >= 80000) {
    echo "<tr><td>openssl_free_key() 状态</td><td class='warning'>⚠️ 已弃用（PHP 8.0+）</td></tr>";
    echo "<tr><td>修复状态</td><td class='success'>✅ 已添加版本检查，不会调用</td></tr>";
} else {
    echo "<tr><td>openssl_free_key() 状态</td><td class='success'>✅ 可用（PHP < 8.0）</td></tr>";
    echo "<tr><td>修复状态</td><td class='info'>ℹ️ 会正常调用</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>2️⃣ 测试数字签名功能</h2>";

// 获取加拿大市场的凭据
$consumer_id = get_option('woo_walmart_CA_consumer_id', '');
$private_key = get_option('woo_walmart_CA_private_key', '');

if (empty($consumer_id) || empty($private_key)) {
    echo "<p class='warning'>⚠️ 未配置加拿大市场凭据，跳过测试</p>";
    echo "<p>请先在设置页面配置 Consumer ID 和 Private Key</p>";
} else {
    echo "<p class='info'>Consumer ID: " . substr($consumer_id, 0, 20) . "...</p>";
    echo "<p class='info'>Private Key: " . substr($private_key, 0, 50) . "... (长度: " . strlen($private_key) . ")</p>";
    
    echo "<h3>测试签名生成:</h3>";
    
    // 创建 API 认证实例
    $api_auth = new Woo_Walmart_API_Key_Auth();
    
    // 使用反射调用私有方法
    $reflection = new ReflectionClass($api_auth);
    $method = $reflection->getMethod('generate_signature');
    $method->setAccessible(true);
    
    // 测试数据
    $test_url = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL';
    $test_method = 'POST';
    $test_timestamp = (string)(time() * 1000);
    
    echo "<p>测试 URL: <code>{$test_url}</code></p>";
    echo "<p>测试方法: <code>{$test_method}</code></p>";
    echo "<p>时间戳: <code>{$test_timestamp}</code></p>";
    
    // 捕获错误
    ob_start();
    $old_error_level = error_reporting(E_ALL);
    
    try {
        $signature = $method->invoke($api_auth, $test_url, $test_method, $test_timestamp);
        
        $errors = ob_get_clean();
        error_reporting($old_error_level);
        
        if (!empty($errors)) {
            echo "<h3 class='warning'>⚠️ 捕获到警告/错误:</h3>";
            echo "<pre>{$errors}</pre>";
            
            if (strpos($errors, 'openssl_free_key') !== false) {
                echo "<p class='error'>❌ 仍然存在 openssl_free_key() 弃用警告</p>";
            } else {
                echo "<p class='success'>✅ 没有 openssl_free_key() 弃用警告</p>";
            }
        } else {
            echo "<p class='success'>✅ 没有任何警告或错误</p>";
        }
        
        if (!empty($signature)) {
            echo "<h3 class='success'>✅ 签名生成成功</h3>";
            echo "<p>签名 (前50字符): <code>" . substr($signature, 0, 50) . "...</code></p>";
            echo "<p>签名长度: <code>" . strlen($signature) . "</code></p>";
        } else {
            echo "<p class='error'>❌ 签名生成失败</p>";
        }
        
    } catch (Exception $e) {
        $errors = ob_get_clean();
        error_reporting($old_error_level);
        
        echo "<p class='error'>❌ 异常: " . $e->getMessage() . "</p>";
        if (!empty($errors)) {
            echo "<pre>{$errors}</pre>";
        }
    }
}

echo "<hr>";
echo "<h2>3️⃣ 测试 API 请求</h2>";

if (!empty($consumer_id) && !empty($private_key)) {
    echo "<p>测试真实的 API 请求...</p>";
    
    // 临时设置为加拿大市场
    $old_business_unit = get_option('woo_walmart_business_unit');
    update_option('woo_walmart_business_unit', 'WALMART_CA');
    
    $api_auth = new Woo_Walmart_API_Key_Auth();
    
    // 捕获错误
    ob_start();
    $old_error_level = error_reporting(E_ALL);
    
    try {
        // 测试获取商品列表
        $result = $api_auth->make_request('/v3/ca/items?limit=1');
        
        $errors = ob_get_clean();
        error_reporting($old_error_level);
        
        if (!empty($errors)) {
            echo "<h3 class='warning'>⚠️ API 请求过程中的警告/错误:</h3>";
            echo "<pre>{$errors}</pre>";
            
            if (strpos($errors, 'openssl_free_key') !== false) {
                echo "<p class='error'>❌ 仍然存在 openssl_free_key() 弃用警告</p>";
            } else {
                echo "<p class='success'>✅ 没有 openssl_free_key() 弃用警告</p>";
            }
        } else {
            echo "<p class='success'>✅ API 请求没有任何警告或错误</p>";
        }
        
        if (is_array($result)) {
            echo "<p class='success'>✅ API 请求成功</p>";
            echo "<p>响应数据: <code>" . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</code></p>";
        } else {
            echo "<p class='warning'>⚠️ API 返回非预期格式</p>";
        }
        
    } catch (Exception $e) {
        $errors = ob_get_clean();
        error_reporting($old_error_level);
        
        echo "<p class='error'>❌ 异常: " . $e->getMessage() . "</p>";
        if (!empty($errors)) {
            echo "<pre>{$errors}</pre>";
        }
    }
    
    // 恢复原来的市场设置
    if ($old_business_unit) {
        update_option('woo_walmart_business_unit', $old_business_unit);
    }
} else {
    echo "<p class='warning'>⚠️ 未配置凭据，跳过 API 测试</p>";
}

echo "<hr>";
echo "<h2>4️⃣ 总结</h2>";

echo "<ul>";
echo "<li><strong>PHP 版本:</strong> " . PHP_VERSION;
if (PHP_VERSION_ID >= 80000) {
    echo " <span class='warning'>(需要修复)</span>";
} else {
    echo " <span class='success'>(无需修复)</span>";
}
echo "</li>";

echo "<li><strong>修复方法:</strong> 添加了 PHP 版本检查，只在 PHP < 8.0 时调用 openssl_free_key()</li>";
echo "<li><strong>兼容性:</strong> <span class='success'>✅ 兼容 PHP 7.x 和 PHP 8.x</span></li>";
echo "</ul>";

?>

</body>
</html>

