<?php
/**
 * 找到真正的Walmart分类定义
 */

set_time_limit(300);
ini_set('memory_limit', '4G');

echo "=== 寻找真正的Walmart分类定义 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

// 搜索关键的分类相关字段
$category_keywords = [
    'category', 'Category', 'CATEGORY',
    'department', 'Department', 'DEPARTMENT', 
    'taxonomy', 'Taxonomy', 'TAXONOMY',
    'classification', 'Classification'
];

echo "🔍 搜索分类相关的字段定义...\n";

$handle = fopen($json_file, 'r');
$category_findings = [];
$chunk_count = 0;

while (!feof($handle) && $chunk_count < 50) {
    $chunk = fread($handle, 1024 * 1024 * 2);
    $chunk_count++;
    
    echo "\r搜索块 #$chunk_count";
    
    foreach ($category_keywords as $keyword) {
        if (stripos($chunk, '"' . $keyword . '"') !== false) {
            // 找到包含分类关键词的行
            $lines = explode("\n", $chunk);
            foreach ($lines as $line_num => $line) {
                if (stripos($line, '"' . $keyword . '"') !== false) {
                    $category_findings[] = [
                        'keyword' => $keyword,
                        'chunk' => $chunk_count,
                        'line' => trim($line),
                        'context' => trim(substr($chunk, max(0, strpos($chunk, $line) - 200), 400))
                    ];
                }
            }
        }
    }
}

fclose($handle);

echo "\n\n=== 分类字段发现 ===\n";

foreach (array_slice($category_findings, 0, 20) as $finding) {
    echo "\n🔑 关键词: {$finding['keyword']}\n";
    echo "📍 位置: 块#{$finding['chunk']}\n";
    echo "📝 内容: {$finding['line']}\n";
    echo "🔍 上下文: " . substr($finding['context'], 0, 200) . "...\n";
    echo "---\n";
}

// 特别搜索可能的分类枚举
echo "\n🎯 搜索可能的分类枚举...\n";

$handle = fopen($json_file, 'r');
$possible_category_enums = [];
$chunk_count = 0;

while (!feof($handle) && $chunk_count < 30) {
    $chunk = fread($handle, 1024 * 1024 * 3);
    $chunk_count++;
    
    echo "\r分析块 #$chunk_count";
    
    // 查找包含明显分类名称的枚举
    if (preg_match_all('/"enum":\s*\[([^\]]+)\]/', $chunk, $enum_matches)) {
        foreach ($enum_matches[1] as $enum_content) {
            if (preg_match_all('/"([^"]+)"/', $enum_content, $value_matches)) {
                $values = $value_matches[1];
                
                // 检查是否包含明显的分类名称
                $category_indicators = [
                    'Home & Garden', 'Electronics', 'Clothing', 'Furniture',
                    'Sports & Recreation', 'Health & Beauty', 'Automotive',
                    'Books & Media', 'Toys & Games', 'Baby & Kids'
                ];
                
                $has_category_names = false;
                foreach ($values as $value) {
                    foreach ($category_indicators as $indicator) {
                        if (stripos($value, $indicator) !== false || 
                            (strpos($value, '&') !== false && strlen($value) > 10)) {
                            $has_category_names = true;
                            break 2;
                        }
                    }
                }
                
                if ($has_category_names && count($values) > 5) {
                    $possible_category_enums[] = [
                        'values' => $values,
                        'count' => count($values),
                        'chunk' => $chunk_count
                    ];
                }
            }
        }
    }
}

fclose($handle);

echo "\n\n=== 可能的分类枚举 ===\n";

foreach (array_slice($possible_category_enums, 0, 5) as $i => $enum) {
    echo "\n📋 枚举 #" . ($i + 1) . " (块#{$enum['chunk']}, {$enum['count']} 个值):\n";
    
    foreach (array_slice($enum['values'], 0, 15) as $value) {
        echo "  - $value\n";
    }
    
    if (count($enum['values']) > 15) {
        echo "  ... 还有 " . (count($enum['values']) - 15) . " 个\n";
    }
}

// 搜索特定的分类字段名
echo "\n🔍 搜索特定的分类字段名...\n";

$specific_fields = [
    'categoryPath', 'categoryId', 'categoryName', 'productCategory',
    'itemCategory', 'taxonomyPath', 'departmentId', 'departmentName'
];

$handle = fopen($json_file, 'r');
$field_findings = [];

while (!feof($handle)) {
    $chunk = fread($handle, 1024 * 1024);
    
    foreach ($specific_fields as $field) {
        if (stripos($chunk, '"' . $field . '"') !== false) {
            $field_findings[$field] = true;
            echo "✅ 找到字段: $field\n";
        }
    }
    
    if (count($field_findings) >= count($specific_fields)) {
        break;
    }
}

fclose($handle);

// 保存发现
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'category_findings' => $category_findings,
    'possible_category_enums' => $possible_category_enums,
    'field_findings' => array_keys($field_findings)
];

$output_file = 'real_categories_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 发现结果已保存到: $output_file\n";

echo "\n=== 结论 ===\n";
echo "🎯 之前分析的6948个项目主要是属性值，不是分类\n";
echo "🎯 真正的分类应该在特定的字段定义中\n";
echo "🎯 需要查看生成的文件获取真正的分类信息\n";
?>
