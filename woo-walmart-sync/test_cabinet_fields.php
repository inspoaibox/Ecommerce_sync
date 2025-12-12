<?php
/**
 * 测试柜体相关字段的生成功能
 * 验证新增的4个字段：cabinet_color, cabinet_material, hardwareFinish, recommendedRooms
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 柜体相关字段测试脚本 ===\n";
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

// 测试字段列表
$test_fields = [
    'cabinet_color' => 'Cabinet Color',
    'cabinet_material' => 'Cabinet Material', 
    'hardwareFinish' => 'Hardware Finish',
    'recommendedRooms' => 'Recommended Rooms'
];

// 获取测试产品
$products = wc_get_products([
    'limit' => 3,
    'status' => 'publish'
]);

if (empty($products)) {
    echo "❌ 没有找到可测试的产品\n";
    exit;
}

echo "找到 " . count($products) . " 个测试产品\n\n";

// 创建映射器实例
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

foreach ($products as $product) {
    echo "=== 测试产品: {$product->get_name()} (ID: {$product->get_id()}) ===\n";
    echo "SKU: " . $product->get_sku() . "\n";
    echo "描述长度: " . strlen($product->get_description()) . " 字符\n\n";

    foreach ($test_fields as $field_name => $field_title) {
        echo "🔧 测试字段: {$field_title} ({$field_name})\n";
        
        try {
            $start_time = microtime(true);
            $result = $method->invoke($mapper, $field_name, $product, 1);
            $end_time = microtime(true);
            $execution_time = round(($end_time - $start_time) * 1000, 2);
            
            echo "  执行时间: {$execution_time}ms\n";
            echo "  结果类型: " . gettype($result) . "\n";
            
            if (is_null($result)) {
                echo "  结果值: NULL (字段将不会传递)\n";
            } elseif (is_array($result)) {
                echo "  结果值: [数组，" . count($result) . " 个元素]\n";
                if (count($result) <= 5) {
                    echo "  数组内容: " . implode(', ', $result) . "\n";
                } else {
                    echo "  前5个元素: " . implode(', ', array_slice($result, 0, 5)) . "...\n";
                }
            } else {
                $display_value = strlen($result) > 100 ? substr($result, 0, 100) . '...' : $result;
                echo "  结果值: {$display_value}\n";
                echo "  字符长度: " . strlen($result) . "\n";
            }
            
            // 验证字段长度限制
            if ($field_name === 'cabinet_color' && !is_null($result) && strlen($result) > 80) {
                echo "  ⚠️ 警告: 柜体颜色超过80字符限制\n";
            } elseif ($field_name === 'cabinet_material' && !is_null($result) && strlen($result) > 400) {
                echo "  ⚠️ 警告: 柜体材质超过400字符限制\n";
            } elseif ($field_name === 'hardwareFinish' && !is_null($result) && strlen($result) > 4000) {
                echo "  ⚠️ 警告: 五金表面处理超过4000字符限制\n";
            }
            
            echo "  ✅ 测试通过\n";
            
        } catch (Exception $e) {
            echo "  ❌ 测试失败: " . $e->getMessage() . "\n";
            echo "  错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
        
        echo "\n";
    }
    
    echo str_repeat('-', 80) . "\n\n";
}

// 测试前端配置一致性
echo "=== 前端配置一致性测试 ===\n";

// 检查autoGenerateFields配置
$main_file_content = file_get_contents(__DIR__ . '/woo-walmart-sync.php');

$auto_generate_count = 0;
if (preg_match_all('/cabinet_color|cabinet_material|hardwareFinish|recommendedRooms/', $main_file_content, $matches)) {
    $auto_generate_count = count($matches[0]);
}

echo "在主文件中找到新字段引用: {$auto_generate_count} 次\n";

if ($auto_generate_count >= 8) { // 每个字段在两个autoGenerateFields数组中各出现一次，加上字段说明
    echo "✅ 前端配置检查通过\n";
} else {
    echo "⚠️ 前端配置可能不完整，请检查autoGenerateFields数组\n";
}

// 测试v5_common_attributes配置
$v5_common_count = 0;
if (preg_match_all('/attributeName.*=>\s*[\'\"](cabinet_color|cabinet_material|hardwareFinish|recommendedRooms)/', $main_file_content, $matches)) {
    $v5_common_count = count($matches[1]);
}

echo "在v5_common_attributes中找到新字段定义: {$v5_common_count} 个\n";

if ($v5_common_count >= 4) {
    echo "✅ 后端配置检查通过\n";
} else {
    echo "⚠️ 后端配置可能不完整，请检查v5_common_attributes数组\n";
}

echo "\n=== 测试完成 ===\n";
echo "总结:\n";
echo "- 新增字段数量: " . count($test_fields) . " 个\n";
echo "- 测试产品数量: " . count($products) . " 个\n";
echo "- 前端配置状态: " . ($auto_generate_count >= 8 ? '✅ 正常' : '⚠️ 需检查') . "\n";
echo "- 后端配置状态: " . ($v5_common_count >= 4 ? '✅ 正常' : '⚠️ 需检查') . "\n";
echo "\n请在分类映射页面测试重置属性功能，验证新字段是否正确显示。\n";
?>
