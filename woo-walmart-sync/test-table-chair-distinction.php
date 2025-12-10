<?php
/**
 * 测试table和chair尺寸区分
 * 确保不会混淆桌子和椅子的尺寸
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 测试Table和Chair尺寸区分 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
require_once dirname(__FILE__) . '/../../../wp-config.php';
require_once dirname(__FILE__) . '/includes/class-product-mapper.php';

$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射访问private方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

// 测试用例：包含table和chair尺寸的混合描述
$test_cases = [
    [
        'name' => '测试1 - Table和Chair尺寸都存在',
        'content' => 'Dining Set with Table 72 inches long, 36 inches wide, and Chair 18 inches wide, 20 inches deep, 38 inches high',
        'expected' => [
            'table_length' => ['measure' => '72', 'unit' => 'in'],
            'table_width' => ['measure' => '36', 'unit' => 'in'],
            'overall_chair_width' => ['measure' => '18', 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => '20', 'unit' => 'in'],
            'overall_chair_height' => ['measure' => '38', 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试2 - 只有Chair尺寸，没有Table',
        'content' => 'Dining Chairs Set, Chair Width 18 inches, Chair Depth 20 inches, Chair Height 38 inches, 20 inches long seat',
        'expected' => [
            'table_length' => ['measure' => '1', 'unit' => 'in'], // 默认值
            'table_width' => ['measure' => '1', 'unit' => 'in'], // 默认值
            'overall_chair_width' => ['measure' => '18', 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => '20', 'unit' => 'in'],
            'overall_chair_height' => ['measure' => '38', 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试3 - 只有Table尺寸，没有Chair',
        'content' => 'Dining Table Only, Table Length 60 inches, Table Width 30 inches, 30 inches long table top',
        'expected' => [
            'table_length' => ['measure' => '60', 'unit' => 'in'],
            'table_width' => ['measure' => '30', 'unit' => 'in'],
            'overall_chair_width' => null,
            'overall_chair_depth' => null,
            'overall_chair_height' => null
        ]
    ],
    [
        'name' => '测试4 - 复杂描述（Chair在前，Table在后）',
        'content' => 'Set includes 4 chairs 18 inches wide and 38 inches high, plus dining table 70 inches long and 36 inches wide',
        'expected' => [
            'table_length' => ['measure' => '70', 'unit' => 'in'],
            'table_width' => ['measure' => '36', 'unit' => 'in'],
            'overall_chair_width' => ['measure' => '18', 'unit' => 'in'],
            'overall_chair_height' => ['measure' => '38', 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试5 - 使用不同单位',
        'content' => 'Dining Set: Table 6 ft long, 3 ft wide; Chairs 18 in wide, 20 in deep',
        'expected' => [
            'table_length' => ['measure' => '6', 'unit' => 'ft'],
            'table_width' => ['measure' => '3', 'unit' => 'ft'],
            'overall_chair_width' => ['measure' => '18', 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => '20', 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试6 - 容易混淆的描述（18 inches wide出现两次）',
        'content' => 'Table 18 inches wide, Chair 18 inches wide',
        'expected' => [
            'table_width' => ['measure' => '18', 'unit' => 'in'],
            'overall_chair_width' => ['measure' => '18', 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试7 - 只有通用尺寸描述（应该不匹配）',
        'content' => 'Furniture Set, 60 inches long, 30 inches wide, 40 inches high',
        'expected' => [
            'table_length' => ['measure' => '1', 'unit' => 'in'], // 默认值（没有明确table关键词）
            'table_width' => ['measure' => '1', 'unit' => 'in'], // 默认值
            'overall_chair_width' => null,
            'overall_chair_height' => null
        ]
    ]
];

$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

foreach ($test_cases as $test_case) {
    echo "=== {$test_case['name']} ===\n";
    echo "描述: {$test_case['content']}\n\n";
    
    // 创建临时产品
    $temp_product = new WC_Product_Simple();
    $temp_product->set_name($test_case['content']);
    $temp_product->set_description($test_case['content']);
    
    $case_passed = true;
    
    foreach ($test_case['expected'] as $field_name => $expected_value) {
        $total_tests++;
        
        try {
            $actual_value = $method->invoke($mapper, $field_name, $temp_product, 1);
            
            echo "字段: {$field_name}\n";
            echo "  预期: " . (is_array($expected_value) ? json_encode($expected_value) : ($expected_value ?? 'NULL')) . "\n";
            echo "  实际: " . (is_array($actual_value) ? json_encode($actual_value) : ($actual_value ?? 'NULL')) . "\n";
            
            // 验证
            $match = false;
            if ($expected_value === null && $actual_value === null) {
                $match = true;
            } elseif (is_array($expected_value) && is_array($actual_value)) {
                if (json_encode($expected_value) === json_encode($actual_value)) {
                    $match = true;
                }
            } elseif ($expected_value == $actual_value) {
                $match = true;
            }
            
            if ($match) {
                echo "  ✅ 匹配\n";
                $passed_tests++;
            } else {
                echo "  ❌ 不匹配\n";
                $failed_tests++;
                $case_passed = false;
            }
            
        } catch (Exception $e) {
            echo "  ❌ 错误: " . $e->getMessage() . "\n";
            $failed_tests++;
            $case_passed = false;
        }
        
        echo "\n";
    }
    
    if ($case_passed) {
        echo "✅ {$test_case['name']} - 全部通过\n";
    } else {
        echo "❌ {$test_case['name']} - 存在失败\n";
    }
    
    echo str_repeat('=', 70) . "\n\n";
}

// 总结
echo "=== 测试总结 ===\n";
echo "总测试数: {$total_tests}\n";
echo "通过: {$passed_tests} ✅\n";
echo "失败: {$failed_tests} ❌\n";
echo "通过率: " . round(($passed_tests / $total_tests) * 100, 2) . "%\n\n";

if ($failed_tests === 0) {
    echo "🎉 所有测试通过！Table和Chair尺寸区分正确！\n";
} else {
    echo "⚠️  存在失败的测试，需要调整正则表达式\n";
}

