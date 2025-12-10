<?php
/**
 * 获取完整的失败商品列表
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 获取完整的失败商品列表 ===\n\n";

global $wpdb;

// 1. 查找2025-08-25 16:41附近的所有失败记录
echo "1. 查找失败的Feed记录:\n";

$feeds_table = $wpdb->prefix . 'walmart_feeds';
$failed_feeds = $wpdb->get_results("
    SELECT sku, status, feed_id, created_at, api_response
    FROM {$feeds_table} 
    WHERE created_at >= '2025-08-25 16:40:00' 
    AND created_at <= '2025-08-25 16:45:00'
    AND (status != 'PROCESSED' OR status IS NULL OR status = 'FAILED')
    ORDER BY created_at DESC
");

if (!empty($failed_feeds)) {
    echo "找到失败的Feed记录数量: " . count($failed_feeds) . "\n\n";
    
    $failed_skus = [];
    $feed_groups = [];
    
    foreach ($failed_feeds as $feed) {
        if (!empty($feed->sku)) {
            $failed_skus[] = $feed->sku;
            
            // 按Feed ID分组
            if (!isset($feed_groups[$feed->feed_id])) {
                $feed_groups[$feed->feed_id] = [];
            }
            $feed_groups[$feed->feed_id][] = $feed->sku;
        }
    }
    
    echo "2. 按Feed ID分组的失败商品:\n";
    foreach ($feed_groups as $feed_id => $skus) {
        echo "Feed ID: {$feed_id} - 失败数量: " . count($skus) . "\n";
        if (count($skus) <= 10) {
            echo "  SKU列表: " . implode(', ', $skus) . "\n";
        } else {
            echo "  前10个SKU: " . implode(', ', array_slice($skus, 0, 10)) . "...\n";
        }
    }
    
    echo "\n3. 完整的失败SKU列表 (总计: " . count($failed_skus) . " 个):\n";
    echo "=== 开始复制 ===\n";
    foreach ($failed_skus as $sku) {
        echo $sku . "\n";
    }
    echo "=== 结束复制 ===\n";
    
    // 4. 与您提供的列表对比
    echo "\n4. 与您提供的列表对比:\n";
    $provided_skus = [
        'W3041S00098', 'W1568P332410', 'W1825P332361', 'W2915S00025', 'W1960P325840',
        'W714S00833', 'W714S01283', 'W714S01022', 'W714S01019', 'W1568S00228',
        'W1825P332359', 'W1658P250947', 'W714S00708', 'W714S00696', 'W714S01208',
        'W714S00641', 'W714S00640', 'W1885P246363', 'W3041S00096', 'W1568P332404',
        'W714S01210', 'W1885P234641', 'W2297S00021', 'W2297P264503', 'N767P263923C',
        'W3041S00027', 'W3041S00071', 'W3041S00070', 'W3041S00069', 'W1926S00074',
        'W1926S00073', 'W834S00349', 'W714S00760', 'W2297S00017', 'W1278P360531',
        'W1568P255135', 'W1926S00075', 'W1926S00069', 'W834S00475', 'W714S01151',
        'W714S01147', 'W714S00668', 'W714S01084', 'W714S01081', 'W2297P264469',
        'W3041S00032', 'W1926S00076', 'W1926S00070', 'W2108S00126', 'W834S00495',
        'W834S00443', 'W834S00439', 'W834S00425', 'W834S00412', 'W2108S00088',
        'W714S01110', 'W3147S00006', 'W3041P272946', 'W2339P346395', 'W2108S00104',
        'W714S01111', 'W714S01107', 'W714S01163', 'W714S01159', 'W714S01031',
        'W1191S00043', 'W3622S00002', 'W2791P306821', 'W834S00457', 'W2817P271187'
    ];
    
    echo "数据库中的失败SKU数量: " . count($failed_skus) . "\n";
    echo "您复制的SKU数量: " . count($provided_skus) . "\n";
    
    // 找出缺失的SKU
    $missing_skus = array_diff($failed_skus, $provided_skus);
    if (!empty($missing_skus)) {
        echo "\n缺失的SKU (数据库有但您的列表没有):\n";
        foreach ($missing_skus as $sku) {
            echo $sku . "\n";
        }
    }
    
    // 找出多余的SKU
    $extra_skus = array_diff($provided_skus, $failed_skus);
    if (!empty($extra_skus)) {
        echo "\n多余的SKU (您的列表有但数据库没有):\n";
        foreach ($extra_skus as $sku) {
            echo $sku . "\n";
        }
    }
    
} else {
    echo "❌ 没有找到失败的Feed记录\n";
    
    // 尝试查找所有记录
    echo "\n尝试查找所有相关记录:\n";
    $all_feeds = $wpdb->get_results("
        SELECT sku, status, feed_id, created_at
        FROM {$feeds_table} 
        WHERE created_at >= '2025-08-25 16:40:00' 
        AND created_at <= '2025-08-25 16:45:00'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    
    if (!empty($all_feeds)) {
        echo "找到的所有记录:\n";
        foreach ($all_feeds as $feed) {
            echo "SKU: {$feed->sku}, 状态: {$feed->status}, Feed: {$feed->feed_id}, 时间: {$feed->created_at}\n";
        }
    }
}

// 5. 检查批量Feed表
echo "\n5. 检查批量Feed表:\n";

$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
if ($wpdb->get_var("SHOW TABLES LIKE '{$batch_feeds_table}'") == $batch_feeds_table) {
    
    $batch_records = $wpdb->get_results("
        SELECT * FROM {$batch_feeds_table} 
        WHERE created_at >= '2025-08-25 16:40:00' 
        AND created_at <= '2025-08-25 16:45:00'
        ORDER BY created_at DESC
    ");
    
    if (!empty($batch_records)) {
        foreach ($batch_records as $batch) {
            echo "批次ID: {$batch->batch_id}\n";
            echo "产品数量: {$batch->product_count}\n";
            echo "状态: {$batch->status}\n";
            
            if (!empty($batch->failed_products)) {
                $failed_products = json_decode($batch->failed_products, true);
                if (is_array($failed_products)) {
                    echo "失败产品数量: " . count($failed_products) . "\n";
                    echo "失败产品列表:\n";
                    foreach ($failed_products as $sku) {
                        echo $sku . "\n";
                    }
                }
            }
            echo "---\n";
        }
    } else {
        echo "❌ 批量Feed表中没有相关记录\n";
    }
} else {
    echo "❌ 批量Feed表不存在\n";
}

echo "\n=== 总结 ===\n";
echo "这个脚本会显示数据库中实际的失败商品列表，\n";
echo "您可以直接复制完整的列表，不受队列管理页面显示限制的影响。\n";

?>
