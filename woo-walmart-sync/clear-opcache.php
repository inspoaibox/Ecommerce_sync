<?php
/**
 * OPcache 清除工具
 *
 * 使用方法：在浏览器访问此文件
 * http://canda.localhost/wp-content/plugins/woo-walmart-sync/clear-opcache.php
 */

// 设置为纯文本输出
header('Content-Type: text/html; charset=utf-8');

// 加载 WordPress（可选，用于权限检查）
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);

    // 检查权限
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('请先登录', '权限不足', array('response' => 403));
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OPcache 清除工具</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            margin: 0 0 20px;
        }
        .success {
            color: #00a32a;
            background: #d7f2e9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            color: #d63638;
            background: #f7d7d9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            color: #2271b1;
            background: #e5f5fa;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px 0 0;
        }
        .btn:hover {
            background: #135e96;
        }
        pre {
            background: #f6f7f7;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🧹 OPcache 清除工具</h1>

        <?php
        // 检查 OPcache 是否启用
        if (!function_exists('opcache_reset')) {
            echo '<div class="error">';
            echo '<strong>✗ OPcache 未启用</strong><br>';
            echo '您的 PHP 配置中未启用 OPcache，或者当前 PHP 版本不支持 OPcache。';
            echo '</div>';
        } else {
            // 尝试清除 OPcache
            $result = opcache_reset();

            if ($result) {
                echo '<div class="success">';
                echo '<strong>✓ OPcache 清除成功！</strong><br>';
                echo '所有 PHP 字节码缓存已清除。代码修改现在应该生效了。';
                echo '</div>';

                // 显示 OPcache 状态
                $status = opcache_get_status(false);
                if ($status) {
                    echo '<div class="info">';
                    echo '<strong>OPcache 状态：</strong><br>';
                    echo '启用状态：' . ($status['opcache_enabled'] ? '已启用' : '未启用') . '<br>';
                    echo '缓存已满：' . ($status['cache_full'] ? '是' : '否') . '<br>';
                    echo '重启次数：' . $status['oom_restarts'] . '<br>';
                    echo '哈希重启次数：' . $status['hash_restarts'];
                    echo '</div>';
                }
            } else {
                echo '<div class="error">';
                echo '<strong>✗ OPcache 清除失败</strong><br>';
                echo '可能是由于权限问题或 OPcache 配置限制。';
                echo '</div>';
            }

            // 显示 OPcache 配置
            echo '<h2>OPcache 配置</h2>';
            echo '<pre>';
            echo 'opcache.enable = ' . ini_get('opcache.enable') . "\n";
            echo 'opcache.enable_cli = ' . ini_get('opcache.enable_cli') . "\n";
            echo 'opcache.memory_consumption = ' . ini_get('opcache.memory_consumption') . "\n";
            echo 'opcache.max_accelerated_files = ' . ini_get('opcache.max_accelerated_files') . "\n";
            echo 'opcache.revalidate_freq = ' . ini_get('opcache.revalidate_freq') . "\n";
            echo 'opcache.validate_timestamps = ' . ini_get('opcache.validate_timestamps');
            echo '</pre>';
        }
        ?>

        <h2>下一步操作</h2>
        <ol>
            <li>如果 OPcache 清除成功，现在可以测试批量同步功能</li>
            <li>如果 OPcache 清除失败，请在 phpstudy 控制面板中重启 PHP 或 Nginx/Apache</li>
            <li>测试完成后，查看诊断工具以确认修复是否生效</li>
        </ol>

        <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="btn">前往产品列表</a>
        <a href="diagnose-batch-sync.php" class="btn">查看诊断报告</a>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn">再次清除缓存</a>
    </div>
</body>
</html>
