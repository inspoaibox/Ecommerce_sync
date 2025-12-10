<?php
/**
 * 测试Feed表补充修复效果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试Feed表补充修复效果 ===\n\n";

function test_with_feed_supplement($batch_id, $expected_failed, $batch_name) {
    $_POST['nonce'] = wp_create_nonce('batch_details_nonce');
    $_POST['batch_id'] = $batch_id;
    $_POST['type'] = 'failed';
    
    echo "测试 {$batch_name}:\n";
    echo "批次ID: " . substr($batch_id, -12) . "\n";
    echo "期望失败数: {$expected_failed}\n";
    
    ob_start();
    handle_get_batch_details();
    $output = ob_get_clean();
    
    // 提取JSON
    $json_start = strpos($output, '{"success"');
    if ($json_start === false) {
        echo "❌ 没有找到JSON响应\n";
        return 0;
    }
    
    $json_output = substr($output, $json_start);
    
    // 简单的JSON提取
    $brace_count = 0;
    $json_end = 0;
    for ($i = 0; $i < strlen($json_output); $i++) {
        if ($json_output[$i] === '{') $brace_count++;
        elseif ($json_output[$i] === '}') {
            $brace_count--;
            if ($brace_count === 0) {
                $json_end = $i + 1;
                break;
            }
        }
    }
    
    $clean_json = $json_end > 0 ? substr($json_output, 0, $json_end) : $json_output;
    $response = json_decode($clean_json, true);
    
    if (!$response || !$response['success']) {
        echo "❌ JSON解析失败或API返回失败\n";
        return 0;
    }
    
    $actual_count = $response['data']['count'];
    $data_source = $response['data']['debug_info']['data_source'] ?? 'unknown';
    $coverage = round(($actual_count / $expected_failed) * 100, 1);
    
    echo "实际获取数: {$actual_count}\n";
    echo "数据来源: {$data_source}\n";
    echo "数据覆盖率: {$coverage}%\n";
    
    // 评估修复效果
    if ($coverage >= 90) {
        echo "修复效果: ✅ 优秀\n";
    } elseif ($coverage >= 80) {
        echo "修复效果: ✅ 良好\n";
    } elseif ($coverage >= 60) {
        echo "修复效果: ⚠️ 一般\n";
    } else {
        echo "修复效果: ❌ 仍需改进\n";
    }
    
    // 显示前5个SKU
    $items = $response['data']['items'] ?? [];
    if (!empty($items)) {
        echo "前5个失败SKU:\n";
        for ($i = 0; $i < min(5, count($items)); $i++) {
            $sku = $items[$i]['sku'] ?? 'UNKNOWN';
            $error = isset($items[$i]['error_message']) ? 
                ' - ' . substr($items[$i]['error_message'], 0, 30) . '...' : '';
            echo "  " . ($i+1) . ". {$sku}{$error}\n";
        }
    }
    
    return $actual_count;
}

// 测试关键批次
echo "修复前获取数量: 25个 (覆盖率32.9%)\n";
echo "修复目标: 接近76个 (覆盖率>80%)\n\n";

$result = test_with_feed_supplement('BATCH_20250824081352_6177', 76, '批次1');

echo "\n" . str_repeat("=", 60) . "\n";
echo "修复效果对比:\n";
echo "修复前: 25个失败商品 (32.9%覆盖率)\n";
echo "修复后: {$result}个失败商品 (" . round(($result/76)*100, 1) . "%覆盖率)\n";

$improvement = $result - 25;
if ($improvement > 0) {
    echo "✅ 改进效果: 增加了 {$improvement} 个失败商品\n";
    
    if ($result >= 76 * 0.9) {
        echo "🎉 修复成功！数据覆盖率达到优秀水平\n";
    } elseif ($result >= 76 * 0.8) {
        echo "✅ 修复有效！数据覆盖率达到良好水平\n";
    } elseif ($result >= 76 * 0.6) {
        echo "⚠️ 修复部分有效，有一定改进\n";
    } else {
        echo "❌ 修复效果有限，需要进一步优化\n";
    }
} else {
    echo "❌ 修复无效，没有改进\n";
}

echo "\n基于真实测试结果的结论:\n";
if ($result >= 60) {
    echo "Feed表补充策略有效，能够显著提高失败商品数据的完整性\n";
} else {
    echo "Feed表补充策略效果有限，需要探索其他数据源或方法\n";
}

?>
