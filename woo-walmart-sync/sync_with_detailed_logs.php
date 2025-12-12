<?php
/**
 * 重新同步产品并获取详细日志
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 重新同步产品并获取详细日志 ===\n\n";

$product_id = 20345;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n\n";

// 1. 清除之前的日志
echo "1. 清除之前的日志:\n";
global $wpdb;
$log_table = $wpdb->prefix . 'walmart_sync_logs';

// 删除这个产品的旧日志
$deleted = $wpdb->delete($log_table, ['product_id' => $product_id]);
echo "删除了 {$deleted} 条旧日志\n";

// 2. 开始新的同步
echo "\n2. 开始新的同步:\n";

require_once 'includes/class-product-sync.php';
$sync = new Woo_Walmart_Product_Sync();

try {
    echo "开始同步...\n";
    $sync_result = $sync->initiate_sync($product_id);
    
    echo "同步结果:\n";
    echo "  成功: " . ($sync_result['success'] ? 'true' : 'false') . "\n";
    echo "  消息: {$sync_result['message']}\n";
    
    if (isset($sync_result['feed_id'])) {
        echo "  Feed ID: {$sync_result['feed_id']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ 同步失败: " . $e->getMessage() . "\n";
}

// 3. 检查新生成的日志
echo "\n3. 检查新生成的日志:\n";

$new_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$log_table} WHERE product_id = %d ORDER BY created_at DESC",
    $product_id
));

if ($new_logs) {
    foreach ($new_logs as $log) {
        echo "\n--- 日志: {$log->log_type} ---\n";
        echo "时间: {$log->created_at}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->context)) {
            $context = json_decode($log->context, true);
            if ($context) {
                echo "上下文:\n";
                
                // 查找JSON内容相关的日志
                if (isset($context['dimension_fields_in_json'])) {
                    echo "  尺寸字段在JSON中的状态:\n";
                    foreach ($context['dimension_fields_in_json'] as $field => $status) {
                        if (is_bool($status)) {
                            echo "    {$field}: " . ($status ? '存在' : '不存在') . "\n";
                        } else {
                            echo "    {$field}: {$status}\n";
                        }
                    }
                }
                
                if (isset($context['measure_unit_pattern'])) {
                    echo "  measure+unit模式匹配数量: {$context['measure_unit_pattern']}\n";
                    
                    if ($context['measure_unit_pattern'] > 0) {
                        echo "  ✅ JSON中包含measure+unit格式\n";
                    } else {
                        echo "  ❌ JSON中不包含measure+unit格式\n";
                        echo "  🎯 这就是单位信息丢失的证据！\n";
                    }
                }
                
                if (isset($context['json_preview'])) {
                    echo "  JSON预览:\n";
                    echo "    " . substr($context['json_preview'], 0, 200) . "...\n";
                }
            }
        }
    }
} else {
    echo "❌ 没有找到新的日志\n";
}

echo "\n=== 检查完成 ===\n";
echo "通过详细日志可以确定单位信息是在哪个环节丢失的。\n";
?>
