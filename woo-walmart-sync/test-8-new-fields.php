<?php
/**
 * 测试8个新增通用字段的自动生成功能
 * 按照字段拓展开发文档的标准测试模板
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 测试8个新增通用字段 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n";
echo "PHP版本: " . phpversion() . "\n\n";

// WordPress环境加载
require_once dirname(__FILE__) . '/../../../wp-config.php';
echo "✅ WordPress加载成功\n\n";

// 加载产品映射器
require_once dirname(__FILE__) . '/includes/class-product-mapper.php';

if (!class_exists('Woo_Walmart_Product_Mapper')) {
    echo "❌ 映射器类不存在\n";
    exit;
}

echo "✅ 映射器类加载成功\n\n";

// 初始化Mapper
$mapper = new Woo_Walmart_Product_Mapper();

// 测试字段列表
$test_fields = [
    'dining_furniture_set_type' => '餐厅家具套装类型',
    'overall_chair_depth' => '椅子整体深度',
    'overall_chair_height' => '椅子整体高度',
    'overall_chair_width' => '椅子整体宽度',
    'seat_back_height_descriptor' => '座椅靠背高度描述',
    'seating_capacity_with_leaf' => '带扩展叶板的座位容量',
    'table_length' => '桌子长度',
    'table_width' => '桌子宽度'
];

echo "📋 测试字段列表:\n";
foreach ($test_fields as $field_name => $field_desc) {
    echo "  - {$field_name}: {$field_desc}\n";
}
echo "\n";

// 获取测试产品
global $wpdb;
$products = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    ORDER BY ID DESC 
    LIMIT 3
");

if (empty($products)) {
    echo "❌ 没有找到测试产品\n";
    exit;
}

echo "✅ 获取到 " . count($products) . " 个产品进行测试\n\n";

// 使用反射访问private方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

// 测试每个产品
foreach ($products as $product_data) {
    $product = wc_get_product($product_data->ID);
    if (!$product) continue;
    
    echo "=== 测试产品: {$product->get_name()} (ID: {$product->get_id()}) ===\n";
    echo "SKU: " . $product->get_sku() . "\n";
    
    // 显示产品内容预览
    $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
    $content_preview = substr(strip_tags($content), 0, 100);
    echo "内容预览: {$content_preview}...\n\n";
    
    // 测试每个字段
    foreach ($test_fields as $field_name => $field_desc) {
        echo "🔍 测试字段: {$field_desc} ({$field_name})\n";
        
        try {
            $start_time = microtime(true);
            $value = $method->invoke($mapper, $field_name, $product, 1);
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            echo "执行时间: {$execution_time}ms\n";
            echo "结果类型: " . gettype($value) . "\n";
            
            if ($value === null) {
                echo "结果: NULL (字段将不会传递)\n";
            } elseif (is_array($value)) {
                if (isset($value['measure']) && isset($value['unit'])) {
                    // 测量对象
                    echo "结果: {$value['measure']} {$value['unit']} (测量对象)\n";
                } else {
                    // 普通数组
                    echo "结果: [" . implode(', ', $value) . "] (数组，" . count($value) . "个元素)\n";
                }
            } elseif (is_int($value)) {
                echo "结果: {$value} (整数)\n";
            } else {
                echo "结果: {$value} (字符串)\n";
            }
            
            echo "✅ {$field_name}字段生成测试通过\n";
            
        } catch (Exception $e) {
            echo "❌ {$field_name}字段生成失败: " . $e->getMessage() . "\n";
            echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
        
        echo str_repeat('-', 50) . "\n";
    }
    
    echo "\n";
}

// 创建模拟产品进行详细测试
echo "=== 模拟产品详细测试 ===\n\n";

$test_cases = [
    [
        'name' => '模拟产品1 - 餐桌套装',
        'content' => 'Dining Table with Bench and Chair Set, Table Length 70 inches, Table Width 36 inches, Chair Height 38 inches, Chair Width 18 inches, Chair Depth 20 inches, Seats 8 with Leaf, High Back Chairs',
        'expected' => [
            'dining_furniture_set_type' => 'Dining Table with Bench and Chair',
            'table_length' => ['measure' => '70', 'unit' => 'in'],
            'table_width' => ['measure' => '36', 'unit' => 'in'],
            'overall_chair_height' => ['measure' => '38', 'unit' => 'in'],
            'overall_chair_width' => ['measure' => '18', 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => '20', 'unit' => 'in'],
            'seating_capacity_with_leaf' => 8,
            'seat_back_height_descriptor' => 'High Back'
        ]
    ],
    [
        'name' => '模拟产品2 - 酒吧桌套装',
        'content' => 'Pub Table Set with Mid Back Stools, Table 42 inches long, 30 inches wide, Seat Height 24 inches',
        'expected' => [
            'dining_furniture_set_type' => 'Pub Table Set',
            'table_length' => ['measure' => '42', 'unit' => 'in'],
            'table_width' => ['measure' => '30', 'unit' => 'in'],
            'overall_chair_height' => ['measure' => '24', 'unit' => 'in'],
            'seat_back_height_descriptor' => 'Mid Back'
        ]
    ],
    [
        'name' => '模拟产品3 - 餐厅角落套装',
        'content' => 'Dining Nook Corner Bench Set, 5 ft table length, 3 ft table width, Low Back Seating, Accommodates 6 with leaf',
        'expected' => [
            'dining_furniture_set_type' => 'Dining Nook',
            'table_length' => ['measure' => '5', 'unit' => 'ft'],
            'table_width' => ['measure' => '3', 'unit' => 'ft'],
            'seat_back_height_descriptor' => 'Low Back',
            'seating_capacity_with_leaf' => 6
        ]
    ]
];

foreach ($test_cases as $test_case) {
    echo "--- {$test_case['name']} ---\n";
    echo "测试内容: {$test_case['content']}\n\n";
    
    // 创建临时产品
    $temp_product = new WC_Product_Simple();
    $temp_product->set_name($test_case['content']);
    $temp_product->set_description($test_case['content']);
    
    foreach ($test_case['expected'] as $field_name => $expected_value) {
        try {
            $actual_value = $method->invoke($mapper, $field_name, $temp_product, 1);
            
            echo "字段: {$field_name}\n";
            echo "  预期: " . (is_array($expected_value) ? json_encode($expected_value) : $expected_value) . "\n";
            echo "  实际: " . (is_array($actual_value) ? json_encode($actual_value) : ($actual_value ?? 'NULL')) . "\n";
            
            // 验证
            if (is_array($expected_value) && is_array($actual_value)) {
                if (json_encode($expected_value) === json_encode($actual_value)) {
                    echo "  ✅ 匹配\n";
                } else {
                    echo "  ⚠️  不完全匹配\n";
                }
            } elseif ($expected_value == $actual_value) {
                echo "  ✅ 匹配\n";
            } else {
                echo "  ⚠️  不匹配\n";
            }
        } catch (Exception $e) {
            echo "  ❌ 错误: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo str_repeat('-', 50) . "\n\n";
}

echo "=== 测试完成 ===\n";
echo "建议:\n";
echo "1. 在分类映射页面测试重置属性功能，验证新字段是否正确显示\n";
echo "2. 使用真实产品测试字段生成效果\n";
echo "3. 根据实际匹配效果调整关键词匹配规则\n";
