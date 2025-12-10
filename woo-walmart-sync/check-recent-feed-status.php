<?php
/**
 * 检查最近的Feed状态，找到具体错误
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查最近的Feed状态 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 查找最近的Feed状态检查日志
echo "=== 查找Feed状态检查日志 ===\n";

$feed_status_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action LIKE '%Feed状态%' 
    OR action LIKE '%feed%status%'
    OR message LIKE '%Feed%'
    ORDER BY created_at DESC 
    LIMIT 10
");

foreach ($feed_status_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "状态: {$log->status}\n";
    echo "消息: {$log->message}\n";
    
    if (!empty($log->response)) {
        $response_data = json_decode($log->response, true);
        
        if ($response_data) {
            // 检查Feed状态
            if (isset($response_data['feedStatus'])) {
                echo "Feed状态: {$response_data['feedStatus']}\n";
            }
            
            // 检查是否有错误的商品
            if (isset($response_data['itemsReceived'])) {
                echo "商品处理结果:\n";
                
                foreach ($response_data['itemsReceived'] as $index => $item) {
                    if (isset($item['sku']) && in_array($item['sku'], ['W116465061', 'N771P254005L'])) {
                        echo "  SKU: {$item['sku']}\n";
                        echo "  状态: {$item['ingestionStatus']}\n";
                        
                        if (isset($item['ingestionErrors']['ingestionError'])) {
                            echo "  错误详情:\n";
                            foreach ($item['ingestionErrors']['ingestionError'] as $error) {
                                echo "    字段: {$error['field']}\n";
                                echo "    错误类型: {$error['type']}\n";
                                echo "    错误代码: {$error['code']}\n";
                                echo "    错误描述: {$error['description']}\n";
                                echo "    ---\n";
                            }
                        }
                    }
                    
                    // 只显示前5个商品
                    if ($index >= 4) break;
                }
            }
        }
    }
    echo "===================\n";
}

// 2. 查找包含DATA_ERROR的日志
echo "\n=== 查找DATA_ERROR日志 ===\n";

$data_error_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE response LIKE '%DATA_ERROR%'
    AND (response LIKE '%seat_depth%' OR response LIKE '%arm_height%')
    ORDER BY created_at DESC 
    LIMIT 5
");

foreach ($data_error_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    
    if (!empty($log->response)) {
        $response_data = json_decode($log->response, true);
        
        if ($response_data && isset($response_data['itemsReceived'])) {
            foreach ($response_data['itemsReceived'] as $item) {
                if (isset($item['ingestionStatus']) && $item['ingestionStatus'] === 'DATA_ERROR') {
                    if (isset($item['ingestionErrors']['ingestionError'])) {
                        foreach ($item['ingestionErrors']['ingestionError'] as $error) {
                            if (in_array($error['field'], ['seat_depth', 'arm_height'])) {
                                echo "SKU: {$item['sku']}\n";
                                echo "字段: {$error['field']}\n";
                                echo "错误: {$error['description']}\n";
                                echo "---\n";
                            }
                        }
                    }
                }
            }
        }
    }
}

// 3. 查找最近的产品同步日志，看看字段是如何生成的
echo "\n=== 查找产品同步过程日志 ===\n";

$sync_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE (action LIKE '%产品同步%' OR action LIKE '%映射%')
    AND product_id IN (
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' 
        AND meta_value IN ('W116465061', 'N771P254005L')
    )
    ORDER BY created_at DESC 
    LIMIT 10
");

foreach ($sync_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "产品ID: {$log->product_id}\n";
    echo "消息: {$log->message}\n";
    
    if (!empty($log->request)) {
        $request_data = json_decode($log->request, true);
        
        if ($request_data) {
            // 查找seat_depth和arm_height字段
            if (isset($request_data['seat_depth'])) {
                echo "  seat_depth: " . json_encode($request_data['seat_depth']) . " (类型: " . gettype($request_data['seat_depth']) . ")\n";
            }
            
            if (isset($request_data['arm_height'])) {
                echo "  arm_height: " . json_encode($request_data['arm_height']) . " (类型: " . gettype($request_data['arm_height']) . ")\n";
            }
            
            // 查找分类信息
            if (isset($request_data['category'])) {
                echo "  使用分类: {$request_data['category']}\n";
            }
            
            if (isset($request_data['walmart_category'])) {
                echo "  沃尔玛分类: {$request_data['walmart_category']}\n";
            }
        }
    }
    echo "---\n";
}

// 4. 检查是否有自动字段生成的日志
echo "\n=== 查找自动字段生成日志 ===\n";

$auto_field_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action LIKE '%自动%' 
    OR action LIKE '%生成%'
    OR action LIKE '%字段%'
    OR message LIKE '%seat_depth%'
    OR message LIKE '%arm_height%'
    ORDER BY created_at DESC 
    LIMIT 10
");

foreach ($auto_field_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "消息: {$log->message}\n";
    
    if (strpos($log->message, 'seat_depth') !== false || strpos($log->message, 'arm_height') !== false) {
        echo "  ✅ 包含相关字段信息\n";
    }
    echo "---\n";
}

echo "\n=== 总结 ===\n";
echo "从以上日志中，我们应该能找到：\n";
echo "1. 具体的错误信息和字段值\n";
echo "2. 字段是在哪个环节生成的\n";
echo "3. 为什么没有进行正确的类型转换\n";

?>
