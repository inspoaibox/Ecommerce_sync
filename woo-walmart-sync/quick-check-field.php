<?php
/**
 * 快速检查字段是否在数据库中
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

global $wpdb;

echo "=== 快速检查 sofa_and_loveseat_design 字段 ===\n\n";

// 获取分类映射 ID 144
$mapping = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE id = 144");

if (!$mapping) {
    echo "❌ 找不到分类映射 ID 144\n";
    exit;
}

echo "分类映射 ID: 144\n";
echo "Walmart分类: {$mapping->walmart_category_path}\n\n";

// 解析 walmart_attributes
$attributes = json_decode($mapping->walmart_attributes, true);

if (!is_array($attributes)) {
    echo "❌ walmart_attributes 不是有效的 JSON 数组\n";
    echo "原始内容: " . substr($mapping->walmart_attributes, 0, 200) . "...\n";
    exit;
}

echo "总字段数: " . count($attributes) . "\n\n";

// 查找 sofa_and_loveseat_design
$found = false;
foreach ($attributes as $index => $attr) {
    if (isset($attr['name']) && $attr['name'] === 'sofa_and_loveseat_design') {
        $found = true;
        echo "✅ 找到字段！\n\n";
        echo "索引位置: {$index}\n";
        echo "字段名称: {$attr['name']}\n";
        echo "映射类型: {$attr['type']}\n";
        echo "来源: " . ($attr['source'] ?? '(空)') . "\n\n";
        echo "完整配置:\n";
        echo json_encode($attr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        break;
    }
}

if (!$found) {
    echo "❌ 未找到字段！\n\n";
    echo "这说明虽然页面显示已配置，但数据库中没有。\n";
    echo "可能的原因：\n";
    echo "1. 保存时出现了错误\n";
    echo "2. 数据库写入失败\n";
    echo "3. 浏览器显示的是缓存数据\n\n";
    
    // 显示前 30 个字段
    echo "数据库中实际的字段（前 30 个）:\n";
    foreach (array_slice($attributes, 0, 30) as $index => $attr) {
        $name = $attr['name'] ?? '(无名称)';
        $type = $attr['type'] ?? '(无类型)';
        echo "  [{$index}] {$name} - {$type}\n";
    }
    
    if (count($attributes) > 30) {
        echo "  ... 还有 " . (count($attributes) - 30) . " 个字段\n";
    }
    echo "\n";
    
    // 搜索相似的字段名
    echo "搜索包含 'sofa' 或 'loveseat' 的字段:\n";
    $similar_found = false;
    foreach ($attributes as $attr) {
        $name = $attr['name'] ?? '';
        if (stripos($name, 'sofa') !== false || stripos($name, 'loveseat') !== false) {
            echo "  - {$name}\n";
            $similar_found = true;
        }
    }
    if (!$similar_found) {
        echo "  (未找到)\n";
    }
    echo "\n";
}

echo "检查完成！\n";
?>

