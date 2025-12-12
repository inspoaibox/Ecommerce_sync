<?php
/**
 * 测试映射器是否完整执行
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试映射器执行情况 ===\n\n";

$product_id = 13917;
$product = wc_get_product($product_id);

if (!$product) {
    echo "❌ 无法获取产品\n";
    exit;
}

echo "产品ID: {$product_id}\n";
echo "SKU: " . $product->get_sku() . "\n\n";

// 获取映射配置
global $wpdb;
$map_table = $wpdb->prefix . 'woo_walmart_category_mapping';

$mapping = $wpdb->get_row("
    SELECT * FROM $map_table 
    WHERE wc_category_name = 'Uncategorized' 
    LIMIT 1
");

if (!$mapping) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

$walmart_category_name = $mapping->walmart_category_path;
$attribute_rules = json_decode($mapping->walmart_attributes, true);

echo "沃尔玛分类: {$walmart_category_name}\n";
echo "属性规则数量: " . count($attribute_rules['name'] ?? []) . "\n\n";

// 加载映射器
require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

echo "=== 开始执行映射器 ===\n";

try {
    // 执行映射
    $result = $mapper->map(
        $product, 
        $walmart_category_name, 
        '123456789012', 
        $attribute_rules, 
        1
    );
    
    echo "✅ 映射器执行完成\n";
    echo "返回数据类型: " . gettype($result) . "\n";
    
    if (is_array($result)) {
        echo "返回数组键: " . implode(', ', array_keys($result)) . "\n";
        
        // 检查图片字段
        if (isset($result['MPItem'][0]['Visible'][$walmart_category_name])) {
            $visible_data = $result['MPItem'][0]['Visible'][$walmart_category_name];
            
            echo "\n=== 检查图片字段 ===\n";
            
            // 主图
            if (isset($visible_data['mainImageUrl'])) {
                echo "✅ 主图字段存在\n";
                echo "主图URL: " . substr($visible_data['mainImageUrl'], 0, 80) . "...\n";
            } else {
                echo "❌ 主图字段缺失\n";
            }
            
            // 副图
            if (isset($visible_data['productSecondaryImageURL'])) {
                $secondary_images = $visible_data['productSecondaryImageURL'];
                echo "✅ 副图字段存在\n";
                echo "副图数量: " . count($secondary_images) . "\n";
                
                if (count($secondary_images) < 5) {
                    echo "❌ 副图不足5张\n";
                    foreach ($secondary_images as $i => $url) {
                        echo "副图" . ($i + 1) . ": " . substr($url, 0, 80) . "...\n";
                    }
                } else {
                    echo "✅ 副图满足5张要求\n";
                }
            } else {
                echo "❌ 副图字段缺失\n";
            }
        } else {
            echo "❌ 没有找到Visible数据\n";
        }
    } else {
        echo "❌ 返回数据不是数组\n";
        echo "返回内容: " . print_r($result, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 映射器执行异常: " . $e->getMessage() . "\n";
    echo "异常文件: " . $e->getFile() . "\n";
    echo "异常行号: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ 映射器执行错误: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
}

// 检查最近的日志
echo "\n=== 检查最近的映射器日志 ===\n";

$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$recent_logs = $wpdb->get_results("
    SELECT action, status, created_at, message FROM {$logs_table} 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY created_at DESC 
    LIMIT 10
");

if (!empty($recent_logs)) {
    foreach ($recent_logs as $log) {
        echo "{$log->created_at} - {$log->action} ({$log->status}): {$log->message}\n";
    }
} else {
    echo "没有找到最近5分钟的日志\n";
}

?>
