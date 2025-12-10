<?php
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

global $wpdb;

echo "=== 检查所有产品类型的 seat_material 规范 ===\n\n";

$specs = $wpdb->get_results("
    SELECT product_type_id, attribute_name, attribute_type, is_required 
    FROM {$wpdb->prefix}walmart_product_attributes 
    WHERE attribute_name = 'seat_material'
    ORDER BY product_type_id
");

if ($specs) {
    echo "找到 " . count($specs) . " 条 seat_material 规范:\n\n";
    foreach ($specs as $spec) {
        echo "产品类型: {$spec->product_type_id}\n";
        echo "  属性类型: {$spec->attribute_type}\n";
        echo "  是否必填: " . ($spec->is_required ? '是' : '否') . "\n";
        
        if ($spec->attribute_type !== 'array') {
            echo "  ❌ 错误：属性类型不是 array！\n";
        } else {
            echo "  ✅ 属性类型正确\n";
        }
        echo "\n";
    }
} else {
    echo "❌ 未找到任何 seat_material 规范\n";
}

echo "=== 检查完成 ===\n";
?>

