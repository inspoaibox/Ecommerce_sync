<?php
/**
 * 检查Feed表的数据结构
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查Feed表的数据结构 ===\n\n";

$batch_id = 'BATCH_20250824081352_6177';
$batch_prefix = 'BATCH_20250824081352';

global $wpdb;
$feeds_table = $wpdb->prefix . 'walmart_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

echo "目标批次: {$batch_id}\n";
echo "批次前缀: {$batch_prefix}\n\n";

// 1. 检查Feed表中feed_id的格式
echo "1. 检查Feed表中feed_id的格式:\n";

$sample_feeds = $wpdb->get_results("
    SELECT feed_id, sku, status, created_at 
    FROM {$feeds_table} 
    WHERE created_at >= '2025-08-24 15:00:00' 
    AND created_at <= '2025-08-24 18:00:00'
    ORDER BY created_at DESC 
    LIMIT 10
");

if (!empty($sample_feeds)) {
    echo "批次时间段内的Feed记录样本:\n";
    foreach ($sample_feeds as $feed) {
        echo "  Feed ID: {$feed->feed_id}\n";
        echo "  SKU: {$feed->sku}\n";
        echo "  状态: {$feed->status}\n";
        echo "  时间: {$feed->created_at}\n";
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到该时间段的Feed记录\n";
}

// 2. 检查batch_items表中的SKU
echo "\n2. 检查batch_items表中的SKU:\n";

$batch_items = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, sku, status FROM {$batch_items_table} 
     WHERE batch_id LIKE %s 
     ORDER BY id DESC 
     LIMIT 10",
    $batch_id . '%'
));

if (!empty($batch_items)) {
    echo "batch_items表中的记录样本:\n";
    foreach ($batch_items as $item) {
        echo "  批次: {$item->batch_id}\n";
        echo "  SKU: {$item->sku}\n";
        echo "  状态: {$item->status}\n";
        echo "  ---\n";
    }
    
    // 获取一个SKU，检查它在Feed表中的记录
    $sample_sku = $batch_items[0]->sku;
    echo "\n检查样本SKU {$sample_sku} 在Feed表中的记录:\n";
    
    $sku_feeds = $wpdb->get_results($wpdb->prepare(
        "SELECT feed_id, status, created_at FROM {$feeds_table} 
         WHERE sku = %s 
         ORDER BY created_at DESC 
         LIMIT 5",
        $sample_sku
    ));
    
    if (!empty($sku_feeds)) {
        foreach ($sku_feeds as $feed) {
            echo "  Feed ID: {$feed->feed_id}\n";
            echo "  状态: {$feed->status}\n";
            echo "  时间: {$feed->created_at}\n";
            echo "  ---\n";
        }
    } else {
        echo "❌ 该SKU在Feed表中没有记录\n";
    }
    
} else {
    echo "❌ batch_items表中没有该批次的记录\n";
}

// 3. 分析Feed ID与批次ID的关联关系
echo "\n3. 分析Feed ID与批次ID的关联关系:\n";

// 检查是否有包含批次信息的Feed ID
$related_feeds = $wpdb->get_results($wpdb->prepare(
    "SELECT feed_id, sku, status, created_at FROM {$feeds_table} 
     WHERE feed_id LIKE %s OR feed_id LIKE %s
     ORDER BY created_at DESC 
     LIMIT 5",
    '%' . $batch_id . '%',
    '%' . $batch_prefix . '%'
));

if (!empty($related_feeds)) {
    echo "找到包含批次信息的Feed记录:\n";
    foreach ($related_feeds as $feed) {
        echo "  Feed ID: {$feed->feed_id}\n";
        echo "  SKU: {$feed->sku}\n";
        echo "  状态: {$feed->status}\n";
        echo "  时间: {$feed->created_at}\n";
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到包含批次信息的Feed记录\n";
    echo "这说明Feed ID与批次ID没有直接关联\n";
}

// 4. 检查可能的关联方式
echo "\n4. 检查可能的关联方式:\n";

echo "可能的关联方式:\n";
echo "1. Feed ID格式可能不包含批次ID\n";
echo "2. 需要通过其他字段关联（如时间、商品等）\n";
echo "3. 可能需要通过中间表关联\n";
echo "4. Feed记录可能在批次处理前就存在\n";

// 5. 建议的修复策略
echo "\n5. 建议的修复策略:\n";

if (empty($related_feeds)) {
    echo "由于Feed ID与批次ID没有直接关联，建议:\n";
    echo "1. 使用batch_items表作为主要数据源\n";
    echo "2. 对于缺失的商品，通过SKU在Feed表中查找最新状态\n";
    echo "3. 结合时间范围，但要更精确（如±10分钟）\n";
    echo "4. 优先使用API响应数据，Feed数据作为补充\n";
} else {
    echo "找到了关联关系，可以继续使用批次ID匹配\n";
}

echo "\n=== 分析完成 ===\n";

?>
