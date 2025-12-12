<?php
/**
 * 检查分类映射配置中的图片字段
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查分类映射配置 ===\n\n";

$test_sku = 'W1191S00043';
$product_id = 25926;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n\n";

// 1. 获取产品分类
$categories = $product->get_category_ids();
echo "产品分类ID: " . implode(', ', $categories) . "\n";

// 2. 查找分类映射
global $wpdb;
$mapping = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        break;
    }
}

if (!$mapping) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

echo "✅ 找到分类映射:\n";
echo "映射ID: {$mapping->id}\n";
echo "WC分类: {$mapping->wc_category_name}\n";
echo "Walmart分类: {$mapping->walmart_category_path}\n\n";

// 3. 检查属性映射配置
if (!empty($mapping->walmart_attributes)) {
    $attributes = json_decode($mapping->walmart_attributes, true);
    
    if ($attributes && is_array($attributes)) {
        echo "3. 属性映射配置:\n";
        
        // 检查是否有图片相关字段
        $image_fields = [];
        
        if (isset($attributes['name']) && is_array($attributes['name'])) {
            foreach ($attributes['name'] as $index => $field_name) {
                $field_name_lower = strtolower($field_name);
                
                if (strpos($field_name_lower, 'image') !== false || 
                    strpos($field_name_lower, 'url') !== false) {
                    
                    $image_fields[] = [
                        'index' => $index,
                        'name' => $field_name,
                        'type' => isset($attributes['type'][$index]) ? $attributes['type'][$index] : 'unknown',
                        'source' => isset($attributes['source'][$index]) ? $attributes['source'][$index] : 'unknown'
                    ];
                }
            }
        }
        
        if (!empty($image_fields)) {
            echo "找到图片相关字段:\n";
            foreach ($image_fields as $field) {
                echo "  字段: {$field['name']}\n";
                echo "  类型: {$field['type']}\n";
                echo "  来源: {$field['source']}\n";
                echo "  索引: {$field['index']}\n";
                
                if ($field['name'] === 'mainImageUrl') {
                    echo "  ❌ 这就是问题所在！mainImageUrl字段绕过了验证逻辑\n";
                }
                
                echo "  ---\n";
            }
        } else {
            echo "没有找到图片相关字段\n";
        }
        
        // 4. 检查所有字段
        echo "\n4. 所有配置字段:\n";
        if (isset($attributes['name'])) {
            foreach ($attributes['name'] as $index => $field_name) {
                $type = isset($attributes['type'][$index]) ? $attributes['type'][$index] : 'unknown';
                $source = isset($attributes['source'][$index]) ? $attributes['source'][$index] : 'unknown';
                
                echo "  {$field_name} ({$type}) - {$source}\n";
                
                if ($index >= 10) {
                    echo "  ... (省略其余字段)\n";
                    break;
                }
            }
        }
        
    } else {
        echo "❌ 属性映射配置格式错误\n";
    }
} else {
    echo "❌ 没有属性映射配置\n";
}

// 5. 测试字段映射
echo "\n5. 测试字段映射:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$get_field_method = $reflection->getMethod('get_field_value');
$get_field_method->setAccessible(true);

// 测试mainImageUrl字段
echo "测试mainImageUrl字段:\n";
$main_image_result = $get_field_method->invoke($mapper, 'mainImageUrl', $product, [], 0);
echo "结果: " . ($main_image_result ?: '(null)') . "\n";

if ($main_image_result) {
    echo "URL: " . substr($main_image_result, 0, 80) . "...\n";
    
    // 检查文件大小
    $headers = get_headers($main_image_result, 1);
    if (isset($headers['Content-Length'])) {
        $size_mb = round($headers['Content-Length'] / 1024 / 1024, 2);
        echo "文件大小: {$size_mb}MB\n";
        
        if ($size_mb > 5) {
            echo "❌ 超过5MB限制！这就是Walmart报错的原因\n";
        }
    }
}

// 6. 测试productSecondaryImageURL字段
echo "\n测试productSecondaryImageURL字段:\n";
$secondary_image_result = $get_field_method->invoke($mapper, 'productSecondaryImageURL', $product, [], 0);

if (is_array($secondary_image_result)) {
    echo "副图数量: " . count($secondary_image_result) . "\n";
    
    foreach ($secondary_image_result as $i => $img_url) {
        if ($i >= 2) break;
        
        echo "副图 " . ($i + 1) . ": " . substr($img_url, 0, 60) . "...\n";
        
        $headers = get_headers($img_url, 1);
        if (isset($headers['Content-Length'])) {
            $size_mb = round($headers['Content-Length'] / 1024 / 1024, 2);
            echo "  大小: {$size_mb}MB\n";
            
            if ($size_mb > 5) {
                echo "  ❌ 超过5MB限制！\n";
            }
        }
    }
} else {
    echo "结果: " . ($secondary_image_result ?: '(null)') . "\n";
}

echo "\n=== 结论 ===\n";
echo "如果分类映射中配置了mainImageUrl或productSecondaryImageURL字段，\n";
echo "系统会直接调用get_field_value()方法获取图片，完全绕过验证逻辑！\n";
echo "这就是为什么超大图片能直接发送给Walmart的原因。\n";

?>
