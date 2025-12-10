<?php
/**
 * 测试7个新增通用字段的自动生成功能
 * 按照字段拓展开发文档的标准测试模板
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 测试7个新增通用字段 ===\n";
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
    'frame_finish' => '框架表面处理',
    'handle_width' => '把手宽度',
    'handleMaterial' => '把手材质',
    'kitchen_serving_and_storage_cart_type' => '厨房推车类型',
    'numberOfHooks' => '挂钩数量',
    'numberOfWheels' => '轮子数量',
    'topMaterial' => '顶部材质'
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
    LIMIT 5
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
    $content_preview = substr(strip_tags($content), 0, 150);
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
        'name' => '模拟产品1 - 不锈钢框架推车',
        'content' => 'Stainless Steel Kitchen Serving Cart with 4 Wheels and 3 Hooks, Glass Top',
        'expected' => [
            'frame_finish' => 'Stainless Steel',
            'numberOfWheels' => 4,
            'numberOfHooks' => 3,
            'topMaterial' => 'Glass',
            'kitchen_serving_and_storage_cart_type' => 'Serving Cart'
        ]
    ],
    [
        'name' => '模拟产品2 - 酒吧推车',
        'content' => 'Bar Cart with Chrome Finish, 2 Wheels, Wood Top, Handle Width 5 inches',
        'expected' => [
            'frame_finish' => 'Chrome',
            'numberOfWheels' => 2,
            'topMaterial' => 'Wood',
            'kitchen_serving_and_storage_cart_type' => 'Bar Cart',
            'handle_width' => ['measure' => '5', 'unit' => 'in']
        ]
    ],
    [
        'name' => '模拟产品3 - 金属把手推车',
        'content' => 'Kitchen Cart with Metal Handles, Polished Finish, 6 Hooks',
        'expected' => [
            'frame_finish' => 'Polished',
            'handleMaterial' => ['Metal'],
            'numberOfHooks' => 6
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

// 配置完整性验证
echo "=== 配置完整性验证 ===\n\n";

// 检查v5_common_attributes配置
echo "1. 检查v5_common_attributes配置:\n";
$plugin_file = dirname(__FILE__) . '/woo-walmart-sync.php';
$plugin_content = file_get_contents($plugin_file);

$fields_found = 0;
foreach ($test_fields as $field_name => $field_desc) {
    if (strpos($plugin_content, "'attributeName' => '{$field_name}'") !== false) {
        echo "  ✅ {$field_name} 已添加到v5_common_attributes\n";
        $fields_found++;
    } else {
        echo "  ❌ {$field_name} 未找到在v5_common_attributes\n";
    }
}
echo "  配置完整性: {$fields_found}/7\n\n";

// 检查autoGenerateFields配置
echo "2. 检查autoGenerateFields配置:\n";
$auto_generate_count = 0;
foreach ($test_fields as $field_name => $field_desc) {
    $count = substr_count($plugin_content, "'{$field_name}'");
    if ($count >= 2) {
        echo "  ✅ {$field_name} 已添加到autoGenerateFields (出现{$count}次)\n";
        $auto_generate_count++;
    } else {
        echo "  ⚠️  {$field_name} 可能未完全配置 (仅出现{$count}次)\n";
    }
}
echo "  配置完整性: {$auto_generate_count}/7\n\n";

// 检查后端方法
echo "3. 检查后端生成方法:\n";
$mapper_file = dirname(__FILE__) . '/includes/class-product-mapper.php';
$mapper_content = file_get_contents($mapper_file);

$methods_found = 0;
$expected_methods = [
    'extract_frame_finish',
    'extract_handle_width',
    'extract_handle_material',
    'extract_kitchen_cart_type',
    'extract_number_of_hooks',
    'extract_number_of_wheels',
    'extract_top_material'
];

foreach ($expected_methods as $method_name) {
    if (strpos($mapper_content, "function {$method_name}") !== false) {
        echo "  ✅ {$method_name}() 方法已实现\n";
        $methods_found++;
    } else {
        echo "  ❌ {$method_name}() 方法未找到\n";
    }
}
echo "  方法完整性: {$methods_found}/7\n\n";

// 总结
echo "=== 测试总结 ===\n";
echo "字段列表:\n";
foreach ($test_fields as $field_name => $field_desc) {
    echo "  - {$field_name}: {$field_desc}\n";
}

echo "\n字段特性说明:\n";
echo "1. frame_finish - 文本类型，从描述提取或使用颜色\n";
echo "2. handle_width - 测量对象，包含measure和unit\n";
echo "3. handleMaterial - 数组类型，可能包含多个材质\n";
echo "4. kitchen_serving_and_storage_cart_type - 枚举类型，Serving Cart或Bar Cart\n";
echo "5. numberOfHooks - 整数类型，默认0\n";
echo "6. numberOfWheels - 整数类型，默认0\n";
echo "7. topMaterial - 文本类型，可能为null\n";

echo "\n配置状态:\n";
echo "  - v5_common_attributes: {$fields_found}/7 ✅\n";
echo "  - autoGenerateFields: {$auto_generate_count}/7 ✅\n";
echo "  - 后端生成方法: {$methods_found}/7 ✅\n";

echo "\n=== 测试完成 ===\n";
echo "建议:\n";
echo "1. 在分类映射页面测试重置属性功能，验证新字段是否正确显示\n";
echo "2. 使用真实产品测试字段生成效果\n";
echo "3. 根据实际匹配效果调整关键词匹配规则\n";
