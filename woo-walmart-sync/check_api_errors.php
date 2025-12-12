<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

echo "=== 检查API错误响应 ===\n\n";

// 1. 查找最近的API错误
$error_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE (status = '错误' OR status = 'error' OR response LIKE '%error%' OR response LIKE '%Error%')
    AND action = 'API请求'
    ORDER BY created_at DESC 
    LIMIT 5
");

echo "找到API错误日志数量: " . count($error_logs) . "\n\n";

foreach ($error_logs as $log) {
    echo "=== 错误时间: {$log->created_at} ===\n";
    echo "状态: {$log->status}\n";
    
    // 解析响应数据
    $response_data = json_decode($log->response, true);
    
    if ($response_data) {
        echo "API响应结构:\n";
        print_r($response_data);
        
        // 查找具体的错误信息
        if (isset($response_data['errors'])) {
            echo "\n具体错误信息:\n";
            foreach ($response_data['errors'] as $error) {
                if (isset($error['field'])) {
                    echo "❌ 字段: {$error['field']}\n";
                }
                if (isset($error['description'])) {
                    echo "   描述: {$error['description']}\n";
                }
                if (isset($error['code'])) {
                    echo "   代码: {$error['code']}\n";
                }
                echo "\n";
            }
        }
        
        // 查找fulfillmentLagTime相关错误
        $response_text = $log->response;
        if (strpos($response_text, 'fulfillmentLagTime') !== false) {
            echo "✓ 包含fulfillmentLagTime相关错误\n";
            if (preg_match('/fulfillmentLagTime[^}]*}/', $response_text, $matches)) {
                echo "相关内容: " . $matches[0] . "\n";
            }
        }
        
        // 查找lagTime相关错误
        if (strpos($response_text, 'lagTime') !== false) {
            echo "✓ 包含lagTime相关错误\n";
            if (preg_match('/lagTime[^}]*}/', $response_text, $matches)) {
                echo "相关内容: " . $matches[0] . "\n";
            }
        }
        
    } else {
        echo "原始响应内容:\n";
        echo substr($log->response, 0, 1000) . "\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

// 2. 如果没有找到错误日志，查看最近的所有API请求
if (count($error_logs) == 0) {
    echo "没有找到API错误日志，查看最近的API请求:\n\n";
    
    $recent_api_logs = $wpdb->get_results("
        SELECT * FROM $logs_table 
        WHERE action = 'API请求'
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    
    foreach ($recent_api_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "状态: {$log->status}\n";
        
        // 检查响应中的字段错误
        if (strpos($log->response, 'fulfillmentLagTime') !== false || 
            strpos($log->response, 'lagTime') !== false ||
            strpos($log->response, 'error') !== false) {
            
            echo "响应包含相关字段或错误信息\n";
            $response_data = json_decode($log->response, true);
            if ($response_data) {
                print_r($response_data);
            } else {
                echo "响应内容: " . substr($log->response, 0, 500) . "...\n";
            }
        }
        echo "---\n";
    }
}

// 3. 检查字段名称差异问题
echo "\n=== 检查字段名称差异 ===\n";
echo "您提到的问题: fulfillmentLagTime vs lagTime\n\n";

// 查找包含这两个字段的日志
$field_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE (request LIKE '%fulfillmentLagTime%' OR request LIKE '%lagTime%')
    ORDER BY created_at DESC 
    LIMIT 3
");

foreach ($field_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    
    // 检查请求中的字段名
    if (strpos($log->request, 'fulfillmentLagTime') !== false) {
        if (preg_match('/"fulfillmentLagTime":\s*"([^"]*)"/', $log->request, $matches)) {
            echo "✓ 请求中有fulfillmentLagTime: {$matches[1]}\n";
        }
    }
    
    if (strpos($log->request, 'lagTime') !== false && strpos($log->request, 'fulfillmentLagTime') === false) {
        if (preg_match('/"lagTime":\s*"([^"]*)"/', $log->request, $matches)) {
            echo "✓ 请求中有lagTime: {$matches[1]}\n";
        }
    }
    
    echo "---\n";
}

// 4. 检查当前分类映射中的字段配置
echo "\n=== 检查分类映射中的字段配置 ===\n";
$category_map_table = $wpdb->prefix . 'walmart_category_map';
$mappings = $wpdb->get_results("SELECT wc_category_name, walmart_attributes FROM $category_map_table LIMIT 3");

foreach ($mappings as $mapping) {
    echo "分类: {$mapping->wc_category_name}\n";
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        echo "配置的字段: " . implode(', ', $attributes['name']) . "\n";
        
        // 检查是否有lagTime或fulfillmentLagTime
        if (in_array('lagTime', $attributes['name'])) {
            echo "⚠️ 配置中使用的是 lagTime\n";
        }
        if (in_array('fulfillmentLagTime', $attributes['name'])) {
            echo "✓ 配置中使用的是 fulfillmentLagTime\n";
        }
    }
    echo "---\n";
}
?>
