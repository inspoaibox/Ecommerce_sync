<?php
/**
 * 测试优化后的generate_special_attribute_value方法
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试优化后的generate_special_attribute_value方法 ===\n\n";

// 1. 测试产品13917的图片处理
$product_id = 13917;
$product = wc_get_product($product_id);

if (!$product) {
    echo "❌ 无法获取产品信息\n";
    exit;
}

echo "测试产品: {$product_id} | SKU: {$product->get_sku()}\n\n";

// 2. 创建映射器实例并测试
require_once plugin_dir_path(__FILE__) . 'includes/class-product-mapper.php';

$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

echo "=== 调用优化后的generate_special_attribute_value方法 ===\n";

try {
    $result = $method->invoke($mapper, 'productSecondaryImageURL', $product, 1);
    
    if (is_array($result)) {
        echo "✅ 方法调用成功\n";
        echo "返回图片数量: " . count($result) . "\n";
        echo "返回的图片URLs:\n";
        
        foreach ($result as $i => $url) {
            echo "  " . ($i + 1) . ". " . $url . "\n";
            
            // 检查URL类型
            if (strpos($url, 'b2bfiles1.gigab2b.cn') !== false) {
                echo "    ✅ 远程图库URL\n";
            } else if (strpos($url, 'walmartimages.com') !== false) {
                echo "    ✅ 占位符URL\n";
            } else {
                echo "    ❓ 其他URL\n";
            }
        }
        
        // 验证结果
        if (count($result) >= 5) {
            echo "\n✅ 图片数量满足沃尔玛5张要求\n";
        } else if (count($result) >= 3) {
            echo "\n⚠️ 图片数量为" . count($result) . "张，可能需要补足\n";
        } else {
            echo "\n❌ 图片数量不足3张\n";
        }
        
    } else {
        echo "❌ 方法返回非数组结果: " . print_r($result, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 方法调用异常: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ 方法调用错误: " . $e->getMessage() . "\n";
}

// 3. 检查生成的日志
echo "\n=== 检查生成的日志 ===\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$recent_logs = $wpdb->get_results($wpdb->prepare("
    SELECT id, created_at, action, status, message 
    FROM {$logs_table} 
    WHERE (
        action LIKE '动态映射-图片补足%' OR
        action LIKE '动态映射-图片不足%'
    )
    AND product_id = %d
    ORDER BY id DESC 
    LIMIT 5
", $product_id));

if (!empty($recent_logs)) {
    echo "找到相关日志:\n";
    foreach ($recent_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->status} | {$log->message}\n";
    }
} else {
    echo "❌ 没有找到相关日志\n";
}

// 4. 对比原有逻辑和新逻辑
echo "\n=== 对比原有逻辑和新逻辑 ===\n";

// 模拟原有的简单逻辑
$gallery_image_ids = $product->get_gallery_image_ids();
$old_logic_images = [];
foreach ($gallery_image_ids as $image_id) {
    $image_url = wp_get_attachment_image_url($image_id, 'full');
    if ($image_url) {
        $old_logic_images[] = $image_url;
    }
}

echo "原有逻辑结果: " . count($old_logic_images) . "张图片\n";
echo "新逻辑结果: " . (is_array($result) ? count($result) : 0) . "张图片\n";

if (is_array($result) && count($result) > count($old_logic_images)) {
    echo "✅ 新逻辑成功补足了图片\n";
    echo "补足数量: " . (count($result) - count($old_logic_images)) . "张\n";
} else {
    echo "⚠️ 新逻辑没有补足图片或补足失败\n";
}

// 5. 验证占位符配置
echo "\n=== 验证占位符配置 ===\n";

$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "占位符1: " . (!empty($placeholder_1) ? '已配置' : '未配置') . "\n";
echo "占位符2: " . (!empty($placeholder_2) ? '已配置' : '未配置') . "\n";

if (!empty($placeholder_1)) {
    echo "占位符1 URL: " . substr($placeholder_1, 0, 80) . "...\n";
    echo "占位符1 验证: " . (filter_var($placeholder_1, FILTER_VALIDATE_URL) ? '有效' : '无效') . "\n";
}

if (!empty($placeholder_2)) {
    echo "占位符2 URL: " . substr($placeholder_2, 0, 80) . "...\n";
    echo "占位符2 验证: " . (filter_var($placeholder_2, FILTER_VALIDATE_URL) ? '有效' : '无效') . "\n";
}

echo "\n=== 测试完成 ===\n";

?>
