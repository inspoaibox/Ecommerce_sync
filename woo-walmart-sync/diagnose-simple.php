<?php
/**
 * 简化版加拿大市场诊断工具
 *
 * 使用方法：
 * 1. 复制此代码
 * 2. 在 WordPress 后台 → 工具 → 站点健康 → 信息 → 调试
 * 或者在任何 PHP 执行环境中运行
 */

// 确保在 WordPress 环境中
if (!defined('ABSPATH')) {
    // 如果直接访问，尝试加载 WordPress
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        die('请在 WordPress 环境中运行此脚本');
    }
}

// 检查权限
if (!current_user_can('manage_options')) {
    die('权限不足');
}

// 输出 HTML 头部
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>加拿大市场诊断</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f0f0f1; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1d2327; margin: 0 0 10px; }
        h2 { color: #1d2327; font-size: 18px; margin: 20px 0 10px; border-bottom: 2px solid #2271b1; padding-bottom: 5px; }
        .success { color: #00a32a; }
        .error { color: #d63638; }
        .warning { color: #dba617; }
        .code { background: #f6f7f7; padding: 10px; border-radius: 4px; font-family: Consolas, Monaco, monospace; font-size: 13px; }
        .status-ok { background: #d7f2e9; color: #1e4620; padding: 8px 12px; border-radius: 4px; display: inline-block; }
        .status-fail { background: #f7d7d9; color: #3c1618; padding: 8px 12px; border-radius: 4px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 8px; border-bottom: 1px solid #ddd; }
        table td:first-child { font-weight: 600; width: 200px; }
        .btn { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #135e96; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🇨🇦 加拿大市场诊断报告</h1>
        <p>当前时间：<?php echo current_time('Y-m-d H:i:s'); ?></p>
    </div>

    <?php
    // 步骤 1: 检查主市场配置
    echo '<div class="card">';
    echo '<h2>1️⃣ 主市场配置</h2>';
    $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
    echo '<table>';
    echo '<tr><td>当前主市场</td><td><strong>' . esc_html($business_unit) . '</strong></td></tr>';

    if ($business_unit === 'WALMART_CA') {
        echo '<tr><td>状态</td><td><span class="status-ok">✓ 已正确设置为加拿大市场</span></td></tr>';
    } else {
        echo '<tr><td>状态</td><td><span class="status-fail">✗ 主市场不是加拿大</span></td></tr>';
    }
    echo '</table>';
    echo '</div>';

    // 步骤 2: 检查 API 凭证
    echo '<div class="card">';
    echo '<h2>2️⃣ API 凭证配置</h2>';

    $ca_client_id = get_option('woo_walmart_CA_client_id', '');
    $ca_client_secret = get_option('woo_walmart_CA_client_secret', '');

    echo '<table>';
    echo '<tr><td>配置项名称</td><td>woo_walmart_CA_client_id</td></tr>';
    if (empty($ca_client_id)) {
        echo '<tr><td>Client ID</td><td><span class="status-fail">✗ 未配置</span></td></tr>';
    } else {
        echo '<tr><td>Client ID</td><td><span class="status-ok">✓ 已配置</span> (' . esc_html(substr($ca_client_id, 0, 20)) . '...)</td></tr>';
    }

    echo '<tr><td>配置项名称</td><td>woo_walmart_CA_client_secret</td></tr>';
    if (empty($ca_client_secret)) {
        echo '<tr><td>Client Secret</td><td><span class="status-fail">✗ 未配置</span></td></tr>';
    } else {
        echo '<tr><td>Client Secret</td><td><span class="status-ok">✓ 已配置</span> (长度: ' . strlen($ca_client_secret) . ')</td></tr>';
    }
    echo '</table>';
    echo '</div>';

    // 步骤 3: 检查市场配置文件
    echo '<div class="card">';
    echo '<h2>3️⃣ 市场配置读取</h2>';

    $config_file = plugin_dir_path(__FILE__) . 'includes/class-multi-market-config.php';
    if (!file_exists($config_file)) {
        echo '<p class="error">✗ 市场配置文件不存在</p>';
    } else {
        require_once $config_file;

        $market_code = str_replace('WALMART_', '', $business_unit);
        $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);

        if (!$market_config) {
            echo '<p class="error">✗ 无法读取市场配置</p>';
        } else {
            echo '<table>';
            echo '<tr><td>市场代码</td><td>' . esc_html($market_code) . '</td></tr>';
            echo '<tr><td>Feed Type</td><td>' . esc_html($market_config['feed_types']['item'] ?? 'N/A') . '</td></tr>';

            if (isset($market_config['auth_config'])) {
                $auth_config = $market_config['auth_config'];
                echo '<tr><td>Client ID 配置项</td><td>' . esc_html($auth_config['client_id_option']) . '</td></tr>';
                echo '<tr><td>Client Secret 配置项</td><td>' . esc_html($auth_config['client_secret_option']) . '</td></tr>';
                echo '<tr><td>Market Header</td><td>' . esc_html($auth_config['market_header']) . '</td></tr>';

                // 验证配置项是否一致
                if ($auth_config['client_id_option'] === 'woo_walmart_CA_client_id') {
                    echo '<tr><td>配置项验证</td><td><span class="status-ok">✓ 配置项名称正确</span></td></tr>';
                } else {
                    echo '<tr><td>配置项验证</td><td><span class="status-fail">✗ 配置项名称错误：期望 woo_walmart_CA_client_id，实际 ' . esc_html($auth_config['client_id_option']) . '</span></td></tr>';
                }
            }
            echo '</table>';
        }
    }
    echo '</div>';

    // 步骤 4: 测试 API 认证类
    echo '<div class="card">';
    echo '<h2>4️⃣ API 认证类测试</h2>';

    $auth_file = plugin_dir_path(__FILE__) . 'includes/class-api-key-auth.php';
    if (!file_exists($auth_file)) {
        echo '<p class="error">✗ API 认证类文件不存在</p>';
    } else {
        require_once $auth_file;

        try {
            $api_auth = new Woo_Walmart_API_Key_Auth();
            echo '<p class="success">✓ API 认证类初始化成功</p>';

            // 使用反射检查私有属性
            $reflection = new ReflectionClass($api_auth);

            $client_id_property = $reflection->getProperty('client_id');
            $client_id_property->setAccessible(true);
            $loaded_client_id = $client_id_property->getValue($api_auth);

            $client_secret_property = $reflection->getProperty('client_secret');
            $client_secret_property->setAccessible(true);
            $loaded_client_secret = $client_secret_property->getValue($api_auth);

            echo '<table>';
            if (empty($loaded_client_id)) {
                echo '<tr><td>Client ID 加载</td><td><span class="status-fail">✗ 未能加载</span></td></tr>';
            } else {
                echo '<tr><td>Client ID 加载</td><td><span class="status-ok">✓ 已加载</span> (' . esc_html(substr($loaded_client_id, 0, 20)) . '...)</td></tr>';
            }

            if (empty($loaded_client_secret)) {
                echo '<tr><td>Client Secret 加载</td><td><span class="status-fail">✗ 未能加载</span></td></tr>';
            } else {
                echo '<tr><td>Client Secret 加载</td><td><span class="status-ok">✓ 已加载</span> (长度: ' . strlen($loaded_client_secret) . ')</td></tr>';
            }
            echo '</table>';

        } catch (Exception $e) {
            echo '<p class="error">✗ 初始化失败: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    echo '</div>';

    // 步骤 5: 诊断结论
    echo '<div class="card">';
    echo '<h2>5️⃣ 诊断结论</h2>';

    $issues = array();
    $fixes = array();

    if ($business_unit !== 'WALMART_CA') {
        $issues[] = '主市场未设置为加拿大';
        $fixes[] = '在 API 设置页面将主市场设置为"加拿大 (CA)"';
    }

    if (empty($ca_client_id) || empty($ca_client_secret)) {
        $issues[] = '加拿大市场 API 凭证未配置';
        $fixes[] = '在 API 设置页面填入加拿大市场的 Client ID 和 Client Secret';
    }

    if (isset($loaded_client_id) && empty($loaded_client_id)) {
        $issues[] = 'API 认证类未能加载 Client ID';
        $fixes[] = '检查 class-multi-market-config.php 中的 client_id_option 是否为 woo_walmart_CA_client_id';
    }

    if (empty($issues)) {
        echo '<p class="success" style="font-size: 18px;">✓ 所有检查通过！配置正确。</p>';
        echo '<p>如果仍然遇到问题，请检查 API 凭证是否正确，或查看同步日志获取详细错误信息。</p>';
    } else {
        echo '<p class="error" style="font-size: 18px;">发现 ' . count($issues) . ' 个问题：</p>';
        echo '<ol>';
        foreach ($issues as $issue) {
            echo '<li>' . esc_html($issue) . '</li>';
        }
        echo '</ol>';

        echo '<p><strong>修复建议：</strong></p>';
        echo '<ol>';
        foreach ($fixes as $fix) {
            echo '<li>' . esc_html($fix) . '</li>';
        }
        echo '</ol>';
    }
    echo '</div>';

    // 快速操作链接
    echo '<div class="card">';
    echo '<h2>6️⃣ 快速操作</h2>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=woo-walmart-sync-settings')) . '" class="btn">前往 API 设置</a>';
    echo '<a href="' . esc_url(admin_url('admin.php?page=woo-walmart-category-mapping')) . '" class="btn">前往分类映射</a>';
    echo '<a href="' . esc_url($_SERVER['REQUEST_URI']) . '" class="btn">刷新诊断</a>';
    echo '</div>';
    ?>
</body>
</html>
