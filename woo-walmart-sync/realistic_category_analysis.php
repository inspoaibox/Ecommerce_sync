<?php
/**
 * 重新用常识分析Walmart分类层级
 */

set_time_limit(300);
ini_set('memory_limit', '4G');

echo "=== 用常识重新分析Walmart分类层级 ===\n";

// 读取之前的完整分析结果
$analysis_file = 'complete_hierarchy_20250802_044254.json';

if (!file_exists($analysis_file)) {
    echo "❌ 未找到之前的分析文件\n";
    exit;
}

$data = json_decode(file_get_contents($analysis_file), true);
$all_categories = [];

// 从所有枚举中提取分类
foreach ($data['category_enums'] as $enum) {
    $all_categories = array_merge($all_categories, $enum['values']);
}

$all_categories = array_unique($all_categories);
echo "总共 " . count($all_categories) . " 个分类\n";

// 用常识重新分类
echo "\n🧠 用常识重新分析分类层级...\n";

$realistic_hierarchy = [
    'departments' => [],      // 部门级 (10-50个)
    'categories' => [],       // 分类级 (100-500个)  
    'subcategories' => [],    // 子分类级 (500-2000个)
    'products' => [],         // 产品级 (2000+个)
    'attributes' => []        // 属性级
];

// 已知的Walmart主要部门
$known_departments = [
    'Electronics', 'Clothing', 'Home', 'Garden', 'Automotive', 'Sports', 
    'Toys', 'Baby', 'Health', 'Beauty', 'Grocery', 'Pharmacy', 'Photo',
    'Jewelry', 'Shoes', 'Books', 'Movies', 'Music', 'Video Games',
    'Cell Phones', 'Computers', 'Appliances', 'Furniture', 'Patio', 
    'Crafts', 'Party', 'Wedding', 'Seasonal', 'Travel', 'Office'
];

foreach ($all_categories as $category) {
    $category_clean = trim($category);
    $word_count = str_word_count($category_clean);
    $length = strlen($category_clean);
    
    // 1. 部门级判断 - 单个词，是已知部门
    if ($word_count == 1 && in_array($category_clean, $known_departments)) {
        $realistic_hierarchy['departments'][] = $category_clean;
    }
    // 2. 分类级判断 - 2-3个词，包含部门名
    elseif ($word_count >= 2 && $word_count <= 4) {
        $is_category = false;
        foreach ($known_departments as $dept) {
            if (stripos($category_clean, $dept) !== false) {
                $is_category = true;
                break;
            }
        }
        
        // 或者是常见的分类模式
        if (!$is_category && (
            strpos($category_clean, '&') !== false ||
            preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+$/', $category_clean)
        )) {
            $is_category = true;
        }
        
        if ($is_category) {
            $realistic_hierarchy['categories'][] = $category_clean;
        } else {
            $realistic_hierarchy['subcategories'][] = $category_clean;
        }
    }
    // 3. 子分类级判断 - 4-6个词，描述性
    elseif ($word_count >= 4 && $word_count <= 8 && $length < 60) {
        $realistic_hierarchy['subcategories'][] = $category_clean;
    }
    // 4. 产品级判断 - 很具体的产品描述
    elseif ($word_count > 6 || $length > 50) {
        $realistic_hierarchy['products'][] = $category_clean;
    }
    // 5. 属性级判断 - 单个词，看起来像属性
    elseif ($word_count == 1 && $length < 20) {
        // 检查是否为属性词
        $attribute_patterns = [
            'ing$', 'ed$', 'er$', 'ly$'  // 动词、形容词等
        ];
        
        $is_attribute = false;
        foreach ($attribute_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/', strtolower($category_clean))) {
                $is_attribute = true;
                break;
            }
        }
        
        if ($is_attribute || in_array(strtolower($category_clean), [
            'repairing', 'cleansing', 'moisturizing', 'conditioning', 
            'brightening', 'strengthening', 'softening'
        ])) {
            $realistic_hierarchy['attributes'][] = $category_clean;
        } else {
            $realistic_hierarchy['subcategories'][] = $category_clean;
        }
    }
    else {
        $realistic_hierarchy['subcategories'][] = $category_clean;
    }
}

// 显示合理的分析结果
echo "\n📊 合理的分类层级分析:\n";

foreach ($realistic_hierarchy as $level => $items) {
    $level_names = [
        'departments' => '🏢 部门级',
        'categories' => '📁 分类级', 
        'subcategories' => '📂 子分类级',
        'products' => '📦 产品级',
        'attributes' => '🔧 属性级'
    ];
    
    echo "\n{$level_names[$level]} (" . count($items) . " 个):\n";
    
    // 去重并排序
    $items = array_unique($items);
    sort($items);
    
    foreach (array_slice($items, 0, 20) as $item) {
        echo "  - $item\n";
    }
    
    if (count($items) > 20) {
        echo "  ... 还有 " . (count($items) - 20) . " 个\n";
    }
}

// 分析部门的子分类
echo "\n🔍 分析各部门的子分类数量:\n";

$department_analysis = [];
foreach ($realistic_hierarchy['departments'] as $dept) {
    $dept_subcats = [];
    
    foreach (array_merge($realistic_hierarchy['categories'], $realistic_hierarchy['subcategories']) as $subcat) {
        if (stripos($subcat, $dept) !== false) {
            $dept_subcats[] = $subcat;
        }
    }
    
    if (!empty($dept_subcats)) {
        $department_analysis[$dept] = $dept_subcats;
        echo "$dept: " . count($dept_subcats) . " 个子分类\n";
    }
}

// 显示合理的统计
echo "\n📈 合理的分类统计:\n";
$total = 0;
foreach ($realistic_hierarchy as $level => $items) {
    $count = count(array_unique($items));
    $total += $count;
    
    $level_names = [
        'departments' => '部门级',
        'categories' => '分类级', 
        'subcategories' => '子分类级',
        'products' => '产品级',
        'attributes' => '属性级'
    ];
    
    $percentage = $total > 0 ? ($count / $total) * 100 : 0;
    echo "{$level_names[$level]}: $count 个 (" . number_format($percentage, 1) . "%)\n";
}

echo "总计: $total 个\n";

// 保存合理的分析结果
$realistic_results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'realistic_hierarchy' => $realistic_hierarchy,
    'department_analysis' => $department_analysis,
    'statistics' => [
        'departments' => count(array_unique($realistic_hierarchy['departments'])),
        'categories' => count(array_unique($realistic_hierarchy['categories'])),
        'subcategories' => count(array_unique($realistic_hierarchy['subcategories'])),
        'products' => count(array_unique($realistic_hierarchy['products'])),
        'attributes' => count(array_unique($realistic_hierarchy['attributes']))
    ]
];

$output_file = 'realistic_hierarchy_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($realistic_results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 合理的分析结果已保存到: $output_file\n";

echo "\n=== 合理分析完成 ===\n";
echo "🎯 这个分析结果应该更符合常识\n";
?>
