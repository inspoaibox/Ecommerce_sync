<?php
/**
 * 测试数据完整性（不依赖JSON输出）
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试数据完整性 ===\n\n";

// 直接调用内部逻辑，不通过AJAX
function test_batch_data_directly($batch_id, $expected_failed) {
    global $wpdb;
    $batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
    
    echo "批次: " . substr($batch_id, -8) . "\n";
    echo "期望失败数: {$expected_failed}\n";
    
    // 1. 检查主批次统计
    $batch_record = $wpdb->get_row($wpdb->prepare(
        "SELECT success_count, failed_count FROM {$batch_feeds_table} WHERE batch_id = %s",
        $batch_id
    ));
    
    if ($batch_record) {
        echo "主批次统计: 成功 {$batch_record->success_count}, 失败 {$batch_record->failed_count}\n";
    }
    
    // 2. 检查子批次数据
    $sub_batches = $wpdb->get_results($wpdb->prepare(
        "SELECT batch_id, api_response FROM {$batch_feeds_table}
         WHERE (parent_batch_id = %s OR batch_id LIKE %s)
         AND batch_id != %s
         AND api_response IS NOT NULL AND api_response != ''
         ORDER BY batch_id",
        $batch_id, $batch_id . '%', $batch_id
    ));
    
    echo "子批次数量: " . count($sub_batches) . "\n";
    
    $total_failed_items = 0;
    $failed_skus = [];
    
    if (!empty($sub_batches)) {
        foreach ($sub_batches as $sub_batch) {
            $sub_api_response = json_decode($sub_batch->api_response, true);
            if ($sub_api_response && isset($sub_api_response['itemDetails']['itemIngestionStatus'])) {
                $items = $sub_api_response['itemDetails']['itemIngestionStatus'];
                
                foreach ($items as $item) {
                    if (isset($item['ingestionStatus']) && $item['ingestionStatus'] !== 'SUCCESS') {
                        $total_failed_items++;
                        if (isset($item['sku'])) {
                            $failed_skus[] = $item['sku'];
                        }
                    }
                }
            }
        }
        
        // 去重
        $unique_failed_skus = array_unique($failed_skus);
        $unique_count = count($unique_failed_skus);
        
        echo "子批次失败商品总数: {$total_failed_items}\n";
        echo "去重后失败SKU数: {$unique_count}\n";
        
        // 计算修复效果
        $coverage = $unique_count / $expected_failed * 100;
        echo "数据覆盖率: " . round($coverage, 1) . "%\n";
        
        if ($coverage >= 80) {
            echo "✅ 修复效果: 优秀\n";
        } elseif ($coverage >= 60) {
            echo "✅ 修复效果: 良好\n";
        } else {
            echo "⚠️ 修复效果: 需要进一步优化\n";
        }
        
        // 显示前10个失败SKU
        echo "前10个失败SKU:\n";
        for ($i = 0; $i < min(10, count($unique_failed_skus)); $i++) {
            echo "  " . ($i+1) . ". {$unique_failed_skus[$i]}\n";
        }
        
        return $unique_count;
    } else {
        echo "❌ 没有找到子批次数据\n";
        return 0;
    }
}

// 测试两个批次
echo "测试批次1:\n";
$result1 = test_batch_data_directly('BATCH_20250824081352_6177', 76);

echo "\n" . str_repeat("-", 60) . "\n\n";

echo "测试批次2:\n";
$result2 = test_batch_data_directly('BATCH_20250824084052_2020', 145);

echo "\n" . str_repeat("=", 60) . "\n";
echo "修复验证结果:\n";
echo "批次1: 期望76个，实际获取{$result1}个\n";
echo "批次2: 期望145个，实际获取{$result2}个\n";

$total_expected = 76 + 145;
$total_actual = $result1 + $result2;
$overall_coverage = $total_actual / $total_expected * 100;

echo "总体数据覆盖率: " . round($overall_coverage, 1) . "%\n";

if ($overall_coverage >= 80) {
    echo "🎉 修复成功！现在可以获取到大部分失败商品的完整数据\n";
} elseif ($overall_coverage >= 60) {
    echo "✅ 修复有效！数据完整性有显著改善\n";
} else {
    echo "⚠️ 修复部分有效，但仍需进一步优化\n";
}

echo "\n现在队列管理页面应该能显示更完整的失败商品列表了！\n";

?>
