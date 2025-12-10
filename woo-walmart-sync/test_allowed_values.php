<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试允许值解析 ===\n\n";

$allowed_values_string = "Yes|No";

echo "原始字符串: '$allowed_values_string'\n";

// 测试JSON解码
$json_decoded = json_decode($allowed_values_string, true);
echo "JSON解码结果: " . var_export($json_decoded, true) . "\n";

// 测试按|分割
$split_result = explode('|', $allowed_values_string);
echo "按|分割结果: " . var_export($split_result, true) . "\n";

// 检查get_field_spec方法如何处理
if (class_exists('Walmart_Spec_Service')) {
    $spec_service = new Walmart_Spec_Service();
    $spec = $spec_service->get_field_spec('Luggage & Luggage Sets', 'isProp65WarningRequired');
    
    echo "\nget_field_spec返回的allowed_values:\n";
    echo "类型: " . gettype($spec['allowed_values']) . "\n";
    echo "内容: " . var_export($spec['allowed_values'], true) . "\n";
    
    if (is_array($spec['allowed_values'])) {
        echo "第一个值: '{$spec['allowed_values'][0]}'\n";
    }
}

echo "\n=== 完成 ===\n";
?>
