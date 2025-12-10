<?php
/**
 * 检查映射表的实际情况
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查映射表的实际情况 ===\n\n";

global $wpdb;

// 1. 检查所有可能的映射表
$possible_tables = [
    $wpdb->prefix . 'woo_walmart_category_mapping',
    $wpdb->prefix . 'walmart_category_mapping',
    $wpdb->prefix . 'woo_walmart_category_mappings',
    $wpdb->prefix . 'walmart_category_mappings'
];

foreach ($possible_tables as $table) {
    echo "检查表: {$table}\n";
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    
    if ($table_exists) {
        echo "✅ 表存在\n";
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "记录数: {$count}\n";
        
        if ($count > 0) {
            echo "前5条记录:\n";
            $records = $wpdb->get_results("SELECT * FROM {$table} LIMIT 5");
            foreach ($records as $record) {
                echo "  ID: {$record->id}";
                if (isset($record->wc_category_name)) {
                    echo " | WC分类: {$record->wc_category_name}";
                }
                if (isset($record->walmart_category_path)) {
                    echo " | 沃尔玛分类: {$record->walmart_category_path}";
                }
                if (isset($record->local_category_id)) {
                    echo " | 本地分类ID: {$record->local_category_id}";
                }
                echo "\n";
            }
        }
    } else {
        echo "❌ 表不存在\n";
    }
    echo "\n";
}

// 2. 检查这些产品是如何同步的
echo "=== 检查产品同步方式 ===\n";

$error_skus = ['W116465061', 'N771P254005L'];

foreach ($error_skus as $sku) {
    echo "产品 {$sku}:\n";
    
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if ($product_id) {
        // 检查是否有沃尔玛相关的元数据
        $walmart_meta = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d 
            AND meta_key LIKE '%walmart%'
        ", $product_id));
        
        if (!empty($walmart_meta)) {
            echo "  沃尔玛相关元数据:\n";
            foreach ($walmart_meta as $meta) {
                echo "    {$meta->meta_key}: {$meta->meta_value}\n";
            }
        } else {
            echo "  ❌ 没有沃尔玛相关元数据\n";
        }
        
        // 检查最近的同步日志
        $logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
        $recent_logs = $wpdb->get_results($wpdb->prepare("
            SELECT action, status, message, created_at 
            FROM {$logs_table} 
            WHERE product_id = %d 
            ORDER BY created_at DESC 
            LIMIT 5
        ", $product_id));
        
        if (!empty($recent_logs)) {
            echo "  最近的同步日志:\n";
            foreach ($recent_logs as $log) {
                echo "    {$log->created_at} | {$log->action} | {$log->status} | {$log->message}\n";
            }
        }
    }
    echo "\n";
}

// 3. 检查是否有其他方式的分类映射
echo "=== 检查其他可能的分类映射方式 ===\n";

// 检查选项表中是否有分类映射配置
$mapping_options = $wpdb->get_results("
    SELECT option_name, option_value 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '%walmart%' 
    AND option_name LIKE '%category%'
");

if (!empty($mapping_options)) {
    echo "选项表中的分类映射配置:\n";
    foreach ($mapping_options as $option) {
        echo "  {$option->option_name}: " . substr($option->option_value, 0, 100) . "...\n";
    }
} else {
    echo "❌ 选项表中没有分类映射配置\n";
}

// 4. 检查是否使用了默认分类
echo "\n=== 检查默认分类配置 ===\n";

$default_category = get_option('woo_walmart_default_category', '');
if (!empty($default_category)) {
    echo "✅ 找到默认分类配置: {$default_category}\n";
    echo "这可能解释了为什么没有具体的分类映射也能同步\n";
} else {
    echo "❌ 没有默认分类配置\n";
}

// 5. 检查产品同步时使用的实际分类
echo "\n=== 检查实际同步时使用的分类 ===\n";

foreach ($error_skus as $sku) {
    echo "产品 {$sku} 的同步数据:\n";
    
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if ($product_id) {
        // 查找包含分类信息的日志
        $logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
        $category_logs = $wpdb->get_results($wpdb->prepare("
            SELECT request, response, created_at 
            FROM {$logs_table} 
            WHERE product_id = %d 
            AND (request LIKE '%category%' OR request LIKE '%Desk%' OR request LIKE '%Chair%')
            ORDER BY created_at DESC 
            LIMIT 3
        ", $product_id));
        
        foreach ($category_logs as $log) {
            echo "  {$log->created_at}:\n";
            
            if (!empty($log->request)) {
                $request_data = json_decode($log->request, true);
                if ($request_data) {
                    if (isset($request_data['walmart_category'])) {
                        echo "    使用分类: {$request_data['walmart_category']}\n";
                    }
                    if (isset($request_data['category'])) {
                        echo "    分类: {$request_data['category']}\n";
                    }
                }
            }
        }
    }
    echo "\n";
}

?>
