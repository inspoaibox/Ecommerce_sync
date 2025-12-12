<?php
/**
 * 诊断加拿大市场 Token 获取问题
 *
 * 使用方法：在浏览器访问此文件
 * 例如：http://your-site.com/wp-content/plugins/woo-walmart-sync/diagnose-canada-token-issue.php
 */

// 设置错误显示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 加载 WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('无法找到 WordPress。请确保插件安装在正确的位置。');
}
require_once($wp_load_path);

// 确保只有管理员可以访问
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('请先登录 WordPress 管理后台，然后再访问此页面。', '权限不足', array('response' => 403));
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>加拿大市场 Token 诊断</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 3px; font-weight: bold; }
        .status.ok { background: #d4edda; color: #155724; }
        .status.fail { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>🇨🇦 加拿大市场 Token 获取诊断报告</h1>

    <?php
    echo '<div class="section">';
    echo '<h2>步骤 1: 检查当前主市场配置</h2>';
    $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
    echo '<p><strong>当前主市场：</strong> ' . esc_html($business_unit) . '</p>';

    if ($business_unit !== 'WALMART_CA') {
        echo '<p class="warning">⚠️ 警告：当前主市场不是加拿大。请在 API 设置页面将主市场设置为"加拿大 (CA)"。</p>';
    } else {
        echo '<p class="success">✓ 主市场已正确设置为加拿大</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>步骤 2: 检查加拿大市场 API 凭证配置</h2>';

    $ca_client_id = get_option('woo_walmart_CA_client_id', '');
    $ca_client_secret = get_option('woo_walmart_CA_client_secret', '');

    echo '<p><strong>Client ID 配置项：</strong> woo_walmart_CA_client_id</p>';
    if (empty($ca_client_id)) {
        echo '<p class="error">❌ Client ID 未配置</p>';
    } else {
        echo '<p class="success">✓ Client ID 已配置：' . esc_html(substr($ca_client_id, 0, 15)) . '...</p>';
    }

    echo '<p><strong>Client Secret 配置项：</strong> woo_walmart_CA_client_secret</p>';
    if (empty($ca_client_secret)) {
        echo '<p class="error">❌ Client Secret 未配置</p>';
    } else {
        echo '<p class="success">✓ Client Secret 已配置 (长度: ' . strlen($ca_client_secret) . ' 字符)</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>步骤 3: 检查市场配置读取</h2>';

    require_once plugin_dir_path(__FILE__) . 'includes/class-multi-market-config.php';

    $market_code = str_replace('WALMART_', '', $business_unit);
    $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);

    if (!$market_config) {
        echo '<p class="error">❌ 无法读取市场配置</p>';
    } else {
        echo '<p class="success">✓ 市场配置读取成功</p>';
        echo '<p><strong>Feed 类型：</strong> ' . esc_html($market_config['feed_types']['item']) . '</p>';

        if (isset($market_config['auth_config'])) {
            $auth_config = $market_config['auth_config'];
            echo '<p><strong>认证配置：</strong></p>';
            echo '<ul>';
            echo '<li>Client ID 配置项：' . esc_html($auth_config['client_id_option']) . '</li>';
            echo '<li>Client Secret 配置项：' . esc_html($auth_config['client_secret_option']) . '</li>';
            echo '<li>Market Header：' . esc_html($auth_config['market_header']) . '</li>';
            echo '</ul>';
        }
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>步骤 4: 测试 API 认证类初始化</h2>';

    try {
        require_once plugin_dir_path(__FILE__) . 'includes/class-api-key-auth.php';
        $api_auth = new Woo_Walmart_API_Key_Auth();

        echo '<p class="success">✓ API 认证类初始化成功</p>';

        // 使用反射读取私有属性
        $reflection = new ReflectionClass($api_auth);
        $client_id_property = $reflection->getProperty('client_id');
        $client_id_property->setAccessible(true);
        $loaded_client_id = $client_id_property->getValue($api_auth);

        $client_secret_property = $reflection->getProperty('client_secret');
        $client_secret_property->setAccessible(true);
        $loaded_client_secret = $client_secret_property->getValue($api_auth);

        if (empty($loaded_client_id)) {
            echo '<p class="error">❌ API 认证类未能加载 Client ID</p>';
        } else {
            echo '<p class="success">✓ API 认证类已加载 Client ID：' . esc_html(substr($loaded_client_id, 0, 15)) . '...</p>';
        }

        if (empty($loaded_client_secret)) {
            echo '<p class="error">❌ API 认证类未能加载 Client Secret</p>';
        } else {
            echo '<p class="success">✓ API 认证类已加载 Client Secret (长度: ' . strlen($loaded_client_secret) . ' 字符)</p>';
        }

    } catch (Exception $e) {
        echo '<p class="error">❌ API 认证类初始化失败：' . esc_html($e->getMessage()) . '</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>步骤 5: 测试获取 Access Token</h2>';

    if (!empty($loaded_client_id) && !empty($loaded_client_secret)) {
        echo '<p class="info">正在请求 Access Token...</p>';

        $token = $api_auth->get_access_token(true); // 强制获取新 token

        if ($token === false) {
            echo '<p class="error">❌ 获取 Access Token 失败</p>';
            echo '<p>请检查同步日志表 (wp_woo_walmart_sync_logs) 查看详细错误信息。</p>';

            // 查询最近的 Token 获取日志
            global $wpdb;
            $log_table = $wpdb->prefix . 'woo_walmart_sync_logs';
            $recent_logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $log_table WHERE action = '获取Token' ORDER BY created_at DESC LIMIT 3"
            ));

            if (!empty($recent_logs)) {
                echo '<h3>最近的 Token 请求日志：</h3>';
                foreach ($recent_logs as $log) {
                    echo '<div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #dc3545;">';
                    echo '<p><strong>时间：</strong>' . esc_html($log->created_at) . '</p>';
                    echo '<p><strong>状态：</strong>' . esc_html($log->status) . '</p>';
                    if (!empty($log->response)) {
                        $response_data = json_decode($log->response, true);
                        if (is_array($response_data)) {
                            echo '<p><strong>HTTP 状态码：</strong>' . esc_html($response_data['code'] ?? 'N/A') . '</p>';
                            echo '<p><strong>响应消息：</strong>' . esc_html($response_data['message'] ?? 'N/A') . '</p>';
                            if (!empty($response_data['body'])) {
                                echo '<p><strong>响应内容：</strong></p>';
                                echo '<pre>' . esc_html(substr($response_data['body'], 0, 500)) . '</pre>';
                            }
                        }
                    }
                    echo '</div>';
                }
            }
        } else {
            echo '<p class="success">✓ Access Token 获取成功！</p>';
            echo '<p><strong>Token 前缀：</strong>' . esc_html(substr($token, 0, 20)) . '...</p>';
        }
    } else {
        echo '<p class="error">❌ 跳过 Token 测试：Client ID 或 Secret 未正确加载</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>步骤 6: 配置修复建议</h2>';

    $has_issues = false;

    if ($business_unit !== 'WALMART_CA') {
        echo '<div class="error">';
        echo '<p><strong>问题 1：</strong>主市场未设置为加拿大</p>';
        echo '<p><strong>解决方案：</strong></p>';
        echo '<ol>';
        echo '<li>进入 WordPress 后台 → Walmart 同步 → 设置</li>';
        echo '<li>在"主市场选择"中选择"加拿大 (CA)"</li>';
        echo '<li>保存设置</li>';
        echo '</ol>';
        echo '</div>';
        $has_issues = true;
    }

    if (empty($ca_client_id) || empty($ca_client_secret)) {
        echo '<div class="error">';
        echo '<p><strong>问题 2：</strong>加拿大市场 API 凭证未配置</p>';
        echo '<p><strong>解决方案：</strong></p>';
        echo '<ol>';
        echo '<li>进入 WordPress 后台 → Walmart 同步 → 设置</li>';
        echo '<li>找到"加拿大 (CA)"市场配置区域</li>';
        echo '<li>填入您的 Client ID 和 Client Secret</li>';
        echo '<li>点击"测试加拿大市场连接"验证</li>';
        echo '<li>保存设置</li>';
        echo '</ol>';
        echo '</div>';
        $has_issues = true;
    }

    if (!$has_issues) {
        echo '<p class="success">✓ 配置检查通过！如果仍然有问题，请检查您的 API 凭证是否正确。</p>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>步骤 7: 快速操作</h2>';
    echo '<p><a href="' . admin_url('admin.php?page=woo-walmart-sync-settings') . '" class="button button-primary">前往 API 设置页面</a></p>';
    echo '<p><a href="' . admin_url('admin.php?page=woo-walmart-category-mapping') . '" class="button button-secondary">前往分类映射页面</a></p>';
    echo '</div>';
    ?>

    <div class="section" style="background: #e7f3ff; border-left: 4px solid #007bff;">
        <h2>💡 修复摘要</h2>
        <p>本次诊断已修复以下问题：</p>
        <ul>
            <li><strong>配置字段名称不一致：</strong>已将 <code>class-multi-market-config.php</code> 中的 <code>woo_walmart_CA_consumer_id</code> 修正为 <code>woo_walmart_CA_client_id</code>，与 API 设置页面保持一致。</li>
            <li><strong>Feed Type 硬编码：</strong>已在分类映射 AJAX 函数中实现动态 Feed Type 获取，支持加拿大市场的 <code>MP_ITEM_INTL</code>。</li>
        </ul>
        <p><strong>下一步：</strong></p>
        <ol>
            <li>确保在 API 设置页面填入正确的加拿大市场 API 凭证</li>
            <li>点击"测试加拿大市场连接"按钮验证</li>
            <li>前往分类映射页面，点击"从沃尔玛更新分类列表"</li>
        </ol>
    </div>
</body>
</html>
