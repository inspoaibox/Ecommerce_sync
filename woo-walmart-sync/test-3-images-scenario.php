<?php
/**
 * 测试3张图片的补足场景
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试3张图片的补足场景 ===\n\n";

// 模拟一个只有3张图片的产品
class Mock_Product {
    private $gallery_ids;
    private $product_id;
    
    public function __construct($gallery_ids, $product_id = 99999) {
        $this->gallery_ids = $gallery_ids;
        $this->product_id = $product_id;
    }
    
    public function get_gallery_image_ids() {
        return $this->gallery_ids;
    }
    
    public function get_id() {
        return $this->product_id;
    }
    
    public function get_sku() {
        return 'TEST_3_IMAGES';
    }
}

// 创建模拟产品（只有3张图片）
$mock_product = new Mock_Product([
    'remote_1',
    'remote_2', 
    'remote_3'
]);

// 模拟远程图库数据
$mock_remote_urls = [
    'https://example.com/image1.jpg',
    'https://example.com/image2.jpg',
    'https://example.com/image3.jpg'
];

// 设置模拟的远程图库数据
update_post_meta($mock_product->get_id(), '_remote_gallery_urls', $mock_remote_urls);

echo "模拟产品信息:\n";
echo "产品ID: {$mock_product->get_id()}\n";
echo "SKU: {$mock_product->get_sku()}\n";
echo "图库ID: " . implode(', ', $mock_product->get_gallery_image_ids()) . "\n";
echo "远程图库: " . count($mock_remote_urls) . "张\n\n";

// 测试优化后的方法
require_once plugin_dir_path(__FILE__) . 'includes/class-product-mapper.php';

$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

echo "=== 调用generate_special_attribute_value方法 ===\n";

try {
    $result = $method->invoke($mapper, 'productSecondaryImageURL', $mock_product, 1);
    
    if (is_array($result)) {
        echo "✅ 方法调用成功\n";
        echo "返回图片数量: " . count($result) . "\n";
        echo "返回的图片URLs:\n";
        
        foreach ($result as $i => $url) {
            echo "  " . ($i + 1) . ". " . $url . "\n";
            
            // 检查URL类型
            if (strpos($url, 'example.com') !== false) {
                echo "    ✅ 模拟远程图库URL\n";
            } else if (strpos($url, 'walmartimages.com') !== false) {
                echo "    ✅ 占位符URL\n";
            } else {
                echo "    ❓ 其他URL\n";
            }
        }
        
        // 验证结果
        if (count($result) == 5) {
            echo "\n✅ 3张图片成功补足到5张！\n";
            
            // 检查是否包含两个占位符
            $placeholder_count = 0;
            foreach ($result as $url) {
                if (strpos($url, 'walmartimages.com') !== false) {
                    $placeholder_count++;
                }
            }
            
            if ($placeholder_count == 2) {
                echo "✅ 成功添加了2个占位符\n";
            } else {
                echo "❌ 占位符数量不正确: {$placeholder_count}\n";
            }
            
        } else {
            echo "\n❌ 补足失败，期望5张，实际" . count($result) . "张\n";
        }
        
    } else {
        echo "❌ 方法返回非数组结果: " . print_r($result, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 方法调用异常: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "❌ 方法调用错误: " . $e->getMessage() . "\n";
}

// 检查生成的日志
echo "\n=== 检查生成的日志 ===\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$recent_logs = $wpdb->get_results($wpdb->prepare("
    SELECT id, created_at, action, status, message 
    FROM {$logs_table} 
    WHERE action LIKE '动态映射-图片补足-3张%'
    AND product_id = %d
    ORDER BY id DESC 
    LIMIT 3
", $mock_product->get_id()));

if (!empty($recent_logs)) {
    echo "找到3张补足日志:\n";
    foreach ($recent_logs as $log) {
        echo "ID: {$log->id} | {$log->created_at} | {$log->action} | {$log->message}\n";
    }
} else {
    echo "❌ 没有找到3张补足日志\n";
}

// 清理测试数据
delete_post_meta($mock_product->get_id(), '_remote_gallery_urls');

echo "\n=== 测试完成 ===\n";

?>
