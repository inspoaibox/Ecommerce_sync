<?php
/**
 * 修复 sofa_and_loveseat_design 字段的case匹配问题
 * 在switch语句中添加转换后名称的case分支
 */

echo "=== 修复 case 匹配问题 ===\n";
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

// 查找 sofa_and_loveseat_design case 并在其前面添加转换后的case
$search_pattern = "/(\s+)case 'sofa_and_loveseat_design':\s*\n(\s+)\/\/ 沙发设计风格[^\n]*\n(\s+)return \\\$this->extract_sofa_loveseat_design\(\\\$product\);/";

if (!preg_match($search_pattern, $content)) {
    echo "❌ 找不到目标case分支，尝试简化搜索...\n";
    
    // 尝试更简单的搜索
    $simple_pattern = "/case 'sofa_and_loveseat_design':/";
    if (!preg_match($simple_pattern, $content)) {
        echo "❌ 完全找不到 sofa_and_loveseat_design case\n";
        exit;
    }
    
    // 手动查找和替换
    $lines = explode("\n", $content);
    $new_lines = [];
    $case_found = false;
    
    foreach ($lines as $line_num => $line) {
        if (strpos($line, "case 'sofa_and_loveseat_design':") !== false && !$case_found) {
            $case_found = true;
            $indent = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
            
            // 添加转换后名称的case
            $new_lines[] = $indent . "case 'sofaandloveseatdesign':";
            $new_lines[] = $indent . "    // 转换后的属性名匹配";
            $new_lines[] = $line; // 保留原始case
            continue;
        }
        $new_lines[] = $line;
    }
    
    if ($case_found) {
        $new_content = implode("\n", $new_lines);
        echo "✅ 使用手动方式找到并修复\n";
    } else {
        echo "❌ 手动方式也找不到case\n";
        exit;
    }
} else {
    // 使用正则表达式替换
    $replacement = '$1case \'sofaandloveseatdesign\':
$1    // 转换后的属性名匹配 (sofa_and_loveseat_design -> sofaandloveseatdesign)
$1case \'sofa_and_loveseat_design\':
$2// 沙发设计风格：从产品标题和描述中提取设计风格关键词（必须返回数组）
$3return $this->extract_sofa_loveseat_design($product);';
    
    $new_content = preg_replace($search_pattern, $replacement, $content);
    echo "✅ 使用正则表达式修复\n";
}

if ($new_content === $content) {
    echo "❌ 修复失败，内容没有变化\n";
    exit;
}

// 验证修复结果
if (strpos($new_content, "case 'sofaandloveseatdesign':") !== false) {
    echo "✅ 确认添加了转换后的case分支\n";
} else {
    echo "❌ 修复验证失败\n";
    exit;
}

// 写入修改后的文件
if (!file_put_contents($mapper_file, $new_content)) {
    echo "❌ 无法写入修改后的文件\n";
    exit;
}

echo "✅ 修复已应用\n";
echo "📝 修复内容：添加了 'sofaandloveseatdesign' case分支\n\n";

echo "🧪 **测试步骤**：\n";
echo "1. 重新运行字段生成测试：\n";
echo "   php debug-sofa-design-detailed.php\n";
echo "2. 确认 generate_special_attribute_value 不再返回null\n";
echo "3. 重新同步产品 W714P357249\n";
echo "4. 查看Walmart API是否还报告字段缺失\n\n";

echo "⚠️ **验证要点**：\n";
echo "1. 两个case分支都指向同一个方法\n";
echo "2. 转换后的属性名应该能正确匹配\n";
echo "3. 字段应该返回 ['Mid-Century Modern'] 而不是null\n\n";

echo "🔄 **恢复命令**：\n";
echo "cp {$backup_file} {$mapper_file}\n\n";

// 显示修复的具体内容
echo "📋 **修复详情**：\n";
echo "添加了以下case分支：\n";
echo "```php\n";
echo "case 'sofaandloveseatdesign':\n";
echo "    // 转换后的属性名匹配 (sofa_and_loveseat_design -> sofaandloveseatdesign)\n";
echo "case 'sofa_and_loveseat_design':\n";
echo "    // 沙发设计风格：从产品标题和描述中提取设计风格关键词（必须返回数组）\n";
echo "    return \$this->extract_sofa_loveseat_design(\$product);\n";
echo "```\n\n";

echo "=== 修复完成 ===\n";
?>
