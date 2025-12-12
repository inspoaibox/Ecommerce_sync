<?php
/**
 * 测试 Sofas & Couches 分类的 features 字段
 */

require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 测试 Sofas & Couches 分类的 features 字段 ===\n\n";

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

$mapper = new Woo_Walmart_Product_Mapper();

// 测试用例
$test_cases = [
    [
        'name' => '测试1: 包含 Reclining 关键词',
        'product_name' => 'Modern Reclining Sofa with USB Charging Port',
        'description' => 'This comfortable reclining sofa features adjustable back positions and built-in USB ports for convenient charging.',
        'expected' => ['Reclining', 'USB']
    ],
    [
        'name' => '测试2: 包含 Tufted 和 Storage 关键词',
        'description' => 'Elegant tufted sofa with hidden storage compartment underneath the seat. Features button tufted backrest and spacious drawer.',
        'expected' => ['Tufted', 'Storage']
    ],
    [
        'name' => '测试3: 包含 Nailhead Trim 关键词',
        'product_name' => 'Classic Sofa with Nailhead Trim',
        'description' => 'Traditional design sofa featuring decorative nailhead trim along the arms and base.',
        'expected' => ['Nailhead Trim']
    ],
    [
        'name' => '测试4: 包含 Massaging 关键词',
        'description' => 'Luxury massage sofa with therapeutic vibration function. Includes wireless remote control for easy adjustment.',
        'expected' => ['Massaging']
    ],
    [
        'name' => '测试5: 包含 Multifunctional 关键词',
        'product_name' => 'Convertible Sleeper Sofa',
        'description' => 'Versatile sofa bed that easily converts into a comfortable sleeping surface. Perfect for small spaces.',
        'expected' => ['Multifunctional']
    ],
    [
        'name' => '测试6: 包含多个特性',
        'product_name' => 'Premium Reclining Sofa with USB and Storage',
        'description' => 'Multi-functional reclining sofa with tufted cushions, USB charging ports, hidden storage compartments, and massage function.',
        'expected' => ['Reclining', 'USB', 'Tufted', 'Storage', 'Multifunctional', 'Massaging']
    ],
    [
        'name' => '测试7: 没有匹配的关键词（应返回默认值）',
        'product_name' => 'Simple Modern Sofa',
        'description' => 'Clean lines and contemporary design. Made with high-quality fabric and solid wood frame.',
        'expected' => ['Multifunctional']
    ],
    [
        'name' => '测试8: Futon 关键词（应匹配 Multifunctional）',
        'product_name' => 'Futon Sofa Bed',
        'description' => 'Space-saving futon that transforms from sofa to bed. Ideal for apartments and guest rooms.',
        'expected' => ['Multifunctional']
    ],
    [
        'name' => '测试9: Pull out 关键词（应匹配 Multifunctional）',
        'description' => 'Comfortable sofa with pull out bed mechanism. Easy to convert for overnight guests.',
        'expected' => ['Multifunctional']
    ],
    [
        'name' => '测试10: USB Port 变体关键词',
        'description' => 'Modern sofa with built-in charging ports and power outlets for your devices.',
        'expected' => ['USB']
    ]
];

$passed = 0;
$failed = 0;

foreach ($test_cases as $index => $test) {
    echo "{$test['name']}\n";
    
    // 创建模拟产品
    $product_data = [
        'name' => $test['product_name'] ?? 'Test Sofa',
        'description' => $test['description'] ?? '',
        'short_description' => $test['short_description'] ?? ''
    ];
    
    // 创建临时产品对象
    $product = new WC_Product_Simple();
    $product->set_name($product_data['name']);
    $product->set_description($product_data['description']);
    $product->set_short_description($product_data['short_description']);
    
    // 测试特性提取
    $result = $mapper->test_extract_features_walmart_category($product, 'Sofas & Couches');
    
    echo "  产品名称: {$product_data['name']}\n";
    echo "  描述: " . substr($product_data['description'], 0, 80) . "...\n";
    echo "  提取结果: " . (is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : 'null') . "\n";
    echo "  期望结果: " . json_encode($test['expected'], JSON_UNESCAPED_UNICODE) . "\n";
    
    // 验证结果
    if (is_array($result) && is_array($test['expected'])) {
        sort($result);
        sort($test['expected']);
        
        if ($result === $test['expected']) {
            echo "  ✅ 通过\n";
            $passed++;
        } else {
            echo "  ❌ 失败：结果不匹配\n";
            echo "    差异：\n";
            $missing = array_diff($test['expected'], $result);
            $extra = array_diff($result, $test['expected']);
            if (!empty($missing)) {
                echo "    缺少: " . json_encode($missing, JSON_UNESCAPED_UNICODE) . "\n";
            }
            if (!empty($extra)) {
                echo "    多余: " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n";
            }
            $failed++;
        }
    } else {
        echo "  ❌ 失败：结果类型不匹配\n";
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
    echo "\n✅ 所有测试通过！Sofas & Couches 分类的 features 字段配置成功！\n";
} else {
    echo "\n❌ 有测试失败，请检查配置\n";
}
?>

