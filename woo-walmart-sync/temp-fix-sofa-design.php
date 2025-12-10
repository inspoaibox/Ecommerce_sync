<?php
/**
 * 临时修复 sofa_and_loveseat_design 字段被过滤的问题
 * 这是一个临时解决方案，用于测试字段是否能正常传递
 */

echo "=== sofa_and_loveseat_design 字段临时修复 ===\n";
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

// 查找目标代码行
$search_pattern = '/if \( ! is_null\( \$value \) && ! \$this->is_empty_field_value\( \$value \) \) \{/';

if (!preg_match($search_pattern, $content)) {
    echo "❌ 找不到目标代码行\n";
    exit;
}

// 临时修复：为 sofa_and_loveseat_design 字段添加特殊处理
$replacement = 'if ( ! is_null( $value ) && ! $this->is_empty_field_value( $value ) ) {
                    // 🔧 临时修复：sofa_and_loveseat_design 字段特殊处理
                } elseif ($walmart_attr_name === \'sofa_and_loveseat_design\' && !is_null($value)) {
                    // 强制包含 sofa_and_loveseat_design 字段，即使被判定为空
                    woo_walmart_sync_log(\'临时修复-强制包含字段\', \'调试\', [
                        \'field\' => $walmart_attr_name,
                        \'value\' => $value,
                        \'original_empty_check\' => $this->is_empty_field_value($value)
                    ], "强制包含字段: {$walmart_attr_name}");
                    
                    $item_data[\'Visible\'][$walmart_category_name][ $walmart_attr_name ] = $value;
                } else {';

// 执行替换
$new_content = preg_replace(
    '/if \( ! is_null\( \$value \) && ! \$this->is_empty_field_value\( \$value \) \) \{/',
    $replacement,
    $content
);

if ($new_content === $content) {
    echo "❌ 替换失败，内容没有变化\n";
    exit;
}

// 写入修改后的文件
if (!file_put_contents($mapper_file, $new_content)) {
    echo "❌ 无法写入修改后的文件\n";
    exit;
}

echo "✅ 临时修复已应用\n";
echo "📝 修复内容：为 sofa_and_loveseat_design 字段添加强制包含逻辑\n\n";

echo "🧪 **测试步骤**：\n";
echo "1. 重新同步产品 W714P357249\n";
echo "2. 检查同步日志中是否出现 '临时修复-强制包含字段' 记录\n";
echo "3. 查看Walmart API是否还报告字段缺失\n\n";

echo "⚠️ **重要提醒**：\n";
echo "1. 这是临时修复，仅用于测试\n";
echo "2. 测试完成后请恢复原文件：\n";
echo "   cp {$backup_file} {$mapper_file}\n";
echo "3. 找到根本原因后需要正式修复\n\n";

echo "🔄 **恢复命令**：\n";
echo "cp {$backup_file} {$mapper_file}\n\n";

echo "=== 临时修复完成 ===\n";
?>
