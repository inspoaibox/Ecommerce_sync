<?php
// 刷新子批次的API数据

require_once '../../../wp-load.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== 刷新子批次API数据 ===\n\n";

$sub_batch_ids = [
    'BATCH_20250903061604_1994_CHUNK_1',
    'BATCH_20250903061604_1994_CHUNK_2'
];

if (class_exists('Woo_Walmart_Product_Sync')) {
    $sync = new Woo_Walmart_Product_Sync();
    
    foreach ($sub_batch_ids as $batch_id) {
        echo "🔄 刷新批次: $batch_id\n";
        
        $result = $sync->check_single_batch_feed_status($batch_id);
        
        if ($result['success']) {
            echo "  ✅ 刷新成功: {$result['status']}\n";
        } else {
            echo "  ❌ 刷新失败: {$result['message']}\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Woo_Walmart_Product_Sync 类不存在\n";
}

echo "=== 刷新完成 ===\n";
echo "💡 现在可以测试批次详情查询功能\n";
?>
