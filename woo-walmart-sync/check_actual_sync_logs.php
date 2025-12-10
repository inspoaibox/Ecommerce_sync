<?php
/**
 * 检查实际的同步日志
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际的同步日志 ===\n\n";

global $wpdb;

// 1. 检查同步日志表是否存在
$log_table = $wpdb->prefix . 'walmart_sync_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$log_table}'");

if ($table_exists) {
    echo "✅ 同步日志表存在: {$log_table}\n";
    
    // 2. 查找所有相关的同步日志
    echo "\n2. 查找产品20345和20344的同步日志:\n";
    
    $product_logs = $wpdb->get_results(
        "SELECT * FROM {$log_table} WHERE product_id IN (20345, 20344) ORDER BY created_at DESC LIMIT 10"
    );
    
    if ($product_logs) {
        foreach ($product_logs as $log) {
            echo "\n--- 日志记录 ---\n";
            echo "产品ID: {$log->product_id}\n";
            echo "时间: {$log->created_at}\n";
            echo "类型: {$log->log_type}\n";
            echo "消息: {$log->message}\n";
            
            if (!empty($log->context)) {
                $context = json_decode($log->context, true);
                if ($context) {
                    echo "上下文数据:\n";
                    
                    // 查找walmart_data
                    if (isset($context['walmart_data'])) {
                        $walmart_data = $context['walmart_data'];
                        echo "  发现walmart_data\n";
                        
                        // 查找尺寸字段
                        if (isset($walmart_data['MPItem'][0]['Visible'])) {
                            $visible = $walmart_data['MPItem'][0]['Visible'];
                            foreach ($visible as $category => $fields) {
                                echo "  分类: {$category}\n";
                                
                                $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
                                foreach ($dimension_fields as $field) {
                                    if (isset($fields[$field])) {
                                        $value = $fields[$field];
                                        echo "    {$field}: " . json_encode($value, JSON_UNESCAPED_UNICODE);
                                        
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
                    
                    // 查找API响应
                    if (isset($context['api_response'])) {
                        echo "  发现API响应数据\n";
                        $api_response = $context['api_response'];
                        
                        // 检查是否有错误信息
                        if (isset($api_response['errors'])) {
                            echo "  API错误:\n";
                            foreach ($api_response['errors'] as $error) {
                                if (isset($error['field']) && in_array($error['field'], $dimension_fields)) {
                                    echo "    字段 {$error['field']}: {$error['description']}\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        echo "❌ 没有找到这两个产品的同步日志\n";
        
        // 3. 查找最近的所有同步日志
        echo "\n3. 查找最近的所有同步日志:\n";
        $all_recent_logs = $wpdb->get_results(
            "SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT 5"
        );
        
        if ($all_recent_logs) {
            foreach ($all_recent_logs as $log) {
                echo "产品ID: {$log->product_id}, 时间: {$log->created_at}, 消息: {$log->message}\n";
            }
        } else {
            echo "❌ 没有任何同步日志\n";
        }
    }
    
} else {
    echo "❌ 同步日志表不存在\n";
}

// 4. 检查产品的同步状态
echo "\n4. 检查产品的同步状态:\n";

$product_ids = [20345, 20344];
foreach ($product_ids as $product_id) {
    $sync_status = get_post_meta($product_id, '_walmart_sync_status', true);
    $walmart_item_id = get_post_meta($product_id, '_walmart_item_id', true);
    $last_sync = get_post_meta($product_id, '_walmart_last_sync', true);
    
    echo "产品 {$product_id}:\n";
    echo "  同步状态: " . ($sync_status ?: '未设置') . "\n";
    echo "  沃尔玛商品ID: " . ($walmart_item_id ?: '未设置') . "\n";
    echo "  最后同步时间: " . ($last_sync ?: '未设置') . "\n";
}

// 5. 手动触发一次同步测试
echo "\n5. 手动触发一次同步测试:\n";

$product_id = 20345;
$product = wc_get_product($product_id);

if ($product) {
    echo "准备同步产品: {$product->get_name()}\n";
    
    try {
        require_once 'includes/class-product-sync.php';
        $sync = new Woo_Walmart_Product_Sync();
        
        echo "开始同步...\n";
        $sync_result = $sync->initiate_sync($product_id);
        
        echo "同步结果:\n";
        echo "  成功: " . ($sync_result['success'] ? 'true' : 'false') . "\n";
        echo "  消息: {$sync_result['message']}\n";
        
        if (isset($sync_result['data'])) {
            echo "  数据: " . json_encode($sync_result['data'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 同步失败: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 检查完成 ===\n";
?>
