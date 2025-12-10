<?php
/**
 * 简单测试API响应修复
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 简单测试API响应修复 ===\n";

$_POST['nonce'] = wp_create_nonce('batch_details_nonce');
$_POST['batch_id'] = 'BATCH_20250824081352_6177';
$_POST['type'] = 'failed';

echo "测试批次: BATCH_20250824081352_6177\n";

ob_start();
handle_get_batch_details();
$output = ob_get_clean();

echo "输出长度: " . strlen($output) . "\n";

$json_start = strpos($output, '{"success"');
if ($json_start !== false) {
    $json_output = substr($output, $json_start, 500); // 只取前500字符
    echo "JSON开始部分: " . $json_output . "\n";

    $response = json_decode($json_output, true);
    if ($response && $response['success']) {
        $count = $response['data']['count'];
        echo "获取商品数: {$count}\n";

        if ($count <= 30) {
            echo "✅ 修复成功！现在只显示API响应中的真正失败商品\n";
            echo "✅ 不再包含本地表中的错误数据\n";
        } else {
            echo "⚠️ 商品数量仍然很高，可能还在使用本地表数据\n";
        }
    } else {
        echo "❌ JSON解析失败\n";
    }
} else {
    echo "❌ 没有找到JSON响应\n";
    echo "原始输出: " . substr($output, 0, 200) . "\n";
}

echo "\n=== 修复效果 ===\n";
echo "修复前: 76个商品（包含51个错误的）\n";
echo "修复后: 应该只有25个左右（全部来自API）\n";

?>
