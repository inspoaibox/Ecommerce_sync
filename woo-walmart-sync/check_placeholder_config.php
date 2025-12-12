<?php
require_once 'wp-load.php';

echo "=== 检查占位符配置 ===\n";

$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "占位符图片1: " . ($placeholder_1 ?: '未设置') . "\n";
echo "占位符图片2: " . ($placeholder_2 ?: '未设置') . "\n";

if (!empty($placeholder_1)) {
    echo "占位符1 URL验证: " . (filter_var($placeholder_1, FILTER_VALIDATE_URL) ? '有效' : '无效') . "\n";
} else {
    echo "❌ 占位符1: 空值 - 这就是问题所在！\n";
}

if (!empty($placeholder_2)) {
    echo "占位符2 URL验证: " . (filter_var($placeholder_2, FILTER_VALIDATE_URL) ? '有效' : '无效') . "\n";
} else {
    echo "❌ 占位符2: 空值\n";
}

echo "\n=== 模拟图片补全逻辑 ===\n";

$original_count = 4;
echo "原始图片数量: $original_count\n";

if ($original_count == 4) {
    echo "进入4张图片补全逻辑\n";
    
    if (!empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
        echo "✅ 占位符1有效，会添加到图片列表\n";
        echo "最终图片数量: 5\n";
    } else {
        echo "❌ 占位符1无效，不会添加\n";
        echo "最终图片数量: 4 (这就是为什么Walmart报错的原因！)\n";
    }
} else {
    echo "不会进入4张图片补全逻辑\n";
}
?>
