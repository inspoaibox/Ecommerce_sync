<?php
/**
 * 搜索Walmart的真正分类关键词
 */

set_time_limit(300);
ini_set('memory_limit', '4G');

echo "=== 搜索Walmart真正的分类关键词 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

// 您提供的真正的Walmart分类
$walmart_categories = [
    'Media',
    'Fashion', 
    'Office & Stationery',
    'Toys',
    'Garden & Patio',
    'Photography',
    'Electronics',
    'Occasion & Seasonal',
    'Furniture',
    'Business & Industrial',
    'Sports & Outdoors',
    'Safety & Emergency'
];

// 简化版本（去掉&符号）
$simplified_categories = [
    'Media',
    'Fashion',
    'Office',
    'Stationery', 
    'Toys',
    'Garden',
    'Patio',
    'Photography',
    'Electronics',
    'Occasion',
    'Seasonal',
    'Furniture',
    'Business',
    'Industrial',
    'Sports',
    'Outdoors',
    'Safety',
    'Emergency'
];

echo "🔍 搜索以下分类关键词:\n";
foreach ($walmart_categories as $category) {
    echo "  - $category\n";
}

$findings = [];
$handle = fopen($json_file, 'r');
$chunk_count = 0;
$total_size = filesize($json_file);
$processed_size = 0;

while (!feof($handle)) {
    $chunk = fread($handle, 1024 * 1024 * 2); // 2MB chunks
    $processed_size += strlen($chunk);
    $chunk_count++;
    
    $progress = ($processed_size / $total_size) * 100;
    echo "\r进度: " . number_format($progress, 1) . "% (块 #$chunk_count)";
    
    // 搜索完整的分类名
    foreach ($walmart_categories as $category) {
        if (stripos($chunk, $category) !== false) {
            // 找到匹配的行
            $lines = explode("\n", $chunk);
            foreach ($lines as $line_num => $line) {
                if (stripos($line, $category) !== false) {
                    $findings[] = [
                        'category' => $category,
                        'chunk' => $chunk_count,
                        'line' => trim($line),
                        'type' => 'exact_match'
                    ];
                }
            }
        }
    }
    
    // 搜索简化版本
    foreach ($simplified_categories as $category) {
        if (stripos($chunk, '"' . $category . '"') !== false) {
            $lines = explode("\n", $chunk);
            foreach ($lines as $line_num => $line) {
                if (stripos($line, '"' . $category . '"') !== false) {
                    $findings[] = [
                        'category' => $category,
                        'chunk' => $chunk_count,
                        'line' => trim($line),
                        'type' => 'simplified_match'
                    ];
                }
            }
        }
    }
}

fclose($handle);

echo "\n\n=== 搜索结果 ===\n";

if (empty($findings)) {
    echo "❌ 未找到任何匹配的分类关键词\n";
} else {
    echo "✅ 找到 " . count($findings) . " 个匹配项\n\n";
    
    // 按分类分组显示
    $grouped_findings = [];
    foreach ($findings as $finding) {
        $grouped_findings[$finding['category']][] = $finding;
    }
    
    foreach ($grouped_findings as $category => $matches) {
        echo "🎯 分类: $category (" . count($matches) . " 个匹配)\n";
        
        foreach (array_slice($matches, 0, 5) as $match) {
            echo "  📍 块#{$match['chunk']} ({$match['type']}): {$match['line']}\n";
        }
        
        if (count($matches) > 5) {
            echo "  ... 还有 " . (count($matches) - 5) . " 个匹配\n";
        }
        echo "\n";
    }
}

// 特别搜索PTG相关内容
echo "🔍 搜索PTG相关内容...\n";

$handle = fopen($json_file, 'r');
$ptg_findings = [];
$chunk_count = 0;

while (!feof($handle) && $chunk_count < 50) {
    $chunk = fread($handle, 1024 * 1024 * 2);
    $chunk_count++;
    
    echo "\r搜索PTG块 #$chunk_count";
    
    if (stripos($chunk, 'PTG') !== false || stripos($chunk, 'ptg') !== false) {
        $lines = explode("\n", $chunk);
        foreach ($lines as $line) {
            if (stripos($line, 'PTG') !== false || stripos($line, 'ptg') !== false) {
                $ptg_findings[] = [
                    'chunk' => $chunk_count,
                    'line' => trim($line)
                ];
            }
        }
    }
}

fclose($handle);

echo "\n\n=== PTG搜索结果 ===\n";

if (empty($ptg_findings)) {
    echo "❌ 未找到PTG相关内容\n";
} else {
    echo "✅ 找到 " . count($ptg_findings) . " 个PTG匹配项\n\n";
    
    foreach (array_slice($ptg_findings, 0, 10) as $finding) {
        echo "📍 块#{$finding['chunk']}: {$finding['line']}\n";
    }
    
    if (count($ptg_findings) > 10) {
        echo "... 还有 " . (count($ptg_findings) - 10) . " 个\n";
    }
}

// 搜索可能的分类枚举（包含这些关键词的）
echo "\n🔍 搜索包含这些分类的枚举...\n";

$handle = fopen($json_file, 'r');
$category_enums = [];
$chunk_count = 0;

while (!feof($handle) && $chunk_count < 30) {
    $chunk = fread($handle, 1024 * 1024 * 3);
    $chunk_count++;
    
    echo "\r搜索枚举块 #$chunk_count";
    
    // 查找包含我们关键词的枚举
    if (preg_match_all('/"enum":\s*\[([^\]]+)\]/', $chunk, $enum_matches)) {
        foreach ($enum_matches[1] as $enum_content) {
            // 检查枚举是否包含我们的分类关键词
            $contains_category = false;
            foreach ($walmart_categories as $category) {
                if (stripos($enum_content, $category) !== false) {
                    $contains_category = true;
                    break;
                }
            }
            
            if ($contains_category) {
                if (preg_match_all('/"([^"]+)"/', $enum_content, $value_matches)) {
                    $category_enums[] = [
                        'values' => $value_matches[1],
                        'chunk' => $chunk_count,
                        'count' => count($value_matches[1])
                    ];
                }
            }
        }
    }
}

fclose($handle);

echo "\n\n=== 包含分类关键词的枚举 ===\n";

if (empty($category_enums)) {
    echo "❌ 未找到包含分类关键词的枚举\n";
} else {
    echo "✅ 找到 " . count($category_enums) . " 个相关枚举\n\n";
    
    foreach (array_slice($category_enums, 0, 3) as $i => $enum) {
        echo "📋 枚举 #" . ($i + 1) . " (块#{$enum['chunk']}, {$enum['count']} 个值):\n";
        
        foreach (array_slice($enum['values'], 0, 15) as $value) {
            echo "  - $value\n";
        }
        
        if (count($enum['values']) > 15) {
            echo "  ... 还有 " . (count($enum['values']) - 15) . " 个\n";
        }
        echo "\n";
    }
}

// 保存搜索结果
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'searched_categories' => $walmart_categories,
    'findings' => $findings,
    'ptg_findings' => $ptg_findings,
    'category_enums' => $category_enums,
    'summary' => [
        'categories_found' => count($grouped_findings ?? []),
        'total_matches' => count($findings),
        'ptg_matches' => count($ptg_findings),
        'relevant_enums' => count($category_enums)
    ]
];

$output_file = 'walmart_category_search_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "💾 搜索结果已保存到: $output_file\n";

echo "\n=== 搜索完成 ===\n";
echo "🎯 如果找到匹配项，说明这个文件包含真正的分类信息\n";
echo "🎯 如果没找到，说明分类信息可能在其他文件中\n";
?>
