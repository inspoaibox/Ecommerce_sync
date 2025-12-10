<?php
/**
 * 测试 seat_material 字段修复
 */

require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 测试 seat_material 字段修复 ===\n\n";

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
require_once WOO_WALMART_SYNC_PATH . 'includes/class-walmart-spec-service.php';

$mapper = new Woo_Walmart_Product_Mapper();
$spec_service = new Walmart_Spec_Service();

$reflection = new ReflectionClass($mapper);

// 设置 spec_service
$spec_property = $reflection->getProperty('spec_service');
$spec_property->setAccessible(true);
$spec_property->setValue($mapper, $spec_service);

// 设置 current_product_type_id
$product_type_property = $reflection->getProperty('current_product_type_id');
$product_type_property->setAccessible(true);

$convert_method = $reflection->getMethod('convert_field_data_type');
$convert_method->setAccessible(true);

// 测试用例
$test_cases = [
    [
        'name' => '数组输入（正常情况）',
        'value' => ['Leather', 'Fabric'],
        'product_type' => 'Dining Chairs',
        'expected_type' => 'array',
        'expected_count' => 2
    ],
    [
        'name' => '字符串输入（逗号分隔）',
        'value' => 'Leather, Fabric, Cotton',
        'product_type' => 'Dining Chairs',
        'expected_type' => 'array',
        'expected_count' => 3
    ],
    [
        'name' => '字符串输入（分号分隔）',
        'value' => 'Leather; Fabric',
        'product_type' => 'Dining Chairs',
        'expected_type' => 'array',
        'expected_count' => 2
    ],
    [
        'name' => '单个字符串',
        'value' => 'Leather',
        'product_type' => 'Dining Chairs',
        'expected_type' => 'array',
        'expected_count' => 1
    ],
    [
        'name' => '空数组',
        'value' => [],
        'product_type' => 'Dining Chairs',
        'expected_type' => 'array',
        'expected_value' => ['Please see product description material']
    ],
    [
        'name' => '空字符串',
        'value' => '',
        'product_type' => 'Dining Chairs',
        'expected_type' => 'array',
        'expected_value' => ['Please see product description material']
    ],
    [
        'name' => 'null值',
        'value' => null,
        'product_type' => 'Dining Chairs',
        'expected_type' => 'array',
        'expected_value' => ['Please see product description material']
    ],
    [
        'name' => '数组输入（无规范的产品类型）',
        'value' => ['Leather', 'Fabric'],
        'product_type' => 'Unknown Category',
        'expected_type' => 'array',
        'expected_count' => 2
    ],
    [
        'name' => '字符串输入（无规范的产品类型）',
        'value' => 'Leather',
        'product_type' => 'Unknown Category',
        'expected_type' => 'array',
        'expected_count' => 1
    ],
    [
        'name' => '空值（无规范的产品类型）',
        'value' => null,
        'product_type' => 'Unknown Category',
        'expected_type' => 'array',
        'expected_value' => ['Please see product description material']
    ]
];

$passed = 0;
$failed = 0;

foreach ($test_cases as $index => $test) {
    echo "测试 " . ($index + 1) . ": {$test['name']}\n";
    echo "  输入值: " . json_encode($test['value'], JSON_UNESCAPED_UNICODE) . " (" . gettype($test['value']) . ")\n";
    echo "  产品类型: {$test['product_type']}\n";
    
    // 设置产品类型
    $product_type_property->setValue($mapper, $test['product_type']);
    
    // 转换
    $result = $convert_method->invoke($mapper, 'seat_material', $test['value'], null);
    
    echo "  结果类型: " . gettype($result) . "\n";
    echo "  结果内容: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    
    // 验证
    $test_passed = true;
    
    if (gettype($result) !== $test['expected_type']) {
        echo "  ❌ 失败：类型不匹配（期望 {$test['expected_type']}）\n";
        $test_passed = false;
    } elseif (!is_array($result)) {
        echo "  ❌ 失败：结果不是数组\n";
        $test_passed = false;
    } elseif (isset($test['expected_count']) && count($result) !== $test['expected_count']) {
        echo "  ❌ 失败：数组长度不匹配（期望 {$test['expected_count']}，实际 " . count($result) . "）\n";
        $test_passed = false;
    } elseif (isset($test['expected_value']) && $result !== $test['expected_value']) {
        echo "  ❌ 失败：值不匹配（期望 " . json_encode($test['expected_value'], JSON_UNESCAPED_UNICODE) . "）\n";
        $test_passed = false;
    } else {
        echo "  ✅ 通过\n";
    }
    
    if ($test_passed) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo "\n";
}

echo "=== 测试总结 ===\n";
echo "总测试数: " . count($test_cases) . "\n";
echo "通过: {$passed}\n";
echo "失败: {$failed}\n";
echo "通过率: " . round(($passed / count($test_cases)) * 100, 2) . "%\n";

if ($failed === 0) {
    echo "\n✅ 所有测试通过！seat_material 字段修复成功！\n";
} else {
    echo "\n❌ 有测试失败，请检查代码\n";
}
?>

