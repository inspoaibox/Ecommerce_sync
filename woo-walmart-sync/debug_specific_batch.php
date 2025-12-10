<?php
// 诊断特定批次的状态问题

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

echo "=== 特定批次状态诊断 ===\n\n";

// 目标批次ID（从页面显示的简写ID推断）
$target_display_id = '604_1994';

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

// 1. 找到完整的批次ID
echo "1. 查找完整批次ID:\n";
$all_batches = $wpdb->get_results(
    "SELECT batch_id, status, batch_type, parent_batch_id FROM $batch_feeds_table 
     WHERE batch_id LIKE '%{$target_display_id}%' 
     ORDER BY created_at DESC"
);

if (empty($all_batches)) {
    echo "  ❌ 没有找到匹配的批次\n";
    exit;
}

$master_batch_id = null;
foreach ($all_batches as $batch) {
    echo "  找到批次: {$batch->batch_id} | 状态: {$batch->status} | 类型: {$batch->batch_type}\n";
    if ($batch->batch_type === 'master') {
        $master_batch_id = $batch->batch_id;
    }
}

if (!$master_batch_id) {
    echo "  ❌ 没有找到主批次\n";
    exit;
}

echo "  ✅ 主批次ID: $master_batch_id\n\n";

// 2. 检查主批次详细信息
echo "2. 主批次详细信息:\n";
$master_batch = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $batch_feeds_table WHERE batch_id = %s",
    $master_batch_id
));

if ($master_batch) {
    echo "  批次ID: {$master_batch->batch_id}\n";
    echo "  状态: {$master_batch->status}\n";
    echo "  商品数量: {$master_batch->product_count}\n";
    echo "  成功数量: {$master_batch->success_count}\n";
    echo "  失败数量: {$master_batch->failed_count}\n";
    echo "  进度: {$master_batch->progress_current}/{$master_batch->progress_total}\n";
    echo "  创建时间: {$master_batch->created_at}\n";
    echo "  更新时间: {$master_batch->updated_at}\n";
    echo "  完成时间: " . ($master_batch->completed_at ?: '未完成') . "\n";
} else {
    echo "  ❌ 无法获取主批次信息\n";
    exit;
}

// 3. 检查所有子批次状态
echo "\n3. 子批次状态详情:\n";
$sub_batches = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, status, success_count, failed_count, feed_id, created_at, updated_at, completed_at 
     FROM $batch_feeds_table 
     WHERE parent_batch_id = %s 
     ORDER BY chunk_index ASC",
    $master_batch_id
));

if (empty($sub_batches)) {
    echo "  ❌ 没有找到子批次\n";
} else {
    $total_sub_batches = count($sub_batches);
    $completed_count = 0;
    $error_count = 0;
    $processing_count = 0;
    $other_count = 0;
    
    foreach ($sub_batches as $i => $sub_batch) {
        echo "  子批次 " . ($i + 1) . ":\n";
        echo "    ID: {$sub_batch->batch_id}\n";
        echo "    状态: {$sub_batch->status}\n";
        echo "    成功/失败: {$sub_batch->success_count}/{$sub_batch->failed_count}\n";
        echo "    Feed ID: " . ($sub_batch->feed_id ?: '无') . "\n";
        echo "    创建时间: {$sub_batch->created_at}\n";
        echo "    更新时间: {$sub_batch->updated_at}\n";
        echo "    完成时间: " . ($sub_batch->completed_at ?: '未完成') . "\n";
        
        // 统计状态
        switch ($sub_batch->status) {
            case 'COMPLETED':
                $completed_count++;
                break;
            case 'ERROR':
                $error_count++;
                break;
            case 'PROCESSING':
            case 'SUBMITTED':
                $processing_count++;
                break;
            default:
                $other_count++;
        }
        echo "\n";
    }
    
    echo "  状态统计:\n";
    echo "    总子批次数: $total_sub_batches\n";
    echo "    已完成: $completed_count\n";
    echo "    错误: $error_count\n";
    echo "    处理中: $processing_count\n";
    echo "    其他: $other_count\n";
    
    // 4. 分析主批次状态判断逻辑
    echo "\n4. 主批次状态判断分析:\n";
    echo "  当前逻辑判断:\n";
    
    if ($completed_count === $total_sub_batches) {
        echo "    ✅ 所有子批次已完成 -> 应该是 COMPLETED\n";
        $expected_status = 'COMPLETED';
    } elseif ($error_count === $total_sub_batches) {
        echo "    ❌ 所有子批次都错误 -> 应该是 ERROR\n";
        $expected_status = 'ERROR';
    } elseif ($completed_count + $error_count === $total_sub_batches) {
        echo "    ✅ 所有子批次都已处理完成（部分成功+部分失败）-> 应该是 COMPLETED\n";
        $expected_status = 'COMPLETED';
    } else {
        echo "    ⚠️ 还有子批次在处理中 -> 应该是 PROCESSING\n";
        $expected_status = 'PROCESSING';
    }
    
    echo "  预期状态: $expected_status\n";
    echo "  实际状态: {$master_batch->status}\n";
    
    if ($expected_status !== $master_batch->status) {
        echo "  ❌ 状态不匹配！需要手动更新\n";
        
        // 5. 手动更新主批次状态
        if (isset($_GET['fix']) && $_GET['fix'] === '1') {
            echo "\n5. 手动修复主批次状态:\n";
            
            $total_success = array_sum(array_column($sub_batches, 'success_count'));
            $total_failed = array_sum(array_column($sub_batches, 'failed_count'));
            
            $update_data = [
                'status' => $expected_status,
                'success_count' => $total_success,
                'failed_count' => $total_failed,
                'progress_current' => $total_success + $total_failed,
                'updated_at' => current_time('mysql')
            ];
            
            if ($expected_status === 'COMPLETED' || $expected_status === 'ERROR') {
                $update_data['completed_at'] = current_time('mysql');
            }
            
            $result = $wpdb->update(
                $batch_feeds_table,
                $update_data,
                ['batch_id' => $master_batch_id]
            );
            
            if ($result !== false) {
                echo "  ✅ 主批次状态已更新为: $expected_status\n";
                echo "  成功数量: $total_success\n";
                echo "  失败数量: $total_failed\n";
            } else {
                echo "  ❌ 更新失败\n";
            }
        } else {
            echo "\n5. 修复建议:\n";
            echo "  💡 添加 ?fix=1 参数来手动修复主批次状态\n";
        }
    } else {
        echo "  ✅ 状态匹配，无需修复\n";
    }
}

echo "\n=== 诊断完成 ===\n";
?>
