<?php
/**
 * 测试真实产品的三个字段提取
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

echo "=== 测试真实产品的三个字段 ===\n\n";

// 真实产品SKU列表
$test_skus = [
    'W714P357249',
    'W487S00390',
    'WF310165AAA',
    'WY000387AAA',
    'N723S9687C',
    'W834S00471',
    'WF310166AAA',
    'W2311P345745',
    'W487S00388',
    'W2824S00132'
];

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// 获取三个提取方法
$method_design = $reflection->getMethod('extract_sofa_loveseat_design');
$method_design->setAccessible(true);

$method_size_desc = $reflection->getMethod('extract_size_descriptor');
$method_size_desc->setAccessible(true);

$method_bed_size = $reflection->getMethod('extract_sofa_bed_size');
$method_bed_size->setAccessible(true);

$success_count = 0;
$total_count = 0;

foreach ($test_skus as $sku) {
    // 通过SKU查找产品
    $product_id = wc_get_product_id_by_sku($sku);
    
    if (!$product_id) {
        echo "⚠️ SKU: {$sku} - 产品不存在\n\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        echo "⚠️ SKU: {$sku} - 无法加载产品\n\n";
        continue;
    }
    
    $total_count++;
    
    echo str_repeat("=", 80) . "\n";
    echo "产品 {$total_count}: {$product->get_name()}\n";
    echo str_repeat("=", 80) . "\n";
    echo "SKU: {$sku}\n";
    echo "ID: {$product_id}\n";
    
    // 显示产品内容摘要
    $name = $product->get_name();
    $desc = $product->get_description();
    $short_desc = $product->get_short_description();
    
    echo "\n产品内容:\n";
    echo "  标题: " . substr($name, 0, 100) . (strlen($name) > 100 ? '...' : '') . "\n";
    echo "  描述长度: " . strlen($desc) . " 字符\n";
    echo "  简短描述长度: " . strlen($short_desc) . " 字符\n";
    
    echo "\n字段提取结果:\n";
    echo str_repeat("-", 80) . "\n";
    
    $has_any_value = false;
    
    // 测试 sofa_and_loveseat_design
    try {
        $design_result = $method_design->invoke($mapper, $product);
        echo "\n1. sofa_and_loveseat_design:\n";
        echo "   返回值: " . json_encode($design_result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "   类型: " . gettype($design_result) . "\n";
        
        if (is_array($design_result) && !empty($design_result)) {
            echo "   ✅ 成功提取: " . implode(', ', $design_result) . "\n";
            $has_any_value = true;
        } elseif (is_null($design_result)) {
            echo "   ⚠️ 返回 null\n";
        } else {
            echo "   ⚠️ 返回空数组\n";
        }
    } catch (Exception $e) {
        echo "\n1. sofa_and_loveseat_design:\n";
        echo "   ❌ 错误: " . $e->getMessage() . "\n";
    }
    
    // 测试 sizeDescriptor
    try {
        $size_desc_result = $method_size_desc->invoke($mapper, $product);
        echo "\n2. sizeDescriptor:\n";
        echo "   返回值: " . json_encode($size_desc_result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "   类型: " . gettype($size_desc_result) . "\n";
        
        if (!is_null($size_desc_result) && $size_desc_result !== '') {
            echo "   ✅ 成功提取: {$size_desc_result}\n";
            $has_any_value = true;
        } else {
            echo "   ⚠️ 未提取到值\n";
        }
    } catch (Exception $e) {
        echo "\n2. sizeDescriptor:\n";
        echo "   ❌ 错误: " . $e->getMessage() . "\n";
    }
    
    // 测试 sofa_bed_size
    try {
        $bed_size_result = $method_bed_size->invoke($mapper, $product);
        echo "\n3. sofa_bed_size:\n";
        echo "   返回值: " . json_encode($bed_size_result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "   类型: " . gettype($bed_size_result) . "\n";
        
        if (!is_null($bed_size_result) && $bed_size_result !== '') {
            echo "   ✅ 成功提取: {$bed_size_result}\n";
            $has_any_value = true;
        } else {
            echo "   ⚠️ 未提取到值\n";
        }
    } catch (Exception $e) {
        echo "\n3. sofa_bed_size:\n";
        echo "   ❌ 错误: " . $e->getMessage() . "\n";
    }
    
    if ($has_any_value) {
        $success_count++;
    }
    
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "【测试总结】\n";
echo str_repeat("=", 80) . "\n";
echo "测试产品总数: {$total_count}\n";
echo "至少提取到一个字段的产品数: {$success_count}\n";
echo "成功率: " . ($total_count > 0 ? round($success_count / $total_count * 100, 2) : 0) . "%\n";

if ($success_count > 0) {
    echo "\n✅ 字段提取功能正常工作！\n";
} else {
    echo "\n⚠️ 所有产品都未能提取到字段值，请检查产品内容或提取逻辑。\n";
}

echo "\n测试完成！\n";
?>

