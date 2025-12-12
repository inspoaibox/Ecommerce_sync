<?php
// 加载WordPress
require_once('d:/phpstudy_pro/WWW/test.localhost/wp-config.php');

global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

echo "=== 直接修复数据库中的必填级别数据 ===\n";

// 1. 读取当前数据
$current = $wpdb->get_row("SELECT * FROM $map_table WHERE wc_category_id = 15");
if (!$current) {
    echo "未找到分类ID 15的数据\n";
    exit;
}

$decoded = json_decode($current->walmart_attributes, true);
if (!$decoded) {
    echo "JSON解码失败\n";
    exit;
}

echo "当前属性数量: " . count($decoded['name'] ?? []) . "\n";
echo "当前必填级别数量: " . count($decoded['required_level'] ?? []) . "\n";

// 2. 创建正确的必填级别数据（基于V5.0规范）
$v5_required_levels = [
    'productName' => 'sell',
    'brand' => 'sell', 
    'keyFeatures' => 'visible',
    'mainImageUrl' => 'visible',
    'sku' => 'system',
    'price' => 'system',
    'shortDescription' => 'recommended'
];

// 3. 修复必填级别数组
$fixed_required_levels = [];
foreach ($decoded['name'] as $index => $name) {
    if (isset($v5_required_levels[$name])) {
        $fixed_required_levels[] = $v5_required_levels[$name];
    } else {
        $fixed_required_levels[] = ''; // 其他属性保持空
    }
}

// 4. 更新数据
$decoded['required_level'] = $fixed_required_levels;

echo "\n=== 修复后的数据 ===\n";
echo "修复后必填级别数量: " . count($decoded['required_level']) . "\n";

// 显示前10个修复后的必填级别
echo "\n=== 前10个修复后的必填级别 ===\n";
for ($i = 0; $i < min(10, count($decoded['name'])); $i++) {
    $name = $decoded['name'][$i];
    $required = $decoded['required_level'][$i];
    echo "[$i] $name => '$required'\n";
}

// 5. 保存到数据库
$json_fixed = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
$result = $wpdb->update(
    $map_table,
    ['walmart_attributes' => $json_fixed],
    ['wc_category_id' => 15]
);

echo "\n=== 保存结果 ===\n";
echo "更新结果: " . ($result !== false ? "成功" : "失败") . "\n";

// 6. 验证修复结果
$verify = $wpdb->get_row("SELECT * FROM $map_table WHERE wc_category_id = 15");
$verify_decoded = json_decode($verify->walmart_attributes, true);

echo "\n=== 验证修复结果 ===\n";
echo "验证 - 必填级别数量: " . count($verify_decoded['required_level'] ?? []) . "\n";
echo "验证 - 前3个必填级别: " . implode(', ', array_slice($verify_decoded['required_level'] ?? [], 0, 3)) . "\n";

// 显示有必填级别的属性
echo "\n=== 有必填级别的属性 ===\n";
foreach ($verify_decoded['name'] as $index => $name) {
    $required = $verify_decoded['required_level'][$index] ?? '';
    if (!empty($required)) {
        echo "$name => $required\n";
    }
}

echo "\n修复完成！现在刷新页面应该能看到必填级别标识了。\n";
?>
