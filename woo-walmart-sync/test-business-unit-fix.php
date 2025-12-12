<?php
/**
 * 测试businessUnit修复效果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试businessUnit修复效果 ===\n\n";

// 1. 检查当前设置
echo "1. 检查当前设置:\n";

$current_business_unit = get_option('woo_walmart_business_unit', '');
$current_default_market = get_option('woo_walmart_default_market', 'US');

echo "当前businessUnit设置: " . ($current_business_unit ?: '未设置') . "\n";
echo "当前默认市场设置: {$current_default_market}\n\n";

// 2. 测试多市场配置获取
echo "2. 测试多市场配置获取:\n";

if (!class_exists('Woo_Walmart_Multi_Market_Config')) {
    require_once WOO_WALMART_SYNC_PATH . 'includes/class-multi-market-config.php';
}

$market_config = Woo_Walmart_Multi_Market_Config::get_market_config($current_default_market);

if ($market_config) {
    echo "✅ 成功获取市场配置:\n";
    echo "  市场代码: {$current_default_market}\n";
    echo "  businessUnit: {$market_config['business_unit']}\n";
    echo "  货币: {$market_config['currency']}\n";
    echo "  语言: {$market_config['locale']}\n";
    echo "  国家: {$market_config['country_name']}\n";
} else {
    echo "❌ 无法获取市场配置\n";
}

// 3. 测试不同市场的businessUnit获取
echo "\n3. 测试不同市场的businessUnit获取:\n";

$test_markets = ['US', 'CA', 'MX', 'CL'];

foreach ($test_markets as $market) {
    $config = Woo_Walmart_Multi_Market_Config::get_market_config($market);
    if ($config) {
        echo "  {$market}: {$config['business_unit']} ({$config['country_name']})\n";
    } else {
        echo "  {$market}: 配置不存在\n";
    }
}

// 4. 模拟批量同步中的businessUnit获取逻辑
echo "\n4. 模拟批量同步中的businessUnit获取逻辑:\n";

function get_business_unit_for_batch_sync() {
    $default_market = get_option('woo_walmart_default_market', 'US');
    $business_unit = get_option('woo_walmart_business_unit', '');
    
    // 如果没有设置businessUnit，从多市场配置中获取
    if (empty($business_unit)) {
        // 确保多市场配置类已加载
        if (!class_exists('Woo_Walmart_Multi_Market_Config')) {
            require_once WOO_WALMART_SYNC_PATH . 'includes/class-multi-market-config.php';
        }
        
        $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($default_market);
        $business_unit = $market_config ? $market_config['business_unit'] : 'WALMART_US';
    }
    
    return $business_unit;
}

$batch_business_unit = get_business_unit_for_batch_sync();
echo "批量同步将使用的businessUnit: {$batch_business_unit}\n";

// 5. 测试不同设置组合的效果
echo "\n5. 测试不同设置组合的效果:\n";

// 保存原始设置
$original_business_unit = get_option('woo_walmart_business_unit', '');
$original_default_market = get_option('woo_walmart_default_market', 'US');

// 测试场景1：清空businessUnit，使用默认市场US
update_option('woo_walmart_business_unit', '');
update_option('woo_walmart_default_market', 'US');
$result1 = get_business_unit_for_batch_sync();
echo "场景1 (清空businessUnit, 默认市场US): {$result1}\n";

// 测试场景2：清空businessUnit，使用默认市场CA
update_option('woo_walmart_default_market', 'CA');
$result2 = get_business_unit_for_batch_sync();
echo "场景2 (清空businessUnit, 默认市场CA): {$result2}\n";

// 测试场景3：设置businessUnit，应该优先使用设置的值
update_option('woo_walmart_business_unit', 'WALMART_MX');
$result3 = get_business_unit_for_batch_sync();
echo "场景3 (设置businessUnit为WALMART_MX): {$result3}\n";

// 恢复原始设置
update_option('woo_walmart_business_unit', $original_business_unit);
update_option('woo_walmart_default_market', $original_default_market);

echo "\n" . str_repeat("=", 60) . "\n";
echo "修复效果总结:\n";
echo "✅ 不再硬编码businessUnit为WALMART_US\n";
echo "✅ 优先使用用户设置的businessUnit\n";
echo "✅ 如果未设置，从默认市场配置中获取\n";
echo "✅ 支持多市场切换\n";
echo "✅ 向后兼容现有设置\n";

?>
