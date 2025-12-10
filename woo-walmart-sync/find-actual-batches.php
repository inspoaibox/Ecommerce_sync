<?php
/**
 * 查找实际的批次记录
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 查找实际的批次记录 ===\n\n";

global $wpdb;

// 1. 查找所有批次记录
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

echo "1. 查找最近的批次记录:\n";
$recent_batches = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at, completed_at
    FROM {$batch_feeds_table}
    WHERE DATE(created_at) = '2025-08-25'
    ORDER BY created_at DESC
    LIMIT 10
");

if (!empty($recent_batches)) {
    foreach ($recent_batches as $batch) {
        echo "  批次ID: {$batch->batch_id}\n";
        echo "  状态: {$batch->status}\n";
        echo "  产品数量: {$batch->product_count}\n";
        echo "  成功: {$batch->success_count} | 失败: {$batch->failed_count}\n";
        echo "  创建时间: {$batch->created_at}\n";
        echo "  完成时间: " . ($batch->completed_at ?: '未完成') . "\n";
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到2025-08-25的批次记录\n";
}

// 2. 查找包含200个商品的批次
echo "\n2. 查找包含200个商品的批次:\n";
$large_batches = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at
    FROM {$batch_feeds_table}
    WHERE product_count = 200
    ORDER BY created_at DESC
    LIMIT 5
");

if (!empty($large_batches)) {
    foreach ($large_batches as $batch) {
        echo "  批次ID: {$batch->batch_id}\n";
        echo "  状态: {$batch->status}\n";
        echo "  成功: {$batch->success_count} | 失败: {$batch->failed_count}\n";
        echo "  创建时间: {$batch->created_at}\n";
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到200个商品的批次\n";
}

// 3. 查找失败数量为145或76的批次
echo "\n3. 查找失败数量为145或76的批次:\n";
$failed_batches = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at
    FROM {$batch_feeds_table}
    WHERE failed_count IN (145, 76)
    ORDER BY created_at DESC
    LIMIT 5
");

if (!empty($failed_batches)) {
    foreach ($failed_batches as $batch) {
        echo "  批次ID: {$batch->batch_id}\n";
        echo "  状态: {$batch->status}\n";
        echo "  产品数量: {$batch->product_count}\n";
        echo "  成功: {$batch->success_count} | 失败: {$batch->failed_count}\n";
        echo "  创建时间: {$batch->created_at}\n";
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到失败数量为145或76的批次\n";
}

// 4. 查找所有COMPLETED状态的批次
echo "\n4. 查找所有COMPLETED状态的批次:\n";
$completed_batches = $wpdb->get_results("
    SELECT batch_id, status, product_count, success_count, failed_count, created_at
    FROM {$batch_feeds_table}
    WHERE status = 'COMPLETED'
    ORDER BY created_at DESC
    LIMIT 10
");

if (!empty($completed_batches)) {
    foreach ($completed_batches as $batch) {
        echo "  批次ID: {$batch->batch_id}\n";
        echo "  产品数量: {$batch->product_count}\n";
        echo "  成功: {$batch->success_count} | 失败: {$batch->failed_count}\n";
        echo "  创建时间: {$batch->created_at}\n";
        
        // 检查这个批次是否匹配您描述的情况
        if ($batch->product_count == 200 && ($batch->failed_count == 145 || $batch->failed_count == 76)) {
            echo "  🎯 这个批次可能就是您要找的！\n";
        }
        echo "  ---\n";
    }
} else {
    echo "❌ 没有找到COMPLETED状态的批次\n";
}

// 5. 查找batch_items表中的数据
echo "\n5. 检查batch_items表:\n";
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$batch_items_table}'") == $batch_items_table;
if ($table_exists) {
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$batch_items_table}");
    echo "  batch_items表总记录数: {$total_items}\n";
    
    if ($total_items > 0) {
        $recent_items = $wpdb->get_results("
            SELECT batch_id, COUNT(*) as count, 
                   SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM {$batch_items_table}
            GROUP BY batch_id
            ORDER BY batch_id DESC
            LIMIT 5
        ");
        
        echo "  最近的batch_items记录:\n";
        foreach ($recent_items as $item) {
            echo "    批次: {$item->batch_id}, 总数: {$item->count}, 成功: {$item->success_count}, 失败: {$item->failed_count}\n";
        }
    }
} else {
    echo "  ❌ batch_items表不存在\n";
}

echo "\n=== 查找结果 ===\n";
echo "请根据上面的结果确定具体的批次ID，然后我们可以进一步诊断数据完整性问题。\n";

?>
