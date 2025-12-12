<?php
/**
 * 检查数据库表结构
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 检查数据库表结构 ===\n\n";

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\canda.localhost';
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';

echo "✅ WordPress环境加载成功\n\n";

global $wpdb;

// === 1. 检查所有沃尔玛相关表 ===
echo "=== 1. 检查沃尔玛相关表 ===\n";

$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}walmart%'");

if (empty($tables)) {
    echo "❌ 没有找到任何沃尔玛相关表\n";
} else {
    echo "找到的沃尔玛相关表:\n";
    foreach ($tables as $table) {
        $table_name = array_values((array)$table)[0];
        echo "- $table_name\n";
    }
}

echo "\n";

// === 2. 检查分类映射表 ===
echo "=== 2. 检查分类映射表 ===\n";

$category_mapping_table = $wpdb->prefix . 'walmart_category_mapping';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$category_mapping_table'");

if ($table_exists) {
    echo "✅ 分类映射表存在: $category_mapping_table\n";
    
    // 检查表结构
    $columns = $wpdb->get_results("DESCRIBE $category_mapping_table");
    echo "表结构:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type})\n";
    }
    
    // 检查数据数量
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $category_mapping_table");
    echo "数据行数: $count\n";
    
} else {
    echo "❌ 分类映射表不存在: $category_mapping_table\n";
    echo "这就是问题所在！\n";
}

echo "\n";

// === 3. 检查产品同步相关表 ===
echo "=== 3. 检查产品同步相关表 ===\n";

$sync_tables = [
    $wpdb->prefix . 'walmart_sync_log',
    $wpdb->prefix . 'walmart_product_mapping',
    $wpdb->prefix . 'walmart_feed_status',
    $wpdb->prefix . 'walmart_api_log'
];

foreach ($sync_tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "✅ $table (数据行数: $count)\n";
    } else {
        echo "❌ $table (不存在)\n";
    }
}

echo "\n";

// === 4. 检查产品的沃尔玛相关元数据 ===
echo "=== 4. 检查产品的沃尔玛相关元数据 ===\n";

$walmart_meta_keys = $wpdb->get_results("
    SELECT DISTINCT meta_key, COUNT(*) as count 
    FROM {$wpdb->postmeta} 
    WHERE meta_key LIKE '%walmart%' 
    GROUP BY meta_key
    ORDER BY count DESC
");

if (empty($walmart_meta_keys)) {
    echo "❌ 没有找到沃尔玛相关的产品元数据\n";
} else {
    echo "找到的沃尔玛相关元数据:\n";
    foreach ($walmart_meta_keys as $meta) {
        echo "- {$meta->meta_key} (使用次数: {$meta->count})\n";
    }
}

echo "\n";

// === 5. 检查失败产品的具体信息 ===
echo "=== 5. 检查失败产品的具体信息 ===\n";

$test_sku = '83A-300V00WT';
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $test_sku
));

if ($product_id) {
    echo "测试产品ID: $product_id (SKU: $test_sku)\n";
    
    // 获取产品的所有元数据
    $meta_data = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '%walmart%'",
        $product_id
    ));
    
    if (empty($meta_data)) {
        echo "❌ 产品没有沃尔玛相关元数据\n";
    } else {
        echo "产品的沃尔玛元数据:\n";
        foreach ($meta_data as $meta) {
            $value = strlen($meta->meta_value) > 100 ? substr($meta->meta_value, 0, 100) . '...' : $meta->meta_value;
            echo "- {$meta->meta_key}: $value\n";
        }
    }
    
    // 检查产品分类
    $product = wc_get_product($product_id);
    if ($product) {
        $categories = $product->get_category_ids();
        echo "产品分类ID: " . implode(', ', $categories) . "\n";
        
        // 获取分类名称
        foreach ($categories as $cat_id) {
            $term = get_term($cat_id);
            if ($term) {
                echo "- 分类 $cat_id: {$term->name}\n";
            }
        }
    }
}

echo "\n";

// === 6. 建议的解决方案 ===
echo "=== 6. 建议的解决方案 ===\n";

if (!$table_exists) {
    echo "🎯 主要问题: 分类映射表不存在\n\n";
    
    echo "这解释了为什么产品映射失败:\n";
    echo "1. 产品无法找到对应的沃尔玛分类\n";
    echo "2. 映射过程无法完成\n";
    echo "3. 可能使用了默认或错误的履行中心ID\n\n";
    
    echo "解决方案:\n";
    echo "1. 🔧 创建分类映射表\n";
    echo "2. 📋 导入分类映射数据\n";
    echo "3. 🔗 为产品分类建立沃尔玛分类映射\n";
    echo "4. 🧪 重新测试产品映射\n\n";
    
    echo "立即操作:\n";
    echo "1. 检查插件是否有分类映射功能\n";
    echo "2. 运行插件的数据库初始化脚本\n";
    echo "3. 手动创建必要的数据库表\n";
    
} else {
    echo "✅ 分类映射表存在，问题可能在其他地方\n";
}

echo "\n=== 检查完成 ===\n";
?>
