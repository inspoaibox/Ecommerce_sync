<?php
/**
 * 检查Feed数据的详细内容
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查Feed数据的详细内容 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 查找最近包含这些字段的Feed数据
echo "=== 查找最近的Feed数据 ===\n";

$feed_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE (action LIKE '%文件上传%' OR action LIKE '%Feed%')
    AND (request LIKE '%seat_depth%' OR request LIKE '%arm_height%')
    ORDER BY created_at DESC 
    LIMIT 3
");

foreach ($feed_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "状态: {$log->status}\n";
    
    if (!empty($log->request)) {
        $request_data = json_decode($log->request, true);
        
        if ($request_data) {
            // 查找MPItem数组中的数据
            if (isset($request_data['MPItem']) && is_array($request_data['MPItem'])) {
                echo "找到 " . count($request_data['MPItem']) . " 个产品\n";
                
                foreach ($request_data['MPItem'] as $index => $item) {
                    if (isset($item['sku'])) {
                        echo "  产品 {$index}: SKU {$item['sku']}\n";
                        
                        // 检查seat_depth字段
                        if (isset($item['seat_depth'])) {
                            echo "    seat_depth: " . json_encode($item['seat_depth'], JSON_UNESCAPED_UNICODE) . "\n";
                            echo "    seat_depth类型: " . gettype($item['seat_depth']) . "\n";
                            
                            if (is_string($item['seat_depth'])) {
                                echo "    ❌ 问题：seat_depth是字符串 '{$item['seat_depth']}'\n";
                                echo "    ❌ 应该是JSONObject格式: {\"measure\": X, \"unit\": \"in\"}\n";
                            } else if (is_array($item['seat_depth'])) {
                                echo "    ✅ seat_depth是数组/对象格式\n";
                            }
                        }
                        
                        // 检查arm_height字段
                        if (isset($item['arm_height'])) {
                            echo "    arm_height: " . json_encode($item['arm_height'], JSON_UNESCAPED_UNICODE) . "\n";
                            echo "    arm_height类型: " . gettype($item['arm_height']) . "\n";
                            
                            if (is_string($item['arm_height'])) {
                                echo "    ❌ 问题：arm_height是字符串 '{$item['arm_height']}'\n";
                                echo "    ❌ 应该是JSONObject格式: {\"measure\": X, \"unit\": \"in\"}\n";
                            } else if (is_array($item['arm_height'])) {
                                echo "    ✅ arm_height是数组/对象格式\n";
                            }
                        }
                        
                        // 只检查前2个产品，避免输出太多
                        if ($index >= 1) break;
                    }
                }
            }
        }
    }
    echo "---\n";
}

// 2. 查找具体的错误响应
echo "\n=== 查找具体的错误响应 ===\n";

$error_responses = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE response LIKE '%seat_depth%' 
    OR response LIKE '%arm_height%'
    OR response LIKE '%DATA_ERROR%'
    ORDER BY created_at DESC 
    LIMIT 3
");

foreach ($error_responses as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    
    if (!empty($log->response)) {
        $response_data = json_decode($log->response, true);
        
        if ($response_data) {
            // 查找错误信息
            if (isset($response_data['errors'])) {
                echo "API错误:\n";
                foreach ($response_data['errors'] as $error) {
                    if (isset($error['field']) && in_array($error['field'], ['seat_depth', 'arm_height'])) {
                        echo "  字段: {$error['field']}\n";
                        echo "  错误类型: {$error['type']}\n";
                        echo "  错误代码: {$error['code']}\n";
                        echo "  错误描述: {$error['description']}\n";
                        
                        if (isset($error['sku'])) {
                            echo "  产品SKU: {$error['sku']}\n";
                        }
                    }
                }
            }
            
            // 查找Feed状态中的错误
            if (isset($response_data['feedStatus']) && $response_data['feedStatus'] === 'ERROR') {
                if (isset($response_data['itemsReceived'])) {
                    echo "Feed错误详情:\n";
                    foreach ($response_data['itemsReceived'] as $item) {
                        if (isset($item['ingestionErrors']['ingestionError'])) {
                            foreach ($item['ingestionErrors']['ingestionError'] as $error) {
                                if (in_array($error['field'], ['seat_depth', 'arm_height'])) {
                                    echo "  SKU: {$item['sku']}\n";
                                    echo "  字段: {$error['field']}\n";
                                    echo "  错误: {$error['description']}\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    echo "---\n";
}

// 3. 检查是否有批量同步的相关日志
echo "\n=== 检查批量同步日志 ===\n";

$batch_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action LIKE '%批量%'
    AND (request LIKE '%seat_depth%' OR request LIKE '%arm_height%')
    ORDER BY created_at DESC 
    LIMIT 2
");

foreach ($batch_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    
    if (!empty($log->request)) {
        $request_data = json_decode($log->request, true);
        
        if ($request_data && isset($request_data['product_data'])) {
            echo "批量产品数据:\n";
            foreach ($request_data['product_data'] as $index => $product) {
                if (isset($product['seat_depth']) || isset($product['arm_height'])) {
                    echo "  产品 {$index}:\n";
                    
                    if (isset($product['seat_depth'])) {
                        echo "    seat_depth: " . json_encode($product['seat_depth']) . "\n";
                    }
                    
                    if (isset($product['arm_height'])) {
                        echo "    arm_height: " . json_encode($product['arm_height']) . "\n";
                    }
                }
                
                if ($index >= 1) break; // 只显示前2个
            }
        }
    }
    echo "---\n";
}

echo "\n=== 关键发现 ===\n";
echo "通过以上日志分析，我们应该能看到：\n";
echo "1. seat_depth和arm_height字段的实际值和类型\n";
echo "2. 是字符串还是JSONObject格式\n";
echo "3. 这些字段是在哪个环节被添加的\n";

?>
