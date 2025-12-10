<?php
/**
 * 测试修复后的批次详情获取功能
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试修复后的批次详情获取功能 ===\n\n";

// 模拟AJAX请求参数
$_POST['nonce'] = wp_create_nonce('batch_details_nonce');

// 测试两个批次的失败商品
$test_cases = [
    [
        'batch_id' => 'BATCH_20250824081352_6177',
        'type' => 'failed',
        'expected_count' => 76
    ],
    [
        'batch_id' => 'BATCH_20250824084052_2020', 
        'type' => 'failed',
        'expected_count' => 145
    ]
];

foreach ($test_cases as $test_case) {
    echo "--- 测试批次: {$test_case['batch_id']} ---\n";
    echo "类型: {$test_case['type']}\n";
    echo "期望数量: {$test_case['expected_count']}\n\n";
    
    $_POST['batch_id'] = $test_case['batch_id'];
    $_POST['type'] = $test_case['type'];
    
    // 直接调用函数并捕获JSON响应
    try {
        // 设置输出缓冲
        ob_start();

        // 调用修复后的函数
        handle_get_batch_details();

        // 获取输出
        $output = ob_get_clean();

        // 清理可能的额外输出
        $output = trim($output);

    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ 函数执行异常: " . $e->getMessage() . "\n";
        continue;
    }
    
    // 解析JSON响应
    if (!empty($output)) {
        $response = json_decode($output, true);
        
        if ($response && isset($response['success']) && $response['success']) {
            $data = $response['data'];
            $actual_count = $data['count'];
            $items = $data['items'];
            
            echo "✅ 获取成功!\n";
            echo "实际数量: {$actual_count}\n";
            echo "期望数量: {$test_case['expected_count']}\n";
            
            if ($actual_count == $test_case['expected_count']) {
                echo "🎯 数量完全匹配!\n";
            } else {
                echo "⚠️ 数量不匹配 (差异: " . abs($actual_count - $test_case['expected_count']) . ")\n";
            }
            
            // 显示前10个SKU
            if (!empty($items)) {
                echo "\n前10个失败SKU:\n";
                $display_items = array_slice($items, 0, 10);
                foreach ($display_items as $i => $item) {
                    $error_msg = isset($item['error_message']) ? ' - ' . substr($item['error_message'], 0, 50) . '...' : '';
                    echo "  " . ($i + 1) . ". {$item['sku']}{$error_msg}\n";
                }
                
                if (count($items) > 10) {
                    echo "  ... 还有 " . (count($items) - 10) . " 个\n";
                }
            }
            
            // 显示调试信息
            if (isset($data['debug_info'])) {
                $debug = $data['debug_info'];
                echo "\n调试信息:\n";
                echo "  数据来源: {$debug['data_source']}\n";
                if (isset($debug['sub_batches_count'])) {
                    echo "  子批次数量: {$debug['sub_batches_count']}\n";
                }
            }
            
            // 生成完整的SKU列表用于复制（只显示前50个，避免输出过长）
            echo "\n=== 完整SKU列表 (前50个，可复制) ===\n";
            $display_count = min(50, count($items));
            for ($i = 0; $i < $display_count; $i++) {
                echo $items[$i]['sku'] . "\n";
            }
            if (count($items) > 50) {
                echo "... 还有 " . (count($items) - 50) . " 个SKU\n";
            }
            echo "=== SKU列表结束 ===\n";
            
        } else {
            echo "❌ 获取失败: " . ($response['data'] ?? '未知错误') . "\n";
        }
    } else {
        echo "❌ 没有返回数据\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

echo "=== 测试总结 ===\n";
echo "修复后的批次详情获取功能:\n";
echo "1. 优先从子批次获取完整数据\n";
echo "2. 自动去重处理\n";
echo "3. 详细的调试日志\n";
echo "4. 完整的失败商品列表\n";

?>
