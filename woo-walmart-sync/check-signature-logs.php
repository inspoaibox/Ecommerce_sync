<?php
/**
 * 检查签名生成日志
 */

// 加载 WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('无法找到 WordPress');
}
require_once($wp_load_path);

// 检查权限
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('权限不足');
}

header('Content-Type: text/plain; charset=utf-8');

global $wpdb;

echo "========================================\n";
echo "签名生成日志检查\n";
echo "========================================\n\n";

// 1. 检查当前配置
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);
$auth_method = get_option("woo_walmart_{$market_code}_auth_method", 'oauth');

echo "当前配置:\n";
echo "市场: {$business_unit}\n";
echo "认证方式: {$auth_method}\n\n";

if ($auth_method === 'signature') {
    $consumer_id = get_option("woo_walmart_{$market_code}_consumer_id", '');
    $private_key = get_option("woo_walmart_{$market_code}_private_key", '');
    $legacy_channel_type = get_option("woo_walmart_{$market_code}_legacy_channel_type", '');

    echo "旧版凭证检查:\n";
    echo "Consumer ID: " . (!empty($consumer_id) ? substr($consumer_id, 0, 20) . '...' : '(空)') . "\n";
    echo "Private Key: " . (!empty($private_key) ? substr($private_key, 0, 50) . '...' : '(空)') . "\n";
    echo "Channel Type: " . (!empty($legacy_channel_type) ? $legacy_channel_type : '(空)') . "\n\n";

    if (empty($consumer_id)) {
        echo "❌ 错误: Consumer ID 为空\n\n";
    }
    if (empty($private_key)) {
        echo "❌ 错误: Private Key 为空\n\n";
    }
    if (empty($legacy_channel_type)) {
        echo "⚠️  警告: Channel Type 为空\n\n";
    }
} else {
    echo "ℹ️  当前使用 OAuth 2.0 认证，不需要签名\n\n";
}

// 2. 查看签名生成日志
echo "========================================\n";
echo "最近的签名生成尝试:\n";
echo "========================================\n\n";

$signature_logs = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs
     WHERE action = '生成签名'
     ORDER BY created_at DESC
     LIMIT 10"
);

if (empty($signature_logs)) {
    echo "没有找到签名生成日志\n";
} else {
    foreach ($signature_logs as $index => $log) {
        echo "【日志 " . ($index + 1) . "】\n";
        echo str_repeat("-", 70) . "\n";
        echo "时间: {$log->created_at}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n\n";

        if (!empty($log->request)) {
            echo "请求参数:\n";
            $request = json_decode($log->request, true);
            if ($request) {
                echo json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            } else {
                echo $log->request . "\n\n";
            }
        }

        if (!empty($log->response)) {
            echo "响应内容:\n";
            echo $log->response . "\n";
        }

        echo "\n" . str_repeat("=", 70) . "\n\n";
    }
}

// 3. 测试签名生成（如果配置了）
if ($auth_method === 'signature' && !empty($consumer_id) && !empty($private_key)) {
    echo "========================================\n";
    echo "实时测试签名生成:\n";
    echo "========================================\n\n";

    // 加载 API 类
    require_once plugin_dir_path(__FILE__) . 'includes/class-api-key-auth.php';

    try {
        $api = new Woo_Walmart_API_Key_Auth();
        $reflection = new ReflectionClass($api);
        $method = $reflection->getMethod('generate_signature');
        $method->setAccessible(true);

        $result = $method->invoke($api);

        if ($result && is_array($result)) {
            echo "✓ 签名生成成功\n\n";
            echo "签名 (前50字符): " . substr($result['signature'], 0, 50) . "...\n";
            echo "时间戳: {$result['timestamp']}\n";
            echo "密钥版本: {$result['key_version']}\n";
        } else {
            echo "✗ 签名生成失败\n";
            echo "返回值: " . var_export($result, true) . "\n";
        }
    } catch (Exception $e) {
        echo "✗ 签名生成异常: " . $e->getMessage() . "\n";
        echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    }
}

echo "\n完成\n";
