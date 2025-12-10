<?php
/**
 * 简单直接地查找Walmart类目
 */

set_time_limit(120);
ini_set('memory_limit', '2G');

echo "=== 简单查找Walmart类目 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在: $json_file\n";
    exit;
}

echo "📁 开始搜索类目...\n";

// 使用grep命令快速搜索包含&的行（通常是类目名）
$categories = [];
$attributes = [];

// 搜索类目模式
echo "🔍 搜索类目模式...\n";
$cmd = 'findstr /i "& " "' . $json_file . '"';
$output = [];
exec($cmd, $output);

echo "找到 " . count($output) . " 行包含类目模式\n";

foreach (array_slice($output, 0, 50) as $line) {
    // 提取引号中的类目名
    if (preg_match('/"([^"]*&[^"]*)"/', $line, $matches)) {
        $category = $matches[1];
        if (strlen($category) > 5 && !in_array($category, $categories)) {
            $categories[] = $category;
        }
    }
}

// 搜索常见属性
echo "\n🔍 搜索常见属性...\n";
$common_attrs = ['brand', 'color', 'size', 'weight', 'material', 'model', 'manufacturer'];

foreach ($common_attrs as $attr) {
    $cmd = 'findstr /i "\"' . $attr . '\"" "' . $json_file . '"';
    $output = [];
    exec($cmd, $output);
    
    if (count($output) > 0) {
        $attributes[$attr] = count($output);
        echo "  $attr: " . count($output) . " 次出现\n";
    }
}

// 显示结果
echo "\n=== 发现的类目 (" . count($categories) . " 个) ===\n";
foreach ($categories as $i => $category) {
    echo ($i + 1) . ". $category\n";
}

echo "\n=== 属性统计 ===\n";
foreach ($attributes as $attr => $count) {
    echo "$attr: $count 次\n";
}

// 保存结果
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'categories' => $categories,
    'attributes' => $attributes
];

$output_file = 'simple_categories_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 结果已保存到: $output_file\n";

// 尝试找到一个具体的类目定义
echo "\n🎯 尝试提取具体类目定义...\n";

if (!empty($categories)) {
    $target_category = $categories[0]; // 取第一个类目
    echo "目标类目: $target_category\n";
    
    // 搜索该类目的完整定义
    $cmd = 'findstr /A:2 /B:2 "' . $target_category . '" "' . $json_file . '"';
    $output = [];
    exec($cmd, $output);
    
    echo "找到 " . count($output) . " 行相关内容\n";
    
    foreach (array_slice($output, 0, 10) as $line) {
        echo "  " . trim($line) . "\n";
    }
}

echo "\n=== 完成 ===\n";
?>
