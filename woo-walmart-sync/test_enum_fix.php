<?php
/**
 * 测试枚举值修复
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试枚举值修复 ===\n\n";

// 加载修复后的函数
require_once 'woo-walmart-sync.php';

// 1. 测试从数据库获取属性
echo "1. 测试从数据库获取属性:\n";

$test_categories = [
    ['id' => 'furniture_other', 'name' => 'Furniture'],
    ['id' => 'home_other', 'name' => 'Home'],
    ['id' => 'bed_frames', 'name' => 'Bed Frames']
];

foreach ($test_categories as $category) {
    echo "\n测试分类: {$category['name']} (ID: {$category['id']})\n";
    
    $attributes = get_attributes_from_database($category['id'], $category['name']);
    
    echo "找到 " . count($attributes) . " 个属性\n";
    
    $features_found = false;
    foreach ($attributes as $attr) {
        if ($attr['attributeName'] === 'features') {
            $features_found = true;
            echo "✅ 找到features字段:\n";
            echo "  - defaultType: {$attr['defaultType']}\n";
            echo "  - enumValues: " . (isset($attr['enumValues']) ? count($attr['enumValues']) . " 个值" : "无") . "\n";
            
            if (isset($attr['enumValues'])) {
                echo "  - 枚举值:\n";
                foreach (array_slice($attr['enumValues'], 0, 5) as $i => $value) {
                    echo "    [{$i}] {$value}\n";
                }
                if (count($attr['enumValues']) > 5) {
                    echo "    ... 还有 " . (count($attr['enumValues']) - 5) . " 个\n";
                }
            }
            break;
        }
    }
    
    if (!$features_found) {
        echo "❌ 没有找到features字段\n";
    }
}

// 2. 测试模拟AJAX调用
echo "\n\n2. 测试模拟AJAX调用:\n";

function simulate_ajax_call($category_name) {
    echo "\n模拟AJAX调用: get_walmart_category_attributes\n";
    echo "分类名称: {$category_name}\n";
    
    // 模拟推断分类ID
    $category_id = strtolower(str_replace([' ', '&', ','], ['_', 'and', ''], $category_name));
    echo "推断的分类ID: {$category_id}\n";
    
    // 清除缓存
    $transient_key = 'walmart_attributes_' . $category_id;
    delete_transient($transient_key);
    echo "已清除缓存: {$transient_key}\n";
    
    // 调用修复后的函数
    $attributes = get_attributes_from_database($category_id, $category_name);
    
    echo "从数据库获取到 " . count($attributes) . " 个属性\n";
    
    // 查找features字段
    foreach ($attributes as $attr) {
        if ($attr['attributeName'] === 'features') {
            echo "✅ features字段数据:\n";
            echo "  - attributeName: {$attr['attributeName']}\n";
            echo "  - defaultType: {$attr['defaultType']}\n";
            echo "  - isrequired: " . ($attr['isrequired'] ? 'true' : 'false') . "\n";
            echo "  - description: {$attr['description']}\n";
            echo "  - group: {$attr['group']}\n";
            
            if (isset($attr['enumValues'])) {
                echo "  - enumValues: " . count($attr['enumValues']) . " 个值\n";
                echo "  - 前5个枚举值:\n";
                foreach (array_slice($attr['enumValues'], 0, 5) as $i => $value) {
                    echo "    [{$i}] {$value}\n";
                }
            } else {
                echo "  - enumValues: 无\n";
            }
            
            return $attr;
        }
    }
    
    echo "❌ 没有找到features字段\n";
    return null;
}

$test_result = simulate_ajax_call('Furniture');

// 3. 验证前端应该接收到的数据格式
echo "\n\n3. 验证前端应该接收到的数据格式:\n";

if ($test_result) {
    echo "前端JavaScript应该接收到:\n";
    echo "{\n";
    echo "  attributeName: '{$test_result['attributeName']}',\n";
    echo "  defaultType: '{$test_result['defaultType']}',\n";
    echo "  isrequired: " . ($test_result['isrequired'] ? 'true' : 'false') . ",\n";
    echo "  description: '{$test_result['description']}',\n";
    echo "  group: '{$test_result['group']}',\n";
    
    if (isset($test_result['enumValues'])) {
        echo "  enumValues: [";
        $enum_sample = array_slice($test_result['enumValues'], 0, 3);
        echo "'" . implode("', '", $enum_sample) . "'";
        if (count($test_result['enumValues']) > 3) {
            echo ", ... +" . (count($test_result['enumValues']) - 3) . " more";
        }
        echo "]\n";
    } else {
        echo "  enumValues: null\n";
    }
    echo "}\n";
    
    echo "\n前端处理流程:\n";
    echo "1. 加载V5.0规范时，会调用: \$newRow.find('.attr-type-selector').data('enum-values', attr.enumValues)\n";
    echo "2. 用户选择'沃尔玛字段'时，会调用: loadWalmartFieldOptions(selectElement, attributeName, currentValue, enumValues)\n";
    echo "3. loadWalmartFieldOptions会优先使用传入的enumValues参数\n";
    
    if (isset($test_result['enumValues']) && !empty($test_result['enumValues'])) {
        echo "4. ✅ 应该显示 " . count($test_result['enumValues']) . " 个枚举选项\n";
    } else {
        echo "4. ❌ 会显示'无预定义选项'\n";
    }
} else {
    echo "❌ 无法验证，因为没有找到features字段\n";
}

echo "\n=== 测试完成 ===\n";
?>
