<?php
/**
 * 分析Walmart分类层级结构
 */

set_time_limit(300);
ini_set('memory_limit', '4G');

echo "=== 分析Walmart分类层级结构 ===\n";

// 读取之前提取的结果
$furniture_file = 'furniture_analysis_20250802_043127.json';

if (!file_exists($furniture_file)) {
    echo "❌ 未找到之前的分析文件，重新分析...\n";
    
    $json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';
    
    // 快速重新提取分类
    $handle = fopen($json_file, 'r');
    $categories = [];
    $furniture_items = [];
    
    $chunk_count = 0;
    while (!feof($handle) && $chunk_count < 20) { // 只读前20个块
        $chunk = fread($handle, 1024 * 1024 * 2);
        $chunk_count++;
        
        echo "\r分析块 #$chunk_count";
        
        // 提取分类
        if (preg_match_all('/"([^"]*&[^"]*)"/', $chunk, $matches)) {
            foreach ($matches[1] as $category) {
                if (strlen($category) > 5 && !in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }
        }
        
        // 提取Furniture相关
        if (stripos($chunk, 'furniture') !== false) {
            $lines = explode("\n", $chunk);
            foreach ($lines as $line) {
                if (stripos($line, 'furniture') !== false) {
                    $furniture_items[] = trim($line);
                }
            }
        }
    }
    
    fclose($handle);
    
} else {
    echo "✅ 读取之前的分析文件...\n";
    $data = json_decode(file_get_contents($furniture_file), true);
    $categories = $data['categories_findings'] ?? [];
    $furniture_items = array_column($data['furniture_findings'] ?? [], 'content');
}

echo "\n\n=== 分类层级分析 ===\n";

// 分析分类层级
$hierarchy_analysis = [
    'level_1' => [], // 一级分类 (如 "Home")
    'level_2' => [], // 二级分类 (如 "Home & Garden") 
    'level_3' => [], // 三级分类 (如 "Home & Garden, Kitchen")
    'level_4' => [], // 四级分类 (更细分)
    'unknown' => []
];

foreach ($categories as $category) {
    // 分析分类层级的特征
    $comma_count = substr_count($category, ',');
    $ampersand_count = substr_count($category, '&');
    $word_count = str_word_count($category);
    
    // 层级判断逻辑
    if ($comma_count === 0 && $ampersand_count === 0 && $word_count <= 2) {
        // 一级分类: 单个词或两个词，无连接符
        $hierarchy_analysis['level_1'][] = $category;
    } elseif ($comma_count === 0 && $ampersand_count === 1) {
        // 二级分类: 包含一个&符号
        $hierarchy_analysis['level_2'][] = $category;
    } elseif ($comma_count >= 1 || ($ampersand_count >= 1 && $word_count > 4)) {
        // 三级分类: 包含逗号或多个词+&符号
        $hierarchy_analysis['level_3'][] = $category;
    } elseif ($word_count > 6 || strlen($category) > 50) {
        // 四级分类: 很长的描述性分类
        $hierarchy_analysis['level_4'][] = $category;
    } else {
        $hierarchy_analysis['unknown'][] = $category;
    }
}

// 显示层级分析结果
foreach ($hierarchy_analysis as $level => $items) {
    if (!empty($items)) {
        echo "\n📁 " . strtoupper($level) . " (" . count($items) . " 个):\n";
        foreach (array_slice($items, 0, 10) as $item) {
            echo "  - $item\n";
        }
        if (count($items) > 10) {
            echo "  ... 还有 " . (count($items) - 10) . " 个\n";
        }
    }
}

echo "\n=== Furniture项目分析 ===\n";

// 分析Furniture项目的性质
$furniture_analysis = [
    'categories' => [], // 分类
    'attributes' => [], // 属性
    'values' => [],     // 属性值
    'other' => []       // 其他
];

foreach (array_slice($furniture_items, 0, 50) as $item) {
    $clean_item = trim(str_replace(['"', ':', ',', '{', '}'], '', $item));
    
    if (empty($clean_item)) continue;
    
    // 判断是分类还是属性
    if (strpos($clean_item, 'Furniture') !== false) {
        if (strpos($clean_item, '&') !== false || strpos($clean_item, ',') !== false) {
            // 包含连接符，可能是分类
            $furniture_analysis['categories'][] = $clean_item;
        } elseif (preg_match('/^[A-Z][a-z]+ Furniture$/', $clean_item)) {
            // 形如 "Bedroom Furniture" 的分类
            $furniture_analysis['categories'][] = $clean_item;
        } elseif (preg_match('/Furniture [A-Z]/', $clean_item)) {
            // 形如 "Furniture Legs" 的属性或配件
            $furniture_analysis['attributes'][] = $clean_item;
        } else {
            $furniture_analysis['other'][] = $clean_item;
        }
    } else {
        $furniture_analysis['values'][] = $clean_item;
    }
}

// 显示Furniture分析结果
foreach ($furniture_analysis as $type => $items) {
    if (!empty($items)) {
        $type_name = [
            'categories' => '🏷️ 分类',
            'attributes' => '🔧 属性/配件', 
            'values' => '📝 属性值',
            'other' => '❓ 其他'
        ][$type];
        
        echo "\n$type_name (" . count($items) . " 个):\n";
        foreach (array_slice($items, 0, 10) as $item) {
            echo "  - $item\n";
        }
        if (count($items) > 10) {
            echo "  ... 还有 " . (count($items) - 10) . " 个\n";
        }
    }
}

// 生成层级统计
echo "\n=== 层级统计总结 ===\n";
$total_categories = array_sum(array_map('count', $hierarchy_analysis));
echo "📊 总分类数: $total_categories\n";

foreach ($hierarchy_analysis as $level => $items) {
    $count = count($items);
    $percentage = $total_categories > 0 ? ($count / $total_categories) * 100 : 0;
    echo "  " . strtoupper($level) . ": $count 个 (" . number_format($percentage, 1) . "%)\n";
}

echo "\n🪑 Furniture项目统计:\n";
$total_furniture = array_sum(array_map('count', $furniture_analysis));
echo "📊 总Furniture项目: $total_furniture\n";

foreach ($furniture_analysis as $type => $items) {
    $count = count($items);
    $percentage = $total_furniture > 0 ? ($count / $total_furniture) * 100 : 0;
    $type_name = ['categories' => '分类', 'attributes' => '属性', 'values' => '值', 'other' => '其他'][$type];
    echo "  $type_name: $count 个 (" . number_format($percentage, 1) . "%)\n";
}

// 保存分析结果
$analysis_result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'hierarchy_analysis' => $hierarchy_analysis,
    'furniture_analysis' => $furniture_analysis,
    'statistics' => [
        'total_categories' => $total_categories,
        'level_distribution' => array_map('count', $hierarchy_analysis),
        'furniture_distribution' => array_map('count', $furniture_analysis)
    ]
];

$output_file = 'hierarchy_analysis_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($analysis_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 详细分析结果已保存到: $output_file\n";

echo "\n=== 分析完成 ===\n";
echo "🎯 结论: 可以看出Walmart的分类层级结构和Furniture的具体构成\n";
?>
