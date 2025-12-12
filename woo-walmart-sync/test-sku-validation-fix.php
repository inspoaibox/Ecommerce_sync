<?php
/**
 * 测试SKU验证修复
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试SKU验证修复 ===\n\n";

// 测试问题SKU
$test_sku = 'W3622S00002';

echo "测试SKU: {$test_sku}\n\n";

global $wpdb;

// 1. 查找产品
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $test_sku
));

if (!$product_id) {
    echo "❌ 产品不存在\n";
    exit;
}

echo "✅ 找到产品ID: {$product_id}\n";

$product = wc_get_product($product_id);
echo "产品名称: {$product->get_name()}\n";
echo "产品状态: {$product->get_status()}\n";

// 2. 获取产品分类
$categories = $product->get_category_ids();
echo "产品分类ID: " . implode(', ', $categories) . "\n";

$category_names = [];
foreach ($categories as $cat_id) {
    $cat = get_term($cat_id);
    if ($cat) {
        $category_names[] = $cat->name;
    }
}
echo "分类名称: " . implode(', ', $category_names) . "\n\n";

// 3. 测试旧的验证逻辑（会失败）
echo "3. 测试旧的验证逻辑:\n";
$old_mapping_table = $wpdb->prefix . 'woo_walmart_category_mapping';
$old_has_mapping = false;

foreach ($categories as $cat_id) {
    $old_mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$old_mapping_table} WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($old_mapping) {
        $old_has_mapping = true;
        echo "  ✅ 旧表找到映射: {$old_mapping->walmart_category_path}\n";
        break;
    }
}

if (!$old_has_mapping) {
    echo "  ❌ 旧表未找到映射\n";
}

// 4. 测试新的验证逻辑（应该成功）
echo "\n4. 测试新的验证逻辑:\n";
$new_mapping_table = $wpdb->prefix . 'walmart_category_map';
$new_has_mapping = false;
$found_mapping = null;

foreach ($categories as $cat_id) {
    echo "  检查分类ID: {$cat_id}\n";
    
    // 方法1：直接查询
    $direct_mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$new_mapping_table} WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($direct_mapping) {
        $new_has_mapping = true;
        $found_mapping = $direct_mapping;
        echo "    ✅ 直接查询找到映射: {$direct_mapping->walmart_category_path}\n";
        break;
    } else {
        echo "    ❌ 直接查询未找到\n";
    }
    
    // 方法2：共享映射查询
    $shared_mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$new_mapping_table} 
         WHERE local_category_ids IS NOT NULL 
         AND JSON_CONTAINS(local_category_ids, %s)",
        json_encode(strval($cat_id))
    ));
    
    if ($shared_mapping) {
        $new_has_mapping = true;
        $found_mapping = $shared_mapping;
        echo "    ✅ 共享查询找到映射: {$shared_mapping->walmart_category_path}\n";
        
        $local_ids = json_decode($shared_mapping->local_category_ids, true);
        echo "    共享分类IDs: " . implode(', ', $local_ids) . "\n";
        break;
    } else {
        echo "    ❌ 共享查询未找到\n";
    }
}

// 5. 测试修复后的validate_sku_list_for_sync函数
echo "\n5. 测试修复后的validate_sku_list_for_sync函数:\n";

$validation_results = validate_sku_list_for_sync([$test_sku]);

echo "验证结果:\n";
echo "  总计: {$validation_results['total']}\n";
echo "  有效: " . count($validation_results['valid']) . "\n";
echo "  无效: " . count($validation_results['invalid']) . "\n";
echo "  未映射: " . count($validation_results['unmapped']) . "\n\n";

if (!empty($validation_results['valid'])) {
    echo "✅ 有效产品:\n";
    foreach ($validation_results['valid'] as $valid_product) {
        echo "  SKU: {$valid_product['sku']}\n";
        echo "  名称: {$valid_product['name']}\n";
        echo "  分类: {$valid_product['category']}\n";
        echo "  状态: {$valid_product['status']}\n";
    }
}

if (!empty($validation_results['invalid'])) {
    echo "❌ 无效产品:\n";
    foreach ($validation_results['invalid'] as $invalid_product) {
        echo "  SKU: {$invalid_product['sku']}\n";
        echo "  原因: {$invalid_product['reason']}\n";
    }
}

if (!empty($validation_results['unmapped'])) {
    echo "⚠️ 未映射产品:\n";
    foreach ($validation_results['unmapped'] as $unmapped_product) {
        echo "  SKU: {$unmapped_product['sku']}\n";
        echo "  名称: {$unmapped_product['name']}\n";
        echo "  分类: {$unmapped_product['category']}\n";
        echo "  原因: {$unmapped_product['reason']}\n";
    }
}

// 6. 显示找到的映射详情
if ($found_mapping) {
    echo "\n6. 找到的映射详情:\n";
    echo "  映射ID: {$found_mapping->id}\n";
    echo "  WC分类ID: {$found_mapping->wc_category_id}\n";
    echo "  WC分类名: {$found_mapping->wc_category_name}\n";
    echo "  Walmart分类: {$found_mapping->walmart_category_path}\n";
    
    if (isset($found_mapping->local_category_ids) && !empty($found_mapping->local_category_ids)) {
        $local_ids = json_decode($found_mapping->local_category_ids, true);
        echo "  共享分类IDs: " . implode(', ', $local_ids) . "\n";
        echo "  映射类型: 共享映射\n";
    } else {
        echo "  映射类型: 直接映射\n";
    }
    
    if (!empty($found_mapping->walmart_attributes)) {
        $attributes = json_decode($found_mapping->walmart_attributes, true);
        if ($attributes && isset($attributes['name'])) {
            echo "  配置属性数量: " . count($attributes['name']) . "\n";
        }
    }
}

echo "\n=== 测试完成 ===\n";

if ($new_has_mapping) {
    echo "✅ 修复成功！SKU验证现在可以正确识别分类映射。\n";
} else {
    echo "❌ 修复失败！仍然无法找到分类映射。\n";
    echo "请检查分类映射配置是否正确。\n";
}

?>
