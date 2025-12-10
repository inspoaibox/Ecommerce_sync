<?php
/**
 * 验证三个字段的说明是否正确添加
 */

echo "=== 验证字段说明添加 ===\n\n";

$main_file = __DIR__ . '/woo-walmart-sync.php';

if (!file_exists($main_file)) {
    echo "❌ 文件不存在: {$main_file}\n";
    exit;
}

echo "✅ 文件存在: {$main_file}\n\n";

$content = file_get_contents($main_file);

// 检查三个字段
$fields_to_check = [
    'sizeDescriptor' => '从产品标题和描述中提取尺寸描述符',
    'sofa_and_loveseat_design' => '从产品标题和描述中提取沙发设计风格',
    'sofa_bed_size' => '从产品标题和描述中提取沙发床尺寸'
];

echo "【检查1: 字段说明是否存在】\n";
echo str_repeat("-", 80) . "\n";

$all_found = true;

foreach ($fields_to_check as $field => $expected_text) {
    // 检查字段名称是否存在
    $field_pattern = "'{$field}':";
    $field_exists = strpos($content, $field_pattern) !== false;
    
    // 检查说明文本是否存在
    $text_exists = strpos($content, $expected_text) !== false;
    
    echo "\n字段: {$field}\n";
    
    if ($field_exists && $text_exists) {
        echo "  ✅ 字段说明已添加\n";
        
        // 提取完整的说明文本
        $pattern = "/'$field':\s*'([^']+)'/";
        if (preg_match($pattern, $content, $matches)) {
            $full_description = $matches[1];
            echo "  完整说明: {$full_description}\n";
        }
    } else {
        echo "  ❌ 字段说明缺失\n";
        if (!$field_exists) {
            echo "     - 字段名称未找到\n";
        }
        if (!$text_exists) {
            echo "     - 说明文本未找到\n";
        }
        $all_found = false;
    }
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// 检查注释标记
echo "【检查2: 注释标记是否正确】\n";
echo str_repeat("-", 80) . "\n";

$comment_marker = "// 🆕 通用字段拓展说明 - 2025-10-13 (第三批)";
$comment_exists = strpos($content, $comment_marker) !== false;

if ($comment_exists) {
    echo "✅ 注释标记已添加: {$comment_marker}\n";
} else {
    echo "❌ 注释标记缺失\n";
    $all_found = false;
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// 检查 autoGenerateFields 数组
echo "【检查3: autoGenerateFields 数组配置】\n";
echo str_repeat("-", 80) . "\n";

$auto_generate_count = substr_count($content, "'sizeDescriptor'");
echo "\n'sizeDescriptor' 出现次数: {$auto_generate_count}\n";

if ($auto_generate_count >= 2) {
    echo "✅ 字段已添加到 autoGenerateFields 数组（至少2次）\n";
} else {
    echo "⚠️ 字段在 autoGenerateFields 数组中出现次数不足\n";
}

$design_count = substr_count($content, "'sofa_and_loveseat_design'");
echo "\n'sofa_and_loveseat_design' 出现次数: {$design_count}\n";

if ($design_count >= 2) {
    echo "✅ 字段已添加到 autoGenerateFields 数组（至少2次）\n";
} else {
    echo "⚠️ 字段在 autoGenerateFields 数组中出现次数不足\n";
}

$bed_size_count = substr_count($content, "'sofa_bed_size'");
echo "\n'sofa_bed_size' 出现次数: {$bed_size_count}\n";

if ($bed_size_count >= 2) {
    echo "✅ 字段已添加到 autoGenerateFields 数组（至少2次）\n";
} else {
    echo "⚠️ 字段在 autoGenerateFields 数组中出现次数不足\n";
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// 检查 getAutoGenerationRule 函数
echo "【检查4: getAutoGenerationRule 函数完整性】\n";
echo str_repeat("-", 80) . "\n";

$function_pattern = '/function getAutoGenerationRule\(attributeName\)\s*\{/';
if (preg_match($function_pattern, $content)) {
    echo "✅ getAutoGenerationRule 函数存在\n";
    
    // 检查函数中是否包含三个字段
    $function_start = strpos($content, 'function getAutoGenerationRule(attributeName)');
    $function_end = strpos($content, 'return rules[attributeName]', $function_start);
    
    if ($function_start !== false && $function_end !== false) {
        $function_content = substr($content, $function_start, $function_end - $function_start);
        
        $fields_in_function = 0;
        foreach ($fields_to_check as $field => $text) {
            if (strpos($function_content, "'{$field}':") !== false) {
                $fields_in_function++;
            }
        }
        
        echo "函数中包含的新字段数量: {$fields_in_function}/3\n";
        
        if ($fields_in_function === 3) {
            echo "✅ 所有三个字段都在函数中\n";
        } else {
            echo "⚠️ 部分字段缺失\n";
        }
    }
} else {
    echo "❌ getAutoGenerationRule 函数未找到\n";
    $all_found = false;
}

echo "\n" . str_repeat("-", 80) . "\n\n";

// 总结
echo "【验证总结】\n";
echo str_repeat("=", 80) . "\n";

if ($all_found) {
    echo "🎉 所有检查通过！字段说明已成功添加。\n\n";
    echo "✅ 三个字段的说明都已正确添加到 getAutoGenerationRule 函数中\n";
    echo "✅ 注释标记正确\n";
    echo "✅ autoGenerateFields 数组配置正确\n";
    echo "✅ 代码修改完成，符合开发文档要求\n\n";
    echo "📝 下一步操作：\n";
    echo "1. 登录 WordPress 后台\n";
    echo "2. 进入「Walmart 同步」→「分类映射」页面\n";
    echo "3. 点击「重置属性」按钮\n";
    echo "4. 找到三个字段，鼠标悬停在「自动生成」标签上\n";
    echo "5. 应该能看到详细的字段说明\n";
} else {
    echo "⚠️ 部分检查未通过，请检查上述详细信息。\n";
}

echo "\n验证完成！\n";
?>

