<?php
/**
 * 测试 长*宽*高 格式的尺寸提取
 * 例如: Table: 25 in * 30 in * 20 in 或 Chair: 18 * 20 * 38 in
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 测试 长*宽*高 格式尺寸提取 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
require_once dirname(__FILE__) . '/../../../wp-config.php';
require_once dirname(__FILE__) . '/includes/class-product-mapper.php';

$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射访问private方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

// 测试用例
$test_cases = [
    [
        'name' => '测试1 - Table完整单位格式 (25 in * 30 in * 20 in)',
        'content' => 'Dining Set with Table: 72 in * 36 in * 30 in, Chair: 18 in * 20 in * 38 in',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.0, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.0, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试2 - 只有最后一个单位 (25 * 30 * 20 in)',
        'content' => 'Dining Table: 72 * 36 * 30 in, Chairs: 18 * 20 * 38 in',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.0, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.0, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试3 - 使用x代替* (72x36x30 in)',
        'content' => 'Table: 72x36x30 in, Chair: 18x20x38 in',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.0, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.0, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试4 - 使用X代替* (72X36X30 inches)',
        'content' => 'Dining Table: 72X36X30 inches, Chairs: 18X20X38 inches',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.0, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.0, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试5 - 带引号 (72"x36"x30")',
        'content' => 'Table: 72"x36"x30", Chair: 18"x20"x38"',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.0, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.0, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试6 - 使用ft单位 (6 ft * 3 ft * 2.5 ft)',
        'content' => 'Table: 6 ft * 3 ft * 2.5 ft',
        'expected' => [
            'table_length' => ['measure' => 6.0, 'unit' => 'ft'],
            'table_width' => ['measure' => 3.0, 'unit' => 'ft']
        ]
    ],
    [
        'name' => '测试7 - 混合格式 (Table用*，Chair用x)',
        'content' => 'Dining Set: Table 72 * 36 * 30 in, Chair 18x20x38 in',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.0, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.0, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试8 - 带空格和不带空格 (72 x 36 x 30 vs 72x36x30)',
        'content' => 'Table: 72 x 36 x 30 in, Chair: 18x20x38in',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.0, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.0, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试9 - 只有Table的长*宽格式（没有高度）',
        'content' => 'Dining Table: 72 x 36 in',
        'expected' => [
            'table_length' => ['measure' => 72.0, 'unit' => 'in'],
            'table_width' => ['measure' => 36.0, 'unit' => 'in']
        ]
    ],
    [
        'name' => '测试10 - 小数点尺寸 (72.5 x 36.25 x 30.75 in)',
        'content' => 'Table: 72.5 x 36.25 x 30.75 in, Chair: 18.5 x 20.25 x 38.5 in',
        'expected' => [
            'table_length' => ['measure' => 72.5, 'unit' => 'in'],
            'table_width' => ['measure' => 36.25, 'unit' => 'in'],
            'overall_chair_width' => ['measure' => 18.5, 'unit' => 'in'],
            'overall_chair_depth' => ['measure' => 20.25, 'unit' => 'in'],
            'overall_chair_height' => ['measure' => 38.5, 'unit' => 'in']
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
            echo "  预期: " . json_encode($expected_value) . "\n";
            echo "  实际: " . (is_array($actual_value) ? json_encode($actual_value) : ($actual_value ?? 'NULL')) . "\n";
            
            // 验证
            $match = false;
            if (is_array($expected_value) && is_array($actual_value)) {
                if (json_encode($expected_value) === json_encode($actual_value)) {
                    $match = true;
                }
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
    echo "🎉 所有测试通过！长*宽*高格式识别正确！\n";
} else {
    echo "⚠️  存在失败的测试，需要添加对 长*宽*高 格式的支持\n";
}

