<?php
// 检查批次商品的实际状态值

// 尝试加载WordPress
$wp_load_paths = [
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '../wp-load.php'
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!function_exists('get_option')) {
    die('请通过WordPress环境访问此脚本');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== 批次商品状态检查 ===\n\n";

// 目标批次ID
$target_batch_id = 'BATCH_20250903061604_1994';

global $wpdb;
$batch_items_table = $wpdb->prefix . 'walmart_batch_items';

// 1. 检查所有子批次的商品状态
echo "1. 查找所有相关批次:\n";
$all_batch_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT batch_id FROM $batch_items_table WHERE batch_id LIKE %s",
    '%' . '604_1994' . '%'
));

foreach ($all_batch_ids as $batch_id) {
    echo "  找到批次: $batch_id\n";
}

echo "\n2. 各批次商品状态统计:\n";
foreach ($all_batch_ids as $batch_id) {
    echo "批次: $batch_id\n";
    
    // 统计各种状态的商品数量
    $status_stats = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count FROM $batch_items_table 
         WHERE batch_id = %s 
         GROUP BY status 
         ORDER BY count DESC",
        $batch_id
    ));
    
    if (empty($status_stats)) {
        echo "  ❌ 没有找到商品记录\n";
    } else {
        foreach ($status_stats as $stat) {
            echo "  {$stat->status}: {$stat->count} 个商品\n";
        }
    }
    echo "\n";
}

// 3. 检查处理中商品的具体信息
echo "3. 处理中商品详细信息:\n";
foreach ($all_batch_ids as $batch_id) {
    echo "批次: $batch_id\n";
    
    // 查找所有可能的"处理中"状态
    $processing_statuses = ['PENDING', 'INPROGRESS', 'PROCESSING', 'SUBMITTED'];
    
    foreach ($processing_statuses as $status) {
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT sku, status, error_message, processed_at FROM $batch_items_table 
             WHERE batch_id = %s AND status = %s 
             ORDER BY id ASC 
             LIMIT 10",
            $batch_id,
            $status
        ));
        
        if (!empty($items)) {
            echo "  状态 '$status' 的商品:\n";
            foreach ($items as $item) {
                echo "    SKU: {$item->sku} | 状态: {$item->status} | 处理时间: " . ($item->processed_at ?: '未处理') . "\n";
                if (!empty($item->error_message)) {
                    echo "      错误信息: {$item->error_message}\n";
                }
            }
        }
    }
    echo "\n";
}

// 4. 测试查询逻辑
echo "4. 测试不同状态的查询结果:\n";
$test_batch_id = $all_batch_ids[0] ?? '';
if ($test_batch_id) {
    $test_statuses = [
        'success' => 'SUCCESS',
        'failed' => 'ERROR', 
        'processing_old' => 'PENDING',
        'processing_new' => 'INPROGRESS'
    ];
    
    foreach ($test_statuses as $type => $status) {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $batch_items_table WHERE batch_id = %s AND status = %s",
            $test_batch_id,
            $status
        ));
        echo "  {$type} ({$status}): {$count} 个商品\n";
    }
}

// 5. 修复建议
echo "\n5. 修复建议:\n";
if (isset($_GET['fix']) && $_GET['fix'] === '1') {
    echo "🔄 正在修复状态映射...\n";
    
    // 将旧的PENDING状态更新为INPROGRESS
    foreach ($all_batch_ids as $batch_id) {
        $updated = $wpdb->update(
            $batch_items_table,
            ['status' => 'INPROGRESS'],
            ['batch_id' => $batch_id, 'status' => 'PENDING']
        );
        
        if ($updated > 0) {
            echo "  ✅ 批次 $batch_id: 更新了 $updated 个商品的状态 (PENDING → INPROGRESS)\n";
        }
        
        // 同样处理PROCESSING状态
        $updated2 = $wpdb->update(
            $batch_items_table,
            ['status' => 'INPROGRESS'],
            ['batch_id' => $batch_id, 'status' => 'PROCESSING']
        );
        
        if ($updated2 > 0) {
            echo "  ✅ 批次 $batch_id: 更新了 $updated2 个商品的状态 (PROCESSING → INPROGRESS)\n";
        }
    }
    
    echo "✅ 状态修复完成！\n";
} else {
    echo "💡 添加 ?fix=1 参数来修复状态映射问题\n";
}

echo "\n=== 检查完成 ===\n";
?>
