<?php
echo "=== Character和Character_group字段删除验证测试 ===\n";

// 加载WordPress
require_once 'D:\\phpstudy_pro\\WWW\\test.localhost\\wp-config.php';
require_once 'D:\\phpstudy_pro\\WWW\\test.localhost\\wp-load.php';

echo "1. 验证newleimu.json文件中的字段删除:\n";

// 检查newleimu.json文件
$json_content = file_get_contents('newleimu.json');
$json_data = json_decode($json_content, true);

if ($json_data === null) {
    echo "❌ JSON文件解析失败\n";
    exit;
}

// 检查是否还存在character和character_group字段
$character_found = false;
$character_group_found = false;

foreach ($json_data as $item) {
    if (isset($item['attributeName'])) {
        if ($item['attributeName'] === 'character') {
            $character_found = true;
        }
        if ($item['attributeName'] === 'character_group') {
            $character_group_found = true;
        }
    }
}

if (!$character_found) {
    echo "✅ character字段已从newleimu.json中删除\n";
} else {
    echo "❌ character字段仍然存在于newleimu.json中\n";
}

if (!$character_group_found) {
    echo "✅ character_group字段已从newleimu.json中删除\n";
} else {
    echo "❌ character_group字段仍然存在于newleimu.json中\n";
}

echo "\n2. 验证variantAttributeNames中的character删除:\n";

// 查找variantAttributeNames字段
$variant_attr_found = false;
$character_in_variant = false;

foreach ($json_data as $item) {
    if (isset($item['attributeName']) && $item['attributeName'] === 'variantAttributeNames') {
        $variant_attr_found = true;
        
        // 检查allowed_values中是否包含character
        if (isset($item['allowed_values']) && is_array($item['allowed_values'])) {
            if (in_array('character', $item['allowed_values'])) {
                $character_in_variant = true;
            }
        }
        
        // 检查enumValues中是否包含character
        if (isset($item['enumValues']) && is_array($item['enumValues'])) {
            if (in_array('character', $item['enumValues'])) {
                $character_in_variant = true;
            }
        }
        break;
    }
}

if ($variant_attr_found) {
    if (!$character_in_variant) {
        echo "✅ character已从variantAttributeNames的枚举值中删除\n";
    } else {
        echo "❌ character仍然存在于variantAttributeNames的枚举值中\n";
    }
} else {
    echo "⚠️ 未找到variantAttributeNames字段\n";
}

echo "\n3. 测试加载默认属性时是否包含这两个字段:\n";

// 模拟加载默认属性
try {
    // 直接从JSON数据中统计字段
    $total_attributes = count($json_data);
    echo "JSON文件中总属性数量: {$total_attributes}\n";
    
    // 列出前20个属性名称
    echo "\n前20个属性名称:\n";
    $count = 0;
    foreach ($json_data as $item) {
        if (isset($item['attributeName']) && $count < 20) {
            echo "  " . ($count + 1) . ". {$item['attributeName']}\n";
            $count++;
        }
    }
    
    // 检查是否还有其他character相关字段
    echo "\n4. 检查是否还有其他character相关字段:\n";
    $character_related_fields = [];
    
    foreach ($json_data as $item) {
        if (isset($item['attributeName'])) {
            $attr_name = strtolower($item['attributeName']);
            if (strpos($attr_name, 'character') !== false) {
                $character_related_fields[] = $item['attributeName'];
            }
        }
    }
    
    if (empty($character_related_fields)) {
        echo "✅ 没有找到其他character相关字段\n";
    } else {
        echo "⚠️ 发现其他character相关字段:\n";
        foreach ($character_related_fields as $field) {
            echo "  - {$field}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ 测试过程中出错: " . $e->getMessage() . "\n";
}

echo "\n5. 验证数据库中的字段状态:\n";

global $wpdb;
$attr_table = $wpdb->prefix . 'walmart_product_attributes';

// 检查数据库中是否还有这两个字段
$character_records = $wpdb->get_results("
    SELECT * FROM $attr_table 
    WHERE attribute_name IN ('character', 'character_group')
    LIMIT 10
");

if (empty($character_records)) {
    echo "✅ 数据库中没有找到character和character_group字段记录\n";
} else {
    echo "⚠️ 数据库中仍然存在这些字段的记录:\n";
    foreach ($character_records as $record) {
        echo "  - 产品类型: {$record->product_type_name}, 字段: {$record->attribute_name}\n";
    }
    echo "注意: 这些是历史记录，新的属性加载不会再包含这些字段\n";
}

echo "\n6. 测试总结:\n";

$all_checks_passed = true;

$checks = [
    'character字段删除' => !$character_found,
    'character_group字段删除' => !$character_group_found,
    'variantAttributeNames中character删除' => !$character_in_variant,
    '无其他character相关字段' => empty($character_related_fields)
];

foreach ($checks as $check_name => $passed) {
    if ($passed) {
        echo "✅ {$check_name}: 通过\n";
    } else {
        echo "❌ {$check_name}: 失败\n";
        $all_checks_passed = false;
    }
}

if ($all_checks_passed) {
    echo "\n🎉 Character和Character_group字段删除完全成功！\n";
    echo "\n📋 效果说明:\n";
    echo "1. 新加载的默认属性将不再包含character和character_group字段\n";
    echo "2. variantAttributeNames的枚举选项中也不再包含character\n";
    echo "3. 这两个字段不会出现在产品映射配置中\n";
    echo "4. 现有的数据库记录不受影响（历史数据保留）\n";
    echo "5. 新的产品类型配置将自动排除这两个字段\n";
} else {
    echo "\n❌ 仍有部分删除操作未完成\n";
}

echo "\n📋 用户操作指南:\n";
echo "1. 访问分类映射管理页面\n";
echo "2. 点击'重置属性'按钮重新加载属性\n";
echo "3. 确认属性列表中不再显示character和character_group字段\n";
echo "4. 保存配置\n";
echo "5. 新的产品同步将不再包含这两个字段\n";

echo "\n=== 测试完成 ===\n";
?>
