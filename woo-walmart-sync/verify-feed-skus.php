<?php
/**
 * 验证Feed补充的SKU是否真的是失败的
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 验证Feed补充的SKU ===\n\n";

// 从输出中选择几个Feed补充的SKU进行验证
$test_skus = [
    'W714S00847',
    'W714S00846', 
    'W3041S00143',
    'W2795P291715',
    'B102S00054'
];

$batch_id = 'BATCH_20250824081352_6177';
$batch_time = '2025-08-24 16:13:52';

global $wpdb;
$feeds_table = $wpdb->prefix . 'walmart_feeds';

echo "批次时间: {$batch_time}\n";
echo "验证的SKU: " . implode(', ', $test_skus) . "\n\n";

foreach ($test_skus as $sku) {
    echo "检查SKU: {$sku}\n";
    
    // 查找这个SKU的所有Feed记录
    $feed_records = $wpdb->get_results($wpdb->prepare(
        "SELECT feed_id, status, created_at FROM {$feeds_table}
         WHERE sku = %s
         ORDER BY created_at DESC
         LIMIT 5",
        $sku
    ));
    
    if (!empty($feed_records)) {
        echo "  Feed记录:\n";
        $has_success = false;
        $latest_status = '';
        
        foreach ($feed_records as $feed) {
            $time_diff = strtotime($batch_time) - strtotime($feed->created_at);
            $hours_diff = round($time_diff / 3600, 1);
            
            echo "    {$feed->created_at} - {$feed->status} (距批次{$hours_diff}小时)\n";
            
            if ($feed->status === 'PROCESSED') {
                $has_success = true;
            }
            
            if (empty($latest_status)) {
                $latest_status = $feed->status;
            }
        }
        
        // 判断这个SKU是否应该被包含在失败列表中
        if ($has_success) {
            echo "  ❌ 问题: 这个SKU有成功记录，不应该在失败列表中\n";
        } else {
            echo "  ✅ 合理: 这个SKU确实没有成功记录\n";
        }
        
        // 检查时间范围是否合理
        $first_record = end($feed_records);
        $time_diff = abs(strtotime($batch_time) - strtotime($first_record->created_at));
        $hours_diff = round($time_diff / 3600, 1);
        
        if ($hours_diff > 2) {
            echo "  ⚠️ 时间差异: {$hours_diff}小时，可能不属于这个批次\n";
        } else {
            echo "  ✅ 时间合理: {$hours_diff}小时内\n";
        }
        
    } else {
        echo "  ❌ 没有找到Feed记录\n";
    }
    
    echo "\n";
}

echo "=== 验证总结 ===\n";
echo "如果发现问题SKU，说明过滤逻辑仍需改进\n";
echo "如果所有SKU都合理，说明修复成功\n";

// 统计分析
echo "\n=== 统计分析 ===\n";

$problem_count = 0;
$reasonable_count = 0;

foreach ($test_skus as $sku) {
    $has_success = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$feeds_table} WHERE sku = %s AND status = 'PROCESSED'",
        $sku
    ));
    
    if ($has_success > 0) {
        $problem_count++;
    } else {
        $reasonable_count++;
    }
}

echo "问题SKU: {$problem_count}个\n";
echo "合理SKU: {$reasonable_count}个\n";

if ($problem_count == 0) {
    echo "🎉 所有验证的SKU都是合理的失败商品\n";
} elseif ($problem_count < count($test_skus) / 2) {
    echo "✅ 大部分SKU是合理的，修复基本成功\n";
} else {
    echo "⚠️ 仍有较多问题SKU，需要进一步改进过滤逻辑\n";
}

?>
