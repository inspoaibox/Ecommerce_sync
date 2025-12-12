<?php
/**
 * 按照正确的层级结构查找Walmart分类
 * 24个分类 -> 488个PTG -> 6961个PT
 */

set_time_limit(300);
ini_set('memory_limit', '4G');

echo "=== 查找正确的Walmart分类层级结构 ===\n";
echo "目标: 24个分类 -> 488个PTG -> 6961个PT\n\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

// 搜索关键词
$search_terms = [
    'PTG', 'ptg', 'Product Type Group',
    'PT', 'Product Type', 'productType',
    'category', 'Category', 'CATEGORY'
];

echo "🔍 搜索分类层级相关的字段...\n";

$findings = [];
$handle = fopen($json_file, 'r');
$chunk_count = 0;
$total_size = filesize($json_file);
$processed_size = 0;

while (!feof($handle) && $chunk_count < 100) {
    $chunk = fread($handle, 1024 * 1024 * 2);
    $processed_size += strlen($chunk);
    $chunk_count++;
    
    $progress = ($processed_size / $total_size) * 100;
    echo "\r进度: " . number_format($progress, 1) . "% (块 #$chunk_count)";
    
    foreach ($search_terms as $term) {
        if (stripos($chunk, $term) !== false) {
            // 提取包含该词的上下文
            $lines = explode("\n", $chunk);
            foreach ($lines as $line_num => $line) {
                if (stripos($line, $term) !== false) {
                    // 获取更多上下文
                    $context_start = max(0, $line_num - 2);
                    $context_end = min(count($lines) - 1, $line_num + 2);
                    $context = [];
                    
                    for ($i = $context_start; $i <= $context_end; $i++) {
                        $context[] = trim($lines[$i]);
                    }
                    
                    $findings[] = [
                        'term' => $term,
                        'chunk' => $chunk_count,
                        'line' => trim($line),
                        'context' => $context
                    ];
                }
            }
        }
    }
}

fclose($handle);

echo "\n\n=== 分类层级字段发现 ===\n";

// 按搜索词分组
$grouped_findings = [];
foreach ($findings as $finding) {
    $grouped_findings[$finding['term']][] = $finding;
}

foreach ($grouped_findings as $term => $matches) {
    echo "\n🔑 关键词: $term (" . count($matches) . " 个匹配)\n";
    
    foreach (array_slice($matches, 0, 5) as $match) {
        echo "📍 块#{$match['chunk']}: {$match['line']}\n";
        
        // 显示上下文
        echo "   上下文:\n";
        foreach ($match['context'] as $context_line) {
            if (!empty($context_line)) {
                echo "   > $context_line\n";
            }
        }
        echo "\n";
    }
    
    if (count($matches) > 5) {
        echo "   ... 还有 " . (count($matches) - 5) . " 个匹配\n";
    }
}

// 特别搜索可能的分类枚举
echo "\n🔍 搜索可能包含24个分类的枚举...\n";

$handle = fopen($json_file, 'r');
$category_enums = [];
$chunk_count = 0;

while (!feof($handle) && $chunk_count < 50) {
    $chunk = fread($handle, 1024 * 1024 * 3);
    $chunk_count++;
    
    echo "\r搜索枚举块 #$chunk_count";
    
    // 查找枚举，特别关注数量在20-30之间的
    if (preg_match_all('/"enum":\s*\[([^\]]+)\]/', $chunk, $enum_matches)) {
        foreach ($enum_matches[1] as $enum_content) {
            if (preg_match_all('/"([^"]+)"/', $enum_content, $value_matches)) {
                $values = $value_matches[1];
                $count = count($values);
                
                // 查找可能是24个分类的枚举
                if ($count >= 20 && $count <= 30) {
                    $category_enums[] = [
                        'values' => $values,
                        'count' => $count,
                        'chunk' => $chunk_count,
                        'type' => 'possible_24_categories'
                    ];
                }
                // 查找可能是488个PTG的枚举
                elseif ($count >= 400 && $count <= 600) {
                    $category_enums[] = [
                        'values' => array_slice($values, 0, 20), // 只保存前20个作为示例
                        'count' => $count,
                        'chunk' => $chunk_count,
                        'type' => 'possible_488_PTG'
                    ];
                }
                // 查找可能是6961个PT的枚举
                elseif ($count >= 6000 && $count <= 8000) {
                    $category_enums[] = [
                        'values' => array_slice($values, 0, 20), // 只保存前20个作为示例
                        'count' => $count,
                        'chunk' => $chunk_count,
                        'type' => 'possible_6961_PT'
                    ];
                }
            }
        }
    }
}

fclose($handle);

echo "\n\n=== 可能的分类层级枚举 ===\n";

if (empty($category_enums)) {
    echo "❌ 未找到符合数量的枚举\n";
} else {
    echo "✅ 找到 " . count($category_enums) . " 个可能的分类枚举\n\n";
    
    foreach ($category_enums as $enum) {
        echo "📋 {$enum['type']} (块#{$enum['chunk']}, {$enum['count']} 个值):\n";
        
        foreach ($enum['values'] as $value) {
            echo "  - $value\n";
        }
        
        if ($enum['count'] > count($enum['values'])) {
            echo "  ... 还有 " . ($enum['count'] - count($enum['values'])) . " 个\n";
        }
        echo "\n";
    }
}

// 搜索特定的分类字段定义
echo "🔍 搜索特定的分类字段定义...\n";

$field_patterns = [
    'categoryPath', 'categoryId', 'categoryName',
    'ptgPath', 'ptgId', 'ptgName', 'PTG',
    'productTypePath', 'productTypeId', 'productTypeName',
    'taxonomyPath', 'taxonomy'
];

$handle = fopen($json_file, 'r');
$field_definitions = [];

while (!feof($handle)) {
    $chunk = fread($handle, 1024 * 1024);
    
    foreach ($field_patterns as $pattern) {
        if (stripos($chunk, '"' . $pattern . '"') !== false) {
            // 尝试提取字段定义
            $regex = '/"' . preg_quote($pattern, '/') . '":\s*{([^}]+)}/i';
            if (preg_match($regex, $chunk, $matches)) {
                $field_definitions[$pattern] = $matches[1];
                echo "✅ 找到字段定义: $pattern\n";
            } else {
                echo "🔍 找到字段引用: $pattern\n";
            }
        }
    }
}

fclose($handle);

// 保存结果
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'target_structure' => [
        'categories' => 24,
        'PTG' => 488,
        'PT' => 6961
    ],
    'findings' => $grouped_findings,
    'possible_enums' => $category_enums,
    'field_definitions' => $field_definitions
];

$output_file = 'correct_hierarchy_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 分析结果已保存到: $output_file\n";

echo "\n=== 分析完成 ===\n";
echo "🎯 查找是否有符合24->488->6961结构的分类层级\n";
?>
