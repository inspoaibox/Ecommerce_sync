<?php
/**
 * 检查实际发送给沃尔玛API的数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际发送给沃尔玛的API数据 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找最近的API请求日志，特别是包含这些SKU的
$failed_skus = ['B202P222191', 'B202S00513', 'B202S00514', 'B202S00492', 'B202S00493'];

echo "查找最近的API请求日志...\n\n";

$api_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action = 'API请求' 
    AND (request LIKE '%B202P222191%' OR request LIKE '%B202S00513%' OR request LIKE '%B202S00514%' OR request LIKE '%B202S00492%' OR request LIKE '%B202S00493%')
    ORDER BY created_at DESC 
    LIMIT 3
");

if (empty($api_logs)) {
    echo "未找到包含这些SKU的API请求日志，查找最近的API请求...\n";
    
    $api_logs = $wpdb->get_results("
        SELECT * FROM {$logs_table} 
        WHERE action = 'API请求' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
}

foreach ($api_logs as $log) {
    echo "=== API请求时间: {$log->created_at} ===\n";
    echo "状态: {$log->status}\n";
    
    // 解析请求数据
    $request_data = json_decode($log->request, true);
    if ($request_data) {
        echo "请求数据解析成功\n";
        
        // 检查是否包含MPItem
        if (isset($request_data['MPItem'])) {
            $mp_item = $request_data['MPItem'];
            echo "包含MPItem数据\n";
            
            // 检查Visible部分
            if (isset($mp_item['Visible'])) {
                echo "包含Visible数据\n";
                
                foreach ($mp_item['Visible'] as $category => $data) {
                    echo "\n【分类: {$category}】\n";
                    
                    // 检查SKU
                    if (isset($data['sku'])) {
                        echo "SKU: {$data['sku']}\n";
                    }
                    
                    // 检查主图
                    if (isset($data['mainImageUrl'])) {
                        echo "主图: {$data['mainImageUrl']}\n";
                    }
                    
                    // 重点检查副图
                    if (isset($data['productSecondaryImageURL'])) {
                        $secondary_images = $data['productSecondaryImageURL'];
                        echo "副图数量: " . count($secondary_images) . "\n";
                        
                        if (count($secondary_images) > 0) {
                            echo "副图URLs:\n";
                            foreach ($secondary_images as $i => $url) {
                                echo "  " . ($i + 1) . ". {$url}\n";
                                
                                // 检查URL是否可访问
                                $headers = @get_headers($url, 1);
                                if ($headers && strpos($headers[0], '200') !== false) {
                                    echo "     ✅ URL可访问\n";
                                } else {
                                    echo "     ❌ URL不可访问或有问题\n";
                                }
                            }
                        } else {
                            echo "❌ 副图数组为空！\n";
                        }
                    } else {
                        echo "❌ 缺少productSecondaryImageURL字段！\n";
                    }
                }
            }
        } else if (isset($request_data['MPItemFeedHeader'])) {
            echo "这是Feed头信息\n";
            $header = $request_data['MPItemFeedHeader'];
            if (isset($header['requestId'])) {
                echo "请求ID: {$header['requestId']}\n";
            }
        }
    } else {
        echo "❌ 无法解析请求数据\n";
        echo "原始请求数据长度: " . strlen($log->request) . "\n";
        echo "请求数据前500字符:\n";
        echo substr($log->request, 0, 500) . "\n";
    }
    
    // 检查响应
    if (!empty($log->response)) {
        echo "\n响应数据:\n";
        $response_data = json_decode($log->response, true);
        if ($response_data) {
            if (isset($response_data['feedId'])) {
                echo "Feed ID: {$response_data['feedId']}\n";
            }
            if (isset($response_data['errors'])) {
                echo "错误信息:\n";
                print_r($response_data['errors']);
            }
        } else {
            echo substr($log->response, 0, 300) . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

// 查找Feed状态检查的日志
echo "=== 查找Feed状态检查日志 ===\n";

$feed_status_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action LIKE '%Feed状态%' OR action LIKE '%feed_status%'
    ORDER BY created_at DESC 
    LIMIT 5
");

foreach ($feed_status_logs as $log) {
    echo "时间: {$log->created_at} - {$log->action}\n";
    
    $response_data = json_decode($log->response, true);
    if ($response_data && isset($response_data['itemDetails'])) {
        $item_details = $response_data['itemDetails'];
        if (isset($item_details['itemIngestionStatus'])) {
            foreach ($item_details['itemIngestionStatus'] as $item) {
                if (isset($item['sku']) && in_array($item['sku'], $failed_skus)) {
                    echo "  SKU: {$item['sku']}\n";
                    if (isset($item['ingestionErrors']['ingestionError'])) {
                        foreach ($item['ingestionErrors']['ingestionError'] as $error) {
                            if ($error['field'] === 'productSecondaryImageURL') {
                                echo "  图片错误: {$error['description']}\n";
                            }
                        }
                    }
                }
            }
        }
    }
    echo "---\n";
}

?>
