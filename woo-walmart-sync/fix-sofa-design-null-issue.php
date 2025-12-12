<?php
/**
 * 修复 sofa_and_loveseat_design 字段返回null的问题
 * 在 generate_special_attribute_value 方法中添加null值保护
 */

echo "=== 修复 sofa_and_loveseat_design null 问题 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// 备份原文件
$mapper_file = 'includes/class-product-mapper.php';
$backup_file = 'includes/class-product-mapper.php.backup.' . date('Ymd_His');

if (!file_exists($mapper_file)) {
    die("❌ 找不到映射器文件\n");
}

// 创建备份
if (!copy($mapper_file, $backup_file)) {
    die("❌ 无法创建备份文件\n");
}

echo "✅ 已创建备份: {$backup_file}\n";

// 读取原文件内容
$content = file_get_contents($mapper_file);

// 查找 sofa_and_loveseat_design case
$search_pattern = "/case 'sofa_and_loveseat_design':\s*\/\/[^\n]*\n\s*return \\\$this->extract_sofa_loveseat_design\(\\\$product\);/";

if (!preg_match($search_pattern, $content)) {
    echo "❌ 找不到目标代码行\n";
    echo "尝试查找简化模式...\n";
    
    // 尝试简化的搜索模式
    $simple_pattern = "/case 'sofa_and_loveseat_design':/";
    if (!preg_match($simple_pattern, $content)) {
        echo "❌ 完全找不到 sofa_and_loveseat_design case\n";
        exit;
    } else {
        echo "✅ 找到简化匹配，需要手动检查\n";
    }
}

// 修复：添加null值保护
$replacement = "case 'sofa_and_loveseat_design':
                // 沙发设计风格：从产品标题和描述中提取设计风格关键词（必须返回数组）
                \$result = \$this->extract_sofa_loveseat_design(\$product);
                // 🔧 修复：确保永远不返回null，提供默认值保护
                if (is_null(\$result) || empty(\$result)) {
                    woo_walmart_sync_log('sofa_design_null_fix', '警告', [
                        'product_id' => \$product->get_id(),
                        'product_name' => \$product->get_name(),
                        'original_result' => \$result
                    ], 'sofa_and_loveseat_design 字段返回null，使用默认值');
                    return ['Mid-Century Modern'];
                }
                return \$result;";

// 执行替换
$new_content = preg_replace(
    $search_pattern,
    $replacement,
    $content
);

// 如果第一次替换失败，尝试更精确的替换
if ($new_content === $content) {
    echo "第一次替换失败，尝试更精确的替换...\n";
    
    // 查找更精确的模式
    $lines = explode("\n", $content);
    $new_lines = [];
    $in_sofa_case = false;
    $case_processed = false;
    
    foreach ($lines as $line_num => $line) {
        if (strpos($line, "case 'sofa_and_loveseat_design':") !== false) {
            $in_sofa_case = true;
            $new_lines[] = $line;
            continue;
        }
        
        if ($in_sofa_case && !$case_processed) {
            if (strpos($line, 'return $this->extract_sofa_loveseat_design($product);') !== false) {
                // 替换这一行
                $indent = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
                $new_lines[] = $indent . '$result = $this->extract_sofa_loveseat_design($product);';
                $new_lines[] = $indent . '// 🔧 修复：确保永远不返回null，提供默认值保护';
                $new_lines[] = $indent . 'if (is_null($result) || empty($result)) {';
                $new_lines[] = $indent . '    woo_walmart_sync_log(\'sofa_design_null_fix\', \'警告\', [';
                $new_lines[] = $indent . '        \'product_id\' => $product->get_id(),';
                $new_lines[] = $indent . '        \'product_name\' => $product->get_name(),';
                $new_lines[] = $indent . '        \'original_result\' => $result';
                $new_lines[] = $indent . '    ], \'sofa_and_loveseat_design 字段返回null，使用默认值\');';
                $new_lines[] = $indent . '    return [\'Mid-Century Modern\'];';
                $new_lines[] = $indent . '}';
                $new_lines[] = $indent . 'return $result;';
                $case_processed = true;
                $in_sofa_case = false;
                continue;
            }
        }
        
        // 检查是否离开了当前case
        if ($in_sofa_case && (strpos($line, 'case ') !== false || strpos($line, 'default:') !== false)) {
            $in_sofa_case = false;
        }
        
        $new_lines[] = $line;
    }
    
    if ($case_processed) {
        $new_content = implode("\n", $new_lines);
        echo "✅ 使用精确替换成功\n";
    } else {
        echo "❌ 精确替换也失败了\n";
        exit;
    }
}

if ($new_content === $content) {
    echo "❌ 替换失败，内容没有变化\n";
    exit;
}

// 写入修改后的文件
if (!file_put_contents($mapper_file, $new_content)) {
    echo "❌ 无法写入修改后的文件\n";
    exit;
}

echo "✅ 修复已应用\n";
echo "📝 修复内容：为 sofa_and_loveseat_design 字段添加null值保护\n\n";

echo "🧪 **测试步骤**：\n";
echo "1. 重新同步产品 W714P357249\n";
echo "2. 检查同步日志中是否出现 'sofa_design_null_fix' 警告记录\n";
echo "3. 查看Walmart API是否还报告字段缺失\n\n";

echo "⚠️ **重要提醒**：\n";
echo "1. 这是修复补丁，解决null返回问题\n";
echo "2. 如果问题解决，说明原方法确实返回了null\n";
echo "3. 需要进一步调查为什么原方法返回null\n\n";

echo "🔄 **恢复命令**：\n";
echo "cp {$backup_file} {$mapper_file}\n\n";

echo "=== 修复完成 ===\n";
?>
