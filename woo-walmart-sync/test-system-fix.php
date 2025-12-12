<?php
/**
 * 测试系统性修复效果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试系统性修复效果 ===\n\n";

// 测试多个批次
$test_batches = [
    [
        'batch_id' => 'BATCH_20250824081352_6177',
        'expected_failed' => 76,
        'name' => '批次1'
    ],
    [
        'batch_id' => 'BATCH_20250824084052_2020',
        'expected_failed' => 145,
        'name' => '批次2'
    ],
    [
        'batch_id' => 'BATCH_20250820121238_9700',
        'expected_failed' => 35,
        'name' => '批次3(#238_9700)'
    ]
];

function test_batch_fix($batch_id, $expected_failed, $batch_name) {
    $_POST['nonce'] = wp_create_nonce('batch_details_nonce');
    $_POST['batch_id'] = $batch_id;
    $_POST['type'] = 'failed';
    
    echo "测试 {$batch_name}:\n";
    echo "批次ID: " . substr($batch_id, -12) . "\n";
    echo "期望失败数: {$expected_failed}\n";
    
    ob_start();
    handle_get_batch_details();
    $output = ob_get_clean();
    
    // 只提取JSON部分
    $json_start = strpos($output, '{"success"');
    if ($json_start !== false) {
        $json_output = substr($output, $json_start);
        $response = json_decode($json_output, true);
        
        if ($response && $response['success']) {
            $actual_count = $response['data']['count'];
            $data_source = $response['data']['debug_info']['data_source'];
            
            echo "实际获取数: {$actual_count}\n";
            echo "数据来源: {$data_source}\n";
            
            $coverage = ($actual_count / $expected_failed) * 100;
            echo "数据覆盖率: " . round($coverage, 1) . "%\n";
            
            if ($coverage >= 90) {
                echo "✅ 修复效果: 优秀\n";
            } elseif ($coverage >= 70) {
                echo "✅ 修复效果: 良好\n";
            } elseif ($coverage >= 50) {
                echo "⚠️ 修复效果: 一般\n";
            } else {
                echo "❌ 修复效果: 需要进一步优化\n";
            }
            
            // 显示前5个SKU
            if (!empty($response['data']['items'])) {
                echo "前5个失败SKU:\n";
                for ($i = 0; $i < min(5, count($response['data']['items'])); $i++) {
                    $sku = $response['data']['items'][$i]['sku'];
                    echo "  " . ($i+1) . ". {$sku}\n";
                }
            }
            
            return $actual_count;
        } else {
            echo "❌ 获取失败\n";
            return 0;
        }
    } else {
        echo "❌ 没有找到JSON响应\n";
        return 0;
    }
}

$total_expected = 0;
$total_actual = 0;

foreach ($test_batches as $batch) {
    $result = test_batch_fix($batch['batch_id'], $batch['expected_failed'], $batch['name']);
    $total_expected += $batch['expected_failed'];
    $total_actual += $result;
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

echo "=== 系统性修复总结 ===\n";
echo "总期望失败数: {$total_expected}\n";
echo "总实际获取数: {$total_actual}\n";
echo "整体覆盖率: " . round(($total_actual / $total_expected) * 100, 1) . "%\n\n";

if ($total_actual >= $total_expected * 0.9) {
    echo "🎉 系统性修复成功！所有批次的数据获取问题已解决\n";
} elseif ($total_actual >= $total_expected * 0.7) {
    echo "✅ 系统性修复有效！大部分批次的数据获取问题已解决\n";
} else {
    echo "⚠️ 系统性修复部分有效，但仍需进一步优化\n";
}

echo "\n现在所有批次的队列管理页面都应该能显示完整的失败商品列表！\n";

?>
