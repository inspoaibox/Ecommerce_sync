<?php
/**
 * 检查插件状态和配置
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== Walmart同步插件配置信息 ===\n\n";

// 1. API配置
echo "1. API配置:\n";
echo "  Client ID: " . (get_option('woo_walmart_client_id') ? '已设置' : '未设置') . "\n";
echo "  Client Secret: " . (get_option('woo_walmart_client_secret') ? '已设置' : '未设置') . "\n";
echo "  Business Unit: " . (get_option('woo_walmart_business_unit', '未设置')) . "\n";
echo "  API Version: " . (get_option('woo_walmart_api_version', '未设置')) . "\n";
echo "  Fulfillment Center ID: " . (get_option('woo_walmart_fulfillment_center_id', '未设置')) . "\n";
echo "  Shipping Template: " . (get_option('woo_walmart_shipping_template', '未设置')) . "\n";

// 2. 数据库表状态
echo "\n2. 数据库表状态:\n";
global $wpdb;
$tables = [
    'woo_walmart_sync_logs' => '同步日志表',
    'walmart_upc_pool' => 'UPC池表',
    'walmart_category_map' => '分类映射表',
    'walmart_feeds' => 'Feed状态表',
    'walmart_batch_feeds' => '批量Feed管理表',
    'walmart_products_cache' => 'Walmart商品缓存表',
    'walmart_inventory_sync' => '库存同步状态表'
];

foreach ($tables as $table => $desc) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
    echo "  " . $desc . ": " . ($exists ? '✅ 存在' : '❌ 不存在') . "\n";
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
        echo "    记录数: " . $count . "\n";
    }
}

// 3. 最近的同步活动
echo "\n3. 最近的同步活动:\n";
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table) {
    $recent_logs = $wpdb->get_results("
        SELECT action, status, created_at, product_id 
        FROM $logs_table 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    if ($recent_logs) {
        foreach ($recent_logs as $log) {
            echo "  [{$log->created_at}] {$log->action} - {$log->status}";
            if ($log->product_id) {
                echo " (产品ID: {$log->product_id})";
            }
            echo "\n";
        }
    } else {
        echo "  暂无同步记录\n";
    }
}

// 4. UPC池状态
echo "\n4. UPC池状态:\n";
$upc_table = $wpdb->prefix . 'walmart_upc_pool';
if ($wpdb->get_var("SHOW TABLES LIKE '$upc_table'") === $upc_table) {
    $total_upc = $wpdb->get_var("SELECT COUNT(*) FROM $upc_table");
    $used_upc = $wpdb->get_var("SELECT COUNT(*) FROM $upc_table WHERE is_used = 1");
    $available_upc = $total_upc - $used_upc;
    
    echo "  总UPC数: $total_upc\n";
    echo "  已使用: $used_upc\n";
    echo "  可用: $available_upc\n";
}

// 5. 分类映射状态
echo "\n5. 分类映射状态:\n";
$mapping_table = $wpdb->prefix . 'walmart_category_map';
if ($wpdb->get_var("SHOW TABLES LIKE '$mapping_table'") === $mapping_table) {
    $mappings = $wpdb->get_results("SELECT wc_category_name, walmart_category_path FROM $mapping_table");
    if ($mappings) {
        foreach ($mappings as $mapping) {
            echo "  {$mapping->wc_category_name} -> {$mapping->walmart_category_path}\n";
        }
    } else {
        echo "  暂无分类映射\n";
    }
}

echo "\n=== 配置检查完成 ===\n";
?>
