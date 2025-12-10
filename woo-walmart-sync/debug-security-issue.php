<?php
/**
 * 详细调试安全验证失败问题
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 详细调试安全验证失败问题 ===\n";
echo "调试时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
$wp_path = 'd:\\phpstudy_pro\\WWW\\canda.localhost';
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';

echo "=== 1. 检查当前用户状态 ===\n";

$current_user = wp_get_current_user();
if ($current_user->ID) {
    echo "✅ 当前用户ID: " . $current_user->ID . "\n";
    echo "✅ 用户名: " . $current_user->user_login . "\n";
    echo "✅ 用户角色: " . implode(', ', $current_user->roles) . "\n";
    echo "✅ 用户已登录: " . (is_user_logged_in() ? '是' : '否') . "\n";
    echo "✅ 管理员权限: " . (current_user_can('manage_options') ? '是' : '否') . "\n";
} else {
    echo "❌ 没有当前用户\n";
    
    // 尝试模拟登录
    $admin_user = get_user_by('login', 'admin');
    if (!$admin_user) {
        $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
        if (!empty($admin_users)) {
            $admin_user = $admin_users[0];
        }
    }
    
    if ($admin_user) {
        wp_set_current_user($admin_user->ID);
        echo "✅ 已模拟登录用户: " . $admin_user->user_login . "\n";
    }
}

echo "\n=== 2. 检查nonce机制 ===\n";

// 测试nonce生成和验证
$test_nonce = wp_create_nonce('sku_batch_sync_nonce');
echo "生成的nonce: " . $test_nonce . "\n";

$verify_result = wp_verify_nonce($test_nonce, 'sku_batch_sync_nonce');
echo "nonce验证结果: " . ($verify_result ? '成功' : '失败') . "\n";

// 检查nonce生命周期
echo "nonce生命周期: " . (defined('NONCE_LIFE') ? NONCE_LIFE . '秒' : '默认86400秒') . "\n";

echo "\n=== 3. 检查AJAX处理函数 ===\n";

// 检查函数是否存在
if (function_exists('handle_walmart_batch_sync_products')) {
    echo "✅ handle_walmart_batch_sync_products 函数存在\n";
} else {
    echo "❌ handle_walmart_batch_sync_products 函数不存在\n";
}

// 检查AJAX钩子
if (has_action('wp_ajax_walmart_batch_sync_products')) {
    echo "✅ wp_ajax_walmart_batch_sync_products 钩子已注册\n";
} else {
    echo "❌ wp_ajax_walmart_batch_sync_products 钩子未注册\n";
}

echo "\n=== 4. 检查安全验证代码 ===\n";

$plugin_path = 'd:\\phpstudy_pro\\WWW\\canda.localhost\\wp-content\\plugins\\woo-walmart-sync';
$main_file = $plugin_path . '\\woo-walmart-sync.php';

if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    
    // 检查用户登录检查
    if (strpos($content, "if (!is_user_logged_in())") !== false) {
        echo "✅ 包含用户登录检查\n";
    } else {
        echo "❌ 缺少用户登录检查\n";
    }
    
    // 检查用户权限检查
    if (strpos($content, "if (!current_user_can('manage_options'))") !== false) {
        echo "✅ 包含用户权限检查\n";
    } else {
        echo "❌ 缺少用户权限检查\n";
    }
    
    // 检查nonce验证
    if (strpos($content, "wp_verify_nonce(\$_POST['nonce'], 'sku_batch_sync_nonce')") !== false) {
        echo "✅ 包含nonce验证\n";
    } else {
        echo "❌ 缺少nonce验证\n";
    }
}

echo "\n=== 5. 模拟完整AJAX请求 ===\n";

// 设置AJAX环境
define('DOING_AJAX', true);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

// 获取一些真实的产品ID
$products = wc_get_products(['limit' => 5, 'status' => 'publish']);
$real_product_ids = [];
foreach ($products as $product) {
    $real_product_ids[] = $product->get_id();
}

if (empty($real_product_ids)) {
    $real_product_ids = [1, 2, 3]; // 使用测试ID
}

echo "使用产品ID: " . implode(', ', $real_product_ids) . "\n";

// 设置POST数据
$_POST = [
    'action' => 'walmart_batch_sync_products',
    'product_ids' => $real_product_ids,
    'force_sync' => 0,
    'skip_validation' => 0,
    'nonce' => $test_nonce
];

echo "POST数据设置完成\n";

// 直接调用函数
try {
    echo "开始调用 handle_walmart_batch_sync_products...\n";
    
    ob_start();
    handle_walmart_batch_sync_products();
    $output = ob_get_clean();
    
    echo "函数调用完成，输出: $output\n";
    
    $response = json_decode($output, true);
    if ($response) {
        if ($response['success']) {
            echo "✅ 请求成功\n";
            if (isset($response['data']['message'])) {
                echo "消息: " . $response['data']['message'] . "\n";
            }
        } else {
            echo "❌ 请求失败\n";
            if (isset($response['data']['message'])) {
                echo "错误信息: " . $response['data']['message'] . "\n";
            }
        }
    } else {
        echo "⚠️ 无法解析JSON响应\n";
    }
    
} catch (Exception $e) {
    echo "❌ 调用异常: " . $e->getMessage() . "\n";
    echo "异常跟踪:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 6. 检查WordPress错误日志 ===\n";

// 检查WordPress调试日志
$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    echo "WordPress调试日志存在\n";
    $log_content = file_get_contents($debug_log);
    $log_lines = explode("\n", $log_content);
    $recent_lines = array_slice($log_lines, -20);
    
    echo "最近20行日志:\n";
    foreach ($recent_lines as $line) {
        if (trim($line) && (strpos($line, 'walmart') !== false || strpos($line, 'nonce') !== false || strpos($line, 'security') !== false)) {
            echo "  " . trim($line) . "\n";
        }
    }
} else {
    echo "WordPress调试日志不存在\n";
}

echo "\n=== 7. 检查服务器环境 ===\n";

echo "PHP版本: " . phpversion() . "\n";
echo "WordPress版本: " . get_bloginfo('version') . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "最大执行时间: " . ini_get('max_execution_time') . "秒\n";
echo "POST最大大小: " . ini_get('post_max_size') . "\n";

echo "\n=== 8. 检查可能的插件冲突 ===\n";

$active_plugins = get_option('active_plugins', []);
echo "活跃插件数量: " . count($active_plugins) . "\n";

$security_related = [];
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'security') !== false || 
        strpos($plugin, 'firewall') !== false || 
        strpos($plugin, 'protection') !== false ||
        strpos($plugin, 'captcha') !== false ||
        strpos($plugin, 'anti') !== false) {
        $security_related[] = $plugin;
    }
}

if (!empty($security_related)) {
    echo "⚠️ 发现可能影响AJAX的插件:\n";
    foreach ($security_related as $plugin) {
        echo "  - $plugin\n";
    }
} else {
    echo "✅ 没有发现明显的安全插件冲突\n";
}

echo "\n=== 9. 检查浏览器端问题 ===\n";

echo "可能的浏览器端问题:\n";
echo "1. 浏览器缓存 - 清除浏览器缓存和Cookie\n";
echo "2. 会话过期 - 重新登录WordPress后台\n";
echo "3. 跨域问题 - 检查网站URL配置\n";
echo "4. JavaScript错误 - 检查浏览器控制台\n";
echo "5. 网络问题 - 检查网络连接\n";

echo "\n=== 诊断建议 ===\n";

$suggestions = [];

if (!is_user_logged_in()) {
    $suggestions[] = "确保在WordPress后台登录状态下测试";
}

if (!current_user_can('manage_options')) {
    $suggestions[] = "使用管理员账户登录";
}

if (!empty($security_related)) {
    $suggestions[] = "临时禁用安全插件测试";
}

$suggestions[] = "清除浏览器缓存";
$suggestions[] = "检查浏览器开发者工具的Network标签";
$suggestions[] = "检查WordPress错误日志";

echo "建议操作:\n";
foreach ($suggestions as $i => $suggestion) {
    echo ($i + 1) . ". $suggestion\n";
}

echo "\n=== 调试完成 ===\n";
echo "如果问题仍然存在，请提供:\n";
echo "1. 浏览器开发者工具中的具体错误信息\n";
echo "2. WordPress错误日志中的相关错误\n";
echo "3. 具体的操作步骤和错误提示\n";
?>
