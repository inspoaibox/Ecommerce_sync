<?php
/**
 * 测试完整的API流程：从API调用到数据库保存
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 测试完整API流程 ===\n";
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

// 包含插件文件
require_once 'woo-walmart-sync.php';

global $wpdb;

// 测试的10个新字段
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

echo "=== 步骤1: 测试parse_v5_spec_response函数 ===\n";

// 模拟一个Walmart API响应
$mock_api_response = [
    'schema' => [
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
                                        ],
                                        'brand' => [
                                            'type' => 'string',
                                            'title' => 'Brand'
                                        ]
                                    ],
                                    'required' => ['productName']
                                ]
                            ]
                        ],
                        'Orderable' => [
                            'properties' => [
                                'price' => [
                                    'type' => 'object',
                                    'title' => 'Price'
                                ]
                            ],
                            'required' => ['price']
                        ]
                    ],
                    'required' => ['Visible', 'Orderable']
                ]
            ]
        ]
    ]
];

if (function_exists('parse_v5_spec_response')) {
    echo "✅ parse_v5_spec_response函数存在\n";
    
    $parsed_attributes = parse_v5_spec_response($mock_api_response, 'Dining Chairs');
    
    if (is_array($parsed_attributes)) {
        echo "✅ 解析成功，返回 " . count($parsed_attributes) . " 个字段\n";
        
        // 检查我们的10个新字段
        $found_count = 0;
        foreach ($new_fields as $field_name) {
            foreach ($parsed_attributes as $attr) {
                if (isset($attr['attributeName']) && $attr['attributeName'] === $field_name) {
                    $found_count++;
                    echo "✅ 找到字段: {$field_name}\n";
                    break;
                }
            }
        }
        
        echo "新字段检查结果: {$found_count}/10\n";
        
        if ($found_count === 10) {
            echo "🎉 所有10个新字段都已正确解析！\n";
            
            // 步骤2: 测试保存到数据库
            echo "\n=== 步骤2: 测试保存到数据库 ===\n";
            
            if (function_exists('save_attributes_to_database')) {
                echo "✅ save_attributes_to_database函数存在\n";
                
                $saved_count = save_attributes_to_database($parsed_attributes, 'Dining Chairs', 'Dining Chairs');
                
                echo "保存结果: {$saved_count} 个字段已保存\n";
                
                // 步骤3: 验证数据库中的字段
                echo "\n=== 步骤3: 验证数据库保存结果 ===\n";
                
                $table_name = $wpdb->prefix . 'walmart_product_attributes';
                $db_found_count = 0;
                
                foreach ($new_fields as $field_name) {
                    $count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE attribute_name = %s AND product_type_id = %s",
                        $field_name,
                        'Dining Chairs'
                    ));
                    
                    if ($count > 0) {
                        $db_found_count++;
                        echo "✅ 数据库中找到: {$field_name}\n";
                    } else {
                        echo "❌ 数据库中缺失: {$field_name}\n";
                    }
                }
                
                echo "\n数据库验证结果: {$db_found_count}/10\n";
                
                if ($db_found_count === 10) {
                    echo "🎉 所有字段都已成功保存到数据库！\n";
                    
                    // 步骤4: 测试重置属性功能
                    echo "\n=== 步骤4: 测试重置属性功能 ===\n";
                    
                    if (function_exists('get_attributes_from_database')) {
                        echo "✅ get_attributes_from_database函数存在\n";
                        
                        $db_attributes = get_attributes_from_database('Dining Chairs');
                        
                        if (is_array($db_attributes) && !empty($db_attributes)) {
                            echo "✅ 从数据库读取成功，返回 " . count($db_attributes) . " 个字段\n";
                            
                            $reset_found_count = 0;
                            foreach ($new_fields as $field_name) {
                                foreach ($db_attributes as $attr) {
                                    if (isset($attr['attributeName']) && $attr['attributeName'] === $field_name) {
                                        $reset_found_count++;
                                        echo "✅ 重置属性中找到: {$field_name}\n";
                                        break;
                                    }
                                }
                            }
                            
                            echo "\n重置属性验证结果: {$reset_found_count}/10\n";
                            
                            if ($reset_found_count === 10) {
                                echo "🎉 重置属性功能完全正常！\n";
                                echo "\n=== 🎯 测试结论 ===\n";
                                echo "✅ 所有10个新字段的完整流程都正常工作：\n";
                                echo "   1. ✅ API解析正确\n";
                                echo "   2. ✅ 数据库保存成功\n";
                                echo "   3. ✅ 重置属性功能正常\n";
                                echo "\n现在用户可以正常使用这些新字段了！\n";
                            } else {
                                echo "❌ 重置属性功能有问题\n";
                            }
                        } else {
                            echo "❌ 从数据库读取失败\n";
                        }
                    } else {
                        echo "❌ get_attributes_from_database函数不存在\n";
                    }
                } else {
                    echo "❌ 数据库保存有问题\n";
                }
            } else {
                echo "❌ save_attributes_to_database函数不存在\n";
            }
        } else {
            echo "❌ 字段解析有问题\n";
        }
    } else {
        echo "❌ 解析失败\n";
    }
} else {
    echo "❌ parse_v5_spec_response函数不存在\n";
}

echo "\n测试完成！\n";
?>
