<?php
/**
 * 查找最近的批次记录
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 查找最近的批次记录 ===\n\n";

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

// 1. 查找2025-08-25的所有批次
echo "1. 查找2025-08-25的所有批次:\n";

$recent_batches = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at
    FROM {$batch_feeds_table}
    WHERE DATE(created_at) = '2025-08-25'
    ORDER BY created_at DESC
    LIMIT 20
");

if (!empty($recent_batches)) {
    foreach ($recent_batches as $batch) {
        echo "  批次: {$batch->batch_id}\n";
        echo "  状态: {$batch->status}\n";
        echo "  产品数: {$batch->product_count}, 成功: {$batch->success_count}, 失败: {$batch->failed_count}\n";
        echo "  时间: {$batch->created_at}\n";
        
        // 检查是否匹配 #238_9700 的特征
        if ($batch->product_count == 152 && $batch->success_count == 117 && $batch->failed_count == 35) {
            echo "  🎯 这个批次匹配 #238_9700 的特征！\n";
        }
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到2025-08-25的批次记录\n";
}

// 2. 查找包含152个商品的批次
echo "\n2. 查找包含152个商品的批次:\n";

$batches_152 = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at
    FROM {$batch_feeds_table}
    WHERE product_count = 152
    ORDER BY created_at DESC
    LIMIT 10
");

if (!empty($batches_152)) {
    foreach ($batches_152 as $batch) {
        echo "  批次: {$batch->batch_id}\n";
        echo "  成功: {$batch->success_count}, 失败: {$batch->failed_count}\n";
        echo "  时间: {$batch->created_at}\n";
        
        if ($batch->success_count == 117 && $batch->failed_count == 35) {
            echo "  🎯 完全匹配 #238_9700！\n";
        }
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到152个商品的批次\n";
}

// 3. 查找失败35个的批次
echo "\n3. 查找失败35个的批次:\n";

$batches_failed_35 = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at
    FROM {$batch_feeds_table}
    WHERE failed_count = 35
    ORDER BY created_at DESC
    LIMIT 10
");

if (!empty($batches_failed_35)) {
    foreach ($batches_failed_35 as $batch) {
        echo "  批次: {$batch->batch_id}\n";
        echo "  产品数: {$batch->product_count}, 成功: {$batch->success_count}\n";
        echo "  时间: {$batch->created_at}\n";
        
        if ($batch->product_count == 152 && $batch->success_count == 117) {
            echo "  🎯 完全匹配 #238_9700！\n";
        }
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到失败35个的批次\n";
}

// 4. 查找最新的COMPLETED批次
echo "\n4. 查找最新的COMPLETED批次:\n";

$completed_batches = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at
    FROM {$batch_feeds_table}
    WHERE status = 'COMPLETED'
    ORDER BY created_at DESC
    LIMIT 15
");

if (!empty($completed_batches)) {
    foreach ($completed_batches as $batch) {
        echo "  批次: {$batch->batch_id}\n";
        echo "  产品数: {$batch->product_count}, 成功: {$batch->success_count}, 失败: {$batch->failed_count}\n";
        echo "  时间: {$batch->created_at}\n";
        
        // 检查时间是否接近16:52:10
        if (strpos($batch->created_at, '2025-08-25 16:5') !== false) {
            echo "  ⏰ 时间接近16:52:10\n";
        }
        
        if ($batch->product_count == 152 && $batch->success_count == 117 && $batch->failed_count == 35) {
            echo "  🎯 完全匹配 #238_9700！\n";
        }
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到COMPLETED状态的批次\n";
}

echo "\n=== 查找结果 ===\n";
echo "请根据上面的结果确定 #238_9700 对应的实际批次ID\n";

?>
