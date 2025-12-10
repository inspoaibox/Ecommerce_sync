<?php
/**
 * 检查新批次 #238_9700 的数据完整性
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查批次 #238_9700 的数据完整性 ===\n\n";

global $wpdb;
$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';

// 1. 查找这个批次的实际ID
echo "1. 查找批次的实际ID:\n";

// 直接使用找到的批次ID
$actual_batch_id = 'BATCH_20250820121238_9700';

$batch_record = $wpdb->get_row($wpdb->prepare(
    "SELECT batch_id, status, product_count, success_count, failed_count FROM {$batch_feeds_table} WHERE batch_id = %s",
    $actual_batch_id
));

if ($batch_record) {
    echo "✅ 找到批次: {$batch_record->batch_id}\n";
    echo "  状态: {$batch_record->status}\n";
    echo "  产品数量: {$batch_record->product_count}\n";
    echo "  成功: {$batch_record->success_count}\n";
    echo "  失败: {$batch_record->failed_count}\n";
} else {
    echo "❌ 没有找到批次记录\n";
    exit;
}



echo "\n使用批次ID: {$actual_batch_id}\n\n";

// 2. 检查子批次情况
echo "2. 检查子批次情况:\n";

$sub_batches = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, success_count, failed_count, 
            CASE WHEN api_response IS NOT NULL AND api_response != '' THEN 'YES' ELSE 'NO' END as has_api_response
     FROM {$batch_feeds_table}
     WHERE (parent_batch_id = %s OR batch_id LIKE %s)
     AND batch_id != %s
     ORDER BY batch_id",
    $actual_batch_id, $actual_batch_id . '%', $actual_batch_id
));

if (!empty($sub_batches)) {
    echo "找到 " . count($sub_batches) . " 个子批次:\n";
    $total_sub_success = 0;
    $total_sub_failed = 0;
    
    foreach ($sub_batches as $sub_batch) {
        echo "  {$sub_batch->batch_id}: 成功{$sub_batch->success_count}, 失败{$sub_batch->failed_count}, API响应:{$sub_batch->has_api_response}\n";
        $total_sub_success += $sub_batch->success_count;
        $total_sub_failed += $sub_batch->failed_count;
    }
    
    echo "子批次汇总: 成功{$total_sub_success}, 失败{$total_sub_failed}\n";
} else {
    echo "❌ 没有找到子批次\n";
}

// 3. 从API响应中提取实际失败商品
echo "\n3. 从API响应中提取实际失败商品:\n";

$sub_batches_with_api = $wpdb->get_results($wpdb->prepare(
    "SELECT batch_id, api_response
     FROM {$batch_feeds_table}
     WHERE (parent_batch_id = %s OR batch_id LIKE %s)
     AND batch_id != %s
     AND api_response IS NOT NULL AND api_response != ''
     ORDER BY batch_id",
    $actual_batch_id, $actual_batch_id . '%', $actual_batch_id
));

$all_failed_skus = [];
$total_api_failed = 0;

if (!empty($sub_batches_with_api)) {
    foreach ($sub_batches_with_api as $sub_batch) {
        $api_response = json_decode($sub_batch->api_response, true);
        if ($api_response && isset($api_response['itemDetails']['itemIngestionStatus'])) {
            $items = $api_response['itemDetails']['itemIngestionStatus'];
            $sub_failed_count = 0;
            
            foreach ($items as $item) {
                if (isset($item['ingestionStatus']) && $item['ingestionStatus'] !== 'SUCCESS') {
                    $sub_failed_count++;
                    $total_api_failed++;
                    
                    if (isset($item['sku'])) {
                        $sku = $item['sku'];
                        $error_msg = '';
                        
                        if (isset($item['ingestionErrors']) && is_array($item['ingestionErrors'])) {
                            $errors = [];
                            foreach ($item['ingestionErrors'] as $error) {
                                if (isset($error['description'])) {
                                    $errors[] = $error['description'];
                                }
                            }
                            $error_msg = implode('; ', $errors);
                        }
                        
                        $all_failed_skus[] = [
                            'sku' => $sku,
                            'error_message' => $error_msg,
                            'batch' => $sub_batch->batch_id
                        ];
                    }
                }
            }
            
            echo "  {$sub_batch->batch_id}: API中失败商品 {$sub_failed_count} 个\n";
        }
    }
    
    echo "API响应中失败商品总数: {$total_api_failed}\n";
} else {
    echo "❌ 没有找到有API响应的子批次\n";
}

// 4. 去重并显示完整列表
echo "\n4. 完整的失败商品列表:\n";

// 去重
$unique_failed_skus = [];
$seen_skus = [];

foreach ($all_failed_skus as $item) {
    if (!in_array($item['sku'], $seen_skus)) {
        $unique_failed_skus[] = $item;
        $seen_skus[] = $item['sku'];
    }
}

echo "去重后失败SKU数量: " . count($unique_failed_skus) . "\n";
echo "期望失败数量: 35\n";
echo "数据覆盖率: " . round(count($unique_failed_skus) / 35 * 100, 1) . "%\n\n";

if (count($unique_failed_skus) >= 35 * 0.8) {
    echo "✅ 数据完整性良好\n";
} else {
    echo "⚠️ 数据不完整，缺失 " . (35 - count($unique_failed_skus)) . " 个失败商品\n";
}

echo "\n=== 完整失败SKU列表 (可复制) ===\n";
foreach ($unique_failed_skus as $i => $item) {
    echo ($i + 1) . ". {$item['sku']}\n";
}
echo "=== 列表结束 ===\n";

// 5. 测试修复后的AJAX函数
echo "\n5. 测试修复后的AJAX函数:\n";

$_POST['nonce'] = wp_create_nonce('batch_details_nonce');
$_POST['batch_id'] = $actual_batch_id;
$_POST['type'] = 'failed';

ob_start();
handle_get_batch_details();
$ajax_output = ob_get_clean();

$ajax_response = json_decode($ajax_output, true);
if ($ajax_response && $ajax_response['success']) {
    $ajax_count = $ajax_response['data']['count'];
    echo "AJAX函数返回: {$ajax_count} 个失败商品\n";
    
    if ($ajax_count == count($unique_failed_skus)) {
        echo "✅ AJAX函数与直接查询结果一致\n";
    } else {
        echo "⚠️ AJAX函数与直接查询结果不一致\n";
    }
} else {
    echo "❌ AJAX函数调用失败\n";
}

echo "\n=== 总结 ===\n";
echo "批次 #238_9700 的数据分析完成\n";
echo "期望失败: 35个\n";
echo "实际获取: " . count($unique_failed_skus) . "个\n";
echo "如果数据不完整，说明修复逻辑还需要进一步优化\n";

?>
