<?php
/**
 * 检查Feed的API响应
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查Feed的API响应 ===\n\n";

global $wpdb;
$feed_table = $wpdb->prefix . 'walmart_feeds';

// 1. 获取已处理的Feed的API响应
echo "1. 获取已处理的Feed的API响应:\n";

$processed_feed = $wpdb->get_row(
    "SELECT * FROM {$feed_table} WHERE status = 'PROCESSED' ORDER BY created_at DESC LIMIT 1"
);

if ($processed_feed && !empty($processed_feed->api_response)) {
    echo "✅ 找到API响应数据\n";
    echo "Feed ID: {$processed_feed->feed_id}\n";
    echo "响应数据大小: " . strlen($processed_feed->api_response) . " 字节\n";
    
    $api_response = json_decode($processed_feed->api_response, true);
    
    if ($api_response) {
        echo "✅ API响应JSON解析成功\n";
        
        // 保存完整的API响应到文件
        $response_json = json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents('api_response_debug.json', $response_json);
        echo "✅ 完整API响应已保存到 api_response_debug.json\n";
        
        // 2. 分析API响应中的错误信息
        echo "\n2. 分析API响应中的错误信息:\n";
        
        if (isset($api_response['errors'])) {
            echo "发现 " . count($api_response['errors']) . " 个错误:\n";
            
            $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
            
            foreach ($api_response['errors'] as $index => $error) {
                if (isset($error['field']) && in_array($error['field'], $dimension_fields)) {
                    echo "\n--- 尺寸字段错误 #{$index} ---\n";
                    echo "字段: {$error['field']}\n";
                    echo "错误代码: " . (isset($error['code']) ? $error['code'] : '未知') . "\n";
                    echo "描述: " . (isset($error['description']) ? $error['description'] : '未知') . "\n";
                    
                    if (isset($error['sku'])) {
                        echo "SKU: {$error['sku']}\n";
                    }
                    
                    // 分析错误类型
                    $description = isset($error['description']) ? strtolower($error['description']) : '';
                    if (strpos($description, 'select') !== false) {
                        echo "🎯 这就是沃尔玛后台显示'select'的原因！\n";
                    }
                    if (strpos($description, 'measurement') !== false || strpos($description, 'unit') !== false) {
                        echo "🎯 这个错误与测量单位相关！\n";
                    }
                }
            }
        } else {
            echo "❌ API响应中没有errors字段\n";
        }
        
        // 3. 检查成功的项目
        if (isset($api_response['itemDetails'])) {
            echo "\n3. 检查成功处理的项目:\n";
            
            foreach ($api_response['itemDetails'] as $item) {
                if (isset($item['sku'])) {
                    echo "\nSKU: {$item['sku']}\n";
                    echo "状态: " . (isset($item['ingestionStatus']) ? $item['ingestionStatus'] : '未知') . "\n";
                    
                    if (isset($item['ingestionErrors']['ingestionError'])) {
                        echo "摄取错误:\n";
                        foreach ($item['ingestionErrors']['ingestionError'] as $ing_error) {
                            if (in_array($ing_error['field'], $dimension_fields)) {
                                echo "  字段 {$ing_error['field']}: {$ing_error['description']}\n";
                                
                                if (strpos($ing_error['description'], 'select') !== false) {
                                    echo "    🎯 找到'select'错误！\n";
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // 4. 查找所有包含'select'的错误
        echo "\n4. 查找所有包含'select'的错误:\n";
        
        $response_text = json_encode($api_response, JSON_UNESCAPED_UNICODE);
        if (strpos($response_text, 'select') !== false) {
            echo "✅ 在API响应中找到'select'相关内容\n";
            
            // 使用正则表达式查找包含select的错误
            if (preg_match_all('/"description":\s*"[^"]*select[^"]*"/i', $response_text, $matches)) {
                echo "包含'select'的错误描述:\n";
                foreach ($matches[0] as $match) {
                    echo "  {$match}\n";
                }
            }
        } else {
            echo "❌ 在API响应中没有找到'select'相关内容\n";
        }
        
    } else {
        echo "❌ API响应JSON解析失败\n";
        echo "原始响应前100字符: " . substr($processed_feed->api_response, 0, 100) . "\n";
    }
    
} else {
    echo "❌ 没有找到已处理的Feed或API响应为空\n";
}

echo "\n=== 检查完成 ===\n";
echo "请查看 api_response_debug.json 文件获取完整的API响应信息。\n";
?>
