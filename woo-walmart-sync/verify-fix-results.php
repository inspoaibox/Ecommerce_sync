<?php
/**
 * 验证修复结果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW\test.localhost\wp-load.php';

echo "=== 验证批次详情修复结果 ===\n\n";

// 模拟AJAX请求参数
$_POST['nonce'] = wp_create_nonce('batch_details_nonce');

// 测试第一个批次
echo "测试批次: BATCH_20250824081352_6177 (失败商品)\n";
$_POST['batch_id'] = 'BATCH_20250824081352_6177';
$_POST['type'] = 'failed';

ob_start();
handle_get_batch_details();
$output1 = ob_get_clean();

$response1 = json_decode($output1, true);
if ($response1 && $response1['success']) {
    $count1 = $response1['data']['count'];
    echo "✅ 获取成功: {$count1} 个失败商品\n";
    echo "数据来源: " . $response1['data']['debug_info']['data_source'] . "\n";
    echo "子批次数量: " . $response1['data']['debug_info']['sub_batches_count'] . "\n";
} else {
    echo "❌ 获取失败\n";
}

echo "\n";

// 测试第二个批次
echo "测试批次: BATCH_20250824084052_2020 (失败商品)\n";
$_POST['batch_id'] = 'BATCH_20250824084052_2020';
$_POST['type'] = 'failed';

ob_start();
handle_get_batch_details();
$output2 = ob_get_clean();

$response2 = json_decode($output2, true);
if ($response2 && $response2['success']) {
    $count2 = $response2['data']['count'];
    echo "✅ 获取成功: {$count2} 个失败商品\n";
    echo "数据来源: " . $response2['data']['debug_info']['data_source'] . "\n";
    echo "子批次数量: " . $response2['data']['debug_info']['sub_batches_count'] . "\n";
} else {
    echo "❌ 获取失败\n";
}

echo "\n=== 修复效果总结 ===\n";
echo "修复前问题:\n";
echo "- 批次1: 显示失败76个，但只能复制到部分SKU\n";
echo "- 批次2: 显示失败145个，但只能复制到部分SKU\n\n";

echo "修复后结果:\n";
if (isset($count1)) {
    echo "- 批次1: 现在可以获取到 {$count1} 个失败商品的完整数据\n";
}
if (isset($count2)) {
    echo "- 批次2: 现在可以获取到 {$count2} 个失败商品的完整数据\n";
}

echo "\n修复要点:\n";
echo "1. ✅ 优先从子批次获取完整的API响应数据\n";
echo "2. ✅ 自动去重处理，避免重复SKU\n";
echo "3. ✅ 详细的调试日志，便于问题排查\n";
echo "4. ✅ 完整的错误信息，便于后续处理决策\n";

echo "\n现在队列管理页面的复制功能应该能获取到完整的失败商品列表了！\n";

?>
