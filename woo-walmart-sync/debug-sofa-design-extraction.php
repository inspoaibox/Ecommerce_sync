<?php
/**
 * 调试 sofa_and_loveseat_design 字段提取逻辑
 */

// WordPress 加载
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("错误：无法找到 WordPress。\n");
}

if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

echo "=== 调试 sofa_and_loveseat_design 字段提取 ===\n\n";

// 创建测试产品
$product = new WC_Product_Simple();
$product->set_name('Modern Mid-Century Sofa');
$product->set_description('Comfortable sofa with modern design');
$product->set_short_description('Mid-century modern style');

echo "测试产品信息:\n";
echo "  名称: " . $product->get_name() . "\n";
echo "  描述: " . $product->get_description() . "\n";
echo "  简短描述: " . $product->get_short_description() . "\n\n";

// 手动模拟提取逻辑
$content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());
echo "合并后的内容（小写）:\n";
echo "  {$content}\n\n";

// 设计风格关键词
$design_keywords = [
    'Recamier' => ['recamier', 'récamier', 'recamiere'],
    'Cabriole' => ['cabriole', 'cabriole leg', 'cabriole legs'],
    'Club' => ['club', 'club chair', 'club style'],
    'Tuxedo' => ['tuxedo', 'tuxedo style', 'tuxedo arm'],
    'Mid-Century Modern' => ['mid-century', 'mid century', 'midcentury', 'mcm', 'retro', 'vintage modern'],
    'Camelback' => ['camelback', 'camel back', 'camel-back'],
    'Lawson' => ['lawson', 'lawson style'],
    'Divan' => ['divan', 'daybed']
];

echo "关键词匹配测试:\n";
echo str_repeat("-", 80) . "\n";

$matched_designs = [];

foreach ($design_keywords as $design => $keywords) {
    echo "\n设计风格: {$design}\n";
    echo "  关键词: " . implode(', ', $keywords) . "\n";
    
    $found = false;
    foreach ($keywords as $keyword) {
        $pos = strpos($content, $keyword);
        if ($pos !== false) {
            echo "  ✅ 匹配到: '{$keyword}' (位置: {$pos})\n";
            $matched_designs[] = $design;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "  ❌ 未匹配\n";
    }
}

echo "\n" . str_repeat("-", 80) . "\n\n";

echo "匹配结果:\n";
if (!empty($matched_designs)) {
    echo "  匹配到的设计风格: " . implode(', ', $matched_designs) . "\n";
    echo "  返回值: " . json_encode(array_unique($matched_designs), JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "  未匹配到任何设计风格\n";
    echo "  返回默认值: [\"Mid-Century Modern\"]\n";
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// 使用实际的提取方法测试
echo "使用实际提取方法测试:\n";
echo str_repeat("-", 80) . "\n";

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('extract_sofa_loveseat_design');
$method->setAccessible(true);

try {
    $result = $method->invoke($mapper, $product);
    echo "返回值: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    echo "类型: " . gettype($result) . "\n";
    
    if (is_null($result)) {
        echo "❌ 返回 null - 这是问题所在！\n";
    } elseif (is_array($result) && !empty($result)) {
        echo "✅ 返回数组，包含 " . count($result) . " 个元素\n";
    } elseif (is_array($result) && empty($result)) {
        echo "⚠️ 返回空数组\n";
    }
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n\n";

// 测试 generate_special_attribute_value 方法
echo "测试 generate_special_attribute_value 方法:\n";
echo str_repeat("-", 80) . "\n";

$method2 = $reflection->getMethod('generate_special_attribute_value');
$method2->setAccessible(true);

try {
    $result2 = $method2->invoke($mapper, 'sofa_and_loveseat_design', $product, 1);
    echo "返回值: " . json_encode($result2, JSON_UNESCAPED_UNICODE) . "\n";
    echo "类型: " . gettype($result2) . "\n";
    
    if (is_null($result2)) {
        echo "❌ 返回 null - 这说明 generate_special_attribute_value 中的 case 有问题！\n";
    } else {
        echo "✅ 返回正常\n";
    }
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}

echo "\n调试完成！\n";
?>

