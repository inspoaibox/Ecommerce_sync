<?php
/**
 * 加拿大市场切换问题诊断脚本
 * 
 * 用途：诊断从美国市场切换到加拿大市场后无法同步的具体原因
 * 
 * 使用方法：
 * php diagnose-canada-market-issue.php
 * 或在浏览器访问：http://your-site.com/wp-content/plugins/woo-walmart-sync/diagnose-canada-market-issue.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 加载必要的类
require_once('includes/class-multi-market-config.php');
require_once('includes/class-api-key-auth.php');

echo "=== 加拿大市场切换问题诊断 ===\n\n";

// ========================================
// 步骤1: 检查当前主市场配置
// ========================================
echo "【步骤1】检查当前主市场配置\n";
echo str_repeat("-", 50) . "\n";

$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);

echo "✓ 当前主市场: {$business_unit}\n";
echo "✓ 市场代码: {$market_code}\n\n";

// ========================================
// 步骤2: 检查加拿大市场API凭证
// ========================================
echo "【步骤2】检查加拿大市场API凭证\n";
echo str_repeat("-", 50) . "\n";

$ca_client_id = get_option('woo_walmart_CA_client_id', '');
$ca_client_secret = get_option('woo_walmart_CA_client_secret', '');

echo "✓ CA Client ID: " . (empty($ca_client_id) ? "❌ 未配置" : "✓ 已配置 (" . substr($ca_client_id, 0, 10) . "...)") . "\n";
echo "✓ CA Client Secret: " . (empty($ca_client_secret) ? "❌ 未配置" : "✓ 已配置 (" . substr($ca_client_secret, 0, 10) . "...)") . "\n\n";

if (empty($ca_client_id) || empty($ca_client_secret)) {
    echo "❌ 错误：加拿大市场的API凭证未配置！\n";
    echo "   请在【设置】页面配置加拿大市场的Client ID和Client Secret\n\n";
}

// ========================================
// 步骤3: 检查API端点配置
// ========================================
echo "【步骤3】检查API端点配置\n";
echo str_repeat("-", 50) . "\n";

$config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);

if ($config) {
    echo "✓ 市场配置已加载\n";
    echo "  - API Base URL: {$config['api_base_url']}\n";
    echo "  - Feed Type (item): {$config['feed_types']['item']}\n";
    echo "  - Currency: {$config['currency']}\n";
    
    // 测试端点转换
    $test_endpoints = [
        '/v3/feeds?feedType=MP_ITEM_INTL',
        '/v3/items',
        '/v3/inventory',
        '/v3/token'
    ];
    
    echo "\n  端点转换测试:\n";
    foreach ($test_endpoints as $endpoint) {
        $converted = Woo_Walmart_Multi_Market_Config::get_market_api_endpoint($market_code, $endpoint);
        echo "  - {$endpoint}\n";
        echo "    → {$converted}\n";
    }
} else {
    echo "❌ 错误：无法加载市场配置\n";
}

echo "\n";

// ========================================
// 步骤4: 检查Feed类型配置
// ========================================
echo "【步骤4】检查Feed类型配置\n";
echo str_repeat("-", 50) . "\n";

$feed_type = Woo_Walmart_Multi_Market_Config::get_market_feed_type($market_code, 'item');
echo "✓ 当前市场Feed类型: {$feed_type}\n";

if ($market_code === 'CA' && $feed_type !== 'MP_ITEM_INTL') {
    echo "❌ 错误：加拿大市场应该使用 MP_ITEM_INTL，但当前是 {$feed_type}\n";
} else if ($market_code === 'CA') {
    echo "✓ 正确：加拿大市场使用 MP_ITEM_INTL\n";
}

echo "\n";

// ========================================
// 步骤5: 测试API连接
// ========================================
echo "【步骤5】测试API连接\n";
echo str_repeat("-", 50) . "\n";

if (!empty($ca_client_id) && !empty($ca_client_secret) && $market_code === 'CA') {
    try {
        $api_auth = new Woo_Walmart_API_Key_Auth();
        
        echo "正在测试Token获取...\n";
        $token = $api_auth->get_access_token(true); // 强制刷新
        
        if ($token) {
            echo "✓ Token获取成功: " . substr($token, 0, 20) . "...\n";
            
            echo "\n正在测试Items API...\n";
            $test_result = $api_auth->make_request('/v3/items?limit=1');
            
            if (is_wp_error($test_result)) {
                echo "❌ Items API调用失败\n";
                echo "   错误代码: " . $test_result->get_error_code() . "\n";
                echo "   错误信息: " . $test_result->get_error_message() . "\n";
            } else {
                echo "✓ Items API调用成功\n";
                echo "   响应结构: " . json_encode(array_keys($test_result), JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "❌ Token获取失败\n";
        }
    } catch (Exception $e) {
        echo "❌ API测试异常: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  跳过API测试（凭证未配置或当前不是CA市场）\n";
}

echo "\n";

// ========================================
// 步骤6: 检查分类映射
// ========================================
echo "【步骤6】检查分类映射配置\n";
echo str_repeat("-", 50) . "\n";

global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

// 检查是否有市场字段
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$map_table}");
$has_market_field = false;
foreach ($columns as $column) {
    if ($column->Field === 'market') {
        $has_market_field = true;
        break;
    }
}

echo "✓ 分类映射表是否有market字段: " . ($has_market_field ? "是" : "否") . "\n";

if ($has_market_field) {
    // 统计各市场的映射数量
    $us_count = $wpdb->get_var("SELECT COUNT(*) FROM {$map_table} WHERE market = 'US'");
    $ca_count = $wpdb->get_var("SELECT COUNT(*) FROM {$map_table} WHERE market = 'CA'");
    
    echo "  - 美国市场映射数量: {$us_count}\n";
    echo "  - 加拿大市场映射数量: {$ca_count}\n";
    
    if ($market_code === 'CA' && $ca_count == 0) {
        echo "\n❌ 关键问题：当前主市场是加拿大，但没有配置加拿大市场的分类映射！\n";
        echo "   解决方案：\n";
        echo "   1. 在【分类映射】页面重新配置分类映射\n";
        echo "   2. 或者将美国市场的映射复制到加拿大市场\n";
    }
} else {
    echo "⚠️  分类映射表没有market字段，所有映射共用\n";
}

echo "\n";

// ========================================
// 步骤7: 检查产品同步逻辑
// ========================================
echo "【步骤7】检查产品同步逻辑\n";
echo str_repeat("-", 50) . "\n";

// 获取一个测试产品
$test_product_id = $wpdb->get_var("
    SELECT p.ID 
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'product'
    AND p.post_status = 'publish'
    AND pm.meta_key = '_sku'
    AND pm.meta_value != ''
    LIMIT 1
");

if ($test_product_id) {
    echo "✓ 找到测试产品ID: {$test_product_id}\n";
    
    $product = wc_get_product($test_product_id);
    $product_cat_ids = $product->get_category_ids();
    
    echo "  - 产品分类ID: " . implode(', ', $product_cat_ids) . "\n";
    
    if (!empty($product_cat_ids)) {
        $cat_id = $product_cat_ids[0];
        
        // 查询分类映射
        if ($has_market_field) {
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$map_table} WHERE wc_category_id = %d AND market = %s",
                $cat_id, $market_code
            ));
        } else {
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$map_table} WHERE wc_category_id = %d",
                $cat_id
            ));
        }
        
        if ($mapping) {
            echo "  ✓ 找到分类映射\n";
            echo "    - Walmart分类: {$mapping->walmart_category_path}\n";
            echo "    - 市场: " . ($has_market_field ? $mapping->market : "共用") . "\n";
        } else {
            echo "  ❌ 未找到分类映射\n";
            if ($has_market_field && $market_code === 'CA') {
                echo "    原因：当前是加拿大市场，但该分类没有配置加拿大市场的映射\n";
            }
        }
    }
} else {
    echo "⚠️  未找到可测试的产品\n";
}

echo "\n";

// ========================================
// 诊断总结
// ========================================
echo "=== 诊断总结 ===\n";
echo str_repeat("=", 50) . "\n\n";

$issues = [];
$recommendations = [];

// 检查1: API凭证
if (empty($ca_client_id) || empty($ca_client_secret)) {
    $issues[] = "加拿大市场API凭证未配置";
    $recommendations[] = "在【设置】页面配置加拿大市场的Client ID和Client Secret";
}

// 检查2: 分类映射
if ($has_market_field && $market_code === 'CA' && $ca_count == 0) {
    $issues[] = "加拿大市场没有分类映射配置";
    $recommendations[] = "在【分类映射】页面为加拿大市场配置分类映射";
}

// 检查3: Feed类型
if ($market_code === 'CA' && $feed_type !== 'MP_ITEM_INTL') {
    $issues[] = "加拿大市场Feed类型配置错误";
    $recommendations[] = "检查 class-multi-market-config.php 中的Feed类型配置";
}

if (empty($issues)) {
    echo "✅ 未发现明显问题\n\n";
    echo "如果仍然无法同步，请检查：\n";
    echo "1. Walmart开发者账号是否已启用加拿大市场\n";
    echo "2. API凭证是否正确（不是美国市场的凭证）\n";
    echo "3. 查看同步日志获取详细错误信息\n";
} else {
    echo "❌ 发现以下问题：\n\n";
    foreach ($issues as $index => $issue) {
        echo ($index + 1) . ". {$issue}\n";
    }
    
    echo "\n📋 建议的解决方案：\n\n";
    foreach ($recommendations as $index => $recommendation) {
        echo ($index + 1) . ". {$recommendation}\n";
    }
}

echo "\n";
echo "=== 诊断完成 ===\n";