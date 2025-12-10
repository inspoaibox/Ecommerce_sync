<?php
/**
 * 完整的测试验证脚本 - 避免输出截断
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 完整测试验证 ===\n\n";

// 测试函数 - 只返回关键数据，避免大量输出
function test_batch_simple($batch_id, $expected_failed, $batch_name) {
    $_POST['nonce'] = wp_create_nonce('batch_details_nonce');
    $_POST['batch_id'] = $batch_id;
    $_POST['type'] = 'failed';
    
    // 捕获输出但不显示
    ob_start();
    handle_get_batch_details();
    $output = ob_get_clean();
    
    // 查找JSON开始位置
    $json_start = strpos($output, '{"success"');
    if ($json_start === false) {
        return [
            'success' => false,
            'error' => '没有找到JSON响应',
            'raw_output_length' => strlen($output)
        ];
    }
    
    // 提取JSON部分
    $json_output = substr($output, $json_start);
    
    // 查找JSON结束位置（简单方法：找到最后一个}）
    $brace_count = 0;
    $json_end = 0;
    for ($i = 0; $i < strlen($json_output); $i++) {
        if ($json_output[$i] === '{') {
            $brace_count++;
        } elseif ($json_output[$i] === '}') {
            $brace_count--;
            if ($brace_count === 0) {
                $json_end = $i + 1;
                break;
            }
        }
    }
    
    if ($json_end > 0) {
        $clean_json = substr($json_output, 0, $json_end);
    } else {
        $clean_json = $json_output;
    }
    
    // 解析JSON
    $response = json_decode($clean_json, true);
    
    if (!$response) {
        return [
            'success' => false,
            'error' => 'JSON解析失败: ' . json_last_error_msg(),
            'json_sample' => substr($clean_json, 0, 200)
        ];
    }
    
    if (!$response['success']) {
        return [
            'success' => false,
            'error' => 'API返回失败: ' . ($response['data'] ?? '未知错误')
        ];
    }
    
    $data = $response['data'];
    $actual_count = $data['count'];
    $items = $data['items'] ?? [];
    $debug_info = $data['debug_info'] ?? [];
    
    // 提取前10个SKU
    $sample_skus = [];
    for ($i = 0; $i < min(10, count($items)); $i++) {
        $sample_skus[] = $items[$i]['sku'] ?? 'UNKNOWN_SKU';
    }
    
    return [
        'success' => true,
        'batch_name' => $batch_name,
        'expected_count' => $expected_failed,
        'actual_count' => $actual_count,
        'data_source' => $debug_info['data_source'] ?? 'unknown',
        'sub_batches_count' => $debug_info['sub_batches_count'] ?? 0,
        'coverage_percent' => round(($actual_count / $expected_failed) * 100, 1),
        'sample_skus' => $sample_skus,
        'has_error_messages' => !empty($items[0]['error_message'] ?? '')
    ];
}

// 测试三个批次
$test_cases = [
    ['BATCH_20250824081352_6177', 76, '批次1(#352_6177)'],
    ['BATCH_20250824084052_2020', 145, '批次2(#052_2020)'],
    ['BATCH_20250820121238_9700', 35, '批次3(#238_9700)']
];

$results = [];
$total_expected = 0;
$total_actual = 0;

foreach ($test_cases as $case) {
    echo "测试 {$case[2]}...\n";
    $result = test_batch_simple($case[0], $case[1], $case[2]);
    $results[] = $result;
    
    if ($result['success']) {
        $total_expected += $result['expected_count'];
        $total_actual += $result['actual_count'];
        echo "✅ 成功\n";
    } else {
        echo "❌ 失败: {$result['error']}\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "详细测试结果:\n\n";

foreach ($results as $result) {
    if ($result['success']) {
        echo "批次: {$result['batch_name']}\n";
        echo "期望失败数: {$result['expected_count']}\n";
        echo "实际获取数: {$result['actual_count']}\n";
        echo "数据覆盖率: {$result['coverage_percent']}%\n";
        echo "数据来源: {$result['data_source']}\n";
        echo "子批次数: {$result['sub_batches_count']}\n";
        echo "包含错误信息: " . ($result['has_error_messages'] ? '是' : '否') . "\n";
        echo "样本SKU (前5个): " . implode(', ', array_slice($result['sample_skus'], 0, 5)) . "\n";
        
        // 评估修复效果
        if ($result['coverage_percent'] >= 90) {
            echo "修复效果: ✅ 优秀\n";
        } elseif ($result['coverage_percent'] >= 70) {
            echo "修复效果: ✅ 良好\n";
        } elseif ($result['coverage_percent'] >= 50) {
            echo "修复效果: ⚠️ 一般\n";
        } else {
            echo "修复效果: ❌ 差\n";
        }
        
    } else {
        echo "批次: {$result['batch_name'] ?? '未知'}\n";
        echo "测试失败: {$result['error']}\n";
        if (isset($result['json_sample'])) {
            echo "JSON样本: {$result['json_sample']}\n";
        }
    }
    
    echo "\n" . str_repeat("-", 40) . "\n\n";
}

// 总体评估
echo "总体评估:\n";
echo "总期望失败数: {$total_expected}\n";
echo "总实际获取数: {$total_actual}\n";

if ($total_expected > 0) {
    $overall_coverage = round(($total_actual / $total_expected) * 100, 1);
    echo "整体覆盖率: {$overall_coverage}%\n";
    
    if ($overall_coverage >= 90) {
        echo "🎉 系统性修复成功！\n";
    } elseif ($overall_coverage >= 70) {
        echo "✅ 系统性修复有效！\n";
    } elseif ($overall_coverage >= 50) {
        echo "⚠️ 系统性修复部分有效\n";
    } else {
        echo "❌ 系统性修复效果不佳，需要重新分析问题\n";
    }
} else {
    echo "❌ 所有测试都失败了，修复无效\n";
}

echo "\n基于真实测试结果的结论:\n";
if ($total_actual >= $total_expected * 0.8) {
    echo "修复基本成功，队列管理页面现在能获取到大部分失败商品数据\n";
} elseif ($total_actual >= $total_expected * 0.5) {
    echo "修复部分有效，但仍有改进空间\n";
} else {
    echo "修复效果不理想，需要重新分析和修复\n";
}

?>
