<?php
/**
 * 测试结果写入文件
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

$output_file = 'test-results.txt';
$log = "=== 真实测试结果 ===\n\n";

function test_batch_to_log($batch_id, $expected_failed, $batch_name) {
    $_POST['nonce'] = wp_create_nonce('batch_details_nonce');
    $_POST['batch_id'] = $batch_id;
    $_POST['type'] = 'failed';
    
    ob_start();
    handle_get_batch_details();
    $output = ob_get_clean();
    
    // 查找JSON
    $json_start = strpos($output, '{"success"');
    if ($json_start === false) {
        return "❌ {$batch_name}: 没有找到JSON响应\n";
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
        return "❌ {$batch_name}: JSON解析失败或API返回失败\n";
    }
    
    $actual_count = $response['data']['count'];
    $data_source = $response['data']['debug_info']['data_source'] ?? 'unknown';
    $coverage = round(($actual_count / $expected_failed) * 100, 1);
    
    $result = "✅ {$batch_name}:\n";
    $result .= "  期望: {$expected_failed}, 实际: {$actual_count}, 覆盖率: {$coverage}%\n";
    $result .= "  数据来源: {$data_source}\n";
    
    // 获取前5个SKU
    $items = $response['data']['items'] ?? [];
    if (!empty($items)) {
        $result .= "  前5个SKU: ";
        for ($i = 0; $i < min(5, count($items)); $i++) {
            $result .= ($items[$i]['sku'] ?? 'UNKNOWN') . ', ';
        }
        $result = rtrim($result, ', ') . "\n";
    }
    
    return $result . "\n";
}

// 测试三个批次
$test_cases = [
    ['BATCH_20250824081352_6177', 76, '批次1(#352_6177)'],
    ['BATCH_20250824084052_2020', 145, '批次2(#052_2020)'],
    ['BATCH_20250820121238_9700', 35, '批次3(#238_9700)']
];

foreach ($test_cases as $case) {
    $log .= "测试 {$case[2]}...\n";
    $result = test_batch_to_log($case[0], $case[1], $case[2]);
    $log .= $result;
}

$log .= "=== 测试完成 ===\n";
$log .= "时间: " . date('Y-m-d H:i:s') . "\n";

// 写入文件
file_put_contents($output_file, $log);

echo "测试完成，结果已写入 {$output_file}\n";
echo "请查看文件内容获取真实的测试结果\n";

?>
