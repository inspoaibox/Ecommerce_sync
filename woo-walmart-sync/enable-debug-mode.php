<?php
/**
 * 启用WordPress调试模式
 * 临时启用调试功能来捕获500错误
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../wp-config.php';
}

echo "=== 启用WordPress调试模式 ===\n\n";

$wp_config_path = ABSPATH . 'wp-config.php';

if (!file_exists($wp_config_path)) {
    echo "❌ 找不到wp-config.php文件\n";
    exit;
}

echo "找到wp-config.php: {$wp_config_path}\n";

// 读取当前配置
$config_content = file_get_contents($wp_config_path);

// 检查当前调试设置
echo "\n当前调试设置:\n";
if (strpos($config_content, "define('WP_DEBUG', true)") !== false) {
    echo "  ✅ WP_DEBUG 已启用\n";
} else {
    echo "  ❌ WP_DEBUG 未启用\n";
}

if (strpos($config_content, "define('WP_DEBUG_LOG', true)") !== false) {
    echo "  ✅ WP_DEBUG_LOG 已启用\n";
} else {
    echo "  ❌ WP_DEBUG_LOG 未启用\n";
}

if (strpos($config_content, "define('WP_DEBUG_DISPLAY', false)") !== false) {
    echo "  ✅ WP_DEBUG_DISPLAY 已正确设置为false\n";
} else {
    echo "  ⚠️ WP_DEBUG_DISPLAY 设置可能不正确\n";
}

// 建议的调试配置
echo "\n建议在wp-config.php中添加以下配置:\n";
echo "```php\n";
echo "// 启用调试模式\n";
echo "define('WP_DEBUG', true);\n";
echo "define('WP_DEBUG_LOG', true);\n";
echo "define('WP_DEBUG_DISPLAY', false); // 不在前端显示错误\n";
echo "define('SCRIPT_DEBUG', true);\n";
echo "define('SAVEQUERIES', true);\n\n";
echo "// 增加内存和执行时间限制\n";
echo "ini_set('memory_limit', '512M');\n";
echo "ini_set('max_execution_time', 300);\n";
echo "```\n\n";

// 检查日志文件位置
$log_locations = [
    ABSPATH . 'wp-content/debug.log',
    '/var/log/php_errors.log',
    ini_get('error_log')
];

echo "可能的错误日志位置:\n";
foreach ($log_locations as $log_path) {
    if ($log_path && file_exists($log_path)) {
        echo "  ✅ {$log_path} (存在)\n";
        
        // 检查最近的错误
        $recent_errors = shell_exec("tail -20 '{$log_path}' | grep -i 'walmart\\|ajax\\|fatal'");
        if ($recent_errors) {
            echo "    最近的相关错误:\n";
            echo "    " . str_replace("\n", "\n    ", trim($recent_errors)) . "\n";
        }
    } else {
        echo "  ❌ {$log_path} (不存在)\n";
    }
}

echo "\n=== 调试模式配置完成 ===\n";
echo "请按照建议修改wp-config.php，然后重新测试批量同步功能。\n";
