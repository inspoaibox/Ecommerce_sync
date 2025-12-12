<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== UPC双重扣除问题调试 ===\n\n";

global $wpdb;
$upc_table = $wpdb->prefix . 'walmart_upc_pool';
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

// 检查表是否存在
echo "检查数据库表:\n";
$upc_exists = $wpdb->get_var("SHOW TABLES LIKE '$upc_table'") == $upc_table;
$batch_exists = $wpdb->get_var("SHOW TABLES LIKE '$batch_items_table'") == $batch_items_table;

echo "  UPC池表 ($upc_table): " . ($upc_exists ? '✅ 存在' : '❌ 不存在') . "\n";
echo "  批量同步表 ($batch_items_table): " . ($batch_exists ? '✅ 存在' : '❌ 不存在') . "\n";

if ($upc_exists) {
    // 检查UPC池状态
    echo "\nUPC池状态:\n";
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
    }
    
    // 检查最近分配的UPC
    echo "\n最近分配的UPC (最近10个):\n";
    $recent_upcs = $wpdb->get_results("
        SELECT product_id, upc_code, used_at, is_used
        FROM $upc_table 
        WHERE is_used = 1 
        ORDER BY used_at DESC 
        LIMIT 10
    ");
    
    if ($recent_upcs) {
        foreach ($recent_upcs as $upc) {
            echo "  产品ID: {$upc->product_id}, UPC: {$upc->upc_code}, 时间: {$upc->used_at}\n";
        }
    } else {
        echo "  没有找到已使用的UPC\n";
    }
}

if ($batch_exists) {
    // 检查批量同步记录
    echo "\n批量同步记录 (最近5个批次):\n";
    $recent_batches = $wpdb->get_results("
        SELECT batch_id, COUNT(*) as item_count, MIN(created_at) as created_at
        FROM $batch_items_table 
        GROUP BY batch_id
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    if ($recent_batches) {
        foreach ($recent_batches as $batch) {
            echo "  批次: {$batch->batch_id}, 商品数: {$batch->item_count}, 时间: {$batch->created_at}\n";
        }
        
        // 分析第一个批次
        $first_batch = $recent_batches[0];
        echo "\n分析批次: {$first_batch->batch_id}\n";
        
        // 获取该批次的产品
        $batch_products = $wpdb->get_results($wpdb->prepare("
            SELECT product_id, sku, status
            FROM $batch_items_table 
            WHERE batch_id = %s
            ORDER BY id ASC
            LIMIT 20
        ", $first_batch->batch_id));
        
        if ($batch_products) {
            echo "  批次中的产品 (前20个):\n";
            foreach ($batch_products as $product) {
                echo "    产品ID: {$product->product_id}, SKU: {$product->sku}, 状态: {$product->status}\n";
            }
            
            // 检查这些产品的UPC分配
            if ($upc_exists) {
                $product_ids = array_column($batch_products, 'product_id');
                $product_ids_str = implode(',', array_map('intval', $product_ids));
                
                $upc_assignments = $wpdb->get_results("
                    SELECT product_id, upc_code, used_at
                    FROM $upc_table 
                    WHERE product_id IN ($product_ids_str)
                    ORDER BY product_id, used_at
                ");
                
                echo "\n  这些产品的UPC分配情况:\n";
                echo "    批次产品数: " . count($batch_products) . "\n";
                echo "    UPC分配数: " . count($upc_assignments) . "\n";
                
                if (count($upc_assignments) > count($batch_products)) {
                    echo "    ❌ 发现UPC过度消耗！\n";
                    
                    // 检查重复分配
                    $product_upc_count = [];
                    foreach ($upc_assignments as $assignment) {
                        $pid = $assignment->product_id;
                        if (!isset($product_upc_count[$pid])) {
                            $product_upc_count[$pid] = 0;
                        }
                        $product_upc_count[$pid]++;
                    }
                    
                    foreach ($product_upc_count as $product_id => $count) {
                        if ($count > 1) {
                            echo "      产品 {$product_id} 分配了 {$count} 个UPC\n";
                        }
                    }
                } else {
                    echo "    ✅ UPC消耗正常\n";
                }
            }
        }
    } else {
        echo "  没有找到批量同步记录\n";
    }
}

echo "\n调试完成。\n";
