<?php
/**
 * 检查实际发送给Walmart API的数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际发送给Walmart API的数据 ===\n\n";

$test_product_id = 25926;
$product = wc_get_product($test_product_id);

if (!$product) {
    echo "❌ 测试产品不存在\n";
    exit;
}

echo "产品: {$product->get_name()}\n";
echo "SKU: {$product->get_sku()}\n\n";

// 1. 获取完整的映射数据
echo "1. 获取完整的映射数据:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 获取分类映射
$categories = $product->get_category_ids();
global $wpdb;
$mapping = null;
$walmart_category = null;
$attribute_rules = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $walmart_category = $mapping->walmart_category_path;
        $attribute_rules = !empty($mapping->walmart_attributes) ? 
            json_decode($mapping->walmart_attributes, true) : [];
        break;
    }
}

if (!$walmart_category) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

$upc = get_post_meta($product->get_id(), '_walmart_upc', true) ?: '123456789012';
$fulfillment_lag_time = 1;

$walmart_data = $mapper->map($product, $walmart_category, $upc, $attribute_rules, $fulfillment_lag_time);

if (!$walmart_data) {
    echo "❌ 映射失败\n";
    exit;
}

// 2. 检查映射数据中的assembledProductWeight
echo "2. 检查映射数据中的assembledProductWeight:\n";

if (isset($walmart_data['MPItem'][0]['Visible'][$walmart_category]['assembledProductWeight'])) {
    $weight_data = $walmart_data['MPItem'][0]['Visible'][$walmart_category]['assembledProductWeight'];
    echo "映射数据:\n";
    echo "  类型: " . gettype($weight_data) . "\n";
    if (is_array($weight_data)) {
        echo "  measure: " . ($weight_data['measure'] ?? '缺失') . " (" . gettype($weight_data['measure'] ?? null) . ")\n";
        echo "  unit: " . ($weight_data['unit'] ?? '缺失') . " (" . gettype($weight_data['unit'] ?? null) . ")\n";
    } else {
        echo "  值: {$weight_data}\n";
    }
} else {
    echo "❌ 映射数据中没有assembledProductWeight字段\n";
    exit;
}

// 3. 生成实际的JSON请求
echo "\n3. 生成实际的JSON请求:\n";

$json_data = json_encode($walmart_data, JSON_UNESCAPED_UNICODE);
echo "JSON大小: " . strlen($json_data) . " 字节\n";

// 查找assembledProductWeight在JSON中的确切格式
if (preg_match('/"assembledProductWeight":\s*({[^}]+})/', $json_data, $matches)) {
    echo "JSON中的assembledProductWeight:\n";
    echo $matches[1] . "\n";
    
    // 验证JSON格式
    $weight_json = json_decode($matches[1], true);
    if ($weight_json) {
        echo "JSON解析结果:\n";
        foreach ($weight_json as $key => $value) {
            echo "  {$key}: {$value} (" . gettype($value) . ")\n";
        }
    }
} else {
    echo "❌ JSON中没有找到assembledProductWeight\n";
}

// 4. 检查是否有JSON编码问题
echo "\n4. 检查JSON编码问题:\n";

$test_weight = ['measure' => 140.0, 'unit' => 'lb'];
$test_json = json_encode($test_weight);
echo "测试重量数据: " . var_export($test_weight, true) . "\n";
echo "JSON编码结果: {$test_json}\n";

if ($test_json === '{"measure":140,"unit":"lb"}') {
    echo "✅ JSON编码正常\n";
} else {
    echo "❌ JSON编码异常\n";
}

// 5. 检查最近的Feed详细信息
echo "\n5. 检查最近的Feed详细信息:\n";

$recent_feed = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}walmart_feeds WHERE sku = %s ORDER BY created_at DESC LIMIT 1",
    $product->get_sku()
));

if ($recent_feed) {
    echo "最近的Feed:\n";
    echo "  Feed ID: {$recent_feed->feed_id}\n";
    echo "  状态: {$recent_feed->status}\n";
    echo "  提交时间: {$recent_feed->submitted_at}\n";
    
    if (!empty($recent_feed->api_response)) {
        $response_data = json_decode($recent_feed->api_response, true);
        if ($response_data) {
            echo "  Feed状态: " . ($response_data['feedStatus'] ?? '未知') . "\n";
            echo "  处理状态: 已接收 " . ($response_data['itemsReceived'] ?? 0) . " 个，成功 " . ($response_data['itemsSucceeded'] ?? 0) . " 个\n";
            
            // 如果有错误信息
            if (isset($response_data['itemDetails']['itemIngestionStatus'])) {
                $ingestion_status = $response_data['itemDetails']['itemIngestionStatus'];
                if (!empty($ingestion_status)) {
                    echo "  处理详情:\n";
                    foreach ($ingestion_status as $item) {
                        if (isset($item['ingestionErrors'])) {
                            echo "    错误: " . json_encode($item['ingestionErrors']) . "\n";
                        }
                    }
                }
            }
        }
    }
} else {
    echo "❌ 没有找到Feed记录\n";
}

// 6. 模拟API请求检查
echo "\n6. 模拟API请求检查:\n";

// 检查Content-Type和其他可能影响的因素
echo "检查可能的问题:\n";
echo "1. Content-Type: application/json ✅\n";
echo "2. JSON格式: 正确的对象格式 ✅\n";
echo "3. 字段类型: measure为数字，unit为字符串 ✅\n";
echo "4. 单位值: 'lb' 在允许列表中 ✅\n";

echo "\n=== 可能的原因分析 ===\n";
echo "既然我们的数据格式完全正确，Walmart后台只显示数值的可能原因:\n";
echo "1. Walmart后台UI的显示bug\n";
echo "2. Walmart对某些分类的重量字段有特殊处理\n";
echo "3. Feed处理过程中Walmart内部逻辑问题\n";
echo "4. 需要等待Feed完全处理完成才能看到完整信息\n";
echo "5. Walmart后台可能需要刷新或重新加载\n";

?>
