<?php
/**
 * 文件大小验证测试脚本
 * 专门测试远程图片文件大小验证功能
 */

// 加载WordPress环境
if (!defined('ABSPATH')) {
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
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
        die('无法加载WordPress环境');
    }
}

echo "=== 远程图片文件大小验证测试 ===\n\n";

// 加载验证器
require_once plugin_dir_path(__FILE__) . 'includes/class-remote-image-validator.php';
$validator = new WooWalmartSync_Remote_Image_Validator();

// 测试不同大小的图片
$test_cases = [
    [
        'name' => '小文件图片（约100KB）',
        'url' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&h=600&q=50',
        'expected' => 'pass'
    ],
    [
        'name' => '中等文件图片（约500KB）',
        'url' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1200&h=900&q=80',
        'expected' => 'pass'
    ],
    [
        'name' => '大文件图片（约2MB）',
        'url' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=2400&h=1800&q=90',
        'expected' => 'pass'
    ],
    [
        'name' => '超大文件图片（可能超过5MB）',
        'url' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=4000&h=3000&q=100',
        'expected' => 'unknown'
    ],
    [
        'name' => '不存在的图片',
        'url' => 'https://example.com/non-existent-image.jpg',
        'expected' => 'fail'
    ]
];

foreach ($test_cases as $index => $test_case) {
    echo "测试 " . ($index + 1) . ": {$test_case['name']}\n";
    echo "URL: {$test_case['url']}\n";
    
    $start_time = microtime(true);
    $result = $validator->validate_remote_image($test_case['url'], false, true);
    $end_time = microtime(true);
    
    echo "验证时间: " . number_format(($end_time - $start_time) * 1000, 2) . "ms\n";
    echo "缓存状态: " . ($result['cached'] ? '命中' : '未命中') . "\n";
    
    if ($result['valid']) {
        echo "结果: ✅ 通过验证\n";
        if ($result['image_info'] && $result['image_info']['size'] > 0) {
            $size_mb = $result['image_info']['size'] / 1024 / 1024;
            echo "文件大小: " . number_format($size_mb, 2) . "MB\n";
            
            if ($size_mb > 5) {
                echo "⚠️ 警告: 文件大小超过5MB，但验证器显示通过，可能存在问题\n";
            }
        }
    } else {
        echo "结果: ❌ 验证失败\n";
        if (!empty($result['errors'])) {
            echo "错误原因:\n";
            foreach ($result['errors'] as $error) {
                echo "  - " . $error . "\n";
            }
        }
    }
    
    echo "预期结果: {$test_case['expected']}\n";
    echo str_repeat("-", 50) . "\n\n";
}

// 测试缓存效果
echo "=== 缓存效果测试 ===\n";
$cache_test_url = $test_cases[0]['url'];

echo "第一次验证（建立缓存）:\n";
$first_start = microtime(true);
$first_result = $validator->validate_remote_image($cache_test_url, false, true);
$first_end = microtime(true);
echo "时间: " . number_format(($first_end - $first_start) * 1000, 2) . "ms\n";
echo "缓存: " . ($first_result['cached'] ? '命中' : '未命中') . "\n\n";

echo "第二次验证（使用缓存）:\n";
$second_start = microtime(true);
$second_result = $validator->validate_remote_image($cache_test_url, false, true);
$second_end = microtime(true);
echo "时间: " . number_format(($second_end - $second_start) * 1000, 2) . "ms\n";
echo "缓存: " . ($second_result['cached'] ? '命中' : '未命中') . "\n";

if ($second_result['cached']) {
    $speedup = ($first_end - $first_start) / ($second_end - $second_start);
    echo "性能提升: " . number_format($speedup, 2) . "倍\n";
}

echo "\n=== 批量验证测试 ===\n";
$batch_urls = array_column($test_cases, 'url');

$batch_start = microtime(true);
$batch_result = $validator->batch_validate_remote_images($batch_urls, true);
$batch_end = microtime(true);

echo "批量验证结果:\n";
echo "总图片数: {$batch_result['total_images']}\n";
echo "有效图片: {$batch_result['valid_images']}\n";
echo "无效图片: {$batch_result['invalid_images']}\n";
echo "缓存命中: {$batch_result['cached_results']}\n";
echo "总时间: " . number_format(($batch_end - $batch_start) * 1000, 2) . "ms\n";
echo "平均每张: " . number_format($batch_result['validation_time'] * 1000 / $batch_result['total_images'], 2) . "ms\n";

echo "\n=== 测试完成 ===\n";
echo "✅ 文件大小验证功能测试完成！\n";
echo "\n关键特性:\n";
echo "• 只检查文件大小，不下载完整图片\n";
echo "• 超过5MB的图片会被标记为无效\n";
echo "• 无效图片直接删除，不进行替换\n";
echo "• 智能缓存避免重复网络请求\n";
echo "• 支持批量验证提高效率\n";

?>
