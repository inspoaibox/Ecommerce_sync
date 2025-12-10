<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 全新同步测试 - 追踪header问题 ===\n\n";

$product_id = 6203;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n";
echo "产品ID: {$product_id}\n\n";

// 1. 清理旧的日志（可选）
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

echo "1. 清理最近5分钟的日志（避免混淆）:\n";
$deleted = $wpdb->query("DELETE FROM $logs_table WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
echo "删除了 {$deleted} 条最近的日志\n\n";

// 2. 执行映射
echo "2. 执行产品映射:\n";

// 获取分类映射
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

echo "映射完成\n";
echo "MPItemFeedHeader: " . json_encode($walmart_data['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE) . "\n\n";

// 3. 执行同步
echo "3. 执行API同步:\n";

require_once 'includes/class-product-sync.php';
$sync = new Woo_Walmart_Product_Sync();

$result = $sync->initiate_sync($product_id);

if ($result['success']) {
    echo "✅ 同步提交成功: {$result['message']}\n";
} else {
    echo "❌ 同步失败: {$result['message']}\n";
}

// 4. 等待并检查日志
echo "\n4. 等待3秒后检查新的日志:\n";
sleep(3);

$new_logs = $wpdb->get_results("
    SELECT action, status, request, response, created_at 
    FROM $logs_table 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ORDER BY created_at DESC
");

echo "找到 " . count($new_logs) . " 条新日志:\n\n";

foreach ($new_logs as $log) {
    echo "=== {$log->action} ({$log->status}) - {$log->created_at} ===\n";
    
    if ($log->action === 'API请求-文件上传' || $log->action === 'API请求') {
        echo "这是API请求日志\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "请求方法: " . ($request_data['method'] ?? '未知') . "\n";
            echo "文件大小: " . ($request_data['file_size'] ?? '未知') . " 字节\n";
            
            if (isset($request_data['headers']['Content-Type'])) {
                echo "Content-Type: " . $request_data['headers']['Content-Type'] . "\n";
            }
        }
        
        // 检查响应中的错误
        if ($log->response) {
            $response_data = json_decode($log->response, true);
            if ($response_data && isset($response_data['error'])) {
                echo "API错误: " . $response_data['error'] . "\n";
            }
        }
    }
    
    if ($log->action === '产品映射-最终数据结构') {
        echo "这是映射器生成的最终数据\n";
        
        $mapping_data = json_decode($log->request, true);
        if ($mapping_data && isset($mapping_data['MPItemFeedHeader'])) {
            $header = $mapping_data['MPItemFeedHeader'];
            echo "映射器生成的Header:\n";
            echo json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // 检查是否有问题字段
            if (isset($header['subset'])) {
                echo "⚠️ 发现subset字段！\n";
            }
            if (!isset($header['businessUnit'])) {
                echo "⚠️ 缺少businessUnit字段！\n";
            }
        }
    }
    
    echo "\n";
}

// 5. 检查Feed状态
echo "5. 等待10秒后检查Feed状态:\n";
sleep(10);

if ($result['success'] && isset($result['feed_id'])) {
    $feed_id = $result['feed_id'];
    echo "检查Feed ID: {$feed_id}\n";
    
    require_once 'includes/class-api-key-auth.php';
    $api_auth = new Woo_Walmart_API_Key_Auth();
    
    $feed_status = $api_auth->make_request("/v3/feeds/{$feed_id}?includeDetails=true");
    
    if (!is_wp_error($feed_status)) {
        echo "Feed状态: " . ($feed_status['feedStatus'] ?? '未知') . "\n";
        
        if (isset($feed_status['itemDetails']['itemIngestionStatus'])) {
            $items = $feed_status['itemDetails']['itemIngestionStatus'];
            
            foreach ($items as $item) {
                echo "SKU: {$item['sku']}\n";
                echo "状态: {$item['ingestionStatus']}\n";
                
                if (isset($item['ingestionErrors']['ingestionError'])) {
                    $errors = $item['ingestionErrors']['ingestionError'];
                    echo "错误数量: " . count($errors) . "\n";
                    
                    foreach ($errors as $error) {
                        if ($error['field'] === 'businessUnit' || $error['field'] === 'MPItemFeedHeader') {
                            echo "🎯 关键错误: {$error['field']} - {$error['description']}\n";
                        }
                    }
                }
            }
        }
    } else {
        echo "获取Feed状态失败: " . $feed_status->get_error_message() . "\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "如果映射器生成的header正确但API仍报错，说明问题在文件上传过程中\n";
?>
