<?php
/**
 * 检查Feed表结构和数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查Feed表结构和数据 ===\n\n";

global $wpdb;
$feed_table = $wpdb->prefix . 'walmart_feeds';

// 1. 检查表结构
echo "1. 检查表结构:\n";
$table_structure = $wpdb->get_results("DESCRIBE {$feed_table}");

if ($table_structure) {
    echo "表字段:\n";
    foreach ($table_structure as $column) {
        echo "  {$column->Field}: {$column->Type}\n";
    }
} else {
    echo "❌ 无法获取表结构\n";
}

// 2. 检查所有Feed记录
echo "\n2. 检查所有Feed记录:\n";
$all_feeds = $wpdb->get_results("SELECT * FROM {$feed_table} ORDER BY created_at DESC LIMIT 3");

if ($all_feeds) {
    foreach ($all_feeds as $feed) {
        echo "\n--- Feed: {$feed->feed_id} ---\n";
        echo "状态: {$feed->status}\n";
        echo "创建时间: {$feed->created_at}\n";
        
        // 检查每个字段
        $fields = get_object_vars($feed);
        foreach ($fields as $field_name => $field_value) {
            if (in_array($field_name, ['feed_data', 'response_data', 'error_data'])) {
                if (!empty($field_value)) {
                    echo "{$field_name}: " . strlen($field_value) . " 字节\n";
                    
                    // 尝试解析JSON
                    $json_data = json_decode($field_value, true);
                    if ($json_data) {
                        echo "  ✅ 有效的JSON数据\n";
                        
                        if ($field_name === 'feed_data' && isset($json_data['MPItemFeed']['MPItem'])) {
                            $items = $json_data['MPItemFeed']['MPItem'];
                            echo "  包含 " . count($items) . " 个产品\n";
                            
                            // 检查第一个产品的尺寸字段
                            if (isset($items[0]['Visible'])) {
                                foreach ($items[0]['Visible'] as $category => $fields) {
                                    $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
                                    
                                    foreach ($dimension_fields as $dim_field) {
                                        if (isset($fields[$dim_field])) {
                                            $value = $fields[$dim_field];
                                            echo "    {$dim_field}: " . json_encode($value, JSON_UNESCAPED_UNICODE);
                                            
                                            if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                                                echo " ✅ 有单位\n";
                                            } else {
                                                echo " ❌ 无单位\n";
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        if ($field_name === 'response_data' && isset($json_data['errors'])) {
                            echo "  包含 " . count($json_data['errors']) . " 个错误\n";
                            
                            // 查找尺寸字段相关的错误
                            foreach ($json_data['errors'] as $error) {
                                if (isset($error['field']) && in_array($error['field'], ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'])) {
                                    echo "    字段 {$error['field']} 错误: {$error['description']}\n";
                                }
                            }
                        }
                        
                    } else {
                        echo "  ❌ 无效的JSON数据\n";
                    }
                } else {
                    echo "{$field_name}: 空\n";
                }
            }
        }
    }
} else {
    echo "❌ 没有找到Feed记录\n";
}

// 3. 检查最新的Feed状态
echo "\n3. 检查最新Feed的详细状态:\n";

if (class_exists('Woo_Walmart_Product_Sync')) {
    $sync = new Woo_Walmart_Product_Sync();
    
    // 获取最新的Feed ID
    $latest_feed = $wpdb->get_row("SELECT feed_id FROM {$feed_table} ORDER BY created_at DESC LIMIT 1");
    
    if ($latest_feed) {
        echo "最新Feed ID: {$latest_feed->feed_id}\n";
        
        try {
            // 使用反射调用check_feed_status方法
            $reflection = new ReflectionClass($sync);
            if ($reflection->hasMethod('check_feed_status')) {
                $check_method = $reflection->getMethod('check_feed_status');
                $check_method->setAccessible(true);
                
                $status_result = $check_method->invoke($sync, $latest_feed->feed_id);
                
                echo "Feed状态检查结果:\n";
                echo "  成功: " . ($status_result['success'] ? 'true' : 'false') . "\n";
                echo "  消息: {$status_result['message']}\n";
                
                if (isset($status_result['data'])) {
                    echo "  数据: " . json_encode($status_result['data'], JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ Feed状态检查失败: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== 检查完成 ===\n";
?>
