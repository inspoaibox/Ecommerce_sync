<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;
$spec_table = $wpdb->prefix . 'walmart_product_attributes';

echo "=== 数据库中的产品类型ID ===\n\n";

$types = $wpdb->get_results("SELECT DISTINCT product_type_id, COUNT(*) as count FROM $spec_table GROUP BY product_type_id ORDER BY count DESC LIMIT 10");

foreach ($types as $type) {
    echo "- '{$type->product_type_id}' ({$type->count} 个字段)\n";
}

echo "\n=== 检查拉杆箱相关的产品类型 ===\n\n";

$luggage_types = $wpdb->get_results("SELECT DISTINCT product_type_id, COUNT(*) as count FROM $spec_table WHERE product_type_id LIKE '%uggage%' GROUP BY product_type_id");

if (empty($luggage_types)) {
    echo "❌ 没有找到包含'uggage'的产品类型\n";
} else {
    foreach ($luggage_types as $type) {
        echo "✅ '{$type->product_type_id}' ({$type->count} 个字段)\n";
    }
}

echo "\n=== 检查出错字段是否存在 ===\n\n";

$error_fields = ['luggage_lock_type', 'luggageStyle', 'season', 'netContent'];

foreach ($error_fields as $field) {
    $found = $wpdb->get_results($wpdb->prepare(
        "SELECT product_type_id, attribute_type FROM $spec_table WHERE attribute_name = %s",
        $field
    ));
    
    if (empty($found)) {
        echo "❌ $field: 未找到\n";
    } else {
        echo "✅ $field:\n";
        foreach ($found as $spec) {
            echo "    - 产品类型: '{$spec->product_type_id}', 类型: {$spec->attribute_type}\n";
        }
    }
}

echo "\n=== 完成 ===\n";
?>
