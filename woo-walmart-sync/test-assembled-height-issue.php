<?php
/**
 * 测试assembledProductHeight字段的单位问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试assembledProductHeight字段的单位问题 ===\n\n";

$test_product_id = 25926; // W1191S00043
$product = wc_get_product($test_product_id);

if (!$product) {
    echo "❌ 测试产品不存在\n";
    exit;
}

echo "产品: {$product->get_name()}\n";
echo "SKU: {$product->get_sku()}\n\n";

// 1. 检查Product Size属性的原始值
echo "1. 检查Product Size属性:\n";
$product_size = $product->get_attribute('Product Size') ?:
               $product->get_attribute('product-size') ?:
               $product->get_attribute('product_size');

echo "Product Size原始值: " . ($product_size ?: '(空)') . "\n";

if ($product_size) {
    // 分析原始值中的单位
    if (preg_match('/(\d+(?:\.\d+)?)\s*(ft|in|cm|mm)\b/i', $product_size, $matches)) {
        echo "检测到的单位: {$matches[2]}\n";
        echo "示例数值: {$matches[1]}\n";
    } else {
        echo "❌ 无法从原始值中检测到单位\n";
    }
}

// 2. 测试parse_product_size_dimension函数
echo "\n2. 测试parse_product_size_dimension函数:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$parse_method = $reflection->getMethod('parse_product_size_dimension');
$parse_method->setAccessible(true);

$height_value = $parse_method->invoke($mapper, $product, 2); // 2 = 高度索引
echo "解析出的高度值: " . ($height_value ?: '(null)') . "\n";
echo "数据类型: " . gettype($height_value) . "\n";

// 3. 测试assembledProductHeight字段处理
echo "\n3. 测试assembledProductHeight字段处理:\n";

$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

$height_result = $generate_method->invoke($mapper, 'assembledProductHeight', $product, 1);
echo "assembledProductHeight结果:\n";
print_r($height_result);

// 4. 检查单位是否正确
if (is_array($height_result)) {
    echo "\n4. 单位检查:\n";
    echo "measure: " . ($height_result['measure'] ?? '缺失') . "\n";
    echo "unit: " . ($height_result['unit'] ?? '缺失') . "\n";
    
    if (isset($height_result['unit'])) {
        if ($height_result['unit'] === 'in') {
            echo "✅ 单位为 'in' (英寸)\n";
        } elseif ($height_result['unit'] === 'ft') {
            echo "✅ 单位为 'ft' (英尺)\n";
        } else {
            echo "⚠️ 单位为: {$height_result['unit']}\n";
        }
        
        // 检查是否符合API要求
        $allowed_units = ['ft', 'in'];
        if (in_array($height_result['unit'], $allowed_units)) {
            echo "✅ 单位符合API要求 (ft, in)\n";
        } else {
            echo "❌ 单位不符合API要求，API只允许: " . implode(', ', $allowed_units) . "\n";
        }
    } else {
        echo "❌ 缺少单位信息\n";
    }
} else {
    echo "❌ assembledProductHeight结果不是数组格式\n";
}

// 5. 分析问题和解决方案
echo "\n=== 问题分析 ===\n";

if ($product_size) {
    echo "原始Product Size: {$product_size}\n";
    
    // 检查原始值中的单位
    if (preg_match_all('/(\d+(?:\.\d+)?)\s*(ft|in|cm|mm)\b/i', $product_size, $all_matches, PREG_SET_ORDER)) {
        echo "所有检测到的单位:\n";
        foreach ($all_matches as $match) {
            echo "  数值: {$match[1]}, 单位: {$match[2]}\n";
        }
        
        // 检查是否有混合单位
        $units = array_unique(array_column($all_matches, 2));
        if (count($units) > 1) {
            echo "⚠️ 检测到混合单位: " . implode(', ', $units) . "\n";
            echo "建议: 统一转换为API允许的单位 (ft 或 in)\n";
        } else {
            $detected_unit = strtolower($units[0]);
            echo "统一单位: {$detected_unit}\n";
            
            if (in_array($detected_unit, ['ft', 'in'])) {
                echo "✅ 检测到的单位符合API要求\n";
            } else {
                echo "❌ 检测到的单位需要转换为API允许的单位\n";
                if ($detected_unit === 'cm') {
                    echo "建议: cm转换为in (1 cm = 0.393701 in)\n";
                } elseif ($detected_unit === 'mm') {
                    echo "建议: mm转换为in (1 mm = 0.0393701 in)\n";
                }
            }
        }
    }
}

echo "\n=== 解决方案建议 ===\n";
echo "1. 修改parse_product_size_dimension函数，保留单位信息\n";
echo "2. 在assembledProductHeight处理中，使用检测到的单位而不是硬编码\n";
echo "3. 添加单位转换逻辑，将cm/mm转换为in\n";
echo "4. 确保单位符合API要求 (ft, in)\n";

?>
