<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试分类名称转换问题 ===\n\n";

// 1. 检查缓存的沃尔玛分类列表
echo "1. 检查缓存的沃尔玛分类列表:\n";
$walmart_categories_list = get_transient('walmart_api_categories');

if ($walmart_categories_list) {
    echo "缓存中有 " . count($walmart_categories_list) . " 个分类\n";
    
    // 查找包含 "Luggage" 的分类
    $luggage_categories = [];
    foreach ($walmart_categories_list as $cat) {
        if (stripos($cat['categoryName'], 'luggage') !== false || 
            stripos($cat['categoryId'], 'luggage') !== false) {
            $luggage_categories[] = $cat;
        }
    }
    
    echo "找到 " . count($luggage_categories) . " 个包含 'Luggage' 的分类:\n";
    foreach ($luggage_categories as $cat) {
        echo "  - ID: {$cat['categoryId']} | Name: {$cat['categoryName']}\n";
    }
    
    // 精确查找 "Luggage & Luggage Sets"
    $exact_match = null;
    foreach ($walmart_categories_list as $cat) {
        if ($cat['categoryId'] === 'Luggage & Luggage Sets' || 
            $cat['categoryName'] === 'Luggage & Luggage Sets') {
            $exact_match = $cat;
            break;
        }
    }
    
    if ($exact_match) {
        echo "\n✅ 找到精确匹配:\n";
        echo "  ID: {$exact_match['categoryId']}\n";
        echo "  Name: {$exact_match['categoryName']}\n";
    } else {
        echo "\n❌ 未找到 'Luggage & Luggage Sets' 的精确匹配\n";
    }
    
} else {
    echo "❌ 缓存中没有沃尔玛分类列表\n";
}

// 2. 模拟分类名称查找过程
echo "\n\n2. 模拟分类名称查找过程:\n";
$walmart_category_id = 'Luggage & Luggage Sets';
$walmart_category_name = '';

if (!empty($walmart_categories_list)) {
    foreach($walmart_categories_list as $cat) {
        if ($cat['categoryId'] === $walmart_category_id) {
            $walmart_category_name = $cat['categoryName'];
            break;
        }
    }
}

if (empty($walmart_category_name)) {
    $walmart_category_name = $walmart_category_id;
    echo "⚠️  未找到分类名称，使用ID作为后备: {$walmart_category_name}\n";
} else {
    echo "✅ 找到分类名称: {$walmart_category_name}\n";
}

// 3. 检查是否有分类名称转换逻辑
echo "\n\n3. 检查分类名称转换:\n";
echo "原始分类名: {$walmart_category_name}\n";

// 检查是否有特殊的转换逻辑
$converted_name = $walmart_category_name;

// 可能的转换逻辑
if (strpos($converted_name, '&') !== false || strpos($converted_name, ' ') !== false) {
    // 可能被转换为下划线格式
    $underscore_name = str_replace([' & ', ' '], ['_', '_'], strtolower($converted_name));
    echo "下划线格式: {$underscore_name}\n";
    
    // 可能被转换为简化格式
    $simple_name = preg_replace('/[^a-z0-9]/', '_', strtolower($converted_name));
    echo "简化格式: {$simple_name}\n";
}

// 4. 检查是否有硬编码的分类映射
echo "\n\n4. 检查可能的硬编码映射:\n";
$hardcoded_mappings = [
    'Luggage & Luggage Sets' => 'home_other',
    'luggage_luggage_sets' => 'home_other',
    'luggage' => 'home_other'
];

foreach ($hardcoded_mappings as $original => $mapped) {
    if (stripos($walmart_category_name, $original) !== false || 
        $walmart_category_name === $original) {
        echo "⚠️  可能的硬编码映射: {$original} → {$mapped}\n";
    }
}

echo "\n=== 调试完成 ===\n";
?>
