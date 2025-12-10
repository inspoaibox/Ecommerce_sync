<?php
/**
 * 带进度反馈的 Walmart API 5.0 Schema 分析工具
 * 处理大文件，不受超时限制
 */

// 设置不超时
set_time_limit(0);
ini_set('memory_limit', '2G');

echo "=== Walmart API 5.0 Schema 完整分析工具 ===\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

// 文件路径
$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在: $json_file\n";
    echo "请确保文件路径正确\n";
    exit;
}

// 获取文件大小
$file_size = filesize($json_file);
echo "📁 文件大小: " . number_format($file_size / 1024 / 1024, 2) . " MB\n";

// 步骤1: 读取文件
echo "\n🔄 步骤1: 读取JSON文件...\n";
$start_time = microtime(true);

$json_content = file_get_contents($json_file);
$read_time = microtime(true) - $start_time;

echo "✅ 文件读取完成，耗时: " . number_format($read_time, 2) . " 秒\n";
echo "📊 内容长度: " . number_format(strlen($json_content)) . " 字符\n";

// 步骤2: 解析JSON
echo "\n🔄 步骤2: 解析JSON结构...\n";
$parse_start = microtime(true);

$schema = json_decode($json_content, true);
$parse_time = microtime(true) - $parse_start;

if (!$schema) {
    echo "❌ JSON解析失败: " . json_last_error_msg() . "\n";
    exit;
}

echo "✅ JSON解析完成，耗时: " . number_format($parse_time, 2) . " 秒\n";

// 释放原始内容内存
unset($json_content);

// 步骤3: 分析顶级结构
echo "\n🔄 步骤3: 分析顶级结构...\n";
echo "顶级键数量: " . count($schema) . "\n";
echo "顶级键列表: " . implode(', ', array_keys($schema)) . "\n";

// 步骤4: 分析definitions
$analysis_results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'file_info' => [
        'path' => $json_file,
        'size_mb' => round($file_size / 1024 / 1024, 2),
        'read_time' => $read_time,
        'parse_time' => $parse_time
    ],
    'structure' => [
        'top_level_keys' => array_keys($schema)
    ]
];

if (isset($schema['definitions'])) {
    echo "\n🔄 步骤4: 分析definitions部分...\n";
    $definitions = $schema['definitions'];
    $def_count = count($definitions);
    echo "定义总数: $def_count\n";
    
    $analysis_results['definitions'] = [
        'count' => $def_count,
        'list' => array_keys($definitions)
    ];
    
    echo "前20个定义: " . implode(', ', array_slice(array_keys($definitions), 0, 20)) . "\n";
}

// 步骤5: 深度搜索netContent相关内容
echo "\n🔄 步骤5: 深度搜索netContent相关内容...\n";

function deep_search_with_progress($data, $search_terms, $path = '', &$results = [], &$processed = 0, $total = null) {
    static $last_progress_time = 0;
    
    if ($total === null) {
        $total = count_recursive_elements($data);
        echo "总元素数量: " . number_format($total) . "\n";
    }
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $processed++;
            
            // 每1000个元素显示一次进度
            if ($processed % 1000 == 0 || microtime(true) - $last_progress_time > 2) {
                $progress = ($processed / $total) * 100;
                echo "\r进度: " . number_format($progress, 1) . "% (" . number_format($processed) . "/" . number_format($total) . ")";
                $last_progress_time = microtime(true);
            }
            
            $current_path = $path ? "$path.$key" : $key;
            
            // 检查是否匹配搜索词
            foreach ($search_terms as $term) {
                if (stripos($key, $term) !== false) {
                    $results[] = [
                        'path' => $current_path,
                        'key' => $key,
                        'term' => $term,
                        'value_type' => gettype($value),
                        'value_preview' => is_array($value) ? '[' . count($value) . ' items]' : (is_string($value) ? substr($value, 0, 100) : $value)
                    ];
                }
            }
            
            // 递归搜索
            if (is_array($value) && count($value) > 0) {
                deep_search_with_progress($value, $search_terms, $current_path, $results, $processed, $total);
            }
        }
    }
    
    return $results;
}

function count_recursive_elements($data) {
    $count = 0;
    if (is_array($data)) {
        $count += count($data);
        foreach ($data as $value) {
            if (is_array($value)) {
                $count += count_recursive_elements($value);
            }
        }
    }
    return $count;
}

$search_terms = ['netcontent', 'netContent', 'productnetcontent', 'productNetContent'];
$search_results = [];

$search_start = microtime(true);
deep_search_with_progress($schema, $search_terms, '', $search_results);
$search_time = microtime(true) - $search_start;

echo "\n✅ 搜索完成，耗时: " . number_format($search_time, 2) . " 秒\n";
echo "找到 " . count($search_results) . " 个相关结果\n";

$analysis_results['netcontent_search'] = [
    'search_time' => $search_time,
    'results_count' => count($search_results),
    'results' => $search_results
];

// 步骤6: 保存分析结果
echo "\n🔄 步骤6: 保存分析结果...\n";

$output_file = 'walmart_schema_analysis_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($analysis_results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "✅ 分析结果已保存到: $output_file\n";

// 步骤7: 显示关键发现
echo "\n📋 步骤7: 关键发现摘要\n";
echo "=" . str_repeat("=", 50) . "\n";

if (!empty($search_results)) {
    echo "🔍 netContent相关发现:\n";
    
    $grouped_results = [];
    foreach ($search_results as $result) {
        $grouped_results[$result['term']][] = $result;
    }
    
    foreach ($grouped_results as $term => $results) {
        echo "\n📌 搜索词: $term (找到 " . count($results) . " 个)\n";
        
        foreach (array_slice($results, 0, 5) as $result) {
            echo "  路径: {$result['path']}\n";
            echo "  类型: {$result['value_type']}\n";
            echo "  预览: {$result['value_preview']}\n";
            echo "  ---\n";
        }
        
        if (count($results) > 5) {
            echo "  ... 还有 " . (count($results) - 5) . " 个结果\n";
        }
    }
}

$total_time = microtime(true) - $start_time;
echo "\n⏱️  总耗时: " . number_format($total_time, 2) . " 秒\n";
echo "🎉 分析完成！\n";
echo "\n请查看生成的JSON文件获取完整分析结果。\n";
?>
