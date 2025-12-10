<?php
// 检查是否有重复的SKU

require_once '../../../wp-load.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== 检查SKU重复情况 ===\n\n";

global $wpdb;
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

// 查询子批次的成功商品
$sub_batch_ids = [
    'BATCH_20250903061604_1994_CHUNK_1',
    'BATCH_20250903061604_1994_CHUNK_2'
];

foreach ($sub_batch_ids as $batch_id) {
    echo "子批次: $batch_id\n";
    
    $success_items = $wpdb->get_col($wpdb->prepare(
        "SELECT sku FROM $batch_items_table WHERE batch_id = %s AND status = 'SUCCESS'",
        $batch_id
    ));
    
    echo "  成功商品数: " . count($success_items) . "\n";
    echo "  前5个SKU: " . implode(', ', array_slice($success_items, 0, 5)) . "\n\n";
}

// 检查合并后的重复情况
$all_success_items = $wpdb->get_col($wpdb->prepare(
    "SELECT sku FROM $batch_items_table 
     WHERE batch_id IN ('BATCH_20250903061604_1994_CHUNK_1', 'BATCH_20250903061604_1994_CHUNK_2') 
     AND status = 'SUCCESS'
     ORDER BY sku"
));

echo "合并查询结果:\n";
echo "  总成功商品数: " . count($all_success_items) . "\n";

// 检查重复
$unique_skus = array_unique($all_success_items);
echo "  去重后数量: " . count($unique_skus) . "\n";

if (count($all_success_items) > count($unique_skus)) {
    echo "  ❌ 发现重复SKU\n";
    
    // 找出重复的SKU
    $sku_counts = array_count_values($all_success_items);
    $duplicates = array_filter($sku_counts, function($count) { return $count > 1; });
    
    echo "  重复的SKU:\n";
    foreach ($duplicates as $sku => $count) {
        echo "    $sku: 出现 $count 次\n";
    }
} else {
    echo "  ✅ 没有重复SKU\n";
}

// 检查批次统计
echo "\n批次统计对比:\n";
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

$batch_stats = $wpdb->get_results(
    "SELECT batch_id, success_count, failed_count FROM $batch_feeds_table 
     WHERE batch_id IN ('BATCH_20250903061604_1994', 'BATCH_20250903061604_1994_CHUNK_1', 'BATCH_20250903061604_1994_CHUNK_2')
     ORDER BY batch_id"
);

foreach ($batch_stats as $stat) {
    echo "  {$stat->batch_id}: 成功{$stat->success_count}, 失败{$stat->failed_count}\n";
}

echo "\n=== 检查完成 ===\n";
?>
