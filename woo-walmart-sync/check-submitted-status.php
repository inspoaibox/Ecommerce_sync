<?php
/**
 * 检查SUBMITTED状态的商品数量
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查SUBMITTED状态的商品数量 ===\n\n";

global $wpdb;
$feeds_table = $wpdb->prefix . 'walmart_feeds';

// 1. 统计各种状态的数量
echo "1. 统计各种状态的数量:\n";

$status_counts = $wpdb->get_results("
    SELECT status, COUNT(*) as count 
    FROM {$feeds_table} 
    GROUP BY status 
    ORDER BY count DESC
");

foreach ($status_counts as $status) {
    echo "  {$status->status}: {$status->count} 个\n";
}

// 2. 查找SUBMITTED状态的商品
echo "\n2. 查找SUBMITTED状态的商品:\n";

$submitted_records = $wpdb->get_results("
    SELECT sku, feed_id, created_at 
    FROM {$feeds_table} 
    WHERE status = 'SUBMITTED' 
    ORDER BY created_at DESC 
    LIMIT 150
");

echo "SUBMITTED状态的商品数量: " . count($submitted_records) . "\n\n";

if (!empty($submitted_records)) {
    echo "SUBMITTED状态的SKU列表:\n";
    echo "=== 开始复制 ===\n";
    foreach ($submitted_records as $record) {
        echo $record->sku . "\n";
    }
    echo "=== 结束复制 ===\n";
    
    // 3. 与您提供的列表对比
    echo "\n3. 与您提供的列表对比:\n";
    
    $submitted_skus = array_column($submitted_records, 'sku');
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
    
    echo "数据库SUBMITTED状态数量: " . count($submitted_skus) . "\n";
    echo "您复制的SKU数量: " . count($provided_skus) . "\n";
    
    // 检查匹配度
    $matched_skus = array_intersect($submitted_skus, $provided_skus);
    echo "匹配的SKU数量: " . count($matched_skus) . "\n";
    
    if (count($matched_skus) > 50) {
        echo "✅ 高度匹配！队列管理页面很可能显示的是SUBMITTED状态的商品\n";
    } else {
        echo "❌ 匹配度较低，可能不是SUBMITTED状态\n";
    }
    
    // 4. 检查特定Feed ID的商品
    echo "\n4. 检查特定Feed ID的商品:\n";
    
    $feed_groups = [];
    foreach ($submitted_records as $record) {
        if (!isset($feed_groups[$record->feed_id])) {
            $feed_groups[$record->feed_id] = [];
        }
        $feed_groups[$record->feed_id][] = $record->sku;
    }
    
    foreach ($feed_groups as $feed_id => $skus) {
        echo "Feed ID: {$feed_id} - 商品数量: " . count($skus) . "\n";
        if (count($skus) == 200) {
            echo "  ✅ 这个Feed有200个商品，可能对应队列管理页面的批次\n";
        }
    }
}

// 5. 检查是否有真正的失败状态
echo "\n5. 检查是否有真正的失败状态:\n";

$failed_statuses = ['FAILED', 'ERROR', 'REJECTED', 'DATA_ERROR'];
$total_failed = 0;

foreach ($failed_statuses as $status) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$feeds_table} WHERE status = %s",
        $status
    ));
    
    if ($count > 0) {
        echo "  {$status}: {$count} 个\n";
        $total_failed += $count;
    }
}

if ($total_failed == 0) {
    echo "  ✅ 没有找到真正的失败状态商品\n";
} else {
    echo "  ❌ 找到 {$total_failed} 个真正失败的商品\n";
}

echo "\n=== 结论 ===\n";
echo "如果SUBMITTED状态的商品数量接近145个，\n";
echo "那么队列管理页面很可能把SUBMITTED状态误认为是'失败'状态。\n";
echo "实际上这些商品可能只是还在处理中，不是真正的失败。\n";

?>
