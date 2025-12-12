<?php
/**
 * 检查分类映射状态
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 检查分类映射状态 ===\n\n";

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\canda.localhost';
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';

echo "✅ WordPress环境加载成功\n\n";

global $wpdb;

// === 1. 检查现有的分类映射 ===
echo "=== 1. 检查现有的分类映射 ===\n";

$map_table = $wpdb->prefix . 'walmart_category_map';

// 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$map_table'");

if (!$table_exists) {
    echo "❌ 分类映射表不存在: $map_table\n";
    exit;
}

echo "✅ 分类映射表存在: $map_table\n";

// 获取所有映射
$mappings = $wpdb->get_results("SELECT * FROM $map_table ORDER BY wc_category_id");

echo "现有映射数量: " . count($mappings) . "\n\n";

if (empty($mappings)) {
    echo "❌ 没有任何分类映射！这就是同步失败的原因。\n\n";
    echo "解决方案:\n";
    echo "1. 进入WordPress后台 → 沃尔玛同步 → 分类映射\n";
    echo "2. 为产品分类创建沃尔玛分类映射\n";
    echo "3. 然后重新尝试同步\n";
} else {
    echo "现有的分类映射:\n";
    foreach ($mappings as $mapping) {
        $wc_category = get_term($mapping->wc_category_id);
        $category_name = $wc_category ? $wc_category->name : '分类不存在';
        
        echo "  WC分类ID: {$mapping->wc_category_id} ({$category_name})\n";
        echo "  沃尔玛分类: {$mapping->walmart_category_path}\n";
        echo "  映射名称: {$mapping->wc_category_name}\n";
        echo "  属性规则: " . (empty($mapping->walmart_attributes) ? '无' : '有') . "\n";
        echo "\n";
    }
}

// === 2. 查找有映射的产品进行测试 ===
echo "=== 2. 查找有映射的产品进行测试 ===\n";

if (!empty($mappings)) {
    $mapped_category_ids = array_column($mappings, 'wc_category_id');
    $mapped_ids_str = implode(',', $mapped_category_ids);
    
    echo "已映射的分类ID: " . implode(', ', $mapped_category_ids) . "\n\n";
    
    // 查找属于这些分类的产品
    $products_with_mapping = $wpdb->get_results("
        SELECT p.ID, p.post_title, pm.meta_value as sku, tr.term_taxonomy_id
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND pm.meta_key = '_sku'
        AND pm.meta_value != ''
        AND tt.taxonomy = 'product_cat'
        AND tt.term_id IN ($mapped_ids_str)
        GROUP BY p.ID
        ORDER BY p.ID DESC
        LIMIT 5
    ");
    
    if (!empty($products_with_mapping)) {
        echo "找到有映射的产品:\n";
        foreach ($products_with_mapping as $product_info) {
            echo "  产品ID: {$product_info->ID}\n";
            echo "  SKU: {$product_info->sku}\n";
            echo "  名称: " . substr($product_info->post_title, 0, 50) . "...\n";
            echo "\n";
        }
        
        // 选择第一个产品进行测试
        $test_product_id = $products_with_mapping[0]->ID;
        echo "建议使用产品ID {$test_product_id} 进行同步测试\n";
        
    } else {
        echo "❌ 没有找到属于已映射分类的产品\n";
        echo "这意味着现有的映射可能没有对应的产品\n";
    }
} else {
    echo "由于没有分类映射，无法查找测试产品\n";
}

// === 3. 检查问题产品的分类详情 ===
echo "\n=== 3. 检查问题产品的分类详情 ===\n";

$problem_category_ids = [10552, 10555, 10553, 10554, 10556];

echo "问题产品的分类详情:\n";
foreach ($problem_category_ids as $cat_id) {
    $category = get_term($cat_id);
    if ($category) {
        echo "  分类ID: {$cat_id}\n";
        echo "  分类名: {$category->name}\n";
        echo "  分类层级: " . get_term_parents_list($cat_id, 'product_cat', ['separator' => ' > ', 'link' => false]) . "\n";
        echo "  产品数量: " . $category->count . "\n";
        echo "\n";
    } else {
        echo "  分类ID: {$cat_id} - 分类不存在\n";
    }
}

// === 4. 提供解决方案 ===
echo "=== 4. 解决方案 ===\n";

echo "🎯 问题根源: 产品分类没有沃尔玛映射\n\n";

echo "✅ 解决方案选项:\n\n";

echo "选项1 - 创建分类映射（推荐）:\n";
echo "1. 进入WordPress后台\n";
echo "2. 导航到 沃尔玛同步 → 分类映射\n";
echo "3. 为以下分类创建映射:\n";
foreach ($problem_category_ids as $cat_id) {
    $category = get_term($cat_id);
    if ($category) {
        echo "   - {$category->name} (ID: {$cat_id})\n";
    }
}
echo "4. 选择合适的沃尔玛分类和属性\n";
echo "5. 保存映射后重新尝试同步\n\n";

if (!empty($products_with_mapping)) {
    echo "选项2 - 使用已有映射的产品测试:\n";
    echo "1. 使用产品ID: {$test_product_id}\n";
    echo "2. 该产品已有分类映射，应该能正常同步\n";
    echo "3. 先测试这个产品验证系统功能\n\n";
}

echo "选项3 - 临时测试:\n";
echo "1. 将测试产品移动到已有映射的分类下\n";
echo "2. 进行同步测试\n";
echo "3. 验证功能正常后再处理分类映射\n\n";

echo "🚀 一旦创建了分类映射，同步功能应该就能正常工作了！\n";

echo "\n=== 检查完成 ===\n";
?>
