<?php
/**
 * 专门提取Furniture类目结构
 */

set_time_limit(300);
ini_set('memory_limit', '4G');

echo "=== 提取Furniture类目结构 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在: $json_file\n";
    exit;
}

echo "📁 开始分析JSON Schema文件...\n";

// 分块读取文件，寻找Furniture相关内容
$handle = fopen($json_file, 'r');
$chunk_size = 1024 * 1024 * 2; // 2MB chunks
$buffer = '';
$furniture_found = [];
$netcontent_found = [];
$categories_found = [];

$chunk_count = 0;
$total_size = filesize($json_file);
$processed_size = 0;

while (!feof($handle)) {
    $chunk = fread($handle, $chunk_size);
    $buffer .= $chunk;
    $processed_size += strlen($chunk);
    $chunk_count++;
    
    $progress = ($processed_size / $total_size) * 100;
    echo "\r进度: " . number_format($progress, 1) . "% (块 #$chunk_count)";
    
    // 搜索Furniture相关内容
    if (stripos($buffer, 'furniture') !== false) {
        // 提取包含furniture的行
        $lines = explode("\n", $buffer);
        foreach ($lines as $line_num => $line) {
            if (stripos($line, 'furniture') !== false) {
                $furniture_found[] = [
                    'chunk' => $chunk_count,
                    'line' => $line_num,
                    'content' => trim($line)
                ];
            }
        }
    }
    
    // 搜索netContent相关内容
    if (stripos($buffer, 'netcontent') !== false) {
        $lines = explode("\n", $buffer);
        foreach ($lines as $line_num => $line) {
            if (stripos($line, 'netcontent') !== false) {
                $netcontent_found[] = [
                    'chunk' => $chunk_count,
                    'line' => $line_num,
                    'content' => trim($line)
                ];
            }
        }
    }
    
    // 搜索分类枚举
    if (preg_match_all('/"([^"]*&[^"]*)"/', $buffer, $matches)) {
        foreach ($matches[1] as $match) {
            if (strlen($match) > 5 && !in_array($match, $categories_found)) {
                $categories_found[] = $match;
            }
        }
    }
    
    // 保留最后1MB的buffer，避免跨块的匹配丢失
    if (strlen($buffer) > $chunk_size * 2) {
        $buffer = substr($buffer, -$chunk_size);
    }
    
    // 限制结果数量，避免内存溢出
    if (count($furniture_found) > 100) break;
}

fclose($handle);

echo "\n\n=== 分析结果 ===\n";

// 显示Furniture相关发现
echo "\n🪑 Furniture相关发现 (" . count($furniture_found) . " 个):\n";
foreach (array_slice($furniture_found, 0, 20) as $item) {
    echo "  块#{$item['chunk']}: {$item['content']}\n";
}

// 显示netContent相关发现
echo "\n📦 netContent相关发现 (" . count($netcontent_found) . " 个):\n";
foreach (array_slice($netcontent_found, 0, 10) as $item) {
    echo "  块#{$item['chunk']}: {$item['content']}\n";
}

// 显示分类发现
echo "\n🏷️ 分类发现 (" . count($categories_found) . " 个):\n";
foreach (array_slice($categories_found, 0, 20) as $category) {
    echo "  - $category\n";
}

// 保存详细结果
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'furniture_findings' => $furniture_found,
    'netcontent_findings' => $netcontent_found,
    'categories_findings' => $categories_found
];

$output_file = 'furniture_analysis_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 详细结果已保存到: $output_file\n";

// 尝试提取一个完整的Furniture定义
echo "\n🔍 尝试提取完整的Furniture定义...\n";

// 重新打开文件，寻找完整的Furniture定义
$handle = fopen($json_file, 'r');
$buffer = '';
$in_furniture_section = false;
$brace_level = 0;
$furniture_definition = '';

while (!feof($handle)) {
    $chunk = fread($handle, 1024 * 512); // 512KB chunks for detailed parsing
    $buffer .= $chunk;
    
    // 逐字符解析
    for ($i = 0; $i < strlen($buffer); $i++) {
        $char = $buffer[$i];
        
        // 检查是否进入Furniture相关部分
        if (!$in_furniture_section) {
            $context = substr($buffer, max(0, $i-50), 100);
            if (stripos($context, 'furniture') !== false) {
                $in_furniture_section = true;
                $furniture_definition = '';
                $brace_level = 0;
                echo "📍 找到Furniture相关内容，开始提取...\n";
            }
        }
        
        if ($in_furniture_section) {
            $furniture_definition .= $char;
            
            if ($char === '{') {
                $brace_level++;
            } elseif ($char === '}') {
                $brace_level--;
                
                // 如果回到顶级，可能是完整定义
                if ($brace_level <= 0 && strlen($furniture_definition) > 1000) {
                    echo "✅ 提取到完整定义，长度: " . number_format(strlen($furniture_definition)) . " 字符\n";
                    
                    // 保存Furniture定义
                    $furniture_file = 'furniture_definition_' . date('Ymd_His') . '.json';
                    file_put_contents($furniture_file, $furniture_definition);
                    echo "💾 Furniture定义已保存到: $furniture_file\n";
                    
                    // 尝试解析
                    $furniture_data = json_decode($furniture_definition, true);
                    if ($furniture_data) {
                        echo "✅ JSON解析成功\n";
                        echo "🔑 顶级键: " . implode(', ', array_keys($furniture_data)) . "\n";
                        
                        // 查找properties
                        if (isset($furniture_data['properties'])) {
                            echo "📋 包含属性: " . count($furniture_data['properties']) . " 个\n";
                            $prop_names = array_keys($furniture_data['properties']);
                            echo "🏷️ 属性列表: " . implode(', ', array_slice($prop_names, 0, 10)) . "\n";
                            
                            // 特别查找netContent
                            foreach ($furniture_data['properties'] as $prop_name => $prop_def) {
                                if (stripos($prop_name, 'netcontent') !== false) {
                                    echo "🎯 找到netContent属性: $prop_name\n";
                                    if (isset($prop_def['properties'])) {
                                        echo "  子属性: " . implode(', ', array_keys($prop_def['properties'])) . "\n";
                                    }
                                }
                            }
                        }
                    } else {
                        echo "❌ JSON解析失败: " . json_last_error_msg() . "\n";
                    }
                    
                    break 2; // 退出两层循环
                }
            }
            
            // 防止定义过大
            if (strlen($furniture_definition) > 1024 * 1024 * 10) { // 10MB limit
                echo "⚠️ 定义过大，截断处理\n";
                break 2;
            }
        }
    }
    
    // 保留部分buffer
    if (strlen($buffer) > 1024 * 1024) {
        $buffer = substr($buffer, -1024 * 512);
        $i = 0; // 重置索引
    }
}

fclose($handle);

echo "\n=== 提取完成 ===\n";
echo "💡 建议: 查看生成的JSON文件获取完整的Furniture类目结构\n";
?>
