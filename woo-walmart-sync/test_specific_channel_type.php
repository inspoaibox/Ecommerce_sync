<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 测试指定的Channel Type UUID ===\n\n";

$channel_type_uuid = 'd62e611e-606e-41b9-96cf-38ee37331c47';

echo "1. 测试Channel Type UUID:\n";
echo "UUID: {$channel_type_uuid}\n";
echo "UUID长度: " . strlen($channel_type_uuid) . " 字符\n";
echo "UUID格式: " . (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $channel_type_uuid) ? '✅ 有效' : '❌ 无效') . "\n\n";

// 获取访问令牌
require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();
$access_token = $api_auth->get_access_token();

if (!$access_token) {
    echo "❌ 无法获取访问令牌\n";
    exit;
}

echo "✅ 获取访问令牌成功\n\n";

// 2. 测试GET请求验证Channel Type
echo "2. 测试GET请求验证Channel Type:\n";

$test_url = 'https://marketplace.walmartapis.com/v3/ca/feeds?feedType=MP_ITEM_INTL';

$headers_get = [
    'WM_SEC.ACCESS_TOKEN: ' . $access_token,
    'WM_SVC.NAME: Walmart Marketplace',
    'WM_QOS.CORRELATION_ID: ' . uniqid(),
    'WM_CONSUMER.CHANNEL.TYPE: ' . $channel_type_uuid,
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_get);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP状态码: {$http_code}\n";

if ($http_code == 400) {
    $response_data = json_decode($response, true);
    if ($response_data && isset($response_data['error'])) {
        foreach ($response_data['error'] as $error) {
            if (isset($error['field']) && $error['field'] == 'WM_CONSUMER.CHANNEL.TYPE') {
                echo "❌ Channel Type仍然无效: {$error['description']}\n";
            } else {
                echo "⚠️ 其他错误: {$error['code']} - " . ($error['description'] ?? $error['info'] ?? '') . "\n";
            }
        }
    }
} elseif ($http_code == 405) {
    echo "🎉 成功！Channel Type有效 (405 Method Not Allowed - 端点存在但不支持GET)\n";
} elseif ($http_code == 200) {
    echo "🎉 成功！Channel Type有效 (200 OK)\n";
} else {
    echo "⚠️ 其他状态码: {$http_code}\n";
    if (!empty($response)) {
        echo "响应: " . substr($response, 0, 200) . "...\n";
    }
}

// 3. 如果Channel Type有效，测试实际的Feed提交
if ($http_code == 200 || $http_code == 405) {
    echo "\n3. 测试实际的Feed提交:\n";
    
    $business_unit = get_option('woo_walmart_business_unit', 'WALMART_CA');
    
    // 创建一个最小的测试Feed数据
    $test_feed_data = [
        'MPItemFeedHeader' => [
            'businessUnit' => $business_unit,
            'locale' => 'en',
            'version' => '5.0.20241118-04_39_24-api'
        ],
        'MPItem' => []
    ];
    
    $json_data = json_encode($test_feed_data, JSON_UNESCAPED_UNICODE);
    
    // 构建multipart数据
    $boundary = wp_generate_uuid4();
    $multipart_data = "--$boundary\r\n";
    $multipart_data .= "Content-Disposition: form-data; name=\"file\"; filename=\"test_feed.json\"\r\n";
    $multipart_data .= "Content-Type: application/json\r\n\r\n";
    $multipart_data .= $json_data . "\r\n";
    $multipart_data .= "--$boundary--\r\n";
    
    $headers_feed = [
        'WM_SEC.ACCESS_TOKEN: ' . $access_token,
        'WM_SVC.NAME: Walmart Marketplace',
        'WM_QOS.CORRELATION_ID: ' . uniqid(),
        'WM_CONSUMER.CHANNEL.TYPE: ' . $channel_type_uuid,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Accept: application/json'
    ];
    
    echo "提交空Feed测试...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_feed);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Feed提交HTTP状态码: {$http_code}\n";
    
    if ($http_code == 200 || $http_code == 202) {
        echo "🎉 成功！Feed提交成功\n";
        
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['feedId'])) {
            echo "Feed ID: {$response_data['feedId']}\n";
        }
        
        echo "\n✅ 解决方案确认：使用UUID {$channel_type_uuid} 作为Channel Type可以成功！\n";
        
    } elseif ($http_code == 400) {
        $response_data = json_decode($response, true);
        if ($response_data && isset($response_data['error'])) {
            foreach ($response_data['error'] as $error) {
                if (strpos($error['description'], 'CHANNEL.TYPE') !== false) {
                    echo "❌ 仍然是Channel Type问题: {$error['description']}\n";
                } else {
                    echo "⚠️ 其他错误: {$error['code']} - " . ($error['description'] ?? $error['info'] ?? '') . "\n";
                }
            }
        }
    } else {
        echo "⚠️ 其他状态码: {$http_code}\n";
        if (!empty($response)) {
            echo "响应: " . substr($response, 0, 300) . "...\n";
        }
    }
    
    // 4. 如果成功，测试带有实际产品数据的Feed
    if ($http_code == 200 || $http_code == 202) {
        echo "\n4. 测试带有实际产品数据的Feed:\n";
        
        // 获取一个测试产品
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_sku',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ]);
        
        if (!empty($products)) {
            $product = wc_get_product($products[0]->ID);
            echo "使用测试产品: {$product->get_name()} (SKU: {$product->get_sku()})\n";
            
            // 创建产品映射
            global $wpdb;
            $mapping_table = $wpdb->prefix . 'walmart_category_map';
            $mapping = $wpdb->get_row("SELECT * FROM {$mapping_table} LIMIT 1");
            
            if ($mapping) {
                require_once 'includes/class-product-mapper.php';
                $mapper = new Woo_Walmart_Product_Mapper();
                
                $walmart_category = $mapping->walmart_category_path;
                $attribute_rules = json_decode($mapping->walmart_attributes, true);
                
                try {
                    $mapped_data = $mapper->map($product, $walmart_category, '123456789012', $attribute_rules, 1);
                    
                    echo "✅ 产品映射成功\n";
                    echo "履行中心ID: " . ($mapped_data['MPItem'][0]['Orderable']['fulfillmentCenterID'] ?? '未设置') . "\n";
                    
                    // 提交实际产品数据
                    $json_data = json_encode($mapped_data, JSON_UNESCAPED_UNICODE);
                    
                    $boundary = wp_generate_uuid4();
                    $multipart_data = "--$boundary\r\n";
                    $multipart_data .= "Content-Disposition: form-data; name=\"file\"; filename=\"product_feed.json\"\r\n";
                    $multipart_data .= "Content-Type: application/json\r\n\r\n";
                    $multipart_data .= $json_data . "\r\n";
                    $multipart_data .= "--$boundary--\r\n";
                    
                    $headers_product = [
                        'WM_SEC.ACCESS_TOKEN: ' . $access_token,
                        'WM_SVC.NAME: Walmart Marketplace',
                        'WM_QOS.CORRELATION_ID: ' . uniqid(),
                        'WM_CONSUMER.CHANNEL.TYPE: ' . $channel_type_uuid,
                        'Content-Type: multipart/form-data; boundary=' . $boundary,
                        'Accept: application/json'
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $test_url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart_data);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_product);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    echo "产品Feed提交HTTP状态码: {$http_code}\n";
                    
                    if ($http_code == 200 || $http_code == 202) {
                        echo "🎉 成功！产品Feed提交成功\n";
                        
                        $response_data = json_decode($response, true);
                        if ($response_data && isset($response_data['feedId'])) {
                            echo "产品Feed ID: {$response_data['feedId']}\n";
                        }
                        
                        echo "\n🎯 完美！Channel Type问题已完全解决！\n";
                        
                    } else {
                        echo "⚠️ 产品Feed提交状态码: {$http_code}\n";
                        if (!empty($response)) {
                            $response_data = json_decode($response, true);
                            if ($response_data && isset($response_data['error'])) {
                                foreach ($response_data['error'] as $error) {
                                    echo "错误: {$error['code']} - " . ($error['description'] ?? $error['info'] ?? '') . "\n";
                                }
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "❌ 产品映射失败: " . $e->getMessage() . "\n";
                }
            } else {
                echo "❌ 没有找到分类映射\n";
            }
        } else {
            echo "❌ 没有找到测试产品\n";
        }
    }
}

echo "\n=== 测试完成 ===\n";

if ($http_code == 200 || $http_code == 405) {
    echo "\n🎉 解决方案确认：\n";
    echo "Channel Type UUID: {$channel_type_uuid}\n";
    echo "这个UUID可以成功通过Walmart加拿大API验证！\n";
    echo "\n下一步：修改代码中的Channel Type配置\n";
} else {
    echo "\n❌ 这个UUID仍然无效\n";
    echo "需要联系Walmart支持获取正确的Channel Type值\n";
}
