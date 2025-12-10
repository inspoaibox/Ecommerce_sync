<?php
/**
 * 重新分析，专门查找一级分类
 */

set_time_limit(300);
ini_set('memory_limit', '4G');

echo "=== 重新分析Walmart一级分类 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在\n";
    exit;
}

echo "📁 分析JSON Schema结构，寻找一级分类...\n";

// 分块读取，寻找顶级分类结构
$handle = fopen($json_file, 'r');
$chunk_size = 1024 * 1024 * 2; // 2MB chunks
$buffer = '';

$level1_categories = [];
$all_categories = [];
$category_patterns = [];

$chunk_count = 0;
$total_size = filesize($json_file);
$processed_size = 0;

while (!feof($handle) && $chunk_count < 50) { // 读取前50个块
    $chunk = fread($handle, $chunk_size);
    $buffer .= $chunk;
    $processed_size += strlen($chunk);
    $chunk_count++;
    
    $progress = ($processed_size / $total_size) * 100;
    echo "\r进度: " . number_format($progress, 1) . "% (块 #$chunk_count)";
    
    // 1. 寻找可能的一级分类 - 单个词的大类
    $single_word_categories = ['Furniture', 'Electronics', 'Home', 'Garden', 'Kitchen', 'Clothing', 
                              'Sports', 'Toys', 'Books', 'Health', 'Beauty', 'Automotive', 
                              'Office', 'Pet', 'Baby', 'Jewelry', 'Shoes', 'Bags', 'Tools',
                              'Music', 'Movies', 'Games', 'Food', 'Grocery'];
    
    foreach ($single_word_categories as $category) {
        if (stripos($buffer, '"' . $category . '"') !== false) {
            if (!in_array($category, $level1_categories)) {
                $level1_categories[] = $category;
                echo "\n✅ 发现一级分类: $category";
            }
        }
    }
    
    // 2. 寻找所有包含&的分类
    if (preg_match_all('/"([^"]*&[^"]*)"/', $buffer, $matches)) {
        foreach ($matches[1] as $category) {
            if (strlen($category) > 3 && !in_array($category, $all_categories)) {
                $all_categories[] = $category;
                
                // 分析分类模式
                $parts = explode('&', $category);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (strlen($part) > 2 && !in_array($part, $category_patterns)) {
                        $category_patterns[] = $part;
                    }
                }
            }
        }
    }
    
    // 3. 寻找properties结构中的顶级分类
    if (preg_match_all('/"properties":\s*{[^}]*"([^"]+)":\s*{/', $buffer, $matches)) {
        foreach ($matches[1] as $prop_name) {
            // 检查是否为分类名模式
            if (preg_match('/^[A-Z][a-z]+$/', $prop_name) && strlen($prop_name) > 3) {
                if (!in_array($prop_name, $level1_categories)) {
                    $level1_categories[] = $prop_name;
                    echo "\n🔍 发现可能的一级分类: $prop_name";
                }
            }
        }
    }
    
    // 保留最后1MB的buffer
    if (strlen($buffer) > $chunk_size * 2) {
        $buffer = substr($buffer, -$chunk_size);
    }
}

fclose($handle);

echo "\n\n=== 分析结果 ===\n";

// 显示一级分类
echo "\n🏷️ 发现的一级分类 (" . count($level1_categories) . " 个):\n";
foreach ($level1_categories as $category) {
    echo "  - $category\n";
}

// 分析分类模式，找出最常见的一级分类词
echo "\n📊 分类模式分析 (最常见的分类词):\n";
$pattern_count = array_count_values($category_patterns);
arsort($pattern_count);

foreach (array_slice($pattern_count, 0, 20, true) as $pattern => $count) {
    echo "  $pattern: $count 次\n";
}

// 重新分析所有分类的层级
echo "\n📋 重新分析分类层级:\n";

$hierarchy = [
    'level_1' => [],
    'level_2' => [],
    'level_3' => [],
    'level_4' => []
];

foreach (array_slice($all_categories, 0, 100) as $category) {
    // 重新定义层级判断逻辑
    $parts = preg_split('/[&,]/', $category);
    $part_count = count($parts);
    $total_words = str_word_count($category);
    
    if ($part_count == 1 && $total_words <= 2) {
        // 一级：单个部分，1-2个词
        $hierarchy['level_1'][] = $category;
    } elseif ($part_count == 2 && $total_words <= 4) {
        // 二级：两个部分，总共不超过4个词
        $hierarchy['level_2'][] = $category;
    } elseif ($part_count <= 3 && $total_words <= 8) {
        // 三级：2-3个部分，总共不超过8个词
        $hierarchy['level_3'][] = $category;
    } else {
        // 四级：更复杂的分类
        $hierarchy['level_4'][] = $category;
    }
}

foreach ($hierarchy as $level => $categories) {
    if (!empty($categories)) {
        echo "\n" . strtoupper($level) . " (" . count($categories) . " 个):\n";
        foreach (array_slice($categories, 0, 5) as $cat) {
            echo "  - $cat\n";
        }
    }
}

// 特别查找Walmart官方的顶级分类
echo "\n🎯 查找Walmart官方顶级分类结构...\n";

// 重新打开文件，寻找schema的顶级结构
$handle = fopen($json_file, 'r');
$first_chunk = fread($handle, 1024 * 1024); // 读取第一个1MB
fclose($handle);

// 查找properties的直接子级
if (preg_match('/"properties":\s*{([^}]+)}/', $first_chunk, $matches)) {
    echo "发现顶级properties结构:\n";
    $properties_content = $matches[1];
    
    if (preg_match_all('/"([^"]+)":\s*{/', $properties_content, $prop_matches)) {
        echo "顶级属性/分类:\n";
        foreach ($prop_matches[1] as $prop) {
            echo "  - $prop\n";
        }
    }
}

// 保存结果
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'level1_categories' => $level1_categories,
    'category_patterns' => $pattern_count,
    'hierarchy_analysis' => $hierarchy,
    'total_categories_analyzed' => count($all_categories)
];

$output_file = 'level1_analysis_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 分析结果已保存到: $output_file\n";

echo "\n=== 分析完成 ===\n";
echo "🎯 重点: 查看是否找到了真正的一级分类\n";
?>
