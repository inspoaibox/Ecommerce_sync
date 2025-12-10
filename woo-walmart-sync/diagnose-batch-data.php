<?php
/**
 * 诊断批次数据的完整性问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 诊断批次数据完整性问题 ===\n\n";

global $wpdb;

// 检查实际找到的批次
$batch_ids = [
    'BATCH_20250824081352_6177', // 成功: 124 | 失败: 76
    'BATCH_20250824084052_2020'  // 成功: 55 | 失败: 145
];

foreach ($batch_ids as $batch_id) {
    echo "--- 批次: {$batch_id} ---\n";
    
    // 1. 检查主批次记录
    $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
    $batch_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$batch_feeds_table} WHERE batch_id = %s",
        $batch_id
    ));
    
    if (!$batch_record) {
        echo "❌ 没有找到批次记录\n\n";
        continue;
    }
    
    echo "✅ 批次基本信息:\n";
    echo "  状态: {$batch_record->status}\n";
    echo "  产品数量: {$batch_record->product_count}\n";
    echo "  成功数量: {$batch_record->success_count}\n";
    echo "  失败数量: {$batch_record->failed_count}\n";
    echo "  批次类型: " . ($batch_record->batch_type ?: 'NULL') . "\n";
    echo "  Feed ID: " . ($batch_record->feed_id ?: 'NULL') . "\n";
    
    // 2. 检查batch_items表中的数据
    $batch_items_table = $wpdb->prefix . 'walmart_batch_items';
    $items_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$batch_items_table} WHERE batch_id = %s",
        $batch_id
    ));
    
    echo "\n📊 batch_items表数据:\n";
    echo "  总记录数: {$items_count}\n";
    
    if ($items_count > 0) {
        $status_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM {$batch_items_table} WHERE batch_id = %s GROUP BY status",
            $batch_id
        ));
        
        foreach ($status_counts as $status) {
            echo "  {$status->status}: {$status->count} 个\n";
        }
        
        // 获取失败商品的SKU
        $failed_skus = $wpdb->get_col($wpdb->prepare(
            "SELECT sku FROM {$batch_items_table} WHERE batch_id = %s AND status = 'failed'",
            $batch_id
        ));
        
        echo "  失败SKU数量: " . count($failed_skus) . "\n";
        if (count($failed_skus) <= 10) {
            echo "  失败SKU: " . implode(', ', $failed_skus) . "\n";
        } else {
            echo "  前10个失败SKU: " . implode(', ', array_slice($failed_skus, 0, 10)) . "...\n";
        }
    } else {
        echo "  ❌ batch_items表中没有数据\n";
    }
    
    // 3. 检查API响应数据
    echo "\n📡 API响应数据:\n";
    if (!empty($batch_record->api_response)) {
        $api_response = json_decode($batch_record->api_response, true);
        if ($api_response) {
            echo "  ✅ API响应存在且可解析\n";
            
            // 检查itemDetails结构
            if (isset($api_response['itemDetails']['itemIngestionStatus'])) {
                $items = $api_response['itemDetails']['itemIngestionStatus'];
                echo "  API中的商品数量: " . count($items) . "\n";
                
                $success_items = 0;
                $failed_items = 0;
                $failed_skus_from_api = [];
                
                foreach ($items as $item) {
                    if (isset($item['ingestionStatus'])) {
                        if ($item['ingestionStatus'] === 'SUCCESS') {
                            $success_items++;
                        } else {
                            $failed_items++;
                            if (isset($item['sku'])) {
                                $failed_skus_from_api[] = $item['sku'];
                            }
                        }
                    }
                }
                
                echo "  API中成功商品: {$success_items} 个\n";
                echo "  API中失败商品: {$failed_items} 个\n";
                echo "  API失败SKU数量: " . count($failed_skus_from_api) . "\n";
                
                if (count($failed_skus_from_api) <= 10) {
                    echo "  API失败SKU: " . implode(', ', $failed_skus_from_api) . "\n";
                } else {
                    echo "  API前10个失败SKU: " . implode(', ', array_slice($failed_skus_from_api, 0, 10)) . "...\n";
                }
                
                // 对比统计数据
                echo "\n🔍 数据对比:\n";
                echo "  批次记录失败数: {$batch_record->failed_count}\n";
                echo "  API解析失败数: {$failed_items}\n";
                echo "  batch_items失败数: " . (isset($failed_skus) ? count($failed_skus) : 0) . "\n";
                
                if ($batch_record->failed_count != $failed_items) {
                    echo "  ⚠️ 批次记录与API解析数量不匹配\n";
                }
                
            } else {
                echo "  ❌ API响应中没有itemDetails.itemIngestionStatus\n";
                echo "  API响应结构: " . implode(', ', array_keys($api_response)) . "\n";
            }
        } else {
            echo "  ❌ API响应无法解析为JSON\n";
        }
    } else {
        echo "  ❌ 没有API响应数据\n";
    }
    
    // 4. 检查子批次
    echo "\n🔗 子批次检查:\n";
    $sub_batches = $wpdb->get_results($wpdb->prepare(
        "SELECT batch_id, status, success_count, failed_count, api_response FROM {$batch_feeds_table}
         WHERE parent_batch_id = %s OR batch_id LIKE %s
         ORDER BY batch_id",
        $batch_id,
        $batch_id . '%'
    ));
    
    if (!empty($sub_batches)) {
        echo "  找到 " . count($sub_batches) . " 个子批次:\n";
        
        $total_sub_success = 0;
        $total_sub_failed = 0;
        $all_sub_failed_skus = [];
        
        foreach ($sub_batches as $sub_batch) {
            echo "    子批次 {$sub_batch->batch_id}: 成功 {$sub_batch->success_count}, 失败 {$sub_batch->failed_count}\n";
            
            $total_sub_success += $sub_batch->success_count;
            $total_sub_failed += $sub_batch->failed_count;
            
            // 从子批次API响应中提取失败SKU
            if (!empty($sub_batch->api_response)) {
                $sub_api_response = json_decode($sub_batch->api_response, true);
                if ($sub_api_response && isset($sub_api_response['itemDetails']['itemIngestionStatus'])) {
                    foreach ($sub_api_response['itemDetails']['itemIngestionStatus'] as $item) {
                        if (isset($item['ingestionStatus']) && $item['ingestionStatus'] !== 'SUCCESS' && isset($item['sku'])) {
                            $all_sub_failed_skus[] = $item['sku'];
                        }
                    }
                }
            }
        }
        
        echo "  子批次汇总: 成功 {$total_sub_success}, 失败 {$total_sub_failed}\n";
        echo "  子批次失败SKU数量: " . count($all_sub_failed_skus) . "\n";
        
        if (count($all_sub_failed_skus) <= 10) {
            echo "  子批次失败SKU: " . implode(', ', $all_sub_failed_skus) . "\n";
        } else {
            echo "  子批次前10个失败SKU: " . implode(', ', array_slice($all_sub_failed_skus, 0, 10)) . "...\n";
        }
        
        // 检查主批次统计是否与子批次汇总匹配
        if ($batch_record->failed_count != $total_sub_failed) {
            echo "  ⚠️ 主批次失败数({$batch_record->failed_count})与子批次汇总({$total_sub_failed})不匹配\n";
        }
        
    } else {
        echo "  没有找到子批次\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

echo "=== 诊断结论 ===\n";
echo "1. 检查batch_items表是否有完整数据\n";
echo "2. 检查API响应是否完整存储\n";
echo "3. 检查子批次数据是否需要合并\n";
echo "4. 确定真实的失败商品数据来源\n";

?>
