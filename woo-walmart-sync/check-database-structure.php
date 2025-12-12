<?php
/**
 * 检查数据库表结构和实际数据
 */

// 自动检测 WordPress 根目录
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

echo "=== 检查数据库表结构和实际数据 ===\n\n";

global $wpdb;

// ============================================
// 检查1: 表结构
// ============================================
echo "【检查1: 表结构】\n";
echo str_repeat("-", 80) . "\n";

$table_name = $wpdb->prefix . 'walmart_category_map';
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");

echo "表名: {$table_name}\n";
echo "字段列表:\n";
foreach ($columns as $column) {
    echo "  - {$column->Field} ({$column->Type})\n";
}
echo "\n";

// ============================================
// 检查2: 分类映射 ID 144 的完整数据
// ============================================
echo "【检查2: 分类映射 ID 144 的完整数据】\n";
echo str_repeat("-", 80) . "\n";

$mapping = $wpdb->get_row("SELECT * FROM {$table_name} WHERE id = 144");

if (!$mapping) {
    echo "❌ 找不到 ID 144 的记录\n\n";
    exit;
}

echo "完整记录:\n";
foreach ($mapping as $key => $value) {
    if ($key === 'walmart_attributes') {
        echo "  {$key}: " . substr($value, 0, 200) . "...\n";
    } else {
        echo "  {$key}: {$value}\n";
    }
}
echo "\n";

// ============================================
// 检查3: walmart_attributes 字段内容
// ============================================
echo "【检查3: walmart_attributes 字段内容】\n";
echo str_repeat("-", 80) . "\n";

$attributes_json = $mapping->walmart_attributes;

echo "原始内容长度: " . strlen($attributes_json) . " 字符\n";
echo "前 500 字符:\n";
echo substr($attributes_json, 0, 500) . "\n\n";

// 尝试解析 JSON
$attributes = json_decode($attributes_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ JSON 解析失败: " . json_last_error_msg() . "\n\n";
    exit;
}

if (!is_array($attributes)) {
    echo "❌ 解析结果不是数组\n";
    echo "类型: " . gettype($attributes) . "\n\n";
    exit;
}

echo "✅ JSON 解析成功\n";
echo "字段数量: " . count($attributes) . "\n\n";

// ============================================
// 检查4: 查找 sofa_and_loveseat_design 字段
// ============================================
echo "【检查4: 查找 sofa_and_loveseat_design 字段】\n";
echo str_repeat("-", 80) . "\n";

$found = false;
$field_index = -1;

foreach ($attributes as $index => $attr) {
    if (isset($attr['name']) && $attr['name'] === 'sofa_and_loveseat_design') {
        $found = true;
        $field_index = $index;
        echo "✅ 找到字段！\n";
        echo "索引位置: {$index}\n";
        echo "完整配置:\n";
        echo json_encode($attr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        break;
    }
}

if (!$found) {
    echo "❌ 未找到 sofa_and_loveseat_design 字段\n\n";
    
    // 显示前 20 个字段
    echo "前 20 个字段:\n";
    foreach (array_slice($attributes, 0, 20) as $index => $attr) {
        $name = $attr['name'] ?? '(无名称)';
        $type = $attr['type'] ?? '(无类型)';
        echo "  [{$index}] {$name} - {$type}\n";
    }
    echo "\n";
    
    // 搜索包含 'sofa' 的字段
    echo "包含 'sofa' 的字段:\n";
    foreach ($attributes as $index => $attr) {
        $name = $attr['name'] ?? '';
        if (stripos($name, 'sofa') !== false) {
            echo "  [{$index}] {$name}\n";
        }
    }
    echo "\n";
}

// ============================================
// 检查5: 检查字段名称的变体
// ============================================
echo "【检查5: 检查字段名称的变体】\n";
echo str_repeat("-", 80) . "\n";

$variants = [
    'sofa_and_loveseat_design',
    'sofaAndLoveseatDesign',
    'sofa_loveseat_design',
    'sofaLoveseatDesign',
];

echo "搜索字段名称的变体:\n";
foreach ($variants as $variant) {
    $found_variant = false;
    foreach ($attributes as $attr) {
        if (isset($attr['name']) && $attr['name'] === $variant) {
            echo "  ✅ 找到: {$variant}\n";
            $found_variant = true;
            break;
        }
    }
    if (!$found_variant) {
        echo "  ❌ 未找到: {$variant}\n";
    }
}
echo "\n";

// ============================================
// 检查6: 检查所有 auto_generate 类型的字段
// ============================================
echo "【检查6: 所有 auto_generate 类型的字段】\n";
echo str_repeat("-", 80) . "\n";

$auto_generate_fields = [];
foreach ($attributes as $attr) {
    if (isset($attr['type']) && $attr['type'] === 'auto_generate') {
        $auto_generate_fields[] = $attr['name'] ?? '(无名称)';
    }
}

echo "auto_generate 类型的字段数量: " . count($auto_generate_fields) . "\n";
if (!empty($auto_generate_fields)) {
    echo "字段列表:\n";
    foreach (array_slice($auto_generate_fields, 0, 30) as $field) {
        echo "  - {$field}\n";
    }
    if (count($auto_generate_fields) > 30) {
        echo "  ... 还有 " . (count($auto_generate_fields) - 30) . " 个字段\n";
    }
}
echo "\n";

// ============================================
// 检查7: 检查最近修改时间
// ============================================
echo "【检查7: 检查最近修改时间】\n";
echo str_repeat("-", 80) . "\n";

if (isset($mapping->updated_at)) {
    echo "最后更新时间: {$mapping->updated_at}\n";
} elseif (isset($mapping->update_time)) {
    echo "最后更新时间: {$mapping->update_time}\n";
} else {
    echo "⚠️ 没有找到更新时间字段\n";
}
echo "\n";

// ============================================
// 总结
// ============================================
echo str_repeat("=", 80) . "\n";
echo "【诊断总结】\n";
echo str_repeat("=", 80) . "\n\n";

if ($found) {
    echo "✅ sofa_and_loveseat_design 字段存在于数据库中\n";
    echo "   配置正确，类型为 auto_generate\n\n";
    echo "如果同步还是失败，可能的原因：\n";
    echo "1. 产品的分类没有正确关联到这个分类映射\n";
    echo "2. 字段在映射过程中被过滤掉了\n";
    echo "3. 其他代码逻辑问题\n\n";
    echo "建议：\n";
    echo "1. 运行 diagnose-actual-problem.php 脚本进行深度诊断\n";
    echo "2. 检查产品的分类是否正确\n";
    echo "3. 查看同步日志中的详细信息\n\n";
} else {
    echo "❌ sofa_and_loveseat_design 字段不存在于数据库中\n";
    echo "   虽然在分类映射页面显示有配置，但数据库中没有\n\n";
    echo "可能的原因：\n";
    echo "1. 点击了「重置属性」但没有保存\n";
    echo "2. 保存时出现了错误\n";
    echo "3. 数据库写入失败\n";
    echo "4. 浏览器缓存显示的是旧数据\n\n";
    echo "解决方案：\n";
    echo "1. 清除浏览器缓存\n";
    echo "2. 重新进入分类映射页面\n";
    echo "3. 再次点击「重置属性」\n";
    echo "4. 确认 sofa_and_loveseat_design 出现在字段列表中\n";
    echo "5. 点击「保存」按钮\n";
    echo "6. 等待页面刷新完成\n";
    echo "7. 重新运行此脚本验证\n\n";
}

echo "诊断完成！\n";
?>

