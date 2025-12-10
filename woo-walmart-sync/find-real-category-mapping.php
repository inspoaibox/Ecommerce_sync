<?php
/**
 * 查找产品的真实分类映射
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 查找产品的真实分类映射 ===\n\n";

$target_sku = 'B081S00179';

// 获取产品
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $target_sku
));

echo "产品ID: {$product_id}\n";

// 获取所有分类
$categories = wp_get_post_terms($product_id, 'product_cat');
echo "产品分类:\n";
foreach ($categories as $cat) {
    echo "  ID: {$cat->term_id}, 名称: {$cat->name}, 别名: {$cat->slug}\n";
}

// 检查所有可能的映射表
$mapping_tables = [
    $wpdb->prefix . 'walmart_category_mappings_new',
    $wpdb->prefix . 'walmart_category_mappings',
    $wpdb->prefix . 'woo_walmart_category_mappings'
];

echo "\n检查映射表:\n";

foreach ($mapping_tables as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($table_exists) {
        echo "\n表: {$table}\n";
        
        // 显示表结构
        $columns = $wpdb->get_results("DESCRIBE {$table}");
        echo "字段: ";
        foreach ($columns as $col) {
            echo $col->Field . " ";
        }
        echo "\n";
        
        // 查找映射
        foreach ($categories as $cat) {
            $mappings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE wc_category_id = %d",
                $cat->term_id
            ));
            
            if (!empty($mappings)) {
                echo "分类 {$cat->name} ({$cat->term_id}) 的映射:\n";
                foreach ($mappings as $mapping) {
                    foreach ($mapping as $key => $value) {
                        if ($key !== 'wc_category_id') {
                            echo "  {$key}: {$value}\n";
                        }
                    }
                    echo "  ---\n";
                }
            }
        }
        
        // 显示所有映射记录（前10条）
        $all_mappings = $wpdb->get_results("SELECT * FROM {$table} LIMIT 10");
        if (!empty($all_mappings)) {
            echo "表中的映射样本:\n";
            foreach ($all_mappings as $mapping) {
                $walmart_path = '';
                foreach ($mapping as $key => $value) {
                    if (strpos($key, 'walmart') !== false || strpos($key, 'category') !== false) {
                        $walmart_path = $value;
                        break;
                    }
                }
                echo "  WC分类{$mapping->wc_category_id} → {$walmart_path}\n";
            }
        }
    } else {
        echo "表 {$table} 不存在\n";
    }
}

// 检查产品的直接映射
echo "\n检查产品的直接映射:\n";
$direct_mapping = get_post_meta($product_id, '_walmart_category', true);
if ($direct_mapping) {
    echo "直接映射: {$direct_mapping}\n";
} else {
    echo "没有直接映射\n";
}

// 检查最近的同步记录
echo "\n检查最近的同步记录:\n";
$sync_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs 
     WHERE product_id = %d 
     ORDER BY created_at DESC 
     LIMIT 5",
    $product_id
));

if (!empty($sync_logs)) {
    foreach ($sync_logs as $log) {
        echo "[{$log->created_at}] {$log->action}: {$log->message}\n";
        
        $details = json_decode($log->details, true);
        if ($details && isset($details['walmart_category'])) {
            echo "  使用的分类: {$details['walmart_category']}\n";
        }
    }
} else {
    echo "没有找到同步记录\n";
}

echo "\n=== 结论 ===\n";
echo "如果找到了真实的分类映射，那么问题可能是:\n";
echo "1. 真实分类的图片要求与测试分类不同\n";
echo "2. 某些分类可能不支持占位符图片\n";
echo "3. 分类映射配置有问题\n";

?>
