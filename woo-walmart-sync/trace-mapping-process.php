<?php
/**
 * 跟踪完整的映射过程，找出图片验证被跳过的原因
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 跟踪映射过程 ===\n\n";

$test_sku = 'W1191S00043';
$product_id = 25926;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n\n";

// 1. 手动执行映射器的主图获取逻辑
echo "1. 手动执行主图获取逻辑:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// 步骤1：获取主图ID
$main_image_id = $product->get_image_id();
echo "主图ID: {$main_image_id}\n";

// 步骤2：尝试获取主图URL
$main_image_url = '';
if ($main_image_id) {
    $main_image_url = wp_get_attachment_url($main_image_id);
    echo "wp_get_attachment_url结果: " . ($main_image_url ?: '(空)') . "\n";
}

// 步骤3：检查远程主图
if (empty($main_image_url)) {
    echo "主图URL为空，检查远程主图...\n";
    $remote_main_image = get_post_meta($product->get_id(), '_remote_main_image_url', true);
    echo "远程主图meta: " . ($remote_main_image ?: '(空)') . "\n";
    
    if (!empty($remote_main_image)) {
        $main_image_url = $remote_main_image;
    }
}

// 步骤4：从远程图库获取第一张
if (empty($main_image_url)) {
    echo "仍然没有主图，从远程图库获取第一张...\n";
    $remote_gallery_urls = get_post_meta($product->get_id(), '_remote_gallery_urls', true);
    
    if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
        $first_remote_url = reset($remote_gallery_urls);
        echo "远程图库第一张: " . substr($first_remote_url, 0, 80) . "...\n";
        
        if (filter_var($first_remote_url, FILTER_VALIDATE_URL)) {
            $main_image_url = $first_remote_url;
            echo "✅ 使用远程图库第一张作为主图\n";
        }
    }
}

// 步骤5：最后的占位符
if (empty($main_image_url)) {
    echo "仍然没有主图，使用占位符...\n";
    $main_image_url = wc_placeholder_img_src('full');
    echo "占位符URL: {$main_image_url}\n";
}

echo "最终主图URL: " . substr($main_image_url, 0, 80) . "...\n";

// 2. 检查是否为远程图片
echo "\n2. 检查是否为远程图片:\n";

$is_remote_method = $reflection->getMethod('is_remote_image');
$is_remote_method->setAccessible(true);

$is_remote = $is_remote_method->invoke($mapper, $main_image_url);
echo "是否远程图片: " . ($is_remote ? '是' : '否') . "\n";

// 3. 如果是远程图片，测试验证逻辑
if (!empty($main_image_url) && $is_remote) {
    echo "\n3. 测试主图验证逻辑:\n";
    
    $validate_method = $reflection->getMethod('validate_and_process_remote_image');
    $validate_method->setAccessible(true);
    
    echo "调用validate_and_process_remote_image...\n";
    
    try {
        $validated_main = $validate_method->invoke($mapper, $main_image_url, true, $product_id);
        
        if ($validated_main) {
            echo "✅ 主图验证通过: " . substr($validated_main, 0, 80) . "...\n";
        } else {
            echo "❌ 主图验证失败，返回null\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 验证过程异常: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n3. 主图不是远程图片或为空，跳过验证\n";
}

// 4. 测试副图验证逻辑
echo "\n4. 测试副图验证逻辑:\n";

$get_gallery_method = $reflection->getMethod('get_gallery_images');
$get_gallery_method->setAccessible(true);

$gallery_images = $get_gallery_method->invoke($mapper, $product);
echo "获取到副图数量: " . count($gallery_images) . "\n";

$validated_count = 0;
$failed_count = 0;

foreach ($gallery_images as $index => $image_url) {
    if ($index >= 3) break; // 只测试前3张
    
    echo "\n副图 " . ($index + 1) . ":\n";
    echo "URL: " . substr($image_url, 0, 80) . "...\n";
    
    $is_remote = $is_remote_method->invoke($mapper, $image_url);
    echo "是否远程: " . ($is_remote ? '是' : '否') . "\n";
    
    if ($is_remote) {
        try {
            $validated_url = $validate_method->invoke($mapper, $image_url, false, $product_id);
            
            if ($validated_url) {
                echo "✅ 验证通过\n";
                $validated_count++;
            } else {
                echo "❌ 验证失败\n";
                $failed_count++;
            }
            
        } catch (Exception $e) {
            echo "❌ 验证异常: " . $e->getMessage() . "\n";
            $failed_count++;
        }
    } else {
        echo "本地图片，跳过验证\n";
        $validated_count++;
    }
}

echo "\n副图验证汇总:\n";
echo "  通过: {$validated_count}张\n";
echo "  失败: {$failed_count}张\n";

// 5. 检查验证日志
echo "\n5. 检查验证日志:\n";

global $wpdb;
$recent_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs 
     WHERE product_id = %d 
     AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
     ORDER BY created_at DESC 
     LIMIT 10",
    $product_id
));

$validation_logs = [];
foreach ($recent_logs as $log) {
    if (strpos($log->message, '验证') !== false || strpos($log->message, 'validation') !== false) {
        $validation_logs[] = $log;
    }
}

if (!empty($validation_logs)) {
    echo "找到验证相关日志:\n";
    foreach ($validation_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "消息: {$log->message}\n";
        echo "---\n";
    }
} else {
    echo "❌ 没有找到验证相关日志\n";
    echo "这说明图片验证确实没有被执行！\n";
}

// 6. 对比实际映射过程
echo "\n6. 对比实际映射过程:\n";

// 获取分类映射
$categories = $product->get_category_ids();
$walmart_category = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $walmart_category = $mapping->walmart_category_path;
        break;
    }
}

if ($walmart_category) {
    echo "Walmart分类: {$walmart_category}\n";
    
    // 执行实际映射
    echo "执行实际映射...\n";
    
    try {
        $mapping_result = $mapper->map($product, $walmart_category, '123456789012', [], 1);
        
        if (isset($mapping_result['MPItem'][0]['Visible'][$walmart_category])) {
            $visible_data = $mapping_result['MPItem'][0]['Visible'][$walmart_category];
            
            echo "映射结果:\n";
            
            if (isset($visible_data['mainImageUrl'])) {
                $mapped_main = $visible_data['mainImageUrl'];
                echo "映射后主图: " . substr($mapped_main, 0, 80) . "...\n";
                
                // 对比主图
                if ($mapped_main === $main_image_url) {
                    echo "✅ 主图一致，验证逻辑被跳过\n";
                } else {
                    echo "❌ 主图不一致，可能有其他逻辑\n";
                }
            }
            
            if (isset($visible_data['productSecondaryImageURL'])) {
                $mapped_secondary = $visible_data['productSecondaryImageURL'];
                echo "映射后副图数量: " . count($mapped_secondary) . "\n";
                
                // 检查副图是否包含超大文件
                echo "检查副图文件大小...\n";
                foreach ($mapped_secondary as $i => $img_url) {
                    if ($i >= 2) break;
                    
                    // 简单的HEAD请求检查文件大小
                    $headers = get_headers($img_url, 1);
                    if (isset($headers['Content-Length'])) {
                        $size_mb = round($headers['Content-Length'] / 1024 / 1024, 2);
                        echo "  副图 " . ($i + 1) . ": {$size_mb}MB\n";
                        
                        if ($size_mb > 5) {
                            echo "    ❌ 超过5MB限制！\n";
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 映射失败: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 结论 ===\n";
echo "如果手动验证能正常工作，但实际映射过程中没有验证日志，\n";
echo "说明映射器中的图片验证逻辑存在问题或被绕过了。\n";

?>
