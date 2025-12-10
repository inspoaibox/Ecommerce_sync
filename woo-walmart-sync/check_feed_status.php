<?php
/**
 * 检查Feed状态和实际发送的数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查Feed状态和实际发送的数据 ===\n\n";

// 1. 检查最近的Feed记录
echo "1. 检查最近的Feed记录:\n";

global $wpdb;
$feed_table = $wpdb->prefix . 'walmart_feeds';

// 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$feed_table}'");

if ($table_exists) {
    echo "✅ Feed表存在\n";
    
    // 查找最近的Feed记录
    $recent_feeds = $wpdb->get_results(
        "SELECT * FROM {$feed_table} ORDER BY created_at DESC LIMIT 5"
    );
    
    if ($recent_feeds) {
        foreach ($recent_feeds as $feed) {
            echo "\n--- Feed记录 ---\n";
            echo "Feed ID: {$feed->feed_id}\n";
            echo "状态: {$feed->status}\n";
            echo "创建时间: {$feed->created_at}\n";
            echo "产品数量: {$feed->item_count}\n";
            
            if (!empty($feed->feed_data)) {
                echo "Feed数据大小: " . strlen($feed->feed_data) . " 字节\n";
                
                // 解析Feed数据
                $feed_data = json_decode($feed->feed_data, true);
                if ($feed_data && isset($feed_data['MPItemFeed']['MPItem'])) {
                    $items = $feed_data['MPItemFeed']['MPItem'];
                    
                    foreach ($items as $item) {
                        if (isset($item['@sku'])) {
                            $sku = $item['@sku'];
                            echo "  SKU: {$sku}\n";
                            
                            // 查找尺寸字段
                            if (isset($item['Visible'])) {
                                foreach ($item['Visible'] as $category => $fields) {
                                    $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
                                    
                                    foreach ($dimension_fields as $field) {
                                        if (isset($fields[$field])) {
                                            $value = $fields[$field];
                                            echo "    {$field}: " . json_encode($value, JSON_UNESCAPED_UNICODE);
                                            
                                            if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                                                echo " ✅ 有单位\n";
                                            } else {
                                                echo " ❌ 无单位\n";
                                                echo "      ⚠️ 这就是沃尔玛后台显示'select'的原因！\n";
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            if (!empty($feed->response_data)) {
                echo "API响应数据:\n";
                $response_data = json_decode($feed->response_data, true);
                if ($response_data && isset($response_data['errors'])) {
                    echo "  发现API错误:\n";
                    foreach ($response_data['errors'] as $error) {
                        if (isset($error['field']) && in_array($error['field'], ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'])) {
                            echo "    字段 {$error['field']}: {$error['description']}\n";
                        }
                    }
                }
            }
        }
    } else {
        echo "❌ 没有找到Feed记录\n";
    }
} else {
    echo "❌ Feed表不存在\n";
}

// 2. 检查最新的同步请求
echo "\n2. 检查最新的同步请求:\n";

// 手动触发一次同步并监控数据
$product_id = 20345;
$product = wc_get_product($product_id);

if ($product) {
    echo "准备重新同步产品: {$product->get_name()}\n";
    
    // 创建同步实例
    require_once 'includes/class-product-sync.php';
    $sync = new Woo_Walmart_Product_Sync();
    
    // 使用反射监控同步过程
    $sync_reflection = new ReflectionClass($sync);
    
    // 检查是否有prepare_data或类似方法
    $methods = $sync_reflection->getMethods();
    foreach ($methods as $method) {
        if (strpos($method->getName(), 'prepare') !== false) {
            echo "发现准备数据方法: {$method->getName()}\n";
        }
    }
    
    try {
        echo "开始同步...\n";
        $sync_result = $sync->initiate_sync($product_id);
        
        echo "同步结果:\n";
        echo "  成功: " . ($sync_result['success'] ? 'true' : 'false') . "\n";
        echo "  消息: {$sync_result['message']}\n";
        
        if (isset($sync_result['feed_id'])) {
            echo "  Feed ID: {$sync_result['feed_id']}\n";
            
            // 查找这个Feed的数据
            $feed_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$feed_table} WHERE feed_id = %s",
                $sync_result['feed_id']
            ));
            
            if ($feed_data && !empty($feed_data->feed_data)) {
                echo "  ✅ 找到Feed数据\n";
                
                $feed_json = json_decode($feed_data->feed_data, true);
                if ($feed_json && isset($feed_json['MPItemFeed']['MPItem'][0]['Visible'])) {
                    $visible = $feed_json['MPItemFeed']['MPItem'][0]['Visible'];
                    
                    foreach ($visible as $category => $fields) {
                        echo "  实际发送的尺寸数据:\n";
                        $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
                        
                        foreach ($dimension_fields as $field) {
                            if (isset($fields[$field])) {
                                $value = $fields[$field];
                                echo "    {$field}: " . json_encode($value, JSON_UNESCAPED_UNICODE);
                                
                                if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                                    echo " ✅ 发送时有单位\n";
                                } else {
                                    echo " ❌ 发送时无单位\n";
                                    echo "      🎯 找到问题！这就是沃尔玛后台显示'select'的原因！\n";
                                }
                            }
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 同步失败: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 检查完成 ===\n";
?>
