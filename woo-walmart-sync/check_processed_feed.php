<?php
/**
 * 检查已处理的Feed数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW\test.localhost/wp-load.php';

echo "=== 检查已处理的Feed数据 ===\n\n";

global $wpdb;
$feed_table = $wpdb->prefix . 'walmart_feeds';

// 1. 获取已处理的Feed
echo "1. 获取已处理的Feed:\n";

$processed_feed = $wpdb->get_row(
    "SELECT * FROM {$feed_table} WHERE status = 'PROCESSED' ORDER BY created_at DESC LIMIT 1"
);

if ($processed_feed) {
    echo "✅ 找到已处理的Feed\n";
    echo "Feed ID: {$processed_feed->feed_id}\n";
    echo "状态: {$processed_feed->status}\n";
    echo "创建时间: {$processed_feed->created_at}\n";
    
    // 2. 检查发送的数据
    echo "\n2. 检查发送的数据:\n";
    
    if (!empty($processed_feed->feed_data)) {
        $feed_data = json_decode($processed_feed->feed_data, true);
        
        if ($feed_data && isset($feed_data['MPItemFeed']['MPItem'])) {
            $items = $feed_data['MPItemFeed']['MPItem'];
            
            foreach ($items as $item) {
                if (isset($item['@sku'])) {
                    echo "SKU: {$item['@sku']}\n";
                    
                    if (isset($item['Visible'])) {
                        foreach ($item['Visible'] as $category => $fields) {
                            echo "  分类: {$category}\n";
                            
                            $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
                            
                            foreach ($dimension_fields as $field) {
                                if (isset($fields[$field])) {
                                    $value = $fields[$field];
                                    echo "    {$field}: " . json_encode($value, JSON_UNESCAPED_UNICODE);
                                    
                                    if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                                        echo " ✅ 发送时有单位\n";
                                    } else {
                                        echo " ❌ 发送时无单位\n";
                                        echo "      🎯 找到问题！发送给沃尔玛的数据没有单位！\n";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    // 3. 检查沃尔玛的响应
    echo "\n3. 检查沃尔玛的响应:\n";
    
    if (!empty($processed_feed->response_data)) {
        $response_data = json_decode($processed_feed->response_data, true);
        
        if ($response_data) {
            echo "✅ 找到API响应数据\n";
            
            // 查找错误信息
            if (isset($response_data['errors'])) {
                echo "API错误信息:\n";
                foreach ($response_data['errors'] as $error) {
                    if (isset($error['field']) && in_array($error['field'], ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'])) {
                        echo "  字段 {$error['field']}:\n";
                        echo "    错误代码: {$error['code']}\n";
                        echo "    描述: {$error['description']}\n";
                        
                        if (strpos($error['description'], 'select') !== false || 
                            strpos($error['description'], 'measurement') !== false ||
                            strpos($error['description'], 'unit') !== false) {
                            echo "    🎯 这个错误与单位信息相关！\n";
                        }
                    }
                }
            }
            
            // 查找成功的项目
            if (isset($response_data['itemDetails'])) {
                echo "成功处理的项目:\n";
                foreach ($response_data['itemDetails'] as $item) {
                    if (isset($item['sku'])) {
                        echo "  SKU: {$item['sku']}\n";
                        echo "  状态: " . (isset($item['ingestionStatus']) ? $item['ingestionStatus'] : '未知') . "\n";
                        
                        if (isset($item['ingestionErrors'])) {
                            foreach ($item['ingestionErrors']['ingestionError'] as $error) {
                                if (in_array($error['field'], ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'])) {
                                    echo "    字段 {$error['field']} 错误: {$error['description']}\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        echo "❌ 没有API响应数据\n";
    }
    
} else {
    echo "❌ 没有找到已处理的Feed\n";
}

echo "\n=== 检查完成 ===\n";
echo "如果发送的数据有单位但沃尔玛后台显示'select'，可能是沃尔玛API的验证问题。\n";
echo "如果发送的数据就没有单位，说明问题在同步过程的数据准备阶段。\n";
?>
