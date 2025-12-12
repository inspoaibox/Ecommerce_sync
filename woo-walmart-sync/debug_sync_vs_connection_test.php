<?php
/**
 * 对比连接测试和实际同步的差异
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 对比连接测试和实际同步的差异 ===\n\n";

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\canda.localhost';
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';

echo "✅ WordPress环境加载成功\n\n";

// 设置为加拿大市场
update_option('woo_walmart_business_unit', 'WALMART_CA');

echo "当前配置:\n";
echo "  business_unit: " . get_option('woo_walmart_business_unit') . "\n";
echo "  client_id: " . (get_option('woo_walmart_client_id') ? '已设置' : '未设置') . "\n";
echo "  client_secret: " . (get_option('woo_walmart_client_secret') ? '已设置' : '未设置') . "\n\n";

// === 1. 模拟连接测试（成功的那个） ===
echo "=== 1. 模拟连接测试 ===\n";

require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();

echo "测试简单的API调用（类似连接测试）...\n";

$simple_endpoints = [
    'v3/feeds' => 'Feed列表',
    'v3/items/taxonomy' => '分类法'
];

foreach ($simple_endpoints as $endpoint => $desc) {
    echo "测试 {$desc}: {$endpoint}\n";
    
    $result = $api_auth->make_request($endpoint, 'GET');
    
    if (is_wp_error($result)) {
        echo "  ❌ 失败: " . $result->get_error_message() . "\n";
    } else {
        echo "  ✅ 成功\n";
        if (isset($result['error'])) {
            echo "    有错误但连接成功\n";
        }
    }
}

// === 2. 模拟实际同步过程 ===
echo "\n=== 2. 模拟实际同步过程 ===\n";

// 找一个测试产品
global $wpdb;
$test_product_id = $wpdb->get_var("
    SELECT p.ID 
    FROM {$wpdb->posts} p 
    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
    WHERE p.post_type = 'product' 
    AND p.post_status = 'publish' 
    AND pm.meta_key = '_sku' 
    AND pm.meta_value != '' 
    ORDER BY p.ID DESC 
    LIMIT 1
");

if (!$test_product_id) {
    echo "❌ 没有找到测试产品\n";
    exit;
}

$product = wc_get_product($test_product_id);
echo "测试产品: {$product->get_name()} (SKU: {$product->get_sku()})\n\n";

// === 3. 检查产品映射过程 ===
echo "=== 3. 检查产品映射过程 ===\n";

// 获取产品分类
$product_cat_ids = wp_get_post_terms($test_product_id, 'product_cat', ['fields' => 'ids']);
echo "产品分类ID: " . implode(', ', $product_cat_ids) . "\n";

if (empty($product_cat_ids)) {
    echo "❌ 产品没有分类，无法进行映射\n";
    exit;
}

// 查找分类映射
$map_table = $wpdb->prefix . 'walmart_category_map';
$mapped_category_data = null;

foreach ($product_cat_ids as $cat_id) {
    $mapped_category_data = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, wc_category_name, walmart_attributes FROM $map_table WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapped_category_data) {
        echo "✅ 找到分类映射: {$mapped_category_data->walmart_category_path}\n";
        break;
    }
}

if (!$mapped_category_data) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

// === 4. 测试产品数据映射 ===
echo "\n=== 4. 测试产品数据映射 ===\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

try {
    $upc = '123456789012'; // 测试UPC
    $attribute_rules = json_decode($mapped_category_data->walmart_attributes, true);
    
    echo "开始产品映射...\n";
    $walmart_data = $mapper->map($product, $mapped_category_data->walmart_category_path, $upc, $attribute_rules, 1);
    
    echo "✅ 产品映射成功\n";
    echo "映射数据大小: " . strlen(json_encode($walmart_data)) . " 字节\n";
    
    // 检查关键字段
    if (isset($walmart_data['MPItemFeedHeader'])) {
        echo "✅ 包含MPItemFeedHeader\n";
        echo "  businessUnit: " . ($walmart_data['MPItemFeedHeader']['businessUnit'] ?? '未设置') . "\n";
        echo "  locale: " . ($walmart_data['MPItemFeedHeader']['locale'] ?? '未设置') . "\n";
        echo "  version: " . ($walmart_data['MPItemFeedHeader']['version'] ?? '未设置') . "\n";
    } else {
        echo "❌ 缺少MPItemFeedHeader\n";
    }
    
    if (isset($walmart_data['MPItem']) && !empty($walmart_data['MPItem'])) {
        echo "✅ 包含MPItem数据\n";
        echo "  商品数量: " . count($walmart_data['MPItem']) . "\n";
        
        $first_item = $walmart_data['MPItem'][0];
        echo "  SKU: " . ($first_item['sku'] ?? '未设置') . "\n";
        echo "  产品名: " . ($first_item['productName'] ?? '未设置') . "\n";
    } else {
        echo "❌ 缺少MPItem数据\n";
    }
    
} catch (Exception $e) {
    echo "❌ 产品映射失败: " . $e->getMessage() . "\n";
    echo "错误堆栈: " . $e->getTraceAsString() . "\n";
    exit;
}

// === 5. 测试实际Feed提交 ===
echo "\n=== 5. 测试实际Feed提交 ===\n";

// 获取正确的feedType
require_once 'includes/class-multi-market-config.php';
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);
$feed_type = Woo_Walmart_Multi_Market_Config::get_market_feed_type($market_code, 'item');

echo "使用feedType: {$feed_type}\n";
echo "提交到端点: /v3/feeds?feedType={$feed_type}\n\n";

echo "开始Feed提交...\n";

try {
    $response = $api_auth->make_file_upload_request("/v3/feeds?feedType={$feed_type}", $walmart_data, 'test_feed.json');
    
    if (is_wp_error($response)) {
        echo "❌ Feed提交失败: " . $response->get_error_message() . "\n";
        
        $error_data = $response->get_error_data();
        if ($error_data && isset($error_data['response_body'])) {
            echo "详细错误信息:\n";
            echo $error_data['response_body'] . "\n";
            
            // 解析错误信息
            $error_response = json_decode($error_data['response_body'], true);
            if ($error_response && isset($error_response['error'])) {
                $error = $error_response['error'][0] ?? $error_response['error'];
                echo "\n解析后的错误:\n";
                echo "  代码: " . ($error['code'] ?? '未知') . "\n";
                echo "  字段: " . ($error['field'] ?? '未知') . "\n";
                echo "  描述: " . ($error['description'] ?? $error['info'] ?? '未知') . "\n";
            }
        }
    } else {
        echo "✅ Feed提交成功\n";
        if (isset($response['feedId'])) {
            echo "Feed ID: " . $response['feedId'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Feed提交异常: " . $e->getMessage() . "\n";
}

// === 6. 对比分析 ===
echo "\n=== 6. 对比分析 ===\n";

echo "🔍 连接测试 vs 实际同步对比:\n\n";

echo "连接测试（成功）:\n";
echo "  - 只测试简单的GET请求\n";
echo "  - 不涉及复杂的数据处理\n";
echo "  - 主要验证API权限和网络连接\n\n";

echo "实际同步（失败）:\n";
echo "  - 需要复杂的产品数据映射\n";
echo "  - 涉及大量的数据转换和验证\n";
echo "  - 使用POST请求提交Feed数据\n";
echo "  - 需要正确的数据格式和字段\n\n";

echo "🎯 可能的问题原因:\n";
echo "1. 产品数据映射过程中出现错误\n";
echo "2. Feed数据格式不符合加拿大市场要求\n";
echo "3. 某些字段值不符合API规范\n";
echo "4. 数据编码或格式问题\n";
echo "5. 请求体大小或结构问题\n\n";

echo "📋 建议的调试步骤:\n";
echo "1. 检查上面的Feed提交错误信息\n";
echo "2. 验证产品映射生成的数据是否正确\n";
echo "3. 对比美国市场和加拿大市场的数据差异\n";
echo "4. 检查是否有特定字段导致问题\n";
echo "5. 尝试提交最小化的测试数据\n";

echo "\n=== 调试完成 ===\n";
?>
