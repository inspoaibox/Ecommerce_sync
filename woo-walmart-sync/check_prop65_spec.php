<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

$spec_service = new Walmart_Spec_Service();
$spec = $spec_service->get_field_spec('Luggage & Luggage Sets', 'isProp65WarningRequired');

echo "isProp65WarningRequired规范:\n";
echo "type: " . $spec['type'] . "\n";
echo "allowed_values类型: " . gettype($spec['allowed_values']) . "\n";
echo "allowed_values内容: " . var_export($spec['allowed_values'], true) . "\n";

// 测试默认值生成
$default_value = $spec_service->get_default_value_for_field('isProp65WarningRequired', $spec);
echo "默认值: " . var_export($default_value, true) . "\n";
?>
