<?php
/**
 * 测试图片验证功能
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试图片验证功能 ===\n\n";

// 测试SKU
$test_sku = 'W1191S00043';

echo "测试SKU: {$test_sku}\n\n";

global $wpdb;

// 1. 查找产品
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $test_sku
));

if (!$product_id) {
    echo "❌ 产品不存在\n";
    exit;
}

echo "✅ 找到产品ID: {$product_id}\n";

$product = wc_get_product($product_id);
echo "产品名称: {$product->get_name()}\n\n";

// 2. 获取产品图片
echo "2. 获取产品图片:\n";

// 主图
$main_image_id = $product->get_image_id();
$main_image_url = '';

if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "主图ID: {$main_image_id}\n";
    echo "主图URL: " . substr($main_image_url, 0, 80) . "...\n";
} else {
    echo "❌ 没有主图\n";
}

// 远程主图
$remote_main_image = get_post_meta($product_id, '_remote_main_image_url', true);
if ($remote_main_image) {
    echo "远程主图: " . substr($remote_main_image, 0, 80) . "...\n";
    $main_image_url = $remote_main_image; // 使用远程主图
}

// 图库图片
$gallery_image_ids = $product->get_gallery_image_ids();
echo "图库图片ID数量: " . count($gallery_image_ids) . "\n";

// 远程图库
$remote_gallery_urls = get_post_meta($product_id, '_remote_gallery_urls', true);
if (is_array($remote_gallery_urls)) {
    echo "远程图库数量: " . count($remote_gallery_urls) . "\n";
} else {
    echo "远程图库: 无\n";
}

// 3. 测试图片验证器
echo "\n3. 测试图片验证器:\n";

require_once 'includes/class-remote-image-validator.php';
$validator = new WooWalmartSync_Remote_Image_Validator();

// 测试主图验证
if (!empty($main_image_url)) {
    echo "\n测试主图验证:\n";
    echo "主图URL: " . substr($main_image_url, 0, 100) . "...\n";
    
    $start_time = microtime(true);
    $validation_result = $validator->validate_remote_image($main_image_url, true, false); // 不使用缓存
    $end_time = microtime(true);
    
    echo "验证耗时: " . round(($end_time - $start_time) * 1000, 2) . "ms\n";
    echo "验证结果: " . ($validation_result['valid'] ? '✅ 通过' : '❌ 失败') . "\n";
    
    if (!$validation_result['valid']) {
        echo "错误信息:\n";
        foreach ($validation_result['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }
    
    if (isset($validation_result['image_info'])) {
        $info = $validation_result['image_info'];
        echo "图片信息:\n";
        echo "  尺寸: {$info['width']}x{$info['height']}\n";
        echo "  格式: {$info['format']}\n";
        echo "  大小: " . round($info['size'] / 1024 / 1024, 2) . "MB\n";
        
        if ($info['size'] > 5 * 1024 * 1024) {
            echo "  ❌ 文件过大！超过5MB限制\n";
        } else {
            echo "  ✅ 文件大小符合要求\n";
        }
    }
}

// 测试副图验证
if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
    echo "\n测试副图验证:\n";
    
    $valid_count = 0;
    $invalid_count = 0;
    
    foreach ($remote_gallery_urls as $index => $gallery_url) {
        echo "\n副图 " . ($index + 1) . ":\n";
        echo "URL: " . substr($gallery_url, 0, 100) . "...\n";
        
        $start_time = microtime(true);
        $validation_result = $validator->validate_remote_image($gallery_url, false, false);
        $end_time = microtime(true);
        
        echo "验证耗时: " . round(($end_time - $start_time) * 1000, 2) . "ms\n";
        echo "验证结果: " . ($validation_result['valid'] ? '✅ 通过' : '❌ 失败') . "\n";
        
        if ($validation_result['valid']) {
            $valid_count++;
        } else {
            $invalid_count++;
            echo "错误信息:\n";
            foreach ($validation_result['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }
        
        if (isset($validation_result['image_info'])) {
            $info = $validation_result['image_info'];
            echo "  大小: " . round($info['size'] / 1024 / 1024, 2) . "MB\n";
        }
        
        // 只测试前3张，避免太多输出
        if ($index >= 2) {
            echo "\n... (省略其余副图测试)\n";
            break;
        }
    }
    
    echo "\n副图验证汇总:\n";
    echo "  有效: {$valid_count}张\n";
    echo "  无效: {$invalid_count}张\n";
}

// 4. 测试产品映射器的图片处理
echo "\n4. 测试产品映射器的图片处理:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);

// 测试主图验证
if (!empty($main_image_url)) {
    $validate_method = $reflection->getMethod('validate_and_process_remote_image');
    $validate_method->setAccessible(true);
    
    echo "测试映射器主图验证:\n";
    $validated_main = $validate_method->invoke($mapper, $main_image_url, true, $product_id);
    
    if ($validated_main) {
        echo "✅ 主图验证通过: " . substr($validated_main, 0, 80) . "...\n";
    } else {
        echo "❌ 主图验证失败，返回null\n";
    }
}

// 5. 检查最近的图片验证日志
echo "\n5. 检查最近的图片验证日志:\n";

$log_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$image_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$log_table} 
     WHERE product_id = %d 
     AND (message LIKE '%图片%' OR message LIKE '%image%')
     ORDER BY created_at DESC 
     LIMIT 10",
    $product_id
));

if ($image_logs) {
    foreach ($image_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "级别: {$log->level}\n";
        echo "消息: {$log->message}\n";
        if (!empty($log->context)) {
            $context = json_decode($log->context, true);
            if (isset($context['file_size_mb'])) {
                echo "文件大小: {$context['file_size_mb']}MB\n";
            }
        }
        echo "---\n";
    }
} else {
    echo "没有找到图片验证相关日志\n";
}

echo "\n=== 测试完成 ===\n";

// 总结
echo "\n=== 问题诊断 ===\n";
echo "如果图片验证器工作正常但Walmart仍报错，可能的原因：\n";
echo "1. 图片验证在同步过程中被跳过\n";
echo "2. 验证失败的图片没有被正确替换或删除\n";
echo "3. 占位符图片也超过5MB限制\n";
echo "4. 图片URL格式不符合Walmart要求\n";
echo "5. 缓存导致使用了旧的验证结果\n";

?>
