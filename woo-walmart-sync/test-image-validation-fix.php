<?php
/**
 * 测试图片验证修复效果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试图片验证修复效果 ===\n\n";

$test_sku = 'W1191S00043';
$product_id = 25926;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n\n";

// 1. 测试修复后的主图处理
echo "1. 测试修复后的主图处理:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);

// 找到正确的方法名
$methods = $reflection->getMethods();
$field_method = null;

foreach ($methods as $method) {
    if (strpos($method->getName(), 'field') !== false && $method->isPrivate()) {
        echo "找到方法: " . $method->getName() . "\n";
        if (strpos($method->getName(), 'get') !== false) {
            $field_method = $method;
            break;
        }
    }
}

if (!$field_method) {
    // 尝试直接查找处理字段的逻辑
    echo "直接测试字段处理逻辑...\n";
    
    // 模拟字段处理
    try {
        // 获取分类映射
        global $wpdb;
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
            
            // 执行完整映射
            echo "执行完整映射...\n";
            $mapping_result = $mapper->map($product, $walmart_category, '123456789012', [], 1);
            
            if (isset($mapping_result['MPItem'][0]['Visible'][$walmart_category])) {
                $visible_data = $mapping_result['MPItem'][0]['Visible'][$walmart_category];
                
                echo "\n映射结果:\n";
                
                // 检查主图
                if (isset($visible_data['mainImageUrl'])) {
                    $main_image = $visible_data['mainImageUrl'];
                    echo "主图: " . substr($main_image, 0, 80) . "...\n";
                    
                    // 检查主图文件大小
                    $headers = get_headers($main_image, 1);
                    if (isset($headers['Content-Length'])) {
                        $size_mb = round($headers['Content-Length'] / 1024 / 1024, 2);
                        echo "主图大小: {$size_mb}MB\n";
                        
                        if ($size_mb > 5) {
                            echo "❌ 主图仍然超过5MB限制！修复失败\n";
                        } else {
                            echo "✅ 主图大小符合要求！修复成功\n";
                        }
                    } else {
                        echo "无法获取主图文件大小\n";
                    }
                } else {
                    echo "❌ 没有主图字段\n";
                }
                
                // 检查副图
                if (isset($visible_data['productSecondaryImageURL'])) {
                    $secondary_images = $visible_data['productSecondaryImageURL'];
                    echo "\n副图数量: " . count($secondary_images) . "\n";
                    
                    $valid_secondary_count = 0;
                    $invalid_secondary_count = 0;
                    
                    foreach ($secondary_images as $i => $img_url) {
                        if ($i >= 3) break; // 只检查前3张
                        
                        $headers = get_headers($img_url, 1);
                        if (isset($headers['Content-Length'])) {
                            $size_mb = round($headers['Content-Length'] / 1024 / 1024, 2);
                            echo "副图 " . ($i + 1) . ": {$size_mb}MB\n";
                            
                            if ($size_mb > 5) {
                                echo "  ❌ 超过5MB限制\n";
                                $invalid_secondary_count++;
                            } else {
                                echo "  ✅ 大小符合要求\n";
                                $valid_secondary_count++;
                            }
                        }
                    }
                    
                    echo "\n副图验证汇总:\n";
                    echo "  符合要求: {$valid_secondary_count}张\n";
                    echo "  超过限制: {$invalid_secondary_count}张\n";
                    
                    if ($invalid_secondary_count > 0) {
                        echo "❌ 副图验证逻辑可能没有生效\n";
                    } else {
                        echo "✅ 副图验证逻辑正常工作\n";
                    }
                    
                } else {
                    echo "❌ 没有副图字段\n";
                }
                
            } else {
                echo "❌ 映射结果中没有可见数据\n";
            }
            
        } else {
            echo "❌ 没有找到Walmart分类映射\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 测试异常: " . $e->getMessage() . "\n";
    }
}

// 2. 检查验证日志
echo "\n2. 检查验证日志:\n";

$recent_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs 
     WHERE product_id = %d 
     AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY created_at DESC 
     LIMIT 20",
    $product_id
));

$validation_logs = [];
foreach ($recent_logs as $log) {
    if (strpos($log->message, '验证') !== false || 
        strpos($log->message, 'validation') !== false ||
        strpos($log->message, '图片') !== false) {
        $validation_logs[] = $log;
    }
}

if (!empty($validation_logs)) {
    echo "找到验证相关日志:\n";
    foreach ($validation_logs as $log) {
        echo "时间: {$log->created_at}\n";
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
    echo "❌ 没有找到验证相关日志\n";
}

echo "\n=== 测试结论 ===\n";
echo "如果主图大小符合要求且有验证日志，说明修复成功。\n";
echo "如果主图仍然超过5MB且没有验证日志，说明还有其他问题。\n";

?>
