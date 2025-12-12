<?php
/**
 * 详细诊断 sofa_and_loveseat_design 字段为什么返回null
 * 专门针对产品 W714P357249 进行深度分析
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== sofa_and_loveseat_design 字段详细诊断 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
if (!defined('ABSPATH')) {
    $wp_paths = [
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../../../wp-load.php',
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            echo "✅ WordPress加载成功: {$path}\n";
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("❌ 错误：无法找到WordPress。请手动修改路径。\n");
    }
}

// 加载必要的类
require_once 'includes/class-product-mapper.php';

// 获取失败的产品
$failed_sku = 'W714P357249';
$product_id = wc_get_product_id_by_sku($failed_sku);

if (!$product_id) {
    die("❌ 找不到SKU为 {$failed_sku} 的产品\n");
}

$product = wc_get_product($product_id);
echo "✅ 找到产品: {$product->get_name()} (ID: {$product_id})\n\n";

// ============================================
// 详细分析产品内容
// ============================================
echo "【产品内容分析】\n";
echo str_repeat("-", 80) . "\n";

$product_name = $product->get_name();
$product_description = $product->get_description();
$product_short_description = $product->get_short_description();

echo "产品标题: {$product_name}\n";
echo "产品描述长度: " . strlen($product_description) . " 字符\n";
echo "简短描述长度: " . strlen($product_short_description) . " 字符\n\n";

// 显示描述内容（截取前500字符）
if (!empty($product_description)) {
    echo "产品描述（前500字符）:\n";
    echo substr($product_description, 0, 500) . "\n\n";
}

if (!empty($product_short_description)) {
    echo "简短描述:\n";
    echo $product_short_description . "\n\n";
}

// ============================================
// 模拟 extract_sofa_loveseat_design 方法执行
// ============================================
echo "【模拟字段提取过程】\n";
echo str_repeat("-", 80) . "\n";

// 获取产品内容（与方法中相同的逻辑）
$content = strtolower($product_name . ' ' . $product_description . ' ' . $product_short_description);
echo "合并内容长度: " . strlen($content) . " 字符\n";
echo "合并内容（前200字符）: " . substr($content, 0, 200) . "\n\n";

// 设计风格枚举值及其关键词映射（与方法中相同）
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

$matched_designs = [];

echo "关键词匹配测试:\n";
foreach ($design_keywords as $design => $keywords) {
    echo "测试设计风格: {$design}\n";
    foreach ($keywords as $keyword) {
        $found = strpos($content, $keyword) !== false;
        echo "  - '{$keyword}': " . ($found ? '✅ 找到' : '❌ 未找到') . "\n";
        if ($found) {
            $matched_designs[] = $design;
            echo "    匹配位置: " . strpos($content, $keyword) . "\n";
            break; // 找到匹配就跳到下一个设计风格
        }
    }
    echo "\n";
}

// 去重
$matched_designs = array_unique($matched_designs);

echo "匹配结果:\n";
if (!empty($matched_designs)) {
    echo "✅ 找到匹配的设计风格: " . implode(', ', $matched_designs) . "\n";
    $expected_result = $matched_designs;
} else {
    echo "❌ 没有找到匹配的设计风格\n";
    echo "✅ 应该返回默认值: ['Mid-Century Modern']\n";
    $expected_result = ['Mid-Century Modern'];
}

echo "预期返回值: " . json_encode($expected_result, JSON_UNESCAPED_UNICODE) . "\n\n";

// ============================================
// 实际调用方法测试
// ============================================
echo "【实际方法调用测试】\n";
echo str_repeat("-", 80) . "\n";

try {
    $mapper = new Woo_Walmart_Product_Mapper();
    $reflection = new ReflectionClass($mapper);
    
    // 直接调用 extract_sofa_loveseat_design 方法
    $extract_method = $reflection->getMethod('extract_sofa_loveseat_design');
    $extract_method->setAccessible(true);
    
    echo "调用 extract_sofa_loveseat_design 方法...\n";
    $direct_result = $extract_method->invoke($mapper, $product);
    
    echo "直接调用结果: " . json_encode($direct_result, JSON_UNESCAPED_UNICODE) . "\n";
    echo "结果类型: " . gettype($direct_result) . "\n";
    echo "是否为null: " . (is_null($direct_result) ? 'YES' : 'NO') . "\n\n";
    
    // 通过 generate_special_attribute_value 调用
    $generate_method = $reflection->getMethod('generate_special_attribute_value');
    $generate_method->setAccessible(true);
    
    echo "调用 generate_special_attribute_value 方法...\n";
    $generate_result = $generate_method->invoke($mapper, 'sofa_and_loveseat_design', $product, 1);
    
    echo "generate方法结果: " . json_encode($generate_result, JSON_UNESCAPED_UNICODE) . "\n";
    echo "结果类型: " . gettype($generate_result) . "\n";
    echo "是否为null: " . (is_null($generate_result) ? 'YES' : 'NO') . "\n\n";
    
    // 比较两个结果
    if ($direct_result === $generate_result) {
        echo "✅ 两个方法返回相同结果\n";
    } else {
        echo "❌ 两个方法返回不同结果！\n";
        echo "这表明在 generate_special_attribute_value 中可能有额外的处理逻辑\n";
    }
    
    // 检查是否与预期一致
    if ($generate_result === $expected_result) {
        echo "✅ 实际结果与预期一致\n";
    } else {
        echo "❌ 实际结果与预期不一致！\n";
        echo "预期: " . json_encode($expected_result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "实际: " . json_encode($generate_result, JSON_UNESCAPED_UNICODE) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 方法调用异常: " . $e->getMessage() . "\n";
    echo "异常文件: " . $e->getFile() . "\n";
    echo "异常行号: " . $e->getLine() . "\n";
    echo "异常堆栈:\n" . $e->getTraceAsString() . "\n";
}

// ============================================
// 检查方法是否被正确调用
// ============================================
echo "\n【检查case分支】\n";
echo str_repeat("-", 80) . "\n";

// 检查 generate_special_attribute_value 方法中的 case 分支
$mapper_file = 'includes/class-product-mapper.php';
$content = file_get_contents($mapper_file);

// 查找 sofa_and_loveseat_design case
if (strpos($content, "case 'sofa_and_loveseat_design':") !== false) {
    echo "✅ 找到 sofa_and_loveseat_design case 分支\n";
    
    // 提取相关代码行
    $lines = explode("\n", $content);
    $case_found = false;
    $case_lines = [];
    
    foreach ($lines as $line_num => $line) {
        if (strpos($line, "case 'sofa_and_loveseat_design':") !== false) {
            $case_found = true;
            $case_lines[] = ($line_num + 1) . ": " . trim($line);
            continue;
        }
        
        if ($case_found) {
            $case_lines[] = ($line_num + 1) . ": " . trim($line);
            
            // 如果遇到下一个case或者break，停止
            if (strpos($line, 'case ') !== false && strpos($line, "case 'sofa_and_loveseat_design':") === false) {
                break;
            }
            if (strpos($line, 'break;') !== false || strpos($line, 'return ') !== false) {
                break;
            }
            
            // 限制最多显示10行
            if (count($case_lines) > 10) {
                break;
            }
        }
    }
    
    echo "相关代码:\n";
    foreach ($case_lines as $case_line) {
        echo $case_line . "\n";
    }
    
} else {
    echo "❌ 未找到 sofa_and_loveseat_design case 分支\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【诊断总结】\n";
echo str_repeat("=", 80) . "\n\n";

echo "根据详细分析，问题的可能原因：\n\n";

if (isset($generate_result) && is_null($generate_result)) {
    echo "🚨 **确认问题**: generate_special_attribute_value 返回 null\n\n";
    
    if (isset($direct_result) && !is_null($direct_result)) {
        echo "🔍 **关键发现**: extract_sofa_loveseat_design 方法本身工作正常\n";
        echo "   问题出现在 generate_special_attribute_value 方法的调用过程中\n\n";
        
        echo "🔧 **可能的原因**:\n";
        echo "1. case 分支没有正确匹配\n";
        echo "2. 方法调用过程中发生异常\n";
        echo "3. 参数传递有问题\n";
        echo "4. 方法返回值被其他逻辑覆盖\n\n";
    } else {
        echo "🔍 **关键发现**: extract_sofa_loveseat_design 方法本身返回 null\n";
        echo "   这表明方法内部逻辑有问题\n\n";
        
        echo "🔧 **可能的原因**:\n";
        echo "1. 产品内容获取失败\n";
        echo "2. 字符串处理异常\n";
        echo "3. 默认值返回逻辑被跳过\n";
        echo "4. 方法执行过程中发生异常\n\n";
    }
} else {
    echo "✅ **意外发现**: 方法调用正常，问题可能在其他地方\n\n";
}

echo "=== 详细诊断完成 ===\n";
?>
