<?php
/**
 * 实际API调用调试 - 检查真实的请求和响应
 * 不查代码，直接测试实际的API交互
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 实际API调用调试 ===\n\n";

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\canda.localhost';
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';

echo "✅ WordPress环境加载成功\n\n";

// === 1. 检查当前设置 ===
echo "=== 1. 当前设置检查 ===\n";
$business_unit = get_option('woo_walmart_business_unit', '');
$fulfillment_center_id = get_option('woo_walmart_fulfillment_center_id', '');
$default_market = get_option('woo_walmart_default_market', '');

echo "业务单元: $business_unit\n";
echo "履行中心ID: $fulfillment_center_id\n";
echo "默认市场: $default_market\n\n";

// === 2. 创建简单测试产品并映射 ===
echo "=== 2. 创建测试产品并获取实际映射数据 ===\n";

// 创建测试产品
$test_product = new WC_Product_Simple();
$test_product->set_name('API调试测试产品');
$test_product->set_sku('DEBUG-API-TEST-001');
$test_product->set_regular_price(99.99);
$test_product->set_description('用于调试API调用的测试产品');
$test_product->set_short_description('API调试产品');
$test_product->set_status('publish');
$test_product->set_catalog_visibility('visible');
$test_product->set_stock_status('instock');
$test_product->set_manage_stock(true);
$test_product->set_stock_quantity(50);

// 设置分类
$category_ids = [15]; // 使用现有分类
$test_product->set_category_ids($category_ids);

$product_id = $test_product->save();
echo "✅ 创建测试产品: ID $product_id, SKU: DEBUG-API-TEST-001\n";

// 获取分类映射
global $wpdb;
$category_mapping_table = $wpdb->prefix . 'walmart_category_mapping';
$mapped_category_data = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $category_mapping_table WHERE wc_category_id = %d LIMIT 1",
    $category_ids[0]
));

if (!$mapped_category_data) {
    echo "❌ 未找到分类映射，无法继续测试\n";
    wp_delete_post($product_id, true);
    exit;
}

echo "✅ 找到分类映射: {$mapped_category_data->walmart_category_path}\n\n";

// === 3. 执行实际的产品映射 ===
echo "=== 3. 执行实际产品映射 ===\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

$attribute_rules = json_decode($mapped_category_data->walmart_attributes, true);
if (!is_array($attribute_rules)) {
    $attribute_rules = ['name' => [], 'type' => [], 'source' => []];
}

$test_upc = '123456789012';

try {
    $walmart_data = $mapper->map($test_product, $mapped_category_data->walmart_category_path, $test_upc, $attribute_rules, 1);
    
    echo "✅ 产品映射成功\n";
    
    // 检查生成的履行中心ID
    $orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];
    $generated_fc_id = $orderable['fulfillmentCenterID'] ?? '未设置';
    
    echo "生成的履行中心ID: $generated_fc_id\n";
    echo "期望的履行中心ID: $fulfillment_center_id\n";
    
    if ($generated_fc_id === $fulfillment_center_id) {
        echo "✅ 履行中心ID匹配\n";
    } else {
        echo "❌ 履行中心ID不匹配！\n";
        echo "这可能是问题的根源\n";
    }
    
} catch (Exception $e) {
    echo "❌ 产品映射失败: " . $e->getMessage() . "\n";
    wp_delete_post($product_id, true);
    exit;
}

echo "\n";

// === 4. 模拟实际的API调用 ===
echo "=== 4. 模拟实际API调用 ===\n";

// 构建Feed数据
$feed_data = [
    'MPItemFeedHeader' => [
        'businessUnit' => $business_unit,
        'locale' => 'en',
        'version' => '5.0.20241118-04_39_24-api'
    ],
    'MPItem' => [$walmart_data['MPItem'][0]]
];

$json_data = json_encode($feed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "准备发送的JSON数据:\n";
echo "```json\n";
echo substr($json_data, 0, 1000) . (strlen($json_data) > 1000 ? "...\n[数据被截断，总长度: " . strlen($json_data) . " 字符]" : "");
echo "\n```\n\n";

// 检查关键字段
echo "关键字段检查:\n";
echo "- businessUnit: " . $feed_data['MPItemFeedHeader']['businessUnit'] . "\n";
echo "- fulfillmentCenterID: " . $orderable['fulfillmentCenterID'] . "\n";
echo "- sku: " . $orderable['sku'] . "\n";
echo "- price: " . $orderable['price'] . "\n\n";

// === 5. 实际发送API请求 ===
echo "=== 5. 实际API请求测试 ===\n";

// 获取API认证
if (class_exists('Woo_Walmart_Multi_Market_API_Router')) {
    try {
        $api_router = new Woo_Walmart_Multi_Market_API_Router('CA');
        
        // 获取访问令牌
        $access_token = $api_router->get_access_token();
        
        if (!$access_token) {
            echo "❌ 无法获取访问令牌\n";
        } else {
            echo "✅ 获取访问令牌成功 (长度: " . strlen($access_token) . ")\n";
            
            // 构建API请求
            $api_url = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM';
            
            echo "API端点: $api_url\n";
            
            // 构建multipart数据
            $boundary = '----WebKitFormBoundary' . uniqid();
            $multipart_data = "--$boundary\r\n";
            $multipart_data .= "Content-Disposition: form-data; name=\"file\"; filename=\"batch_feed.json\"\r\n";
            $multipart_data .= "Content-Type: application/json\r\n\r\n";
            $multipart_data .= $json_data . "\r\n";
            $multipart_data .= "--$boundary--\r\n";
            
            // 构建请求头
            $headers = [
                'WM_SEC.ACCESS_TOKEN: ' . $access_token,
                'WM_SVC.NAME: Walmart Marketplace',
                'WM_QOS.CORRELATION_ID: ' . uniqid(),
                'Content-Type: multipart/form-data; boundary=' . $boundary,
                'Accept: application/json'
            ];
            
            echo "请求头:\n";
            foreach ($headers as $header) {
                if (strpos($header, 'ACCESS_TOKEN') !== false) {
                    echo "  " . substr($header, 0, 30) . "...[TOKEN]\n";
                } else {
                    echo "  $header\n";
                }
            }
            echo "\n";
            
            // 发送请求
            echo "发送API请求...\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                echo "❌ CURL错误: $error\n";
            } else {
                echo "✅ API请求完成\n";
                echo "HTTP状态码: $http_code\n";
                
                // 分离响应头和响应体
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $response_headers = substr($response, 0, $header_size);
                $response_body = substr($response, $header_size);
                
                echo "\n响应头:\n";
                echo substr($response_headers, 0, 500) . "\n";
                
                echo "\n响应体:\n";
                echo $response_body . "\n";
                
                // 尝试解析JSON响应
                $json_response = json_decode($response_body, true);
                if ($json_response) {
                    echo "\n解析的响应数据:\n";
                    print_r($json_response);
                    
                    if (isset($json_response['feedId'])) {
                        echo "\n✅ Feed创建成功，Feed ID: " . $json_response['feedId'] . "\n";
                        echo "现在可以使用这个Feed ID查询状态\n";
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ API调用异常: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ API路由器类不存在\n";
}

// === 6. 清理测试数据 ===
echo "\n=== 6. 清理测试数据 ===\n";
wp_delete_post($product_id, true);
echo "✅ 删除测试产品\n";

echo "\n=== 调试完成 ===\n";

echo "\n=== 关键发现总结 ===\n";
echo "1. 检查生成的履行中心ID是否与设置匹配\n";
echo "2. 检查API端点是否正确 (应该是 /v3/ca/feeds)\n";
echo "3. 检查API响应的具体错误信息\n";
echo "4. 检查访问令牌是否有效\n";
echo "5. 检查请求格式是否正确\n";
?>
