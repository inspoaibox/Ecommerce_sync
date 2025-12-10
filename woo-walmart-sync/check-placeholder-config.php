<?php
/**
 * 检查占位符图片配置
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查占位符图片配置 ===\n\n";

// 检查占位符配置
$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "占位符图片1: " . ($placeholder_1 ?: '未配置') . "\n";
echo "占位符图片2: " . ($placeholder_2 ?: '未配置') . "\n\n";

// 验证占位符URL
if (!empty($placeholder_1)) {
    $valid_1 = filter_var($placeholder_1, FILTER_VALIDATE_URL);
    echo "占位符1 URL有效性: " . ($valid_1 ? '有效' : '无效') . "\n";
} else {
    echo "❌ 占位符1未配置！\n";
}

if (!empty($placeholder_2)) {
    $valid_2 = filter_var($placeholder_2, FILTER_VALIDATE_URL);
    echo "占位符2 URL有效性: " . ($valid_2 ? '有效' : '无效') . "\n";
} else {
    echo "❌ 占位符2未配置！\n";
}

echo "\n=== 问题分析 ===\n";

if (empty($placeholder_1) && empty($placeholder_2)) {
    echo "🎯 找到问题根源！\n";
    echo "占位符图片都未配置，所以副图补足逻辑无法生效\n";
    echo "这就是为什么SKU B081S00179只有4张副图，无法补足到5张的原因\n\n";
    
    echo "解决方案:\n";
    echo "1. 配置占位符图片URL\n";
    echo "2. 或者修改补足逻辑，使用默认占位符\n";
    echo "3. 或者提醒用户添加更多产品图片\n";
} else {
    echo "占位符配置正常，问题可能在其他地方\n";
}

// 检查WordPress默认占位符
echo "\n=== WordPress默认占位符 ===\n";
$wp_placeholder = wc_placeholder_img_src('full');
echo "WooCommerce占位符: {$wp_placeholder}\n";

if (filter_var($wp_placeholder, FILTER_VALIDATE_URL)) {
    echo "✅ 可以使用WooCommerce占位符作为备用方案\n";
} else {
    echo "❌ WooCommerce占位符也不可用\n";
}

// 模拟修复方案
echo "\n=== 修复方案测试 ===\n";

if (empty($placeholder_1)) {
    echo "如果使用WooCommerce占位符作为占位符1:\n";
    echo "占位符1: {$wp_placeholder}\n";
    
    // 模拟4张副图的补足
    $gallery_images = [
        'https://b2bfiles1.gigab2b.cn/image/wkseller/24736/20240206/opkxnt7mw8ibmnqndvaj.jpg',
        'https://b2bfiles1.gigab2b.cn/image/wkseller/24736/20240206/pt63zl5foows1fzubkrp.jpg',
        'https://b2bfiles1.gigab2b.cn/image/wkseller/24736/20240206/aau9htobbjsdlpdkkl38.jpg',
        'https://b2bfiles1.gigab2b.cn/image/wkseller/24736/20240206/b0mjt6g1qtfpseuew6si.jpg'
    ];
    
    echo "原始副图数量: " . count($gallery_images) . "\n";
    
    // 添加占位符
    $gallery_images[] = $wp_placeholder;
    
    echo "补足后副图数量: " . count($gallery_images) . "\n";
    echo "是否满足Walmart要求: " . (count($gallery_images) >= 5 ? '是' : '否') . "\n";
}

echo "\n=== 检查完成 ===\n";

?>
