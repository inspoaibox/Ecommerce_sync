<?php
/**
 * 直接测试AJAX市场连接处理函数
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 直接测试AJAX市场连接处理函数 ===\n\n";

// 1. 检查AJAX处理函数是否注册
echo "1. 检查AJAX处理函数注册状态:\n";
global $wp_filter;

if (isset($wp_filter['wp_ajax_walmart_test_market_connection'])) {
    echo "✅ wp_ajax_walmart_test_market_connection 已注册\n";
} else {
    echo "❌ wp_ajax_walmart_test_market_connection 未注册\n";
}

// 2. 模拟AJAX环境
echo "\n2. 模拟AJAX环境:\n";

// 设置AJAX环境
if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}

// 模拟POST数据
$_POST['action'] = 'walmart_test_market_connection';
$_POST['market'] = 'US';
$_POST['nonce'] = wp_create_nonce('walmart_test_market_connection');

echo "模拟POST数据:\n";
echo "  action: {$_POST['action']}\n";
echo "  market: {$_POST['market']}\n";
echo "  nonce: {$_POST['nonce']}\n";

// 3. 直接调用AJAX处理函数
echo "\n3. 直接调用AJAX处理函数:\n";

try {
    // 捕获输出
    ob_start();
    
    // 调用AJAX处理函数
    do_action('wp_ajax_walmart_test_market_connection');
    
    $output = ob_get_clean();
    
    echo "AJAX处理函数输出:\n";
    echo "--- 开始 ---\n";
    echo $output;
    echo "\n--- 结束 ---\n";
    
    // 尝试解析JSON
    $json_data = json_decode($output, true);
    if ($json_data) {
        echo "\n✅ JSON解析成功:\n";
        echo "成功状态: " . ($json_data['success'] ? 'true' : 'false') . "\n";
        if (isset($json_data['data'])) {
            echo "数据: " . json_encode($json_data['data'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        if (isset($json_data['message'])) {
            echo "消息: {$json_data['message']}\n";
        }
    } else {
        echo "\n❌ JSON解析失败\n";
        echo "JSON错误: " . json_last_error_msg() . "\n";
        
        // 检查是否包含HTML错误
        if (strpos($output, '<') !== false || strpos($output, 'Fatal error') !== false) {
            echo "⚠️ 输出包含HTML或错误信息，这就是导致前端JSON解析失败的原因\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ 异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ PHP错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
}

// 4. 检查当前设置
echo "\n4. 检查当前API设置:\n";
$client_id = get_option('woo_walmart_client_id', '');
$client_secret = get_option('woo_walmart_client_secret', '');
$business_unit = get_option('woo_walmart_business_unit', '');

echo "Client ID: " . substr($client_id, 0, 8) . "...\n";
echo "Client Secret: " . (empty($client_secret) ? '空' : '已设置') . "\n";
echo "业务单元: {$business_unit}\n";

// 5. 测试API路由器直接调用
echo "\n5. 测试API路由器直接调用:\n";

try {
    if (class_exists('Woo_Walmart_Multi_Market_API_Router')) {
        $api_router = new Woo_Walmart_Multi_Market_API_Router('US');
        echo "✅ API路由器创建成功\n";
        
        $token_result = $api_router->get_access_token();
        if ($token_result && isset($token_result['access_token'])) {
            echo "✅ 直接调用获取访问令牌成功\n";
        } else {
            echo "❌ 直接调用获取访问令牌失败\n";
            if (is_array($token_result)) {
                echo "错误信息: " . json_encode($token_result, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    } else {
        echo "❌ API路由器类不存在\n";
    }
} catch (Exception $e) {
    echo "❌ API路由器测试异常: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
echo "\n如果AJAX处理函数输出包含HTML或错误信息，那就是导致前端看到'此站点遇到了致命错误'的原因。\n";
?>
