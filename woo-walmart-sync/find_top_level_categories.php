<?php
/**
 * 深入分析Walmart的真正顶级分类结构
 */

set_time_limit(600);
ini_set('memory_limit', '8G');

echo "=== 深入分析Walmart真正的顶级分类结构 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在\n";
    exit;
}

echo "📁 深度分析JSON Schema，寻找所有层级的分类...\n";

// 1. 首先分析文件的整体结构
$handle = fopen($json_file, 'r');
$first_10mb = fread($handle, 1024 * 1024 * 10); // 读取前10MB
fclose($handle);

echo "🔍 分析文件整体结构...\n";

// 查找schema的根结构
if (preg_match('/{[^}]*"properties":\s*{([^}]+)}/s', $first_10mb, $matches)) {
    echo "✅ 发现根级properties结构\n";
    $root_properties = $matches[1];
    
    if (preg_match_all('/"([^"]+)":\s*{/', $root_properties, $prop_matches)) {
        echo "📋 根级属性:\n";
        foreach ($prop_matches[1] as $prop) {
            echo "  - $prop\n";
        }
    }
}

// 2. 寻找所有可能的分类枚举
echo "\n🔍 搜索所有分类枚举...\n";

$handle = fopen($json_file, 'r');
$all_enums = [];
$category_enums = [];
$chunk_count = 0;

while (!feof($handle) && $chunk_count < 100) {
    $chunk = fread($handle, 1024 * 1024 * 2);
    $chunk_count++;
    
    echo "\r分析块 #$chunk_count";
    
    // 查找所有enum定义
    if (preg_match_all('/"enum":\s*\[([^\]]+)\]/', $chunk, $enum_matches)) {
        foreach ($enum_matches[1] as $enum_content) {
            // 提取枚举值
            if (preg_match_all('/"([^"]+)"/', $enum_content, $value_matches)) {
                $enum_values = $value_matches[1];
                
                // 判断是否为分类枚举
                if (count($enum_values) > 5) {
                    $category_like_count = 0;
                    foreach (array_slice($enum_values, 0, 10) as $value) {
                        // 分类特征：包含大写字母开头、空格、&符号等
                        if (preg_match('/^[A-Z]/', $value) && 
                            (strpos($value, ' ') !== false || 
                             strpos($value, '&') !== false || 
                             strpos($value, ',') !== false ||
                             strlen($value) > 8)) {
                            $category_like_count++;
                        }
                    }
                    
                    // 如果大部分值看起来像分类，保存这个枚举
                    if ($category_like_count > count($enum_values) * 0.4) {
                        $category_enums[] = [
                            'values' => $enum_values,
                            'count' => count($enum_values),
                            'category_score' => $category_like_count / count($enum_values)
                        ];
                    }
                }
            }
        }
    }
}

fclose($handle);

echo "\n\n=== 发现的分类枚举 ===\n";

// 按分类数量排序
usort($category_enums, function($a, $b) {
    return $b['count'] - $a['count'];
});

foreach (array_slice($category_enums, 0, 10) as $i => $enum) {
    echo "\n📋 枚举 #" . ($i + 1) . " (包含 {$enum['count']} 个分类，分类度: " . number_format($enum['category_score'] * 100, 1) . "%):\n";
    
    foreach (array_slice($enum['values'], 0, 20) as $value) {
        echo "  - $value\n";
    }
    
    if (count($enum['values']) > 20) {
        echo "  ... 还有 " . (count($enum['values']) - 20) . " 个\n";
    }
}

// 3. 分析分类层级关系
echo "\n🔍 分析分类层级关系...\n";

$all_categories = [];
foreach ($category_enums as $enum) {
    $all_categories = array_merge($all_categories, $enum['values']);
}

$all_categories = array_unique($all_categories);
echo "总共发现 " . count($all_categories) . " 个唯一分类\n";

// 按层级分析
$hierarchy_analysis = [
    'level_0' => [], // 可能的超级分类
    'level_1' => [], // 一级分类
    'level_2' => [], // 二级分类  
    'level_3' => [], // 三级分类
    'level_4' => []  // 四级分类
];

foreach ($all_categories as $category) {
    $word_count = str_word_count($category);
    $separator_count = substr_count($category, '&') + substr_count($category, ',');
    $length = strlen($category);
    
    // 重新定义层级判断
    if ($word_count == 1 && $length < 15) {
        // 超级分类：单个词，很短
        $hierarchy_analysis['level_0'][] = $category;
    } elseif ($word_count <= 2 && $separator_count == 0 && $length < 25) {
        // 一级分类：1-2个词，无分隔符，较短
        $hierarchy_analysis['level_1'][] = $category;
    } elseif ($separator_count == 1 && $word_count <= 4) {
        // 二级分类：一个分隔符，不超过4个词
        $hierarchy_analysis['level_2'][] = $category;
    } elseif ($separator_count <= 2 && $word_count <= 8) {
        // 三级分类：1-2个分隔符，不超过8个词
        $hierarchy_analysis['level_3'][] = $category;
    } else {
        // 四级分类：更复杂
        $hierarchy_analysis['level_4'][] = $category;
    }
}

// 显示层级分析
echo "\n📊 重新分析的分类层级:\n";
foreach ($hierarchy_analysis as $level => $categories) {
    if (!empty($categories)) {
        echo "\n" . strtoupper($level) . " (" . count($categories) . " 个):\n";
        foreach (array_slice($categories, 0, 15) as $cat) {
            echo "  - $cat\n";
        }
        if (count($categories) > 15) {
            echo "  ... 还有 " . (count($categories) - 15) . " 个\n";
        }
    }
}

// 4. 特别查找Walmart的部门/大类
echo "\n🎯 查找Walmart的部门/大类结构...\n";

$walmart_departments = [];
$known_departments = [
    'Electronics', 'Clothing', 'Home', 'Garden', 'Automotive', 'Sports', 
    'Toys', 'Baby', 'Health', 'Beauty', 'Grocery', 'Pharmacy', 'Photo',
    'Jewelry', 'Shoes', 'Books', 'Movies', 'Music', 'Video Games',
    'Cell Phones', 'Computers', 'TV', 'Appliances', 'Furniture',
    'Patio', 'Crafts', 'Party', 'Wedding', 'Seasonal', 'Travel'
];

foreach ($all_categories as $category) {
    foreach ($known_departments as $dept) {
        if (stripos($category, $dept) !== false) {
            if (!isset($walmart_departments[$dept])) {
                $walmart_departments[$dept] = [];
            }
            $walmart_departments[$dept][] = $category;
        }
    }
}

echo "发现的部门及其子分类:\n";
foreach ($walmart_departments as $dept => $subcats) {
    echo "\n🏢 $dept (" . count($subcats) . " 个子分类):\n";
    foreach (array_slice($subcats, 0, 5) as $subcat) {
        echo "  - $subcat\n";
    }
    if (count($subcats) > 5) {
        echo "  ... 还有 " . (count($subcats) - 5) . " 个\n";
    }
}

// 保存详细结果
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_categories' => count($all_categories),
    'category_enums' => $category_enums,
    'hierarchy_analysis' => $hierarchy_analysis,
    'walmart_departments' => $walmart_departments,
    'statistics' => [
        'level_0_count' => count($hierarchy_analysis['level_0']),
        'level_1_count' => count($hierarchy_analysis['level_1']),
        'level_2_count' => count($hierarchy_analysis['level_2']),
        'level_3_count' => count($hierarchy_analysis['level_3']),
        'level_4_count' => count($hierarchy_analysis['level_4']),
        'departments_found' => count($walmart_departments)
    ]
];

$output_file = 'complete_hierarchy_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 完整分析结果已保存到: $output_file\n";

echo "\n=== 分析完成 ===\n";
echo "🎯 现在应该能看到完整的分类层级结构了\n";
?>
