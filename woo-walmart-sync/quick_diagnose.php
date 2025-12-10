<?php
/**
 * 快速诊断Walmart JSON文件结构
 */

set_time_limit(60);
ini_set('memory_limit', '1G');

echo "=== 快速诊断Walmart JSON文件结构 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在: $json_file\n";
    exit;
}

echo "📁 文件大小: " . number_format(filesize($json_file) / 1024 / 1024, 2) . " MB\n";

// 读取文件的前1MB进行快速分析
echo "\n🔍 读取文件前1MB进行结构分析...\n";

$handle = fopen($json_file, 'r');
$sample = fread($handle, 1024 * 1024); // 读取1MB
fclose($handle);

echo "✅ 读取了 " . number_format(strlen($sample)) . " 字符\n";

// 查找JSON的开始
$json_start = strpos($sample, '{');
if ($json_start === false) {
    echo "❌ 未找到JSON开始标记\n";
    exit;
}

echo "📍 JSON开始位置: $json_start\n";

// 尝试找到第一个完整的顶级结构
$brace_count = 0;
$in_string = false;
$first_object = '';
$found_complete = false;

for ($i = $json_start; $i < strlen($sample); $i++) {
    $char = $sample[$i];
    $first_object .= $char;
    
    if ($char === '"' && ($i === 0 || $sample[$i-1] !== '\\')) {
        $in_string = !$in_string;
    }
    
    if (!$in_string) {
        if ($char === '{') {
            $brace_count++;
        } elseif ($char === '}') {
            $brace_count--;
            
            if ($brace_count === 0) {
                $found_complete = true;
                break;
            }
        }
    }
    
    // 限制大小，避免内存问题
    if (strlen($first_object) > 500000) { // 500KB limit
        break;
    }
}

if ($found_complete) {
    echo "✅ 找到完整的JSON对象，大小: " . number_format(strlen($first_object)) . " 字符\n";
    
    // 尝试解析
    $data = json_decode($first_object, true);
    
    if ($data) {
        echo "✅ JSON解析成功\n";
        echo "\n📊 顶级结构分析:\n";
        
        foreach ($data as $key => $value) {
            $type = gettype($value);
            $size = is_array($value) ? count($value) : (is_string($value) ? strlen($value) : 'N/A');
            
            echo "  🔑 $key: $type";
            if (is_array($value)) {
                echo " (包含 $size 个元素)";
            } elseif (is_string($value)) {
                echo " (长度 $size)";
            }
            echo "\n";
            
            // 特别检查definitions
            if ($key === 'definitions' && is_array($value)) {
                echo "    📋 definitions包含的定义:\n";
                $def_count = 0;
                foreach ($value as $def_key => $def_value) {
                    if ($def_count < 10) {
                        echo "      - $def_key\n";
                    }
                    $def_count++;
                }
                if ($def_count > 10) {
                    echo "      ... 还有 " . ($def_count - 10) . " 个定义\n";
                }
                
                // 查找netContent相关定义
                echo "    🔍 查找netContent相关定义:\n";
                foreach ($value as $def_key => $def_value) {
                    if (stripos($def_key, 'netcontent') !== false) {
                        echo "      ✅ 找到: $def_key\n";
                        if (is_array($def_value)) {
                            if (isset($def_value['type'])) {
                                echo "        类型: {$def_value['type']}\n";
                            }
                            if (isset($def_value['properties'])) {
                                echo "        属性: " . implode(', ', array_keys($def_value['properties'])) . "\n";
                            }
                        }
                    }
                }
                
                // 查找分类相关定义
                echo "    🏷️ 查找分类相关定义:\n";
                $category_count = 0;
                foreach ($value as $def_key => $def_value) {
                    if (is_array($def_value) && isset($def_value['enum'])) {
                        $enum_values = $def_value['enum'];
                        if (count($enum_values) > 10) {
                            // 检查是否看起来像分类
                            $category_like = 0;
                            foreach (array_slice($enum_values, 0, 5) as $enum_val) {
                                if (is_string($enum_val) && (
                                    strpos($enum_val, '&') !== false ||
                                    strpos($enum_val, ',') !== false ||
                                    preg_match('/^[A-Z][a-z]+ [A-Z]/', $enum_val)
                                )) {
                                    $category_like++;
                                }
                            }
                            
                            if ($category_like >= 2) {
                                echo "      ✅ 可能的分类字段: $def_key (包含 " . count($enum_values) . " 个值)\n";
                                echo "        示例: " . implode(', ', array_slice($enum_values, 0, 3)) . "\n";
                                $category_count++;
                                
                                if ($category_count >= 5) {
                                    echo "      ... 还有更多分类字段\n";
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        
    } else {
        echo "❌ JSON解析失败: " . json_last_error_msg() . "\n";
        echo "📝 JSON开头预览:\n";
        echo substr($first_object, 0, 500) . "...\n";
    }
    
} else {
    echo "❌ 未找到完整的JSON对象\n";
    echo "📝 文件开头预览:\n";
    echo substr($sample, $json_start, 500) . "...\n";
}

echo "\n=== 诊断完成 ===\n";
echo "💡 建议: 如果发现了definitions，说明这是标准的JSON Schema文件\n";
echo "💡 建议: 重点关注definitions部分的字段定义\n";
?>
