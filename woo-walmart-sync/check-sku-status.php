<?php
/**
 * 检查特定SKU的真实状态
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查SKU B011P370420 的真实状态 ===\n\n";

$target_sku = 'B011P370420';
$batch_id = 'BATCH_20250824081352_6177';

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';
$feeds_table = $wpdb->prefix . 'walmart_feeds';

echo "目标SKU: {$target_sku}\n";
echo "相关批次: {$batch_id}\n\n";

// 1. 检查在子批次API响应中的状态
echo "1. 检查在子批次API响应中的状态:\n";

$sub_batches = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, api_response FROM {$batch_feeds_table}
     WHERE batch_id LIKE %s AND batch_id != %s
     AND api_response IS NOT NULL AND api_response != ''
     ORDER BY batch_id",
    $batch_id . '%', $batch_id
));

$found_in_api = false;
foreach ($sub_batches as $sub_batch) {
    $api_response = json_decode($sub_batch->api_response, true);
    if ($api_response && isset($api_response['itemDetails']['itemIngestionStatus'])) {
        $items = $api_response['itemDetails']['itemIngestionStatus'];
        
        foreach ($items as $item) {
            if (isset($item['sku']) && $item['sku'] === $target_sku) {
                $found_in_api = true;
                echo "✅ 在子批次 {$sub_batch->batch_id} 中找到:\n";
                echo "  SKU: {$item['sku']}\n";
                echo "  状态: {$item['ingestionStatus']}\n";
                
                if (isset($item['ingestionErrors']) && is_array($item['ingestionErrors'])) {
                    echo "  错误信息:\n";
                    foreach ($item['ingestionErrors'] as $error) {
                        if (isset($error['description'])) {
                            echo "    - {$error['description']}\n";
                        }
                    }
                } else {
                    echo "  错误信息: 无\n";
                }
                
                if ($item['ingestionStatus'] === 'SUCCESS') {
                    echo "  🎉 实际状态: 成功！\n";
                } else {
                    echo "  ❌ 实际状态: 失败\n";
                }
                break;
            }
        }
    }
}

if (!$found_in_api) {
    echo "❌ 在子批次API响应中未找到此SKU\n";
}

// 2. 检查在Feed表中的状态
echo "\n2. 检查在Feed表中的状态:\n";

$feed_records = $wpdb->get_results($wpdb->prepare(
    "SELECT feed_id, sku, status, api_response, created_at FROM {$feeds_table}
     WHERE sku = %s
     ORDER BY created_at DESC
     LIMIT 10",
    $target_sku
));

if (!empty($feed_records)) {
    echo "在Feed表中找到 " . count($feed_records) . " 条记录:\n";
    foreach ($feed_records as $feed) {
        echo "  Feed ID: {$feed->feed_id}\n";
        echo "  状态: {$feed->status}\n";
        echo "  时间: {$feed->created_at}\n";
        
        if (!empty($feed->api_response)) {
            $feed_response = json_decode($feed->api_response, true);
            if ($feed_response) {
                echo "  API响应: " . substr(json_encode($feed_response), 0, 100) . "...\n";
            }
        }
        echo "  ---\n";
    }
} else {
    echo "❌ 在Feed表中未找到此SKU\n";
}

// 3. 检查在batch_items表中的状态
echo "\n3. 检查在batch_items表中的状态:\n";

$batch_items = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, sku, status, error_message FROM {$batch_items_table}
     WHERE sku = %s AND batch_id LIKE %s
     ORDER BY id DESC",
    $target_sku, $batch_id . '%'
));

if (!empty($batch_items)) {
    echo "在batch_items表中找到 " . count($batch_items) . " 条记录:\n";
    foreach ($batch_items as $item) {
        echo "  批次: {$item->batch_id}\n";
        echo "  状态: {$item->status}\n";
        echo "  错误信息: " . ($item->error_message ?: '无') . "\n";
        echo "  ---\n";
    }
} else {
    echo "❌ 在batch_items表中未找到此SKU\n";
}

// 4. 分析问题原因
echo "\n4. 问题分析:\n";

if ($found_in_api) {
    echo "✅ SKU在API响应中存在，说明它确实参与了批次处理\n";
    echo "问题可能是:\n";
    echo "1. 修复逻辑错误地将成功商品标记为失败\n";
    echo "2. Feed表中的状态与API响应不一致\n";
    echo "3. 时间范围匹配错误，匹配到了错误的Feed记录\n";
} else {
    echo "⚠️ SKU在API响应中不存在，但出现在失败列表中\n";
    echo "问题可能是:\n";
    echo "1. Feed表补充逻辑错误地包含了不相关的SKU\n";
    echo "2. 时间范围过宽，匹配到了其他批次的商品\n";
    echo "3. SKU实际上不属于这个批次\n";
}

// 5. 检查修复逻辑的问题
echo "\n5. 检查修复逻辑的问题:\n";

$batch_time = $wpdb->get_var($wpdb->prepare(
    "SELECT created_at FROM {$batch_feeds_table} WHERE batch_id = %s",
    $batch_id
));

if ($batch_time) {
    echo "批次时间: {$batch_time}\n";
    
    $time_range_feeds = $wpdb->get_results($wpdb->prepare(
        "SELECT sku, status FROM {$feeds_table}
         WHERE sku = %s
         AND created_at BETWEEN DATE_SUB(%s, INTERVAL 2 HOUR) AND DATE_ADD(%s, INTERVAL 2 HOUR)
         ORDER BY created_at DESC",
        $target_sku, $batch_time, $batch_time
    ));
    
    if (!empty($time_range_feeds)) {
        echo "在时间范围内找到的Feed记录:\n";
        foreach ($time_range_feeds as $feed) {
            echo "  SKU: {$feed->sku}, 状态: {$feed->status}\n";
            
            if ($feed->status === 'PROCESSED') {
                echo "  ⚠️ 这个SKU在Feed表中是PROCESSED状态（成功），不应该被包含在失败列表中！\n";
            }
        }
    }
}

echo "\n=== 分析完成 ===\n";
echo "如果SKU实际上是成功的，需要修复Feed补充逻辑的过滤条件\n";

?>
