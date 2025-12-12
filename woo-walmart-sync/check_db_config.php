<?php
/**
 * 直接查看数据库中的占位符配置
 */

// 加载WordPress环境
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php', 
    __DIR__ . '/../../../../../wp-load.php'
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
    die('无法找到WordPress。');
}

echo "=== 检查数据库中的占位符配置 ===\n";

global $wpdb;

// 直接查询数据库
$placeholder_1 = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'woo_walmart_placeholder_image_1'");
$placeholder_2 = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'woo_walmart_placeholder_image_2'");

echo "数据库中的占位符1: " . ($placeholder_1 ?: '未设置') . "\n";
echo "数据库中的占位符2: " . ($placeholder_2 ?: '未设置') . "\n";

// 使用WordPress函数
$wp_placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$wp_placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "WordPress函数获取的占位符1: " . ($wp_placeholder_1 ?: '未设置') . "\n";
echo "WordPress函数获取的占位符2: " . ($wp_placeholder_2 ?: '未设置') . "\n";

echo "\n=== 分析问题 ===\n";

if (empty($wp_placeholder_1)) {
    echo "❌ 占位符1未配置！这就是问题所在！\n";
    echo "当图片数量为4张时，因为占位符1为空，所以不会添加占位符\n";
    echo "结果：最终还是4张图片，不满足Walmart的5张要求\n";
} else {
    if (filter_var($wp_placeholder_1, FILTER_VALIDATE_URL)) {
        echo "✅ 占位符1配置正确\n";
    } else {
        echo "❌ 占位符1 URL格式无效\n";
    }
}

echo "\n=== 解决方案 ===\n";
echo "需要配置有效的占位符图片URL\n";
echo "建议使用Walmart官方的占位符图片\n";

?>
