<?php
/**
 * 检查日志表
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查日志表 ===\n\n";

global $wpdb;
$log_table = $wpdb->prefix . 'walmart_sync_logs';

// 1. 检查表是否存在
echo "1. 检查表是否存在:\n";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$log_table}'");

if ($table_exists) {
    echo "✅ 日志表存在: {$log_table}\n";
    
    // 检查表结构
    $table_structure = $wpdb->get_results("DESCRIBE {$log_table}");
    echo "表结构:\n";
    foreach ($table_structure as $column) {
        echo "  {$column->Field}: {$column->Type}\n";
    }
    
    // 检查表中的记录数量
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$log_table}");
    echo "总记录数: {$count}\n";
    
    // 检查最近的记录
    $recent_logs = $wpdb->get_results("SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT 5");
    
    if ($recent_logs) {
        echo "\n最近的日志记录:\n";
        foreach ($recent_logs as $log) {
            echo "  时间: {$log->created_at}, 产品: {$log->product_id}, 类型: {$log->log_type}, 消息: " . substr($log->message, 0, 50) . "...\n";
        }
    } else {
        echo "❌ 表中没有记录\n";
    }
    
} else {
    echo "❌ 日志表不存在\n";
    
    // 检查是否需要创建表
    echo "需要创建日志表\n";
}

// 2. 测试日志记录功能
echo "\n2. 测试日志记录功能:\n";

if (function_exists('woo_walmart_sync_log')) {
    echo "✅ woo_walmart_sync_log函数存在\n";
    
    // 测试记录一条日志
    $test_log_result = woo_walmart_sync_log('测试日志', '调试', ['test' => 'data'], '测试日志记录功能');
    
    if ($test_log_result) {
        echo "✅ 测试日志记录成功\n";
        
        // 检查是否真的记录了
        $test_log = $wpdb->get_row("SELECT * FROM {$log_table} WHERE message = '测试日志' ORDER BY created_at DESC LIMIT 1");
        
        if ($test_log) {
            echo "✅ 日志确实记录到数据库中\n";
        } else {
            echo "❌ 日志没有记录到数据库中\n";
        }
    } else {
        echo "❌ 测试日志记录失败\n";
    }
} else {
    echo "❌ woo_walmart_sync_log函数不存在\n";
}

// 3. 检查最新的Feed数据
echo "\n3. 检查最新的Feed数据:\n";

$feed_table = $wpdb->prefix . 'walmart_feeds';
$latest_feed = $wpdb->get_row("SELECT * FROM {$feed_table} WHERE feed_id = '185B9DA62D6552D8B40F6B3433C1EC36@AXkBCgA'");

if ($latest_feed) {
    echo "✅ 找到最新的Feed\n";
    echo "状态: {$latest_feed->status}\n";
    echo "创建时间: {$latest_feed->created_at}\n";
    
    // 检查是否有API响应
    if (!empty($latest_feed->api_response)) {
        echo "✅ 有API响应数据\n";
        
        $api_response = json_decode($latest_feed->api_response, true);
        if ($api_response && isset($api_response['itemDetails']['itemIngestionStatus'])) {
            $items = $api_response['itemDetails']['itemIngestionStatus'];
            
            foreach ($items as $item) {
                if (isset($item['sku']) && $item['sku'] === $product->get_sku()) {
                    echo "找到产品 {$item['sku']}:\n";
                    echo "  状态: {$item['ingestionStatus']}\n";
                    
                    if (isset($item['ingestionErrors']['ingestionError'])) {
                        echo "  摄取错误:\n";
                        foreach ($item['ingestionErrors']['ingestionError'] as $error) {
                            if (in_array($error['field'], ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'])) {
                                echo "    字段 {$error['field']}: {$error['description']}\n";
                                
                                if (strpos($error['description'], 'select') !== false) {
                                    echo "      🎯 这就是'select'错误的来源！\n";
                                }
                            }
                        }
                    }
                    break;
                }
            }
        }
    } else {
        echo "❌ 没有API响应数据\n";
    }
} else {
    echo "❌ 没有找到最新的Feed\n";
}

echo "\n=== 检查完成 ===\n";
?>
