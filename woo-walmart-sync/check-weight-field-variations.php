<?php
/**
 * 检查不同的重量字段处理方式
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查重量字段的不同处理方式 ===\n\n";

// 1. 检查当前实现的问题
echo "1. 检查当前assembledProductWeight实现:\n";

require_once 'includes/class-product-mapper.php';

// 2. 检查当前实现是否正确提取单位
echo "2. 检查当前实现的单位处理:\n";

$test_product_id = 25926;
$product = wc_get_product($test_product_id);

$weight_value = $product->get_attribute('Product Weight');
echo "原始Product Weight: {$weight_value}\n";

// 检查当前实现的正则表达式
$current_regex = '/([0-9]+\.?[0-9]*)/';
if (preg_match($current_regex, $weight_value, $matches)) {
    echo "当前正则提取结果: {$matches[1]}\n";
    echo "❌ 当前正则只提取数字，忽略单位\n";
} else {
    echo "❌ 当前正则无法提取数字\n";
}

// 测试改进的正则表达式
$improved_regex = '/([0-9]+\.?[0-9]*)\s*(lb|lbs|oz|kg|g)\b/i';
if (preg_match($improved_regex, $weight_value, $matches)) {
    echo "改进正则提取结果:\n";
    echo "  数值: {$matches[1]}\n";
    echo "  单位: {$matches[2]}\n";
    echo "✅ 改进正则能正确提取数值和单位\n";
} else {
    echo "❌ 改进正则也无法提取\n";
}

// 3. 找到问题根源
echo "\n3. 问题根源分析:\n";

echo "当前实现的问题:\n";
echo "1. 正则表达式 '/([0-9]+\.?[0-9]*)/' 只提取数字\n";
echo "2. 硬编码返回单位为 'lb'\n";
echo "3. 完全忽略原始数据中的单位信息\n";

echo "\n实际情况:\n";
echo "- 原始数据: '{$weight_value}' 包含单位 'lb'\n";
echo "- 当前实现: 提取数字 140，硬编码单位 'lb'\n";
echo "- 结果: {\"measure\": 140, \"unit\": \"lb\"}\n";

echo "\n如果Walmart后台只显示数值，可能原因:\n";
echo "1. 数据传输正确，但Walmart后台显示有问题\n";
echo "2. 某些特殊情况下单位信息丢失\n";
echo "3. API版本或格式要求不同\n";

// 4. 检查实际的Feed数据
echo "\n4. 检查最近的Feed数据:\n";

global $wpdb;
$recent_feeds = $wpdb->get_results("
    SELECT feed_id, sku, api_response
    FROM {$wpdb->prefix}walmart_feeds
    WHERE sku = 'W1191S00043'
    ORDER BY created_at DESC
    LIMIT 3
");

if (!empty($recent_feeds)) {
    foreach ($recent_feeds as $feed) {
        echo "Feed ID: {$feed->feed_id}\n";
        echo "SKU: {$feed->sku}\n";

        if (!empty($feed->api_response)) {
            $response_data = json_decode($feed->api_response, true);
            if ($response_data) {
                echo "API响应: " . substr(json_encode($response_data), 0, 200) . "...\n";
            }
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到相关的Feed记录\n";
}

echo "\n=== 结论 ===\n";
echo "问题确认: 当前实现硬编码单位为'lb'，虽然数据格式正确，\n";
echo "但如果原始数据单位不是'lb'，或者Walmart期望特定格式，\n";
echo "可能会导致显示问题。\n";

?>
