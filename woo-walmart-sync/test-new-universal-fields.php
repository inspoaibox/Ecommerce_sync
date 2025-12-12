<?php
/**
 * 测试新增的3个通用字段
 * - sizeDescriptor
 * - sofa_and_loveseat_design
 * - sofa_bed_size
 */

require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 测试新增通用字段 ===\n\n";

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

// 使用反射访问私有方法
$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// ============================================
// 测试1: sizeDescriptor 字段
// ============================================
echo "【测试1: sizeDescriptor 字段】\n";
echo str_repeat("=", 60) . "\n\n";

$method = $reflection->getMethod('extract_size_descriptor');
$method->setAccessible(true);

$test_cases_size = [
    ['name' => 'Compact Space-Saving Sofa', 'expected' => 'Compact'],
    ['name' => 'Extra Large Sectional Sofa', 'expected' => 'Extra Large'],
    ['name' => 'Oversized Comfortable Couch', 'expected' => 'Oversized'],
    ['name' => 'Mini Loveseat for Small Spaces', 'expected' => 'Mini'],
    ['name' => 'Slim Modern Sofa', 'expected' => 'Slim'],
    ['name' => 'XXL Giant Sectional', 'expected' => 'XXL'],
    ['name' => 'Travel Size Portable Sofa', 'expected' => 'Travel'],
    ['name' => 'Medium Size Couch', 'expected' => 'Medium'],
    ['name' => 'Standard Sofa', 'expected' => 'Regular'], // 无匹配，默认值
    ['name' => 'Queen Size Sleeper Sofa', 'expected' => 'Regular'], // 无尺寸描述符
];

$passed = 0;
$failed = 0;

foreach ($test_cases_size as $index => $test) {
    $product = new WC_Product_Simple();
    $product->set_name($test['name']);
    
    $result = $method->invoke($mapper, $product);
    
    echo "测试 " . ($index + 1) . ": {$test['name']}\n";
    echo "  期望: {$test['expected']}\n";
    echo "  结果: " . ($result ?? 'null') . "\n";
    
    if ($result === $test['expected']) {
        echo "  ✅ 通过\n";
        $passed++;
    } else {
        echo "  ❌ 失败\n";
        $failed++;
    }
    echo "\n";
}

echo "sizeDescriptor 测试总结: 通过 {$passed}/{" . count($test_cases_size) . "}\n";
echo str_repeat("=", 60) . "\n\n";

// ============================================
// 测试2: sofa_and_loveseat_design 字段
// ============================================
echo "【测试2: sofa_and_loveseat_design 字段】\n";
echo str_repeat("=", 60) . "\n\n";

$method = $reflection->getMethod('extract_sofa_loveseat_design');
$method->setAccessible(true);

$test_cases_design = [
    [
        'name' => 'Mid-Century Modern Sofa',
        'description' => 'Beautiful mid-century design',
        'expected' => ['Mid-Century Modern']
    ],
    [
        'name' => 'Tuxedo Style Loveseat',
        'description' => 'Classic tuxedo arm design',
        'expected' => ['Tuxedo']
    ],
    [
        'name' => 'Camelback Sofa',
        'description' => 'Traditional camel back style',
        'expected' => ['Camelback']
    ],
    [
        'name' => 'Club Chair Style Sofa',
        'description' => 'Comfortable club style seating',
        'expected' => ['Club']
    ],
    [
        'name' => 'Lawson Sofa',
        'description' => 'Classic Lawson style design',
        'expected' => ['Lawson']
    ],
    [
        'name' => 'Divan Daybed',
        'description' => 'Elegant divan style',
        'expected' => ['Divan']
    ],
    [
        'name' => 'Cabriole Leg Sofa',
        'description' => 'Features beautiful cabriole legs',
        'expected' => ['Cabriole']
    ],
    [
        'name' => 'Recamier Chaise',
        'description' => 'Classic recamier design',
        'expected' => ['Recamier']
    ],
    [
        'name' => 'Modern Sofa',
        'description' => 'Contemporary design',
        'expected' => ['Mid-Century Modern'] // 默认值
    ],
    [
        'name' => 'Retro Tuxedo Sofa',
        'description' => 'Vintage modern style with tuxedo arms',
        'expected' => ['Mid-Century Modern', 'Tuxedo'] // 多个匹配
    ]
];

$passed2 = 0;
$failed2 = 0;

foreach ($test_cases_design as $index => $test) {
    $product = new WC_Product_Simple();
    $product->set_name($test['name']);
    $product->set_description($test['description'] ?? '');
    
    $result = $method->invoke($mapper, $product);
    
    echo "测试 " . ($index + 1) . ": {$test['name']}\n";
    echo "  期望: " . json_encode($test['expected'], JSON_UNESCAPED_UNICODE) . "\n";
    echo "  结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    
    // 排序后比较
    sort($result);
    $expected = $test['expected'];
    sort($expected);
    
    if ($result === $expected) {
        echo "  ✅ 通过\n";
        $passed2++;
    } else {
        echo "  ❌ 失败\n";
        $failed2++;
    }
    echo "\n";
}

echo "sofa_and_loveseat_design 测试总结: 通过 {$passed2}/{" . count($test_cases_design) . "}\n";
echo str_repeat("=", 60) . "\n\n";

// ============================================
// 测试3: sofa_bed_size 字段
// ============================================
echo "【测试3: sofa_bed_size 字段】\n";
echo str_repeat("=", 60) . "\n\n";

$method = $reflection->getMethod('extract_sofa_bed_size');
$method->setAccessible(true);

$test_cases_bed_size = [
    [
        'name' => 'Queen Size Sleeper Sofa',
        'description' => 'Converts to a queen size bed',
        'expected' => 'Queen'
    ],
    [
        'name' => 'King Size Sofa Bed',
        'description' => 'Large king-size sleeping surface',
        'expected' => 'King'
    ],
    [
        'name' => 'Full Size Sleeper',
        'description' => 'Full size mattress included',
        'expected' => 'Full'
    ],
    [
        'name' => 'Twin Sofa Bed',
        'description' => 'Perfect twin size for small spaces',
        'expected' => 'Twin'
    ],
    [
        'name' => 'Double Bed Sofa',
        'description' => 'Converts to a comfortable double bed',
        'expected' => 'Full' // double = full
    ],
    [
        'name' => 'Single Bed Sleeper',
        'description' => 'Single bed size',
        'expected' => 'Twin' // single = twin
    ],
    [
        'name' => 'Regular Sofa',
        'description' => 'Standard sofa without bed function',
        'expected' => null // 无匹配
    ],
    [
        'name' => 'Modern Couch',
        'description' => 'Contemporary design',
        'expected' => null // 无匹配
    ]
];

$passed3 = 0;
$failed3 = 0;

foreach ($test_cases_bed_size as $index => $test) {
    $product = new WC_Product_Simple();
    $product->set_name($test['name']);
    $product->set_description($test['description'] ?? '');
    
    $result = $method->invoke($mapper, $product);
    
    echo "测试 " . ($index + 1) . ": {$test['name']}\n";
    echo "  期望: " . ($test['expected'] ?? 'null') . "\n";
    echo "  结果: " . ($result ?? 'null') . "\n";
    
    if ($result === $test['expected']) {
        echo "  ✅ 通过\n";
        $passed3++;
    } else {
        echo "  ❌ 失败\n";
        $failed3++;
    }
    echo "\n";
}

echo "sofa_bed_size 测试总结: 通过 {$passed3}/{" . count($test_cases_bed_size) . "}\n";
echo str_repeat("=", 60) . "\n\n";

// ============================================
// 总体测试总结
// ============================================
echo "【总体测试总结】\n";
echo str_repeat("=", 60) . "\n";
$total_tests = count($test_cases_size) + count($test_cases_design) + count($test_cases_bed_size);
$total_passed = $passed + $passed2 + $passed3;
$total_failed = $failed + $failed2 + $failed3;

echo "总测试数: {$total_tests}\n";
echo "通过: {$total_passed}\n";
echo "失败: {$total_failed}\n";
echo "通过率: " . round(($total_passed / $total_tests) * 100, 2) . "%\n\n";

if ($total_failed === 0) {
    echo "✅ 所有测试通过！3个新字段配置成功！\n";
} else {
    echo "❌ 有测试失败，请检查配置\n";
}
?>

