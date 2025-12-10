<?php
/**
 * 提取Walmart类目和属性映射关系
 */

set_time_limit(600);
ini_set('memory_limit', '8G');

echo "=== 提取Walmart类目属性映射 ===\n";

$json_file = '4.8_Upgrade_5.0_Documentation/MP_ITEM-5.0.20241118-04_39_24-api2.json';

if (!file_exists($json_file)) {
    echo "❌ 文件不存在: $json_file\n";
    exit;
}

echo "📁 开始分析JSON Schema文件...\n";

// 分块读取，寻找类目相关的结构
$handle = fopen($json_file, 'r');
$chunk_size = 1024 * 1024 * 5; // 5MB chunks
$buffer = '';
$categories = [];
$category_attributes = [];

$chunk_count = 0;
$total_size = filesize($json_file);
$processed_size = 0;

// 已知的Walmart主要类目
$known_categories = [
    'Furniture', 'Electronics', 'Home', 'Garden', 'Kitchen', 'Clothing', 
    'Sports', 'Toys', 'Books', 'Health', 'Beauty', 'Automotive', 
    'Office', 'Pet', 'Baby', 'Jewelry', 'Shoes', 'Bags'
];

while (!feof($handle)) {
    $chunk = fread($handle, $chunk_size);
    $buffer .= $chunk;
    $processed_size += strlen($chunk);
    $chunk_count++;
    
    $progress = ($processed_size / $total_size) * 100;
    echo "\r进度: " . number_format($progress, 1) . "% (块 #$chunk_count)";
    
    // 搜索类目定义模式
    // 寻找类似 "Home & Garden": { "properties": { ... } } 的结构
    if (preg_match_all('/"([^"]*&[^"]*)":\s*{/', $buffer, $matches)) {
        foreach ($matches[1] as $category_name) {
            if (!in_array($category_name, $categories) && strlen($category_name) > 3) {
                $categories[] = $category_name;
                echo "\n✅ 发现类目: $category_name";
            }
        }
    }
    
    // 搜索已知类目的属性定义
    foreach ($known_categories as $category) {
        if (stripos($buffer, $category) !== false) {
            // 尝试提取该类目的属性
            $pattern = '/"' . preg_quote($category, '/') . '[^"]*":\s*{[^}]*"properties":\s*{([^}]+)}/i';
            if (preg_match($pattern, $buffer, $matches)) {
                if (!isset($category_attributes[$category])) {
                    $category_attributes[$category] = [];
                }
                
                // 提取属性名
                if (preg_match_all('/"([^"]+)":\s*{/', $matches[1], $attr_matches)) {
                    foreach ($attr_matches[1] as $attr_name) {
                        if (!in_array($attr_name, $category_attributes[$category])) {
                            $category_attributes[$category][] = $attr_name;
                        }
                    }
                }
            }
        }
    }
    
    // 搜索通用属性定义
    // 寻找常见的产品属性
    $common_attributes = [
        'brand', 'manufacturer', 'model', 'color', 'size', 'weight', 
        'dimensions', 'material', 'productName', 'shortDescription', 
        'longDescription', 'keyFeatures', 'price', 'upc', 'gtin', 
        'isbn', 'ean', 'mpn', 'netContent', 'productIdentifiers'
    ];
    
    foreach ($common_attributes as $attr) {
        if (stripos($buffer, '"' . $attr . '"') !== false) {
            // 尝试提取属性定义
            $pattern = '/"' . preg_quote($attr, '/') . '":\s*{([^}]+)}/i';
            if (preg_match($pattern, $buffer, $matches)) {
                $attr_def = $matches[1];
                
                // 解析属性类型
                $type = 'unknown';
                if (preg_match('/"type":\s*"([^"]+)"/', $attr_def, $type_match)) {
                    $type = $type_match[1];
                }
                
                // 检查是否有枚举值
                $has_enum = strpos($attr_def, '"enum"') !== false;
                
                // 检查是否必填
                $required = strpos($attr_def, '"required"') !== false;
                
                if (!isset($category_attributes['_common'])) {
                    $category_attributes['_common'] = [];
                }
                
                $category_attributes['_common'][$attr] = [
                    'type' => $type,
                    'has_enum' => $has_enum,
                    'required' => $required,
                    'definition' => substr($attr_def, 0, 200) . '...'
                ];
            }
        }
    }
    
    // 保留最后2MB的buffer
    if (strlen($buffer) > $chunk_size * 2) {
        $buffer = substr($buffer, -$chunk_size);
    }
    
    // 定期保存进度
    if ($chunk_count % 10 === 0) {
        echo "\n📊 当前统计: " . count($categories) . " 个类目, " . count($category_attributes) . " 个属性组";
    }
}

fclose($handle);

echo "\n\n=== 分析结果 ===\n";

// 显示发现的类目
echo "\n🏷️ 发现的类目 (" . count($categories) . " 个):\n";
foreach (array_slice($categories, 0, 30) as $category) {
    echo "  - $category\n";
}

// 显示类目属性
echo "\n📋 类目属性映射:\n";
foreach ($category_attributes as $category => $attributes) {
    if ($category === '_common') {
        echo "\n🔧 通用属性 (" . count($attributes) . " 个):\n";
        foreach ($attributes as $attr_name => $attr_info) {
            echo "  - $attr_name ({$attr_info['type']})";
            if ($attr_info['has_enum']) echo " [枚举]";
            if ($attr_info['required']) echo " [必填]";
            echo "\n";
        }
    } else {
        echo "\n📁 $category (" . count($attributes) . " 个属性):\n";
        foreach (array_slice($attributes, 0, 10) as $attr) {
            echo "  - $attr\n";
        }
    }
}

// 保存详细结果
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_categories' => count($categories),
    'categories' => $categories,
    'category_attributes' => $category_attributes,
    'summary' => [
        'categories_found' => count($categories),
        'attribute_groups' => count($category_attributes),
        'common_attributes' => isset($category_attributes['_common']) ? count($category_attributes['_common']) : 0
    ]
];

$output_file = 'walmart_category_attributes_' . date('Ymd_His') . '.json';
file_put_contents($output_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n💾 完整结果已保存到: $output_file\n";

// 生成简化的映射表
$simple_mapping = [];
foreach ($category_attributes as $category => $attributes) {
    if ($category !== '_common') {
        $simple_mapping[$category] = $attributes;
    }
}

$mapping_file = 'category_attribute_mapping_' . date('Ymd_His') . '.json';
file_put_contents($mapping_file, json_encode($simple_mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "📋 简化映射表已保存到: $mapping_file\n";

echo "\n=== 提取完成 ===\n";
echo "🎯 重点: 这些就是Walmart各类目的属性字段\n";
echo "💡 建议: 查看生成的JSON文件获取完整的类目-属性映射关系\n";
?>
