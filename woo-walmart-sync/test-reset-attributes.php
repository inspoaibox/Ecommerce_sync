<?php
/**
 * 测试重置属性功能，检查10个新字段是否能正确加载
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 测试重置属性功能 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\canda.localhost';
if (!file_exists($wp_path . '\\wp-config.php')) {
    echo "❌ wp-config.php 不存在\n";
    exit;
}

require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';
echo "✅ WordPress加载成功\n";

global $wpdb;

// 1. 检查数据库中是否有我们的10个新字段
$table_name = $wpdb->prefix . 'walmart_product_attributes';
$new_fields = [
    'door_material',
    'doorOpeningStyle', 
    'doorStyle',
    'has_doors',
    'has_fireplace_feature',
    'maximumScreenSize',
    'mountType',
    'number_of_heat_settings',
    'numberOfCompartments',
    'orientation'
];

echo "=== 检查数据库中的字段 ===\n";
$found_in_db = [];
$missing_in_db = [];

foreach ($new_fields as $field_name) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE attribute_name = %s",
        $field_name
    ));
    
    if ($count > 0) {
        $found_in_db[] = $field_name;
        echo "✅ 数据库中找到: {$field_name} ({$count} 条记录)\n";
    } else {
        $missing_in_db[] = $field_name;
        echo "❌ 数据库中缺失: {$field_name}\n";
    }
}

echo "\n数据库检查结果:\n";
echo "找到: " . count($found_in_db) . "/10\n";
echo "缺失: " . count($missing_in_db) . "/10\n";

// 2. 测试parse_json_schema_attributes函数
echo "\n=== 测试parse_json_schema_attributes函数 ===\n";

// 包含插件文件
require_once 'woo-walmart-sync.php';

// 检查函数是否存在
if (function_exists('parse_json_schema_attributes')) {
    echo "✅ parse_json_schema_attributes函数存在\n";

    // 模拟一个简单的schema数据
    $mock_schema = [
        'properties' => [
            'MPItem' => [
                'items' => [
                    'properties' => [
                        'Visible' => [
                            'properties' => [
                                'Dining Chairs' => [
                                    'properties' => [
                                        'productName' => [
                                            'type' => 'string',
                                            'title' => 'Product Name'
                                        ]
                                    ],
                                    'required' => ['productName']
                                ]
                            ]
                        ]
                    ],
                    'required' => ['Visible']
                ]
            ]
        ]
    ];

    // 调用函数测试
    $result = parse_json_schema_attributes($mock_schema, 'Dining Chairs');

    if (is_array($result)) {
        echo "✅ 函数调用成功，返回 " . count($result) . " 个字段\n";

        // 检查我们的10个新字段是否在结果中
        $found_in_result = [];
        $missing_in_result = [];

        foreach ($new_fields as $field_name) {
            $found = false;
            foreach ($result as $attr) {
                if (isset($attr['attributeName']) && $attr['attributeName'] === $field_name) {
                    $found_in_result[] = $field_name;
                    $found = true;
                    echo "✅ 解析结果中找到: {$field_name}\n";
                    break;
                }
            }
            if (!$found) {
                $missing_in_result[] = $field_name;
                echo "❌ 解析结果中缺失: {$field_name}\n";
            }
        }

        echo "\n解析结果检查:\n";
        echo "找到: " . count($found_in_result) . "/10\n";
        echo "缺失: " . count($missing_in_result) . "/10\n";

    } else {
        echo "❌ 函数调用失败\n";
    }
} else {
    echo "❌ parse_json_schema_attributes函数不存在\n";
}

// 3. 检查前端配置
echo "\n=== 检查前端配置 ===\n";
$plugin_content = file_get_contents('woo-walmart-sync.php');

// 检查autoGenerateFields数组
$auto_generate_count = substr_count($plugin_content, 'door_material');
echo "door_material在文件中出现次数: {$auto_generate_count}\n";

// 检查getAutoGenerationRule函数
if (strpos($plugin_content, "'door_material': '从产品标题和描述中提取门材质信息") !== false) {
    echo "✅ door_material字段说明已添加\n";
} else {
    echo "❌ door_material字段说明缺失\n";
}

echo "\n=== 结论 ===\n";
if (count($missing_in_db) > 0) {
    echo "❌ 问题：数据库中缺失 " . count($missing_in_db) . " 个字段\n";
    echo "解决方案：需要通过'加载V5.0规范'功能从Walmart API获取这些字段\n";
    echo "或者这些字段在Walmart API中不存在，需要手动添加到数据库\n";
} else {
    echo "✅ 所有字段都在数据库中\n";
    echo "问题可能在前端配置或AJAX处理逻辑中\n";
}

echo "\n测试完成！\n";
?>
