<?php
/**
 * 测试占位符图片的有效性
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试占位符图片的有效性 ===\n\n";

// 获取占位符配置
$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "占位符1: {$placeholder_1}\n";
echo "占位符2: {$placeholder_2}\n\n";

// 测试占位符图片
require_once 'includes/class-remote-image-validator.php';
$validator = new WooWalmartSync_Remote_Image_Validator();

if (!empty($placeholder_1)) {
    echo "测试占位符1:\n";
    $result1 = $validator->validate_remote_image($placeholder_1, false, false);
    
    echo "验证结果: " . ($result1['valid'] ? '✅ 有效' : '❌ 无效') . "\n";
    
    if (!$result1['valid']) {
        echo "错误信息:\n";
        foreach ($result1['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }
    
    if (isset($result1['image_info'])) {
        $info = $result1['image_info'];
        echo "图片信息:\n";
        echo "  尺寸: {$info['width']}x{$info['height']}\n";
        echo "  大小: " . round($info['size'] / 1024 / 1024, 2) . "MB\n";
        echo "  格式: {$info['format']}\n";
    }
    echo "\n";
}

if (!empty($placeholder_2)) {
    echo "测试占位符2:\n";
    $result2 = $validator->validate_remote_image($placeholder_2, false, false);
    
    echo "验证结果: " . ($result2['valid'] ? '✅ 有效' : '❌ 无效') . "\n";
    
    if (!$result2['valid']) {
        echo "错误信息:\n";
        foreach ($result2['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }
    
    if (isset($result2['image_info'])) {
        $info = $result2['image_info'];
        echo "图片信息:\n";
        echo "  尺寸: {$info['width']}x{$info['height']}\n";
        echo "  大小: " . round($info['size'] / 1024 / 1024, 2) . "MB\n";
        echo "  格式: {$info['format']}\n";
    }
    echo "\n";
}

echo "=== 问题分析 ===\n";

if (!empty($placeholder_1) && isset($result1) && !$result1['valid']) {
    echo "🚨 占位符1验证失败！这可能是问题的根源\n";
    echo "即使填充了占位符，但占位符本身验证失败，最终还是会被过滤掉\n";
}

if (!empty($placeholder_2) && isset($result2) && !$result2['valid']) {
    echo "🚨 占位符2验证失败！\n";
}

if ((empty($placeholder_1) || (isset($result1) && $result1['valid'])) && 
    (empty($placeholder_2) || (isset($result2) && $result2['valid']))) {
    echo "✅ 占位符图片都是有效的\n";
    echo "问题可能在其他地方:\n";
    echo "1. 填充时机问题（在验证之前还是之后）\n";
    echo "2. 缓存问题\n";
    echo "3. 网络连接问题\n";
    echo "4. Walmart API的特殊要求\n";
}

echo "\n=== 建议的解决方案 ===\n";
echo "1. 确保占位符图片有效且可访问\n";
echo "2. 检查填充逻辑的执行时机\n";
echo "3. 清除图片验证缓存\n";
echo "4. 使用更可靠的占位符图片源\n";

?>
