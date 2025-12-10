<?php
/**
 * 直接在 WordPress 数据库/PHP 环境中运行的诊断代码
 *
 * 使用方法 1：在 phpMyAdmin 的 SQL 标签中运行（仅 SQL 部分）
 * 使用方法 2：复制整个代码到 WordPress 临时文件运行
 */

// ========================================
// SQL 诊断查询（可在 phpMyAdmin 中运行）
// ========================================

-- 1. 检查主市场配置
SELECT 'woo_walmart_business_unit' as option_name, option_value
FROM wp_options
WHERE option_name = 'woo_walmart_business_unit';

-- 预期结果：option_value 应该是 'WALMART_CA'

-- 2. 检查加拿大市场 API 凭证
SELECT 'woo_walmart_CA_client_id' as option_name,
       CASE
           WHEN option_value != '' THEN CONCAT(LEFT(option_value, 20), '...（已配置）')
           ELSE '（未配置）'
       END as value_status
FROM wp_options
WHERE option_name = 'woo_walmart_CA_client_id';

SELECT 'woo_walmart_CA_client_secret' as option_name,
       CASE
           WHEN option_value != '' THEN CONCAT('（已配置，长度: ', LENGTH(option_value), '）')
           ELSE '（未配置）'
       END as value_status
FROM wp_options
WHERE option_name = 'woo_walmart_CA_client_secret';

-- 3. 检查最近的 Token 获取日志
SELECT
    id,
    action,
    status,
    created_at,
    SUBSTRING(response, 1, 200) as response_preview
FROM wp_woo_walmart_sync_logs
WHERE action = '获取Token'
ORDER BY created_at DESC
LIMIT 5;

-- 4. 完整配置检查
SELECT
    option_name,
    CASE
        WHEN option_name LIKE '%secret%' THEN CONCAT('（已配置，长度: ', LENGTH(option_value), '）')
        WHEN option_value != '' THEN CONCAT(LEFT(option_value, 30), '...')
        ELSE '（未配置）'
    END as value
FROM wp_options
WHERE option_name IN (
    'woo_walmart_business_unit',
    'woo_walmart_CA_client_id',
    'woo_walmart_CA_client_secret',
    'woo_walmart_US_client_id',
    'woo_walmart_US_client_secret'
)
ORDER BY option_name;

?>

<?php
// ========================================
// PHP 完整诊断代码
// ========================================

/**
 * 运行方法：
 * 1. 将此代码保存为 check-config.php
 * 2. 上传到插件目录
 * 3. 在浏览器访问：http://canda.localhost/wp-content/plugins/woo-walmart-sync/check-config.php
 */

// 加载 WordPress（如果未加载）
if (!defined('ABSPATH')) {
    $wp_load = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once($wp_load);
    } else {
        die('无法加载 WordPress');
    }
}

// 设置为纯文本输出
header('Content-Type: text/plain; charset=utf-8');

echo "=" . str_repeat("=", 70) . "\n";
echo "  加拿大市场配置诊断报告\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// 1. 主市场配置
echo "【1】主市场配置\n";
echo str_repeat("-", 70) . "\n";
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
echo "当前主市场: {$business_unit}\n";
echo "状态: " . ($business_unit === 'WALMART_CA' ? '✓ 正确' : '✗ 错误（应该是 WALMART_CA）') . "\n\n";

// 2. API 凭证配置
echo "【2】API 凭证配置\n";
echo str_repeat("-", 70) . "\n";

$ca_client_id = get_option('woo_walmart_CA_client_id', '');
$ca_client_secret = get_option('woo_walmart_CA_client_secret', '');

echo "配置项: woo_walmart_CA_client_id\n";
echo "Client ID: " . (empty($ca_client_id) ? '✗ 未配置' : '✓ 已配置 (' . substr($ca_client_id, 0, 20) . '...)') . "\n\n";

echo "配置项: woo_walmart_CA_client_secret\n";
echo "Client Secret: " . (empty($ca_client_secret) ? '✗ 未配置' : '✓ 已配置 (长度: ' . strlen($ca_client_secret) . ')') . "\n\n";

// 3. 市场配置文件检查
echo "【3】市场配置文件\n";
echo str_repeat("-", 70) . "\n";

$config_file = plugin_dir_path(__FILE__) . 'includes/class-multi-market-config.php';
if (!file_exists($config_file)) {
    echo "✗ 配置文件不存在: {$config_file}\n\n";
} else {
    echo "✓ 配置文件存在\n";

    require_once $config_file;
    $market_code = str_replace('WALMART_', '', $business_unit);
    $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);

    if (!$market_config) {
        echo "✗ 无法读取 {$market_code} 市场配置\n\n";
    } else {
        echo "✓ 成功读取市场配置\n";
        echo "Feed Type: " . ($market_config['feed_types']['item'] ?? 'N/A') . "\n";

        if (isset($market_config['auth_config'])) {
            $auth_config = $market_config['auth_config'];
            echo "Client ID 配置项: {$auth_config['client_id_option']}\n";
            echo "Client Secret 配置项: {$auth_config['client_secret_option']}\n";
            echo "Market Header: {$auth_config['market_header']}\n";

            // 验证配置项一致性
            if ($auth_config['client_id_option'] === 'woo_walmart_CA_client_id') {
                echo "配置项验证: ✓ 正确\n\n";
            } else {
                echo "配置项验证: ✗ 错误\n";
                echo "  期望: woo_walmart_CA_client_id\n";
                echo "  实际: {$auth_config['client_id_option']}\n\n";
            }
        }
    }
}

// 4. API 认证类测试
echo "【4】API 认证类测试\n";
echo str_repeat("-", 70) . "\n";

$auth_file = plugin_dir_path(__FILE__) . 'includes/class-api-key-auth.php';
if (!file_exists($auth_file)) {
    echo "✗ API 认证类文件不存在\n\n";
} else {
    require_once $auth_file;

    try {
        $api_auth = new Woo_Walmart_API_Key_Auth();
        echo "✓ API 认证类初始化成功\n";

        // 使用反射读取私有属性
        $reflection = new ReflectionClass($api_auth);

        $client_id_prop = $reflection->getProperty('client_id');
        $client_id_prop->setAccessible(true);
        $loaded_id = $client_id_prop->getValue($api_auth);

        $client_secret_prop = $reflection->getProperty('client_secret');
        $client_secret_prop->setAccessible(true);
        $loaded_secret = $client_secret_prop->getValue($api_auth);

        echo "Client ID 加载: " . (empty($loaded_id) ? '✗ 未能加载' : '✓ 已加载 (' . substr($loaded_id, 0, 20) . '...)') . "\n";
        echo "Client Secret 加载: " . (empty($loaded_secret) ? '✗ 未能加载' : '✓ 已加载 (长度: ' . strlen($loaded_secret) . ')') . "\n\n";

    } catch (Exception $e) {
        echo "✗ 初始化失败: {$e->getMessage()}\n\n";
    }
}

// 5. 诊断总结
echo "【5】诊断总结\n";
echo str_repeat("-", 70) . "\n";

$problems = [];
$solutions = [];

if ($business_unit !== 'WALMART_CA') {
    $problems[] = "主市场未设置为加拿大";
    $solutions[] = "在 API 设置页面将主市场设置为 '加拿大 (CA)'";
}

if (empty($ca_client_id) || empty($ca_client_secret)) {
    $problems[] = "加拿大市场 API 凭证未配置";
    $solutions[] = "在 API 设置页面填入加拿大市场的 Client ID 和 Client Secret";
}

if (isset($loaded_id) && empty($loaded_id)) {
    $problems[] = "API 认证类未能加载 Client ID";
    $solutions[] = "检查 class-multi-market-config.php 中的配置项名称是否正确";
}

if (empty($problems)) {
    echo "✓ 所有检查通过！\n";
    echo "配置正确，如果仍有问题，请检查 API 凭证是否有效。\n";
} else {
    echo "发现 " . count($problems) . " 个问题:\n\n";
    foreach ($problems as $i => $problem) {
        echo "  " . ($i + 1) . ". {$problem}\n";
    }

    echo "\n修复建议:\n\n";
    foreach ($solutions as $i => $solution) {
        echo "  " . ($i + 1) . ". {$solution}\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "诊断完成 - " . current_time('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 70) . "\n";
?>
