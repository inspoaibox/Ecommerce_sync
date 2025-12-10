<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 调试 MPItemFeedHeader 问题 ===\n\n";

$product_id = 6203;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n";
echo "产品ID: {$product_id}\n\n";

// 1. 检查businessUnit设置
echo "1. 检查businessUnit设置:\n";
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
echo "get_option('woo_walmart_business_unit'): " . json_encode($business_unit) . "\n";
echo "类型: " . gettype($business_unit) . "\n";
echo "是否为空: " . (empty($business_unit) ? '是' : '否') . "\n";

// 2. 检查所有相关的WordPress选项
echo "\n2. 检查所有沃尔玛相关选项:\n";
$walmart_options = [
    'woo_walmart_business_unit',
    'woo_walmart_consumer_id', 
    'woo_walmart_private_key',
    'woo_walmart_api_version',
    'woo_walmart_environment'
];

foreach ($walmart_options as $option) {
    $value = get_option($option, '未设置');
    echo "{$option}: " . json_encode($value) . "\n";
}

// 3. 执行映射并检查生成的header
echo "\n3. 执行映射并检查生成的header:\n";

// 获取分类映射
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$product_cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
$main_cat_id = $product_cat_ids[0];

$mapped_data = $wpdb->get_row($wpdb->prepare(
    "SELECT walmart_category_path, walmart_attributes FROM $map_table WHERE wc_category_id = %d", 
    $main_cat_id
));

$attribute_rules = json_decode($mapped_data->walmart_attributes, true);

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

$walmart_data = $mapper->map($product, $mapped_data->walmart_category_path, '123456789012', $attribute_rules, 1);

echo "生成的MPItemFeedHeader:\n";
if (isset($walmart_data['MPItemFeedHeader'])) {
    $header = $walmart_data['MPItemFeedHeader'];
    echo json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // 检查每个字段
    echo "\n字段检查:\n";
    $required_fields = ['businessUnit', 'locale', 'version'];
    foreach ($required_fields as $field) {
        if (isset($header[$field])) {
            echo "✅ {$field}: " . json_encode($header[$field]) . "\n";
        } else {
            echo "❌ {$field}: 缺失\n";
        }
    }
    
    // 检查是否有不应该存在的字段
    $deprecated_fields = ['subset', 'sellingChannel', 'processMode', 'subCategory'];
    echo "\n检查废弃字段:\n";
    foreach ($deprecated_fields as $field) {
        if (isset($header[$field])) {
            echo "⚠️ {$field}: 存在（应该移除）\n";
        } else {
            echo "✅ {$field}: 不存在（正确）\n";
        }
    }
} else {
    echo "❌ MPItemFeedHeader缺失\n";
}

// 4. 检查最近的API请求
echo "\n4. 检查最近的API请求中的header:\n";
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$recent_request = $wpdb->get_row("
    SELECT request, response, created_at 
    FROM $logs_table 
    WHERE action = 'API请求' 
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($recent_request) {
    echo "最近的API请求 ({$recent_request->created_at}):\n";
    $request_data = json_decode($recent_request->request, true);
    
    if ($request_data && isset($request_data['body'])) {
        // 检查是否是文件上传请求
        if (isset($request_data['headers']['Content-Type']) &&
            strpos($request_data['headers']['Content-Type'], 'multipart/form-data') !== false) {
            echo "这是文件上传请求，无法直接解析body\n";
            echo "文件大小: " . (isset($request_data['file_size']) ? $request_data['file_size'] : '未知') . " 字节\n";
        } else {
            $body_data = json_decode($request_data['body'], true);

            if ($body_data && isset($body_data['MPItemFeedHeader'])) {
                $api_header = $body_data['MPItemFeedHeader'];
                echo "API请求中的MPItemFeedHeader:\n";
                echo json_encode($api_header, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

                // 比较映射器生成的和API请求中的
                if (isset($walmart_data['MPItemFeedHeader'])) {
                    $mapper_header = $walmart_data['MPItemFeedHeader'];
                    if (json_encode($mapper_header) !== json_encode($api_header)) {
                        echo "\n⚠️ 映射器生成的header与API请求中的不同！\n";
                        echo "映射器生成: " . json_encode($mapper_header) . "\n";
                        echo "API请求中: " . json_encode($api_header) . "\n";
                    } else {
                        echo "\n✅ 映射器生成的header与API请求中的一致\n";
                    }
                }
            } else {
                echo "❌ API请求中没有MPItemFeedHeader\n";
            }
        }
    }
    
    // 检查API响应中的错误
    if ($recent_request->response) {
        $response_data = json_decode($recent_request->response, true);
        if ($response_data && isset($response_data['itemDetails']['itemIngestionStatus'])) {
            $items = $response_data['itemDetails']['itemIngestionStatus'];
            foreach ($items as $item) {
                if (isset($item['ingestionErrors']['ingestionError'])) {
                    $errors = $item['ingestionErrors']['ingestionError'];
                    echo "\n最近的API错误:\n";
                    foreach ($errors as $error) {
                        if ($error['field'] === 'businessUnit' || $error['field'] === 'MPItemFeedHeader') {
                            echo "❌ {$error['field']}: {$error['description']}\n";
                        }
                    }
                }
            }
        }
    }
} else {
    echo "❌ 没有找到最近的API请求\n";
}

echo "\n=== 调试完成 ===\n";
echo "如果映射器生成的header正确但API收到的不正确，说明在发送过程中header被修改了\n";
?>
