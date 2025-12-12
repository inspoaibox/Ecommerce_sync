<?php
/**
 * 检查API规范中的字段类型信息
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查API规范中的字段类型信息 ===\n\n";

global $wpdb;

// 出错的字段
$error_fields = [
    'luggage_lock_type',
    'productNetContentMeasure', 
    'productNetContentUnit',
    'luggage_inner_dimension_depth',
    'height_with_handle_extended',
    'isProp65WarningRequired',
    'luggage_overall_dimension_depth',
    'luggageStyle',
    'season',
    'netContent'
];

echo "1. 检查数据库中的API规范数据:\n\n";

// 检查是否有API规范数据
$spec_table = $wpdb->prefix . 'walmart_product_attributes';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$spec_table'") == $spec_table;

if (!$table_exists) {
    echo "❌ API规范表不存在\n";
} else {
    $total_specs = $wpdb->get_var("SELECT COUNT(*) FROM $spec_table");
    echo "✅ API规范表存在，共有 $total_specs 条记录\n\n";
    
    // 查找拉杆箱分类的规范
    $luggage_specs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $spec_table WHERE product_type_id LIKE %s",
        '%Luggage%'
    ));
    
    if (empty($luggage_specs)) {
        echo "❌ 没有找到拉杆箱分类的API规范\n";
        
        // 查看所有可用的产品类型
        $all_types = $wpdb->get_results("SELECT DISTINCT product_type_id FROM $spec_table LIMIT 10");
        echo "可用的产品类型:\n";
        foreach ($all_types as $type) {
            echo "  - {$type->product_type_id}\n";
        }
    } else {
        echo "✅ 找到拉杆箱分类的API规范，共 " . count($luggage_specs) . " 个字段\n\n";
        
        // 检查出错字段的API规范
        foreach ($error_fields as $field) {
            $spec = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $spec_table WHERE product_type_id LIKE %s AND attribute_name = %s",
                '%Luggage%', $field
            ));
            
            if ($spec) {
                echo "字段: $field\n";
                echo "  - API类型: {$spec->attribute_type}\n";
                echo "  - 默认类型: {$spec->default_type}\n";
                echo "  - 是否必填: " . ($spec->is_required ? 'Yes' : 'No') . "\n";
                
                if (!empty($spec->allowed_values)) {
                    $allowed = json_decode($spec->allowed_values, true);
                    if (is_array($allowed)) {
                        echo "  - 允许值: " . implode(', ', array_slice($allowed, 0, 5)) . (count($allowed) > 5 ? '...' : '') . "\n";
                    } else {
                        echo "  - 允许值: {$spec->allowed_values}\n";
                    }
                }
                
                if (!empty($spec->validation_rules)) {
                    echo "  - 验证规则: {$spec->validation_rules}\n";
                }
                
                echo "  - 分组: {$spec->attribute_group}\n";
            } else {
                echo "字段: $field - ❌ 未在API规范中找到\n";
            }
            echo "\n";
        }
    }
}

echo "2. 检查convert_field_data_type函数是否使用API规范:\n\n";

// 检查是否有API规范转换的日志
$api_spec_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}walmart_sync_logs 
     WHERE action LIKE %s 
     ORDER BY created_at DESC LIMIT 5",
    '%基于API规范转换%'
));

if (empty($api_spec_logs)) {
    echo "❌ 没有找到API规范转换的日志\n";
    echo "这说明convert_field_data_type函数没有使用API规范进行转换\n";
} else {
    echo "✅ 找到API规范转换日志:\n";
    foreach ($api_spec_logs as $log) {
        echo "  时间: {$log->created_at}\n";
        echo "  消息: {$log->message}\n";
        if (!empty($log->request_data)) {
            $data = json_decode($log->request_data, true);
            echo "  数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
}

echo "3. 建议的解决方案:\n\n";

if ($table_exists && !empty($luggage_specs)) {
    echo "✅ API规范数据可用，应该:\n";
    echo "  1. 修改convert_field_data_type函数，优先使用API规范\n";
    echo "  2. 确保API规范服务正确初始化\n";
    echo "  3. 移除硬编码的字段列表，完全依赖API规范\n";
} else {
    echo "❌ API规范数据不可用，需要:\n";
    echo "  1. 先通过后台界面获取拉杆箱分类的API规范\n";
    echo "  2. 确保API规范正确存储到数据库\n";
    echo "  3. 然后修改转换逻辑使用API规范\n";
}

echo "\n=== 检查完成 ===\n";
