<?php
/**
 * 分析缺失数据的原因
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

// 添加错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 分析缺失数据的原因 ===\n\n";

function analyze_batch_data($batch_id, $expected_failed) {
    global $wpdb;
    $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
    
    echo "分析批次: " . substr($batch_id, -8) . "\n";
    echo "期望失败数: {$expected_failed}\n\n";
    
    // 1. 查找所有相关的批次记录（包括可能的命名变体）
    $all_related_batches = $wpdb->get_results($wpdb->prepare(
        "SELECT batch_id, parent_batch_id, success_count, failed_count, 
                CASE WHEN api_response IS NOT NULL AND api_response != '' THEN 'YES' ELSE 'NO' END as has_api_response
         FROM {$batch_feeds_table}
         WHERE batch_id = %s 
         OR parent_batch_id = %s 
         OR batch_id LIKE %s
         ORDER BY batch_id",
        $batch_id, $batch_id, $batch_id . '%'
    ));
    
    echo "找到相关批次记录:\n";
    $total_found_failed = 0;
    foreach ($all_related_batches as $batch) {
        echo "  {$batch->batch_id}: 成功{$batch->success_count}, 失败{$batch->failed_count}, API响应:{$batch->has_api_response}\n";
        $total_found_failed += $batch->failed_count;
    }
    echo "所有相关批次失败数总和: {$total_found_failed}\n\n";
    
    // 2. 检查是否有其他命名模式的批次
    $batch_date = substr($batch_id, 6, 8); // 提取日期部分
    $similar_batches = $wpdb->get_results($wpdb->prepare(
        "SELECT batch_id, success_count, failed_count
         FROM {$batch_feeds_table}
         WHERE batch_id LIKE %s
         AND batch_id != %s
         ORDER BY batch_id",
        '%' . $batch_date . '%', $batch_id
    ));
    
    if (!empty($similar_batches)) {
        echo "同日期的其他批次:\n";
        foreach ($similar_batches as $batch) {
            echo "  {$batch->batch_id}: 成功{$batch->success_count}, 失败{$batch->failed_count}\n";
        }
        echo "\n";
    }
    
    // 3. 检查子批次的API响应详情
    $sub_batches_with_api = $wpdb->get_results($wpdb->prepare(
        "SELECT batch_id, api_response
         FROM {$batch_feeds_table}
         WHERE (parent_batch_id = %s OR batch_id LIKE %s)
         AND batch_id != %s
         AND api_response IS NOT NULL AND api_response != ''
         ORDER BY batch_id",
        $batch_id, $batch_id . '%', $batch_id
    ));
    
    echo "有API响应的子批次详情:\n";
    $total_api_failed = 0;
    foreach ($sub_batches_with_api as $sub_batch) {
        $api_response = json_decode($sub_batch->api_response, true);
        if ($api_response) {
            $failed_count_in_api = 0;
            if (isset($api_response['itemDetails']['itemIngestionStatus'])) {
                foreach ($api_response['itemDetails']['itemIngestionStatus'] as $item) {
                    if (isset($item['ingestionStatus']) && $item['ingestionStatus'] !== 'SUCCESS') {
                        $failed_count_in_api++;
                    }
                }
            }
            echo "  {$sub_batch->batch_id}: API中失败商品 {$failed_count_in_api} 个\n";
            $total_api_failed += $failed_count_in_api;
        }
    }
    echo "API响应中失败商品总数: {$total_api_failed}\n\n";
    
    // 4. 分析数据缺失的可能原因
    echo "数据缺失分析:\n";
    $missing_count = $expected_failed - $total_api_failed;
    echo "缺失的失败商品数: {$missing_count}\n";
    
    if ($missing_count > 0) {
        echo "可能的原因:\n";
        echo "1. 部分子批次没有API响应数据\n";
        echo "2. 某些失败商品在不同的批次中\n";
        echo "3. 批次拆分逻辑导致数据分散\n";
        echo "4. API响应数据不完整\n";
        
        // 检查没有API响应的子批次
        $sub_batches_no_api = $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id, success_count, failed_count
             FROM {$batch_feeds_table}
             WHERE (parent_batch_id = %s OR batch_id LIKE %s)
             AND batch_id != %s
             AND (api_response IS NULL OR api_response = '')
             ORDER BY batch_id",
            $batch_id, $batch_id . '%', $batch_id
        ));
        
        if (!empty($sub_batches_no_api)) {
            echo "\n没有API响应的子批次:\n";
            $no_api_failed_total = 0;
            foreach ($sub_batches_no_api as $batch) {
                echo "  {$batch->batch_id}: 成功{$batch->success_count}, 失败{$batch->failed_count}\n";
                $no_api_failed_total += $batch->failed_count;
            }
            echo "这些批次的失败数总和: {$no_api_failed_total}\n";
            
            if ($no_api_failed_total > 0) {
                echo "⚠️ 发现问题：有 {$no_api_failed_total} 个失败商品在没有API响应的子批次中\n";
            }
        }
    }
    
    return $total_api_failed;
}

// 分析两个批次
echo "分析批次1:\n";
$result1 = analyze_batch_data('BATCH_20250824081352_6177', 76);

echo "\n" . str_repeat("=", 80) . "\n\n";

echo "分析批次2:\n";
$result2 = analyze_batch_data('BATCH_20250824084052_2020', 145);

echo "\n" . str_repeat("=", 80) . "\n";
echo "分析总结:\n";
echo "批次1: 从API响应中找到 {$result1} 个失败商品\n";
echo "批次2: 从API响应中找到 {$result2} 个失败商品\n";
echo "\n如果发现有子批次没有API响应但有失败统计，\n";
echo "需要进一步修复数据获取逻辑来处理这种情况。\n";

?>
