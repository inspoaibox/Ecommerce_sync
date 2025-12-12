<?php
/**
 * 完整分析 MP_ITEM-5.0.20241118-04_39_24-api2.json 文件
 */

echo "=== 完整分析 Walmart API 5.0 Schema ===\n";

// 读取JSON文件
$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在: $json_file\n";
    echo "请确保文件在当前目录下\n";
    exit;
}

$json_content = file_get_contents($json_file);
$schema = json_decode($json_content, true);

if (!$schema) {
    echo "❌ JSON解析失败\n";
    exit;
}

echo "✅ 成功读取JSON文件\n";
echo "文件大小: " . number_format(strlen($json_content)) . " 字节\n\n";

// 1. 分析顶级结构
echo "=== 1. 顶级结构分析 ===\n";
echo "顶级键: " . implode(', ', array_keys($schema)) . "\n\n";

// 2. 分析definitions部分
if (isset($schema['definitions'])) {
    echo "=== 2. Definitions 分析 ===\n";
    $definitions = $schema['definitions'];
    echo "定义数量: " . count($definitions) . "\n";
    echo "定义列表:\n";
    
    foreach ($definitions as $def_name => $def_content) {
        echo "  - $def_name\n";
    }
    echo "\n";
}

// 3. 重点分析netContent相关定义
echo "=== 3. netContent 相关分析 ===\n";

function find_netcontent_definitions($data, $path = '') {
    $results = [];
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $current_path = $path ? "$path.$key" : $key;
            
            // 检查键名是否包含netContent
            if (stripos($key, 'netcontent') !== false) {
                $results[] = [
                    'path' => $current_path,
                    'key' => $key,
                    'value' => $value
                ];
            }
            
            // 递归搜索
            if (is_array($value)) {
                $sub_results = find_netcontent_definitions($value, $current_path);
                $results = array_merge($results, $sub_results);
            }
        }
    }
    
    return $results;
}

$netcontent_findings = find_netcontent_definitions($schema);

echo "找到 " . count($netcontent_findings) . " 个netContent相关定义:\n\n";

foreach ($netcontent_findings as $finding) {
    echo "路径: {$finding['path']}\n";
    echo "键名: {$finding['key']}\n";
    
    if (is_array($finding['value'])) {
        echo "类型: 对象/数组\n";
        if (isset($finding['value']['type'])) {
            echo "  type: {$finding['value']['type']}\n";
        }
        if (isset($finding['value']['properties'])) {
            echo "  properties: " . implode(', ', array_keys($finding['value']['properties'])) . "\n";
        }
        if (isset($finding['value']['required'])) {
            echo "  required: " . (is_array($finding['value']['required']) ? implode(', ', $finding['value']['required']) : $finding['value']['required']) . "\n";
        }
        if (isset($finding['value']['enum'])) {
            echo "  enum: " . implode(', ', array_slice($finding['value']['enum'], 0, 5)) . (count($finding['value']['enum']) > 5 ? '...' : '') . "\n";
        }
    } else {
        echo "值: " . json_encode($finding['value']) . "\n";
    }
    echo "---\n";
}

// 4. 分析productNetContentMeasure和productNetContentUnit
echo "\n=== 4. productNetContent子字段分析 ===\n";

function find_product_netcontent_fields($data, $path = '') {
    $results = [];
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $current_path = $path ? "$path.$key" : $key;
            
            if (stripos($key, 'productnetcontent') !== false) {
                $results[] = [
                    'path' => $current_path,
                    'key' => $key,
                    'value' => $value
                ];
            }
            
            if (is_array($value)) {
                $sub_results = find_product_netcontent_fields($value, $current_path);
                $results = array_merge($results, $sub_results);
            }
        }
    }
    
    return $results;
}

$product_netcontent_findings = find_product_netcontent_fields($schema);

echo "找到 " . count($product_netcontent_findings) . " 个productNetContent相关字段:\n\n";

foreach ($product_netcontent_findings as $finding) {
    echo "路径: {$finding['path']}\n";
    echo "键名: {$finding['key']}\n";
    
    if (is_array($finding['value'])) {
        if (isset($finding['value']['type'])) {
            echo "  type: {$finding['value']['type']}\n";
        }
        if (isset($finding['value']['enum'])) {
            echo "  enum: " . implode(', ', $finding['value']['enum']) . "\n";
        }
        if (isset($finding['value']['minimum'])) {
            echo "  minimum: {$finding['value']['minimum']}\n";
        }
        if (isset($finding['value']['maximum'])) {
            echo "  maximum: {$finding['value']['maximum']}\n";
        }
    }
    echo "---\n";
}

echo "\n=== 5. 保存详细分析结果 ===\n";

// 保存netContent相关的完整定义
$netcontent_analysis = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_netcontent_findings' => count($netcontent_findings),
    'total_product_netcontent_findings' => count($product_netcontent_findings),
    'netcontent_findings' => $netcontent_findings,
    'product_netcontent_findings' => $product_netcontent_findings
];

file_put_contents('netcontent_analysis_' . date('Ymd_His') . '.json', json_encode($netcontent_analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "✅ 详细分析结果已保存到 netcontent_analysis_" . date('Ymd_His') . ".json\n";

echo "\n=== 分析完成 ===\n";
echo "请查看生成的分析文件获取完整详情\n";
?>
