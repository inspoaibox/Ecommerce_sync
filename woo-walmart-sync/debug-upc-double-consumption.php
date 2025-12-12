<?php
/**
 * 调试UPC双重扣除问题
 * 检查为什么110个商品会消耗220个UPC
 */

// 尝试加载WordPress
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

if (!function_exists('get_option')) {
    die('请通过WordPress环境访问此脚本');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== UPC双重扣除问题调试 ===\n\n";

global $wpdb;
$upc_table = $wpdb->prefix . 'walmart_upc_pool';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 检查UPC池当前状态
echo "1. UPC池当前状态:\n";
$upc_stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used,
        SUM(CASE WHEN is_used = 0 THEN 1 ELSE 0 END) as available
    FROM $upc_table
");

if ($upc_stats) {
    echo "  总UPC数: {$upc_stats->total}\n";
    echo "  已使用: {$upc_stats->used}\n";
    echo "  可用: {$upc_stats->available}\n";
} else {
    echo "  ❌ 无法获取UPC统计信息\n";
}

// 2. 查找最近的批量同步记录
echo "\n2. 查找最近的批量同步记录:\n";
$recent_batches = $wpdb->get_results("
    SELECT batch_id, COUNT(*) as item_count, status, created_at
    FROM $batch_items_table 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    GROUP BY batch_id
    ORDER BY created_at DESC
    LIMIT 5
");

if ($recent_batches) {
    echo "  最近的批次:\n";
    foreach ($recent_batches as $batch) {
        echo "    批次: {$batch->batch_id}, 商品数: {$batch->item_count}, 时间: {$batch->created_at}\n";
    }
    
    // 选择第一个批次进行详细分析
    $target_batch = $recent_batches[0];
    echo "\n  选择批次 {$target_batch->batch_id} 进行详细分析 (商品数: {$target_batch->item_count})\n";
    
} else {
    echo "  ❌ 没有找到最近的批量同步记录\n";
    exit;
}

// 3. 分析目标批次的UPC使用情况
echo "\n3. 分析批次 {$target_batch->batch_id} 的UPC使用情况:\n";

// 获取批次中的所有产品ID
$batch_products = $wpdb->get_results($wpdb->prepare("
    SELECT product_id, sku, status, error_message
    FROM $batch_items_table 
    WHERE batch_id = %s
    ORDER BY id ASC
", $target_batch->batch_id));

if (empty($batch_products)) {
    echo "  ❌ 批次中没有产品记录\n";
    exit;
}

echo "  批次中的产品数: " . count($batch_products) . "\n";

// 检查这些产品的UPC分配情况
$product_ids = array_column($batch_products, 'product_id');
$product_ids_str = implode(',', array_map('intval', $product_ids));

$upc_assignments = $wpdb->get_results("
    SELECT product_id, upc_code, used_at, is_used
    FROM $upc_table 
    WHERE product_id IN ($product_ids_str)
    ORDER BY product_id, used_at
");

echo "  为这些产品分配的UPC数: " . count($upc_assignments) . "\n";

// 4. 检查是否有重复分配
echo "\n4. 检查UPC重复分配情况:\n";
$product_upc_count = [];
foreach ($upc_assignments as $assignment) {
    $pid = $assignment->product_id;
    if (!isset($product_upc_count[$pid])) {
        $product_upc_count[$pid] = 0;
    }
    $product_upc_count[$pid]++;
}

$duplicate_products = [];
foreach ($product_upc_count as $product_id => $upc_count) {
    if ($upc_count > 1) {
        $duplicate_products[] = $product_id;
        echo "  ⚠️ 产品 ID {$product_id} 分配了 {$upc_count} 个UPC\n";
    }
}

if (empty($duplicate_products)) {
    echo "  ✅ 没有发现重复分配的产品\n";
} else {
    echo "  ❌ 发现 " . count($duplicate_products) . " 个产品有重复UPC分配\n";
    
    // 详细分析重复分配的产品
    echo "\n5. 重复分配详细分析:\n";
    foreach ($duplicate_products as $product_id) {
        echo "  产品 ID {$product_id}:\n";
        
        $product_upcs = array_filter($upc_assignments, function($assignment) use ($product_id) {
            return $assignment->product_id == $product_id;
        });
        
        foreach ($product_upcs as $upc) {
            echo "    UPC: {$upc->upc_code}, 分配时间: {$upc->used_at}, 状态: " . ($upc->is_used ? '已使用' : '未使用') . "\n";
        }
    }
}

// 6. 检查批量同步日志中的UPC分配记录
echo "\n6. 检查批量同步日志中的UPC分配记录:\n";
$upc_logs = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $logs_table 
    WHERE (action LIKE '%UPC%' OR action LIKE '%批量%' OR request LIKE '%assign_upc%')
    AND created_at >= %s
    ORDER BY created_at DESC
    LIMIT 10
", $target_batch->created_at));

if ($upc_logs) {
    echo "  找到 " . count($upc_logs) . " 条相关日志:\n";
    foreach ($upc_logs as $log) {
        echo "    时间: {$log->created_at}, 操作: {$log->action}, 状态: {$log->status}\n";
        
        // 检查日志中是否包含产品ID信息
        if (strpos($log->request, 'product_id') !== false) {
            $request_data = json_decode($log->request, true);
            if ($request_data && isset($request_data['product_id'])) {
                echo "      产品ID: {$request_data['product_id']}\n";
            }
        }
    }
} else {
    echo "  ❌ 没有找到相关的UPC分配日志\n";
}

// 7. 检查是否有并发或重试导致的重复处理
echo "\n7. 检查可能的重复处理原因:\n";

// 检查同一时间段内是否有多个相同的批量同步请求
$concurrent_batches = $wpdb->get_results($wpdb->prepare("
    SELECT batch_id, COUNT(*) as item_count, MIN(created_at) as start_time, MAX(created_at) as end_time
    FROM $batch_items_table 
    WHERE created_at BETWEEN DATE_SUB(%s, INTERVAL 5 MINUTE) AND DATE_ADD(%s, INTERVAL 5 MINUTE)
    GROUP BY batch_id
    ORDER BY start_time
", $target_batch->created_at, $target_batch->created_at));

if (count($concurrent_batches) > 1) {
    echo "  ⚠️ 发现同时间段内有多个批次:\n";
    foreach ($concurrent_batches as $batch) {
        echo "    批次: {$batch->batch_id}, 商品数: {$batch->item_count}, 时间: {$batch->start_time} - {$batch->end_time}\n";
    }
} else {
    echo "  ✅ 没有发现并发批次\n";
}

// 8. 总结分析结果
echo "\n=== 分析总结 ===\n";
echo "批次商品数: " . count($batch_products) . "\n";
echo "分配UPC数: " . count($upc_assignments) . "\n";
echo "重复分配产品数: " . count($duplicate_products) . "\n";

if (count($upc_assignments) > count($batch_products)) {
    $excess_upcs = count($upc_assignments) - count($batch_products);
    echo "❌ UPC过度消耗: 多消耗了 {$excess_upcs} 个UPC\n";
    
    if (!empty($duplicate_products)) {
        echo "可能原因: 产品重复分配UPC\n";
    } else {
        echo "可能原因: 其他未知原因导致UPC过度消耗\n";
    }
} else {
    echo "✅ UPC消耗正常\n";
}

echo "\n调试完成。\n";
