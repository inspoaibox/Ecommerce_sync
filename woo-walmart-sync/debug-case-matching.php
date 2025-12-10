<?php
/**
 * 诊断 generate_special_attribute_value 方法中的case匹配问题
 * 重点检查属性名转换逻辑
 */

echo "=== case匹配问题诊断 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// 模拟 generate_special_attribute_value 方法中的属性名处理逻辑
$attribute_name = 'sofa_and_loveseat_design';
echo "原始属性名: {$attribute_name}\n";

// 这是方法中第730行的逻辑
$attr_lower = strtolower(str_replace(['_', '-'], '', $attribute_name));
echo "转换后的属性名: {$attr_lower}\n\n";

// 检查转换后的名称是否能匹配case
$expected_cases = [
    'sofa_and_loveseat_design' => 'sofaandloveseatdesign',
    'sizeDescriptor' => 'sizedescriptor', 
    'sofa_bed_size' => 'sofabedsize'
];

echo "属性名转换对比:\n";
foreach ($expected_cases as $original => $expected) {
    $converted = strtolower(str_replace(['_', '-'], '', $original));
    $matches = ($converted === $expected);
    echo "- {$original} -> {$converted} " . ($matches ? '✅' : '❌') . "\n";
}

echo "\n🚨 **问题发现**:\n";
echo "原始属性名: sofa_and_loveseat_design\n";
echo "转换后: {$attr_lower}\n";
echo "case中查找: sofaandloveseatdesign\n\n";

// 检查实际的case分支
echo "检查实际case分支:\n";

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
            break;
        }
    }
}

// 读取映射器文件
$mapper_file = 'includes/class-product-mapper.php';
if (file_exists($mapper_file)) {
    $content = file_get_contents($mapper_file);
    
    // 查找所有case分支
    $lines = explode("\n", $content);
    $in_switch = false;
    $switch_started = false;
    $cases_found = [];
    
    foreach ($lines as $line_num => $line) {
        // 检测switch语句开始
        if (strpos($line, 'switch ($attr_lower)') !== false) {
            $switch_started = true;
            $in_switch = true;
            echo "找到switch语句在第" . ($line_num + 1) . "行\n";
            continue;
        }
        
        if ($switch_started && $in_switch) {
            // 查找case分支
            if (preg_match("/case '([^']+)':/", $line, $matches)) {
                $case_name = $matches[1];
                $cases_found[] = $case_name;
                
                // 特别检查我们关心的case
                if (strpos($case_name, 'sofa') !== false || strpos($case_name, 'loveseat') !== false) {
                    echo "✅ 找到相关case: '{$case_name}' 在第" . ($line_num + 1) . "行\n";
                }
            }
            
            // 检测switch结束
            if (strpos($line, '}') !== false && strpos($line, 'switch') === false) {
                // 简单的结束检测，可能不够精确
                $brace_count = substr_count($line, '}') - substr_count($line, '{');
                if ($brace_count > 0) {
                    // 可能是switch结束，但这个检测不够精确
                }
            }
        }
    }
    
    echo "\n所有找到的case分支:\n";
    foreach ($cases_found as $case) {
        echo "- '{$case}'\n";
        
        // 检查是否匹配我们的转换后名称
        if ($case === $attr_lower) {
            echo "  ✅ 匹配转换后的属性名\n";
        }
    }
    
    // 特别检查是否存在原始名称的case
    if (in_array('sofa_and_loveseat_design', $cases_found)) {
        echo "\n✅ 找到原始名称的case: 'sofa_and_loveseat_design'\n";
        echo "❌ 但转换后的名称是: '{$attr_lower}'\n";
        echo "🚨 **这就是问题所在！case不匹配！**\n\n";
    } elseif (in_array($attr_lower, $cases_found)) {
        echo "\n✅ 找到转换后名称的case: '{$attr_lower}'\n";
    } else {
        echo "\n❌ 没有找到匹配的case分支\n";
        echo "🚨 **这就是问题所在！没有对应的case！**\n\n";
    }
    
} else {
    echo "❌ 找不到映射器文件\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【问题总结】\n";
echo str_repeat("=", 80) . "\n\n";

echo "🎯 **根本原因**:\n";
echo "1. generate_special_attribute_value 方法在第730行对属性名进行了转换:\n";
echo "   \$attr_lower = strtolower(str_replace(['_', '-'], '', \$attribute_name));\n\n";

echo "2. 转换过程:\n";
echo "   'sofa_and_loveseat_design' -> 'sofaandloveseatdesign'\n\n";

echo "3. 但是switch语句中的case分支使用的是原始名称:\n";
echo "   case 'sofa_and_loveseat_design':\n\n";

echo "4. 因此无法匹配，导致方法返回null\n\n";

echo "🔧 **解决方案**:\n";
echo "需要在switch语句中添加转换后名称的case分支:\n";
echo "case 'sofaandloveseatdesign':\n";
echo "    return \$this->extract_sofa_loveseat_design(\$product);\n\n";

echo "或者修改属性名转换逻辑，保持原始名称不变。\n\n";

echo "=== 诊断完成 ===\n";
?>
