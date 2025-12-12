<?php
/**
 * 测试修复后的过滤逻辑
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试修复后的过滤逻辑 ===\n\n";

function test_filtered_results($batch_id, $expected_failed, $batch_name) {
    $_POST['nonce'] = wp_create_nonce('batch_details_nonce');
    $_POST['batch_id'] = $batch_id;
    $_POST['type'] = 'failed';
    
    echo "测试 {$batch_name}:\n";
    echo "期望失败数: {$expected_failed}\n";
    
    ob_start();
    handle_get_batch_details();
    $output = ob_get_clean();
    
    // 提取JSON
    $json_start = strpos($output, '{"success"');
    if ($json_start === false) {
        echo "❌ 没有找到JSON响应\n";
        return;
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
        return;
    }
    
    $actual_count = $response['data']['count'];
    $items = $response['data']['items'] ?? [];
    
    echo "实际获取数: {$actual_count}\n";
    echo "数据覆盖率: " . round(($actual_count / $expected_failed) * 100, 1) . "%\n";
    
    // 检查是否包含B011P370420
    $contains_target_sku = false;
    $target_sku = 'B011P370420';
    
    foreach ($items as $item) {
        if ($item['sku'] === $target_sku) {
            $contains_target_sku = true;
            echo "⚠️ 仍然包含 {$target_sku}: {$item['error_message']}\n";
            break;
        }
    }
    
    if (!$contains_target_sku) {
        echo "✅ 不再包含错误的SKU {$target_sku}\n";
    }
    
    // 检查补充的商品质量
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
    
    // 显示前5个Feed补充的商品
    if ($feed_items > 0) {
        echo "Feed补充商品样本:\n";
        $count = 0;
        foreach ($items as $item) {
            if (strpos($item['error_message'], 'seat_depth') === false && 
                strpos($item['error_message'], 'productSecondaryImageURL') === false &&
                strpos($item['error_message'], 'image you submitted') === false) {
                echo "  " . ($count + 1) . ". {$item['sku']} - {$item['error_message']}\n";
                $count++;
                if ($count >= 5) break;
            }
        }
    }
    
    return $actual_count;
}

echo "修复目标:\n";
echo "1. 不再包含成功的商品（如B011P370420）\n";
echo "2. 保持较高的数据覆盖率\n";
echo "3. 只包含真正失败的商品\n\n";

$result = test_filtered_results('BATCH_20250824081352_6177', 76, '批次1');

echo "\n" . str_repeat("=", 60) . "\n";
echo "修复效果评估:\n";

if ($result) {
    if ($result >= 60) {
        echo "✅ 数据覆盖率仍然良好: {$result}个失败商品\n";
    } else {
        echo "⚠️ 数据覆盖率下降: {$result}个失败商品\n";
    }
    
    echo "修复成功的标志:\n";
    echo "1. 不再包含B011P370420等成功商品\n";
    echo "2. Feed补充的商品都是真正的失败商品\n";
    echo "3. 错误信息更准确\n";
} else {
    echo "❌ 测试失败\n";
}

?>
