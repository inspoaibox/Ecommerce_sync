<?php
/**
 * 检查代码完整性和逻辑一致性
 * 查找可能导致问题的代码变化
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 代码完整性检查 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// ============================================
// 检查1: 文件完整性
// ============================================
echo "【检查1: 文件完整性】\n";
echo str_repeat("-", 50) . "\n";

$mapper_file = 'includes/class-product-mapper.php';
if (!file_exists($mapper_file)) {
    die("❌ 核心文件不存在: {$mapper_file}\n");
}

$file_size = filesize($mapper_file);
$line_count = count(file($mapper_file));

echo "✅ 文件存在: {$mapper_file}\n";
echo "文件大小: " . number_format($file_size) . " bytes\n";
echo "总行数: {$line_count} 行\n";

// 检查文件是否被截断
$content = file_get_contents($mapper_file);
if (substr($content, -1) !== '}') {
    echo "❌ 警告：文件可能被截断（不以}结尾）\n";
} else {
    echo "✅ 文件结构完整（以}结尾）\n";
}

// 检查PHP语法
$syntax_check = shell_exec("php -l {$mapper_file} 2>&1");
if (strpos($syntax_check, 'No syntax errors') !== false) {
    echo "✅ PHP语法检查通过\n";
} else {
    echo "❌ PHP语法错误:\n{$syntax_check}\n";
}

// ============================================
// 检查2: 关键方法完整性
// ============================================
echo "\n【检查2: 关键方法完整性】\n";
echo str_repeat("-", 50) . "\n";

$critical_methods = [
    'generate_special_attribute_value',
    'map',
    'clean_image_url_for_walmart',
    'extract_sofa_loveseat_design',
    'convert_field_data_type'
];

foreach ($critical_methods as $method) {
    if (strpos($content, "function {$method}") !== false || 
        strpos($content, "private function {$method}") !== false ||
        strpos($content, "public function {$method}") !== false) {
        echo "✅ 方法存在: {$method}\n";
    } else {
        echo "❌ 方法缺失: {$method}\n";
    }
}

// ============================================
// 检查3: Switch语句完整性
// ============================================
echo "\n【检查3: Switch语句完整性】\n";
echo str_repeat("-", 50) . "\n";

// 查找generate_special_attribute_value中的switch语句
$lines = file($mapper_file);
$in_generate_method = false;
$switch_started = false;
$switch_ended = false;
$case_count = 0;
$default_found = false;
$brace_count = 0;

foreach ($lines as $line_num => $line) {
    $line = trim($line);
    
    // 查找方法开始
    if (strpos($line, 'function generate_special_attribute_value') !== false) {
        $in_generate_method = true;
        echo "✅ 找到generate_special_attribute_value方法 (行" . ($line_num + 1) . ")\n";
        continue;
    }
    
    if ($in_generate_method) {
        // 计算大括号
        $brace_count += substr_count($line, '{') - substr_count($line, '}');
        
        // 查找switch开始
        if (strpos($line, 'switch') !== false && strpos($line, '(') !== false) {
            $switch_started = true;
            echo "✅ 找到switch语句 (行" . ($line_num + 1) . ")\n";
            continue;
        }
        
        if ($switch_started && !$switch_ended) {
            // 计算case数量
            if (strpos($line, 'case ') !== false) {
                $case_count++;
            }
            
            // 查找default
            if (strpos($line, 'default:') !== false) {
                $default_found = true;
            }
        }
        
        // 方法结束
        if ($brace_count <= 0 && $in_generate_method) {
            $switch_ended = true;
            break;
        }
    }
}

echo "Switch语句统计:\n";
echo "- Case分支数量: {$case_count}\n";
echo "- Default分支: " . ($default_found ? '存在' : '缺失') . "\n";

if (!$switch_started) {
    echo "❌ 未找到switch语句\n";
} elseif (!$default_found) {
    echo "❌ 缺少default分支\n";
} else {
    echo "✅ Switch语句结构完整\n";
}

// ============================================
// 检查4: 关键Case分支
// ============================================
echo "\n【检查4: 关键Case分支】\n";
echo str_repeat("-", 50) . "\n";

$critical_cases = [
    'mainimageurl',
    'main_image_url',
    'sofa_and_loveseat_design',
    'sofaandloveseatdesign',
    'brand',
    'productname'
];

foreach ($critical_cases as $case) {
    if (strpos($content, "case '{$case}':") !== false) {
        echo "✅ Case存在: {$case}\n";
    } else {
        echo "❌ Case缺失: {$case}\n";
    }
}

// ============================================
// 检查5: 最近的修改痕迹
// ============================================
echo "\n【检查5: 最近的修改痕迹】\n";
echo str_repeat("-", 50) . "\n";

// 查找可能的修改标记
$modification_markers = [
    '// 🔧',
    '// ✅',
    '// 修复',
    '// TODO',
    '// FIXME',
    'clean_image_url_for_walmart',
    'sofaandloveseatdesign',
    'sofabedsize'
];

foreach ($modification_markers as $marker) {
    $count = substr_count($content, $marker);
    if ($count > 0) {
        echo "⚠️ 发现修改标记 '{$marker}': {$count} 处\n";
    }
}

// ============================================
// 检查6: 方法调用一致性
// ============================================
echo "\n【检查6: 方法调用一致性】\n";
echo str_repeat("-", 50) . "\n";

// 检查mainImageUrl的两种处理方式
$main_mapping_pattern = '/\$main_image_url\s*=.*remote_url/';
$generate_pattern = '/case\s+[\'"]main.*image.*url[\'"]:/i';

$main_mapping_count = preg_match_all($main_mapping_pattern, $content);
$generate_case_count = preg_match_all($generate_pattern, $content);

echo "主映射逻辑中的mainImageUrl处理: {$main_mapping_count} 处\n";
echo "generate方法中的mainImageUrl case: {$generate_case_count} 处\n";

if ($main_mapping_count > 0 && $generate_case_count > 0) {
    echo "✅ 两套逻辑都存在\n";
} else {
    echo "❌ 逻辑不完整\n";
}

// ============================================
// 检查7: 可能的代码截断点
// ============================================
echo "\n【检查7: 可能的代码截断点】\n";
echo str_repeat("-", 50) . "\n";

$lines = file($mapper_file);
$suspicious_lines = [];

for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    
    // 检查可疑的截断模式
    if (empty($line) && $i < count($lines) - 10) {
        // 检查后续是否有大量空行
        $empty_count = 0;
        for ($j = $i; $j < min($i + 10, count($lines)); $j++) {
            if (empty(trim($lines[$j]))) {
                $empty_count++;
            }
        }
        if ($empty_count > 5) {
            $suspicious_lines[] = "行" . ($i + 1) . ": 大量空行";
        }
    }
    
    // 检查不完整的语句
    if (substr($line, -1) === ',' && !isset($lines[$i + 1])) {
        $suspicious_lines[] = "行" . ($i + 1) . ": 语句可能不完整";
    }
    
    // 检查不匹配的括号
    $open_braces = substr_count($line, '{');
    $close_braces = substr_count($line, '}');
    if ($open_braces > $close_braces + 1) {
        $suspicious_lines[] = "行" . ($i + 1) . ": 括号可能不匹配";
    }
}

if (empty($suspicious_lines)) {
    echo "✅ 未发现明显的截断迹象\n";
} else {
    echo "⚠️ 发现可疑位置:\n";
    foreach ($suspicious_lines as $line) {
        echo "  - {$line}\n";
    }
}

// ============================================
// 检查8: 文件修改时间
// ============================================
echo "\n【检查8: 文件修改时间】\n";
echo str_repeat("-", 50) . "\n";

$mod_time = filemtime($mapper_file);
$mod_date = date('Y-m-d H:i:s', $mod_time);
$days_ago = floor((time() - $mod_time) / 86400);

echo "最后修改时间: {$mod_date}\n";
echo "距今天数: {$days_ago} 天\n";

if ($days_ago <= 7) {
    echo "⚠️ 文件在最近7天内被修改过\n";
} elseif ($days_ago <= 30) {
    echo "⚠️ 文件在最近30天内被修改过\n";
} else {
    echo "✅ 文件修改时间较早\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【检查总结】\n";
echo str_repeat("=", 80) . "\n";

echo "建议检查的方向:\n";
echo "1. 如果发现语法错误或方法缺失，可能是文件损坏\n";
echo "2. 如果发现修改标记，说明最近有人修改过代码\n";
echo "3. 如果Switch语句不完整，可能是编辑过程中出错\n";
echo "4. 如果文件最近被修改，需要确认修改内容\n";
echo "5. 检查是否有备份文件可以对比\n";

echo "\n=== 检查完成 ===\n";
?>
