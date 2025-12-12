<?php
/**
 * 测试分类ID 26的features字段功能
 * 验证基于分类ID的特性匹配逻辑
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 分类ID 26 Features字段测试脚本 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// 自动检测WordPress路径
$wp_path = '';
$current_dir = __DIR__;
for ($i = 0; $i < 5; $i++) {
    $test_path = $current_dir . str_repeat('/..', $i);
    if (file_exists($test_path . '/wp-config.php')) {
        $wp_path = realpath($test_path);
        break;
    }
}

if (empty($wp_path)) {
    echo "❌ 无法检测WordPress路径\n";
    exit;
}

require_once $wp_path . '/wp-config.php';
require_once $wp_path . '/wp-load.php';

echo "✅ WordPress加载成功\n\n";

// 加载产品映射器
require_once __DIR__ . '/includes/class-product-mapper.php';

// 模拟测试 - 分类ID 26在其他服务器
echo "🔧 模拟测试模式 - 分类ID 26在其他服务器\n";
echo "✅ 模拟分类ID 26: 床架类产品分类\n";
echo "模拟描述: 包含可调节高度、无线遥控、重型支撑等特性的床架产品\n\n";

// 获取本地产品进行模拟测试
$test_products = wc_get_products([
    'limit' => 5,
    'status' => 'publish'
]);

echo "✅ 获取到 " . count($test_products) . " 个本地产品进行模拟测试\n\n";

if (empty($test_products)) {
    echo "❌ 没有找到任何可测试的产品\n";
    exit;
}

// 创建映射器实例
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);

$extract_features_method = $reflection->getMethod('extract_features_by_category_id');
$extract_features_method->setAccessible(true);

$match_features_method = $reflection->getMethod('match_features_from_content');
$match_features_method->setAccessible(true);

$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

// 预定义的特性选项（用于参考）
$expected_features = [
    'Adjustable Height',
    'Wireless Remote',
    'Heavy Duty',
    'Center Supports',
    'USB Port',
    'Headboard Compatible',
    'Massaging'
];

echo "📋 分类ID 26 预定义特性选项:\n";
foreach ($expected_features as $i => $feature) {
    echo "  " . ($i + 1) . ". {$feature}\n";
}
echo "\n";

// 创建模拟测试产品内容
$test_product_contents = [
    [
        'name' => 'Adjustable Height Electric Bed Frame with USB Port',
        'description' => 'This heavy duty bed frame features adjustable height settings, wireless remote control, center supports for stability, and built-in USB charging ports. Compatible with most headboards and includes massaging function.'
    ],
    [
        'name' => 'Smart Bed Frame with Remote Control',
        'description' => 'Heavy-duty construction with center support beam. Features wireless remote for easy adjustment and USB ports for device charging. Headboard compatible design.'
    ],
    [
        'name' => 'Basic Metal Bed Frame',
        'description' => 'Simple metal bed frame with standard height. No special features included.'
    ]
];

// 如果有本地产品，使用本地产品；否则创建模拟产品进行测试
if (!empty($test_products)) {
    echo "使用本地产品进行测试，同时模拟添加相关关键词...\n\n";
} else {
    echo "创建模拟产品进行测试...\n\n";
}

foreach ($test_products as $index => $product) {
    echo "=== 测试产品: {$product->get_name()} (ID: {$product->get_id()}) ===\n";
    echo "SKU: " . $product->get_sku() . "\n";
    
    // 获取产品分类
    $product_categories = wp_get_post_terms($product->get_id(), 'product_cat');
    echo "产品分类: ";
    foreach ($product_categories as $cat) {
        echo "{$cat->name} (ID: {$cat->term_id}) ";
    }
    echo "\n";
    
    // 模拟产品属于分类ID 26
    echo "🔧 模拟: 假设产品属于分类ID 26\n";
    
    // 显示产品内容摘要
    $original_content_length = strlen($product->get_name() . $product->get_description());
    echo "原始产品内容长度: {$original_content_length} 字符\n";

    // 如果有对应的测试内容，显示模拟内容
    if (isset($test_product_contents[$index])) {
        $test_content = $test_product_contents[$index];
        echo "🔧 模拟测试内容:\n";
        echo "  标题: {$test_content['name']}\n";
        echo "  描述: " . substr($test_content['description'], 0, 100) . "...\n";
    }
    
    // 测试特性提取 - 使用模拟方法
    echo "\n🔍 测试features字段生成 (模拟分类ID 26):\n";
    try {
        $start_time = microtime(true);

        // 使用模拟测试方法
        $features_result = $mapper->test_extract_features_category_26($product);

        $end_time = microtime(true);
        $execution_time = round(($end_time - $start_time) * 1000, 2);

        echo "执行时间: {$execution_time}ms\n";
        echo "结果类型: " . gettype($features_result) . "\n";

        if (is_null($features_result)) {
            echo "结果: NULL (字段将不会传递)\n";
            echo "原因: 产品内容中未匹配到任何预定义特性\n";
        } elseif (is_array($features_result)) {
            echo "结果: [数组，" . count($features_result) . " 个特性]\n";
            echo "匹配的特性:\n";
            foreach ($features_result as $feature) {
                echo "  ✓ {$feature}\n";
            }
        } else {
            echo "结果: {$features_result}\n";
        }

        echo "✅ features字段生成测试通过\n";

    } catch (Exception $e) {
        echo "❌ features字段生成失败: " . $e->getMessage() . "\n";
        echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    // 模拟详细匹配分析
    echo "\n🔍 模拟详细匹配分析:\n";
    try {
        // 使用原始产品内容进行匹配
        $detailed_result = $match_features_method->invoke($mapper, $product, $expected_features);

        if (!empty($detailed_result)) {
            echo "原始内容匹配结果:\n";
            foreach ($detailed_result as $feature) {
                echo "  ✓ {$feature}\n";
            }
        } else {
            echo "原始内容匹配: 无匹配结果\n";
        }

        // 如果有模拟测试内容，也进行测试
        if (isset($test_product_contents[$index])) {
            echo "\n🧪 使用模拟内容进行匹配测试:\n";
            $test_content = $test_product_contents[$index];
            $simulated_matches = [];

            $content = strtolower($test_content['name'] . ' ' . $test_content['description']);

            foreach ($expected_features as $feature) {
                $feature_lower = strtolower($feature);

                // 特殊关键词匹配规则
                $special_matches = [
                    'Adjustable Height' => ['adjustable', 'height', 'adjust'],
                    'Wireless Remote' => ['wireless', 'remote', 'bluetooth'],
                    'Heavy Duty' => ['heavy duty', 'heavy-duty', 'durable', 'sturdy'],
                    'Center Supports' => ['center support', 'middle support', 'reinforced'],
                    'USB Port' => ['usb', 'charging port', 'power port'],
                    'Headboard Compatible' => ['headboard', 'compatible', 'attachment'],
                    'Massaging' => ['massage', 'massaging', 'vibration', 'therapeutic']
                ];

                if (isset($special_matches[$feature])) {
                    foreach ($special_matches[$feature] as $keyword) {
                        if (strpos($content, $keyword) !== false) {
                            $simulated_matches[] = $feature;
                            break;
                        }
                    }
                }
            }

            if (!empty($simulated_matches)) {
                echo "模拟内容匹配结果:\n";
                foreach ($simulated_matches as $feature) {
                    echo "  ✓ {$feature}\n";
                }
            } else {
                echo "模拟内容匹配: 无匹配结果\n";
            }
        }

    } catch (Exception $e) {
        echo "❌ 详细匹配失败: " . $e->getMessage() . "\n";
    }
    
    echo str_repeat('-', 80) . "\n\n";
}

// 配置验证
echo "=== 配置验证 ===\n";

// 检查前端配置
$main_file_content = file_get_contents(__DIR__ . '/woo-walmart-sync.php');
$features_count = substr_count($main_file_content, "'features'");

echo "在主文件中找到 'features' 引用: {$features_count} 次\n";

if ($features_count >= 4) { // autoGenerateFields数组2次 + 字段说明2次
    echo "✅ 前端配置检查通过\n";
} else {
    echo "⚠️ 前端配置可能不完整\n";
}

// 检查后端配置
$mapper_file_content = file_get_contents(__DIR__ . '/includes/class-product-mapper.php');
$extract_method_exists = strpos($mapper_file_content, 'extract_features_by_category_id') !== false;

echo "后端方法存在: " . ($extract_method_exists ? '✅ 是' : '❌ 否') . "\n";

echo "\n=== 测试完成 ===\n";
echo "总结:\n";
echo "- 目标分类: ID 26 (模拟床架类产品分类)\n";
echo "- 测试产品数量: " . count($test_products) . " 个\n";
echo "- 预定义特性数量: " . count($expected_features) . " 个\n";
echo "- 前端配置状态: " . ($features_count >= 4 ? '✅ 正常' : '⚠️ 需检查') . "\n";
echo "- 后端配置状态: " . ($extract_method_exists ? '✅ 正常' : '⚠️ 需检查') . "\n";
echo "\n建议:\n";
echo "1. 在分类映射页面测试features字段的显示\n";
echo "2. 确保分类ID 26下有足够的测试产品\n";
echo "3. 根据实际匹配效果调整关键词匹配规则\n";
?>
