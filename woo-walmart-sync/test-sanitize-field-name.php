<?php
/**
 * 测试 sanitize_text_field 是否会过滤字段名称
 */

// 自动检测 WordPress 根目录
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("错误：无法找到 WordPress。\n");
}

echo "=== 测试 sanitize_text_field 对字段名称的影响 ===\n\n";

$test_names = [
    'sofa_and_loveseat_design',
    'sizeDescriptor',
    'sofa_bed_size',
    'seat_material',
    'features',
    'productName',
    'short_description',
    'has_storage',
];

echo "测试字段名称:\n";
foreach ($test_names as $name) {
    $sanitized = sanitize_text_field($name);
    $match = ($name === $sanitized) ? '✅' : '❌';
    echo "{$match} 原始: '{$name}' → 处理后: '{$sanitized}'\n";
    
    if ($name !== $sanitized) {
        echo "   ⚠️ 字段名称被修改了！\n";
    }
}

echo "\n";

// 测试 wp_json_encode
echo "测试 wp_json_encode:\n";
$test_data = [
    'name' => ['sofa_and_loveseat_design', 'sizeDescriptor'],
    'type' => ['auto_generate', 'auto_generate'],
    'source' => ['', '']
];

$json = wp_json_encode($test_data, JSON_UNESCAPED_UNICODE);
echo "JSON 编码结果:\n";
echo $json . "\n\n";

$decoded = json_decode($json, true);
echo "JSON 解码结果:\n";
print_r($decoded);

echo "\n检查完成！\n";
?>

