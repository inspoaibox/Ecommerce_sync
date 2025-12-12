<?php
/**
 * 测试基于批次ID的正确修复
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试基于批次ID的正确修复 ===\n\n";

function test_batch_based_fix($batch_id, $expected_failed, $batch_name) {
    $_POST['nonce'] = wp_create_nonce('batch_details_nonce');
    $_POST['batch_id'] = $batch_id;
    $_POST['type'] = 'failed';
    
    echo "测试 {$batch_name}:\n";
    echo "批次ID: {$batch_id}\n";
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
    $items = $response['data']['items'] ?? [];
    
    echo "实际获取数: {$actual_count}\n";
    echo "数据覆盖率: " . round(($actual_count / $expected_failed) * 100, 1) . "%\n";
    
    // 检查是否还包含不相关的SKU（如B011P370420）
    $suspicious_skus = ['B011P370420', 'B011P370421', 'B011P370422']; // 可能的成功商品
    $found_suspicious = [];
    
    foreach ($items as $item) {
        if (in_array($item['sku'], $suspicious_skus)) {
            $found_suspicious[] = $item['sku'];
        }
    }
    
    if (empty($found_suspicious)) {
        echo "✅ 没有发现可疑的成功商品\n";
    } else {
        echo "⚠️ 发现可疑商品: " . implode(', ', $found_suspicious) . "\n";
    }
    
    // 分析数据来源
    $api_items = 0;
    $feed_items = 0;
    
    foreach ($items as $item) {
        if (strpos($item['error_message'], 'seat_depth') !== false || 
            strpos($item['error_message'], 'productSecondaryImageURL') !== false ||
            strpos($item['error_message'], 'image you submitted') !== false) {
            $api_items++;
        } else {
            $feed_items++;
        }
    }
    
    echo "API响应商品: {$api_items}个\n";
    echo "Feed补充商品: {$feed_items}个\n";
    
    // 显示前3个Feed补充的商品
    if ($feed_items > 0) {
        echo "Feed补充商品样本:\n";
        $count = 0;
        foreach ($items as $item) {
            if (strpos($item['error_message'], 'seat_depth') === false && 
                strpos($item['error_message'], 'productSecondaryImageURL') === false &&
                strpos($item['error_message'], 'image you submitted') === false) {
                echo "  " . ($count + 1) . ". {$item['sku']} - {$item['error_message']}\n";
                $count++;
                if ($count >= 3) break;
            }
        }
    }
    
    return $actual_count;
}

echo "修复原理:\n";
echo "1. 通过批次ID前缀匹配Feed记录\n";
echo "2. 通过batch_items表验证SKU属于该批次\n";
echo "3. 不再使用时间范围匹配\n";
echo "4. 确保只包含真正属于该批次的失败商品\n\n";

$result = test_batch_based_fix('BATCH_20250824081352_6177', 76, '批次1');

echo "\n" . str_repeat("=", 60) . "\n";
echo "修复效果评估:\n";

if ($result) {
    echo "获取到 {$result} 个失败商品\n";
    
    if ($result >= 60) {
        echo "✅ 数据覆盖率良好\n";
    } else {
        echo "⚠️ 数据覆盖率需要改进\n";
    }
    
    echo "\n基于批次ID的修复优势:\n";
    echo "1. 精确匹配：只获取属于该批次的商品\n";
    echo "2. 避免时间误差：不依赖时间范围\n";
    echo "3. 支持多批次：同一SKU在不同批次中的不同状态\n";
    echo "4. 数据准确：确保失败商品确实属于该批次\n";
} else {
    echo "❌ 测试失败\n";
}

?>
