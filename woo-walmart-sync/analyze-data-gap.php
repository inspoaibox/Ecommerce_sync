<?php
/**
 * 深入分析数据缺失原因
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 深入分析数据缺失原因 ===\n\n";

$batch_id = 'BATCH_20250824081352_6177';
echo "分析批次: {$batch_id}\n";
echo "期望失败数: 76\n";
echo "当前获取数: 25\n";
echo "缺失数量: 51\n\n";

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';
$feeds_table = $wpdb->prefix . 'walmart_feeds';

// 1. 详细检查子批次的API响应内容
echo "1. 详细检查子批次API响应:\n";

$sub_batches = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, success_count, failed_count, api_response FROM {$batch_feeds_table}
     WHERE batch_id LIKE %s AND batch_id != %s
     ORDER BY batch_id",
    $batch_id . '%', $batch_id
));

$total_sub_success = 0;
$total_sub_failed = 0;
$total_api_failed = 0;

foreach ($sub_batches as $sub_batch) {
    echo "子批次: {$sub_batch->batch_id}\n";
    echo "  统计: 成功{$sub_batch->success_count}, 失败{$sub_batch->failed_count}\n";
    
    $total_sub_success += $sub_batch->success_count;
    $total_sub_failed += $sub_batch->failed_count;
    
    if (!empty($sub_batch->api_response)) {
        $api_response = json_decode($sub_batch->api_response, true);
        if ($api_response && isset($api_response['itemDetails']['itemIngestionStatus'])) {
            $items = $api_response['itemDetails']['itemIngestionStatus'];
            $api_success = 0;
            $api_failed = 0;
            
            foreach ($items as $item) {
                if (isset($item['ingestionStatus'])) {
                    if ($item['ingestionStatus'] === 'SUCCESS') {
                        $api_success++;
                    } else {
                        $api_failed++;
                        $total_api_failed++;
                    }
                }
            }
            
            echo "  API响应: 成功{$api_success}, 失败{$api_failed}\n";
            echo "  统计vs API: 失败统计{$sub_batch->failed_count} vs API失败{$api_failed}\n";
            
            if ($sub_batch->failed_count != $api_failed) {
                echo "  ⚠️ 统计数据与API数据不匹配！差异: " . ($sub_batch->failed_count - $api_failed) . "\n";
            }
        } else {
            echo "  ❌ API响应格式异常\n";
        }
    } else {
        echo "  ❌ 没有API响应\n";
    }
    echo "\n";
}

echo "子批次汇总:\n";
echo "  统计总计: 成功{$total_sub_success}, 失败{$total_sub_failed}\n";
echo "  API总计: 失败{$total_api_failed}\n";
echo "  缺失的失败商品: " . ($total_sub_failed - $total_api_failed) . "\n\n";

// 2. 检查batch_items表中的数据
echo "2. 检查batch_items表中的数据:\n";

$batch_items_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$batch_items_table} WHERE batch_id LIKE %s",
    $batch_id . '%'
));

echo "batch_items表中相关记录数: {$batch_items_count}\n";

if ($batch_items_count > 0) {
    $status_counts = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count FROM {$batch_items_table} 
         WHERE batch_id LIKE %s GROUP BY status",
        $batch_id . '%'
    ));
    
    echo "状态分布:\n";
    foreach ($status_counts as $status) {
        echo "  {$status->status}: {$status->count}个\n";
    }
    
    // 检查是否有failed状态的记录
    $failed_items = $wpdb->get_results($wpdb->prepare(
        "SELECT batch_id, sku, error_message FROM {$batch_items_table} 
         WHERE batch_id LIKE %s AND status = 'failed'
         LIMIT 10",
        $batch_id . '%'
    ));
    
    if (!empty($failed_items)) {
        echo "batch_items中的失败记录样本:\n";
        foreach ($failed_items as $item) {
            echo "  {$item->batch_id}: {$item->sku}\n";
        }
    } else {
        echo "❌ batch_items表中没有failed状态的记录\n";
    }
} else {
    echo "❌ batch_items表中没有相关数据\n";
}

// 3. 检查feeds表中的相关记录
echo "\n3. 检查feeds表中的相关记录:\n";

// 查找相关时间段的Feed记录
$batch_time = $wpdb->get_var($wpdb->prepare(
    "SELECT created_at FROM {$batch_feeds_table} WHERE batch_id = %s",
    $batch_id
));

if ($batch_time) {
    echo "批次创建时间: {$batch_time}\n";
    
    $related_feeds = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count FROM {$feeds_table}
         WHERE created_at BETWEEN DATE_SUB(%s, INTERVAL 2 HOUR) AND DATE_ADD(%s, INTERVAL 2 HOUR)
         GROUP BY status",
        $batch_time, $batch_time
    ));
    
    echo "相关时间段的Feed记录:\n";
    $total_feeds = 0;
    $failed_feeds = 0;
    
    foreach ($related_feeds as $feed) {
        echo "  {$feed->status}: {$feed->count}个\n";
        $total_feeds += $feed->count;
        if ($feed->status !== 'PROCESSED') {
            $failed_feeds += $feed->count;
        }
    }
    
    echo "Feed记录总计: {$total_feeds}个, 失败: {$failed_feeds}个\n";
    
    if ($failed_feeds > $total_api_failed) {
        echo "⚠️ Feed表中的失败记录({$failed_feeds})多于API响应中的失败记录({$total_api_failed})\n";
        echo "可能的缺失数据在Feed表中: " . ($failed_feeds - $total_api_failed) . "个\n";
    }
}

// 4. 分析可能的解决方案
echo "\n4. 可能的解决方案:\n";

if ($total_sub_failed > $total_api_failed) {
    echo "问题: 子批次统计数据({$total_sub_failed})大于API响应数据({$total_api_failed})\n";
    echo "可能原因:\n";
    echo "  1. API响应不完整，部分失败商品没有包含在itemIngestionStatus中\n";
    echo "  2. 统计数据包含了其他类型的失败（如警告、部分成功等）\n";
    echo "  3. 数据在不同的API响应字段中\n";
    echo "  4. 批次处理过程中数据丢失\n\n";
    
    echo "建议的修复策略:\n";
    echo "  1. 检查API响应的其他字段（如errors、warnings等）\n";
    echo "  2. 从Feed表中补充缺失的失败商品\n";
    echo "  3. 使用统计数据推断缺失的商品\n";
    echo "  4. 组合多个数据源获取完整列表\n";
}

echo "\n=== 分析完成 ===\n";
echo "下一步: 基于分析结果改进数据获取逻辑\n";

?>
