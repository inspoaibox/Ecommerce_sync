<?php
/**
 * 测试assembledProductWeight字段的单位问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试assembledProductWeight字段的单位问题 ===\n\n";

$test_product_id = 25926; // W1191S00043
$product = wc_get_product($test_product_id);

if (!$product) {
    echo "❌ 测试产品不存在\n";
    exit;
}

echo "产品: {$product->get_name()}\n";
echo "SKU: {$product->get_sku()}\n\n";

// 1. 检查Product Weight属性的原始值
echo "1. 检查Product Weight属性:\n";

$weight_attributes = [
    'Product Weight',
    'product_weight', 
    'Assembled Weight',
    'assembled_weight'
];

$found_weight = null;
$found_attribute = null;

foreach ($weight_attributes as $attr) {
    $weight_value = $product->get_attribute($attr);
    if (!empty($weight_value)) {
        echo "✅ 找到属性 '{$attr}': {$weight_value}\n";
        $found_weight = $weight_value;
        $found_attribute = $attr;
        break;
    } else {
        echo "❌ 属性 '{$attr}': 空值\n";
    }
}

// 检查WooCommerce内置重量
$wc_weight = $product->get_weight();
echo "WooCommerce内置重量: " . ($wc_weight ?: '(空)') . "\n";

if (!$found_weight && $wc_weight) {
    $found_weight = $wc_weight;
    $found_attribute = 'WooCommerce内置重量';
}

// 2. 分析原始重量值
echo "\n2. 分析原始重量值:\n";

if ($found_weight) {
    echo "使用的重量值: {$found_weight} (来源: {$found_attribute})\n";
    echo "数据类型: " . gettype($found_weight) . "\n";
    
    // 检查是否包含单位
    if (is_string($found_weight)) {
        if (preg_match('/([0-9]+\.?[0-9]*)\s*(lb|lbs|oz|kg|g)\b/i', $found_weight, $matches)) {
            echo "检测到的数值: {$matches[1]}\n";
            echo "检测到的单位: {$matches[2]}\n";
            
            // 检查单位是否符合API要求
            $detected_unit = strtolower($matches[2]);
            $allowed_units = ['lb', 'oz'];
            
            if (in_array($detected_unit, $allowed_units)) {
                echo "✅ 检测到的单位符合API要求\n";
            } else {
                echo "❌ 检测到的单位不符合API要求，API只允许: " . implode(', ', $allowed_units) . "\n";
                if ($detected_unit === 'lbs') {
                    echo "建议: 'lbs' 应该转换为 'lb'\n";
                } elseif ($detected_unit === 'kg') {
                    echo "建议: 'kg' 应该转换为 'lb' (1 kg = 2.20462 lb)\n";
                } elseif ($detected_unit === 'g') {
                    echo "建议: 'g' 应该转换为 'oz' (1 g = 0.035274 oz)\n";
                }
            }
        } else {
            echo "❌ 无法从字符串中提取单位信息\n";
            echo "字符串格式: '{$found_weight}'\n";
        }
    } else {
        echo "⚠️ 重量值是数字类型，没有单位信息\n";
    }
} else {
    echo "❌ 没有找到任何重量信息\n";
}

// 3. 测试assembledProductWeight字段处理
echo "\n3. 测试assembledProductWeight字段处理:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

$weight_result = $generate_method->invoke($mapper, 'assembledProductWeight', $product, 1);
echo "assembledProductWeight结果:\n";
print_r($weight_result);

// 4. 检查结果是否正确
if (is_array($weight_result)) {
    echo "\n4. 结果验证:\n";
    echo "measure: " . ($weight_result['measure'] ?? '缺失') . "\n";
    echo "unit: " . ($weight_result['unit'] ?? '缺失') . "\n";
    
    if (isset($weight_result['unit'])) {
        $unit = $weight_result['unit'];
        $allowed_units = ['lb', 'oz'];
        
        if (in_array($unit, $allowed_units)) {
            echo "✅ 单位符合API要求\n";
        } else {
            echo "❌ 单位不符合API要求，API只允许: " . implode(', ', $allowed_units) . "\n";
        }
        
        // 检查单位是否与原始数据匹配
        if ($found_weight && is_string($found_weight)) {
            if (preg_match('/([0-9]+\.?[0-9]*)\s*(lb|lbs|oz|kg|g)\b/i', $found_weight, $matches)) {
                $original_unit = strtolower($matches[2]);
                $mapped_unit = strtolower($unit);
                
                if ($original_unit === 'lbs' && $mapped_unit === 'lb') {
                    echo "✅ 正确转换: 'lbs' → 'lb'\n";
                } elseif ($original_unit === $mapped_unit) {
                    echo "✅ 单位保持一致: '{$original_unit}'\n";
                } else {
                    echo "⚠️ 单位转换: '{$original_unit}' → '{$mapped_unit}'\n";
                }
            }
        }
    } else {
        echo "❌ 缺少单位信息\n";
    }
    
    if (isset($weight_result['measure'])) {
        $measure = $weight_result['measure'];
        echo "数值: {$measure}\n";
        echo "数值类型: " . gettype($measure) . "\n";
        
        if (is_numeric($measure) && $measure > 0) {
            echo "✅ 数值有效\n";
        } else {
            echo "❌ 数值无效\n";
        }
    } else {
        echo "❌ 缺少数值信息\n";
    }
} else {
    echo "❌ assembledProductWeight结果不是数组格式\n";
}

// 5. 测试完整映射中的assembledProductWeight
echo "\n5. 测试完整映射中的assembledProductWeight:\n";

// 获取分类映射
$categories = $product->get_category_ids();
global $wpdb;
$mapping = null;
$walmart_category = null;
$attribute_rules = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $walmart_category = $mapping->walmart_category_path;
        $attribute_rules = !empty($mapping->walmart_attributes) ? 
            json_decode($mapping->walmart_attributes, true) : [];
        break;
    }
}

if ($walmart_category) {
    echo "执行完整映射...\n";
    
    $upc = get_post_meta($product->get_id(), '_walmart_upc', true) ?: '123456789012';
    $fulfillment_lag_time = 1;
    
    $walmart_data = $mapper->map($product, $walmart_category, $upc, $attribute_rules, $fulfillment_lag_time);
    
    if ($walmart_data && isset($walmart_data['MPItem'][0]['Visible'][$walmart_category]['assembledProductWeight'])) {
        $mapped_weight = $walmart_data['MPItem'][0]['Visible'][$walmart_category]['assembledProductWeight'];
        echo "映射后的assembledProductWeight:\n";
        print_r($mapped_weight);
        
        if (is_array($mapped_weight) && isset($mapped_weight['measure']) && isset($mapped_weight['unit'])) {
            echo "✅ 映射结果包含完整的measure和unit\n";
            echo "这说明映射器输出是正确的\n";
            echo "如果Walmart后台只显示数值，问题可能在于:\n";
            echo "1. JSON序列化过程\n";
            echo "2. API传输过程\n";
            echo "3. Walmart后台显示逻辑\n";
        } else {
            echo "❌ 映射结果格式错误\n";
        }
    } else {
        echo "❌ 映射结果中没有assembledProductWeight字段\n";
    }
} else {
    echo "❌ 没有找到分类映射\n";
}

echo "\n=== 问题诊断 ===\n";
echo "如果映射器输出的数据包含正确的measure和unit，\n";
echo "但Walmart后台只显示数值，可能的原因:\n";
echo "1. 单位转换问题（如lbs未转换为lb）\n";
echo "2. JSON序列化时数据结构被破坏\n";
echo "3. API请求格式不正确\n";
echo "4. Walmart API处理时丢失单位信息\n";

?>
