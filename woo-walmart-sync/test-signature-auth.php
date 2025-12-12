<?php
/**
 * 测试 Digital Signature 认证实现
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

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Digital Signature 认证测试</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #1d2327;
            margin: 0 0 20px;
        }
        h2 {
            color: #2271b1;
            margin: 20px 0 10px;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 5px;
        }
        .success {
            color: #00a32a;
            background: #d7f2e9;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #d63638;
            background: #f7d7d9;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info {
            color: #2271b1;
            background: #e5f5fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            color: #996800;
            background: #fcf3cf;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
        pre {
            background: #f6f7f7;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-oauth {
            background: #d7f2e9;
            color: #00a32a;
        }
        .badge-signature {
            background: #fcf3cf;
            color: #996800;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Digital Signature 认证测试</h1>

        <?php
        // 获取当前市场配置
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code = str_replace('WALMART_', '', $business_unit);
        $auth_method = get_option("woo_walmart_{$market_code}_auth_method", 'oauth');

        echo '<div class="info">';
        echo '<strong>当前市场：</strong>' . $business_unit . '<br>';
        echo '<strong>认证方式：</strong>';
        if ($auth_method === 'signature') {
            echo '<span class="badge badge-signature">Digital Signature (旧版)</span>';
        } else {
            echo '<span class="badge badge-oauth">OAuth 2.0 (新版)</span>';
        }
        echo '</div>';

        // 测试 1: 检查认证凭证配置
        echo '<h2>✓ 步骤 1: 检查认证凭证配置</h2>';

        if ($auth_method === 'signature') {
            $consumer_id = get_option("woo_walmart_{$market_code}_consumer_id", '');
            $private_key = get_option("woo_walmart_{$market_code}_private_key", '');
            $legacy_channel_type = get_option("woo_walmart_{$market_code}_legacy_channel_type", '');

            echo '<table>';
            echo '<tr><th>配置项</th><th>状态</th><th>值</th></tr>';

            echo '<tr>';
            echo '<td>Consumer ID</td>';
            echo '<td>' . (!empty($consumer_id) ? '<span style="color: #00a32a;">✓ 已配置</span>' : '<span style="color: #d63638;">✗ 未配置</span>') . '</td>';
            echo '<td>' . (!empty($consumer_id) ? esc_html(substr($consumer_id, 0, 20) . '...') : '-') . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<td>Private Key</td>';
            echo '<td>' . (!empty($private_key) ? '<span style="color: #00a32a;">✓ 已配置</span>' : '<span style="color: #d63638;">✗ 未配置</span>') . '</td>';
            echo '<td>' . (!empty($private_key) ? esc_html(substr($private_key, 0, 50) . '...') : '-') . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<td>Channel Type (Legacy)</td>';
            echo '<td>' . (!empty($legacy_channel_type) ? '<span style="color: #00a32a;">✓ 已配置</span>' : '<span style="color: #d63638;">✗ 未配置</span>') . '</td>';
            echo '<td>' . (!empty($legacy_channel_type) ? esc_html($legacy_channel_type) : '-') . '</td>';
            echo '</tr>';

            echo '</table>';

            if (!empty($consumer_id) && !empty($private_key) && !empty($legacy_channel_type)) {
                echo '<div class="success">✓ 所有旧版认证凭证已配置完整</div>';
            } else {
                echo '<div class="error">✗ 旧版认证凭证配置不完整，请前往 <a href="' . admin_url('edit.php?post_type=product&page=woo-walmart-sync-settings') . '">设置页面</a> 填写</div>';
            }
        } else {
            echo '<div class="warning">当前使用 OAuth 2.0 认证，若要测试 Digital Signature 认证，请在设置页面切换认证方式</div>';
        }

        // 测试 2: 测试签名生成
        if ($auth_method === 'signature' && !empty($consumer_id) && !empty($private_key)) {
            echo '<h2>✓ 步骤 2: 测试签名生成</h2>';

            // 加载 API 类
            require_once plugin_dir_path(__FILE__) . 'includes/class-api-key-auth.php';

            // 使用反射访问私有方法进行测试
            $api = new Woo_Walmart_API_Key_Auth();
            $reflection = new ReflectionClass($api);
            $method = $reflection->getMethod('generate_signature');
            $method->setAccessible(true);

            try {
                $signature_result = $method->invoke($api);

                if ($signature_result && is_array($signature_result)) {
                    echo '<div class="success">✓ 签名生成成功</div>';
                    echo '<table>';
                    echo '<tr><th>项目</th><th>值</th></tr>';
                    echo '<tr><td>签名 (前50字符)</td><td>' . esc_html(substr($signature_result['signature'], 0, 50) . '...') . '</td></tr>';
                    echo '<tr><td>时间戳 (毫秒)</td><td>' . esc_html($signature_result['timestamp']) . '</td></tr>';
                    echo '<tr><td>密钥版本</td><td>' . esc_html($signature_result['key_version']) . '</td></tr>';
                    echo '</table>';
                } else {
                    echo '<div class="error">✗ 签名生成失败</div>';
                }
            } catch (Exception $e) {
                echo '<div class="error">✗ 签名生成异常: ' . esc_html($e->getMessage()) . '</div>';
            }
        }

        // 测试 3: 检查 API 请求头构建
        echo '<h2>✓ 步骤 3: 检查完整实现状态</h2>';

        echo '<table>';
        echo '<tr><th>功能</th><th>状态</th></tr>';

        // 检查类文件是否包含新方法
        $class_file = file_get_contents(plugin_dir_path(__FILE__) . 'includes/class-api-key-auth.php');

        $has_generate_signature = strpos($class_file, 'private function generate_signature()') !== false;
        echo '<tr><td>签名生成方法</td><td>' . ($has_generate_signature ? '<span style="color: #00a32a;">✓ 已实现</span>' : '<span style="color: #d63638;">✗ 未实现</span>') . '</td></tr>';

        $has_signature_auth_in_make_request = strpos($class_file, "if (\$this->auth_method === 'signature')") !== false;
        echo '<tr><td>make_request() 双认证支持</td><td>' . ($has_signature_auth_in_make_request ? '<span style="color: #00a32a;">✓ 已实现</span>' : '<span style="color: #d63638;">✗ 未实现</span>') . '</td></tr>';

        $has_signature_headers = strpos($class_file, 'WM_SEC.AUTH_SIGNATURE') !== false;
        echo '<tr><td>签名请求头</td><td>' . ($has_signature_headers ? '<span style="color: #00a32a;">✓ 已实现</span>' : '<span style="color: #d63638;">✗ 未实现</span>') . '</td></tr>';

        $has_consumer_id_header = strpos($class_file, 'WM_CONSUMER.ID') !== false;
        echo '<tr><td>Consumer ID 请求头</td><td>' . ($has_consumer_id_header ? '<span style="color: #00a32a;">✓ 已实现</span>' : '<span style="color: #d63638;">✗ 未实现</span>') . '</td></tr>';

        $has_timestamp_header = strpos($class_file, 'WM_CONSUMER.INTIMESTAMP') !== false;
        echo '<tr><td>时间戳请求头</td><td>' . ($has_timestamp_header ? '<span style="color: #00a32a;">✓ 已实现</span>' : '<span style="color: #d63638;">✗ 未实现</span>') . '</td></tr>';

        echo '</table>';

        if ($has_generate_signature && $has_signature_auth_in_make_request && $has_signature_headers && $has_consumer_id_header && $has_timestamp_header) {
            echo '<div class="success">✓ Digital Signature 认证已完整实现</div>';
        } else {
            echo '<div class="warning">⚠️ Digital Signature 认证实现不完整</div>';
        }

        // 下一步指南
        echo '<h2>📋 下一步操作</h2>';
        echo '<ol>';
        echo '<li>确保在 <a href="' . admin_url('edit.php?post_type=product&page=woo-walmart-sync-settings') . '">设置页面</a> 中配置了所有旧版认证凭证</li>';
        echo '<li>清除 OPcache：访问 <a href="clear-opcache.php">clear-opcache.php</a> 或重启 PHP 服务</li>';
        echo '<li>在产品列表页面测试批量同步功能</li>';
        echo '<li>查看 <a href="get-full-error.php">完整错误日志</a> 以确认认证方式</li>';
        echo '</ol>';
        ?>

        <div class="info">
            <strong>提示：</strong>如果需要切换回 OAuth 2.0 认证，只需在设置页面选择 "OAuth 2.0 (新版)" 并保存即可。
        </div>
    </div>
</body>
</html>
