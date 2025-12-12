<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 测试新的批量同步 ===\n\n";

// 1. 清理最近的日志
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

echo "1. 清理最近10分钟的日志:\n";
$deleted = $wpdb->query("DELETE FROM $logs_table WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
echo "删除了 {$deleted} 条最近的日志\n\n";

// 2. 测试bulk_update_product_info方法
echo "2. 测试批量产品信息更新:\n";

require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();

// 创建测试数据
$test_products = [
    [
        'sku' => 'TEST_BATCH_001',
        'product_name' => 'Test Batch Product 1',
        'short_description' => 'Test batch description 1'
    ]
];

echo "测试数据: " . json_encode($test_products, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "调用bulk_update_product_info...\n";
$result = $api_auth->bulk_update_product_info($test_products);

if (is_wp_error($result)) {
    echo "❌ 调用失败: " . $result->get_error_message() . "\n";
} else {
    echo "✅ 调用成功\n";
    echo "响应: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    
    if (isset($result['feedId'])) {
        $feed_id = $result['feedId'];
        echo "Feed ID: {$feed_id}\n";
        
        // 等待并检查Feed状态
        echo "\n等待10秒后检查Feed状态...\n";
        sleep(10);
        
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
                        
                        $business_unit_errors = 0;
                        $subset_errors = 0;
                        
                        foreach ($errors as $error) {
                            if ($error['field'] === 'businessUnit') {
                                $business_unit_errors++;
                                echo "❌ businessUnit错误: {$error['description']}\n";
                            }
                            if ($error['field'] === 'MPItemFeedHeader' && strpos($error['description'], 'subset') !== false) {
                                $subset_errors++;
                                echo "❌ subset错误: {$error['description']}\n";
                            }
                        }
                        
                        if ($business_unit_errors === 0 && $subset_errors === 0) {
                            echo "🎉 **好消息！没有businessUnit和subset错误了！**\n";
                        } else {
                            echo "⚠️ 仍然有header相关错误\n";
                        }
                        
                    } else {
                        echo "✅ 没有错误！完美！\n";
                    }
                }
            }
        } else {
            echo "获取Feed状态失败: " . $feed_status->get_error_message() . "\n";
        }
    }
}

// 3. 检查新的日志
echo "\n3. 检查新生成的日志:\n";

$new_logs = $wpdb->get_results("
    SELECT action, status, request, response, created_at 
    FROM $logs_table 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY created_at DESC
");

echo "找到 " . count($new_logs) . " 条新日志:\n\n";

foreach ($new_logs as $log) {
    echo "=== {$log->action} ({$log->status}) - {$log->created_at} ===\n";
    
    if ($log->action === '文件上传方法-开始') {
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "文件大小: " . ($request_data['data_size'] ?? '未知') . " 字节\n";
        }
    }
    
    if ($log->action === 'API请求-文件上传') {
        echo "这是文件上传请求\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "文件大小: " . ($request_data['file_size'] ?? '未知') . " 字节\n";
        }
    }
    
    echo "\n";
}

echo "\n=== 总结 ===\n";
echo "如果这次测试没有出现businessUnit和subset错误，说明修复已经生效\n";
echo "如果仍然有错误，可能需要:\n";
echo "1. 重启PHP服务清理OPcache\n";
echo "2. 检查是否有其他进程在使用旧代码\n";
echo "3. 确认WordPress没有缓存旧的代码\n";

echo "\n=== 测试完成 ===\n";
?>
