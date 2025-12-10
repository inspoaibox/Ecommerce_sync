<?php
/**
 * 批量同步问题诊断工具
 *
 * 使用方法：在浏览器访问
 * http://canda.localhost/wp-content/plugins/woo-walmart-sync/diagnose-batch-sync.php
 */

// 设置错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 加载 WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('无法找到 WordPress');
}
require_once($wp_load_path);

// 检查权限
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('请先登录', '权限不足', array('response' => 403));
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>批量同步诊断</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f0f0f1; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1d2327; margin: 0 0 10px; }
        h2 { color: #1d2327; font-size: 18px; margin: 20px 0 10px; border-bottom: 2px solid #2271b1; padding-bottom: 5px; }
        .success { color: #00a32a; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .warning { color: #dba617; font-weight: bold; }
        pre { background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 10px; border-bottom: 1px solid #ddd; vertical-align: top; }
        table td:first-child { font-weight: 600; width: 200px; color: #50575e; }
        .log-entry { background: #f6f7f7; padding: 15px; margin: 10px 0; border-left: 4px solid #2271b1; border-radius: 4px; }
        .log-error { border-left-color: #d63638; }
        .btn { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #135e96; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 批量同步问题诊断</h1>
        <p>当前时间：<?php echo current_time('Y-m-d H:i:s'); ?></p>
    </div>

    <?php
    global $wpdb;

    // 1. 检查主市场配置
    echo '<div class="card">';
    echo '<h2>1️⃣ 主市场配置</h2>';
    $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
    $market_code = str_replace('WALMART_', '', $business_unit);

    echo '<table>';
    echo '<tr><td>当前主市场</td><td><strong>' . esc_html($business_unit) . '</strong></td></tr>';
    echo '<tr><td>市场代码</td><td>' . esc_html($market_code) . '</td></tr>';

    // 获取市场配置
    require_once plugin_dir_path(__FILE__) . 'includes/class-multi-market-config.php';
    $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);

    if ($market_config) {
        $feed_type = $market_config['feed_types']['item'] ?? 'N/A';
        echo '<tr><td>Feed Type</td><td><strong>' . esc_html($feed_type) . '</strong></td></tr>';

        if ($feed_type === 'MP_ITEM_INTL' && $market_code === 'CA') {
            echo '<tr><td>Feed Type 状态</td><td><span class="success">✓ 正确（加拿大市场应使用 MP_ITEM_INTL）</span></td></tr>';
        } else if ($feed_type === 'MP_ITEM' && $market_code === 'US') {
            echo '<tr><td>Feed Type 状态</td><td><span class="success">✓ 正确（美国市场应使用 MP_ITEM）</span></td></tr>';
        } else {
            echo '<tr><td>Feed Type 状态</td><td><span class="error">✗ 可能不正确</span></td></tr>';
        }
    } else {
        echo '<tr><td>市场配置</td><td><span class="error">✗ 无法读取市场配置</span></td></tr>';
    }
    echo '</table>';
    echo '</div>';

    // 2. 检查最近的批量同步日志
    echo '<div class="card">';
    echo '<h2>2️⃣ 最近的批量同步日志</h2>';

    $batch_logs = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs
         WHERE action LIKE '%批量%' OR action LIKE '%Feed%'
         ORDER BY created_at DESC
         LIMIT 10"
    );

    if (empty($batch_logs)) {
        echo '<p class="warning">⚠️ 没有找到批量同步日志</p>';
    } else {
        echo '<p>找到 ' . count($batch_logs) . ' 条相关日志：</p>';

        foreach ($batch_logs as $log) {
            $is_error = stripos($log->status, '失败') !== false || stripos($log->status, '错误') !== false;
            $class = $is_error ? 'log-error' : '';

            echo '<div class="log-entry ' . $class . '">';
            echo '<strong>' . esc_html($log->action) . '</strong> - ';
            echo '<span class="' . ($is_error ? 'error' : 'success') . '">' . esc_html($log->status) . '</span><br>';
            echo '<small>时间：' . esc_html($log->created_at) . '</small><br>';

            if (!empty($log->message)) {
                echo '<p><strong>消息：</strong>' . esc_html($log->message) . '</p>';
            }

            if (!empty($log->request)) {
                $request = json_decode($log->request, true);
                if ($request) {
                    echo '<p><strong>请求参数：</strong></p>';
                    echo '<pre>' . esc_html(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                }
            }

            if (!empty($log->response)) {
                $response = json_decode($log->response, true);
                if ($response) {
                    echo '<p><strong>API 响应：</strong></p>';
                    echo '<pre>' . esc_html(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                } else {
                    echo '<p><strong>响应内容：</strong></p>';
                    echo '<pre>' . esc_html(substr($log->response, 0, 500)) . '</pre>';
                }
            }
            echo '</div>';
        }
    }
    echo '</div>';

    // 3. 检查批次记录
    echo '<div class="card">';
    echo '<h2>3️⃣ 最近的批次记录</h2>';

    $batch_table = $wpdb->prefix . 'walmart_batch_feeds';
    $batches = $wpdb->get_results(
        "SELECT * FROM $batch_table
         ORDER BY created_at DESC
         LIMIT 5"
    );

    if (empty($batches)) {
        echo '<p class="warning">⚠️ 没有找到批次记录</p>';
    } else {
        echo '<p>找到 ' . count($batches) . ' 条批次记录：</p>';

        foreach ($batches as $batch) {
            echo '<div class="log-entry">';
            echo '<table>';
            echo '<tr><td>Batch ID</td><td>' . esc_html($batch->batch_id) . '</td></tr>';
            echo '<tr><td>Feed ID</td><td>' . esc_html($batch->feed_id ?? '未设置') . '</td></tr>';
            echo '<tr><td>状态</td><td><strong>' . esc_html($batch->status) . '</strong></td></tr>';
            echo '<tr><td>同步方法</td><td>' . esc_html($batch->sync_method) . '</td></tr>';
            echo '<tr><td>产品数量</td><td>' . esc_html($batch->product_count) . '</td></tr>';
            echo '<tr><td>成功/失败</td><td>' . esc_html($batch->success_count) . ' / ' . esc_html($batch->failed_count) . '</td></tr>';
            echo '<tr><td>创建时间</td><td>' . esc_html($batch->created_at) . '</td></tr>';

            if (!empty($batch->error_details)) {
                echo '<tr><td>错误详情</td><td><span class="error">' . esc_html($batch->error_details) . '</span></td></tr>';
            }

            if (!empty($batch->api_response)) {
                $api_response = json_decode($batch->api_response, true);
                if ($api_response) {
                    echo '<tr><td>API 响应</td><td><pre>' . esc_html(json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></td></tr>';
                }
            }
            echo '</table>';
            echo '</div>';
        }
    }
    echo '</div>';

    // 4. 测试 API 认证
    echo '<div class="card">';
    echo '<h2>4️⃣ API 认证测试</h2>';

    try {
        require_once plugin_dir_path(__FILE__) . 'includes/class-api-key-auth.php';
        $api_auth = new Woo_Walmart_API_Key_Auth();

        $token = $api_auth->get_access_token(true);

        if ($token) {
            echo '<p class="success">✓ Access Token 获取成功</p>';
            echo '<p>Token 前缀：' . esc_html(substr($token, 0, 30)) . '...</p>';
        } else {
            echo '<p class="error">✗ Access Token 获取失败</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ API 认证异常：' . esc_html($e->getMessage()) . '</p>';
    }
    echo '</div>';

    // 5. 诊断建议
    echo '<div class="card">';
    echo '<h2>5️⃣ 诊断建议</h2>';

    $issues = [];

    // 检查是否有最近的失败日志
    $recent_errors = array_filter($batch_logs ?? [], function($log) {
        return stripos($log->status, '失败') !== false || stripos($log->status, '错误') !== false;
    });

    if (!empty($recent_errors)) {
        $latest_error = reset($recent_errors);
        echo '<h3>最近的错误信息：</h3>';
        echo '<div class="log-entry log-error">';
        echo '<p><strong>操作：</strong>' . esc_html($latest_error->action) . '</p>';
        echo '<p><strong>状态：</strong>' . esc_html($latest_error->status) . '</p>';
        echo '<p><strong>消息：</strong>' . esc_html($latest_error->message) . '</p>';

        // 分析错误类型
        if (!empty($latest_error->response)) {
            $response_str = strtolower($latest_error->response);

            if (strpos($response_str, '401') !== false || strpos($response_str, 'unauthorized') !== false) {
                echo '<p class="error"><strong>问题：</strong>API 认证失败</p>';
                echo '<p><strong>解决方案：</strong>检查 API 凭证是否正确</p>';
            } else if (strpos($response_str, '400') !== false || strpos($response_str, 'bad request') !== false) {
                echo '<p class="error"><strong>问题：</strong>请求参数错误</p>';
                echo '<p><strong>解决方案：</strong>检查 Feed Type 是否正确，产品数据是否完整</p>';
            } else if (strpos($response_str, 'mp_item') !== false) {
                echo '<p class="error"><strong>问题：</strong>可能使用了错误的 Feed Type</p>';
                echo '<p><strong>解决方案：</strong>确认代码中使用的是动态 Feed Type</p>';
            }
        }
        echo '</div>';
    }

    echo '<h3>快速检查清单：</h3>';
    echo '<ul>';
    echo '<li>✓ 主市场设置：' . esc_html($business_unit) . '</li>';
    echo '<li>✓ Feed Type：' . esc_html($feed_type ?? 'N/A') . '</li>';
    echo '<li>✓ API Token：' . (isset($token) && $token ? '可用' : '不可用') . '</li>';
    echo '<li>✓ 最近日志：' . count($batch_logs ?? []) . ' 条</li>';
    echo '<li>✓ 批次记录：' . count($batches ?? []) . ' 条</li>';
    echo '</ul>';
    echo '</div>';

    // 快速操作
    echo '<div class="card">';
    echo '<h2>6️⃣ 快速操作</h2>';
    echo '<a href="' . admin_url('admin.php?page=woo-walmart-sync-settings') . '" class="btn">API 设置</a>';
    echo '<a href="' . admin_url('edit.php?post_type=product') . '" class="btn">产品列表</a>';
    echo '<a href="' . $_SERVER['REQUEST_URI'] . '" class="btn">刷新诊断</a>';
    echo '</div>';
    ?>
</body>
</html>
