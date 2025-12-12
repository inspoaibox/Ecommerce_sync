<?php
/**
 * 清除缓存并重新测试
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 清除缓存并重新测试 ===\n\n";

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\canda.localhost';
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';

echo "✅ WordPress环境加载成功\n\n";

// === 1. 清除WordPress缓存 ===
echo "=== 1. 清除WordPress缓存 ===\n";

// 清除对象缓存
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "✅ 清除对象缓存\n";
}

// 清除瞬态缓存
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
echo "✅ 清除瞬态缓存\n";

// === 2. 重新保存关键设置 ===
echo "\n=== 2. 重新保存关键设置 ===\n";

$current_fc_id = get_option('woo_walmart_fulfillment_center_id', '');
$current_business_unit = get_option('woo_walmart_business_unit', '');

echo "当前履行中心ID: $current_fc_id\n";
echo "当前业务单元: $current_business_unit\n";

// 重新保存（触发任何可能的钩子）
update_option('woo_walmart_fulfillment_center_id', $current_fc_id);
update_option('woo_walmart_business_unit', $current_business_unit);

echo "✅ 重新保存设置完成\n";

// === 3. 创建全新的测试产品 ===
echo "\n=== 3. 创建全新的测试产品 ===\n";

$test_product = new WC_Product_Simple();
$test_product->set_name('缓存清除测试产品 - ' . date('Y-m-d H:i:s'));
$test_product->set_sku('CACHE-CLEAR-TEST-' . time());
$test_product->set_regular_price(99.99);
$test_product->set_description('用于验证缓存清除后的履行中心ID处理');
$test_product->set_short_description('缓存清除测试');
$test_product->set_status('publish');
$test_product->set_catalog_visibility('visible');
$test_product->set_stock_status('instock');
$test_product->set_manage_stock(true);
$test_product->set_stock_quantity(50);

// 设置分类（使用现有分类）
$category_ids = [15]; // 假设分类ID为15
$test_product->set_category_ids($category_ids);

$product_id = $test_product->save();
echo "✅ 创建测试产品: ID $product_id, SKU: " . $test_product->get_sku() . "\n";

// === 4. 测试新产品的映射 ===
echo "\n=== 4. 测试新产品映射 ===\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 检查履行中心ID方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('get_market_specific_fulfillment_center_id');
$method->setAccessible(true);

$fc_result = $method->invoke($mapper);
echo "履行中心ID方法结果: $fc_result\n";

if ($fc_result === $current_fc_id) {
    echo "✅ 履行中心ID方法正确\n";
} else {
    echo "❌ 履行中心ID方法错误\n";
}

// 检查分类映射
global $wpdb;
$category_mapping_table = $wpdb->prefix . 'walmart_category_mapping';
$mapped_category_data = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $category_mapping_table WHERE wc_category_id = %d LIMIT 1",
    $category_ids[0]
));

if ($mapped_category_data) {
    echo "✅ 找到分类映射: {$mapped_category_data->walmart_category_path}\n";
    
    // 执行完整映射
    $attribute_rules = json_decode($mapped_category_data->walmart_attributes, true);
    if (!is_array($attribute_rules)) {
        $attribute_rules = ['name' => [], 'type' => [], 'source' => []];
    }
    
    $test_upc = '123456789012';
    
    try {
        $walmart_data = $mapper->map($test_product, $mapped_category_data->walmart_category_path, $test_upc, $attribute_rules, 1);
        
        echo "✅ 产品映射成功\n";
        
        $orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];
        $mapped_fc_id = $orderable['fulfillmentCenterID'] ?? '未设置';
        
        echo "映射生成的履行中心ID: $mapped_fc_id\n";
        
        if ($mapped_fc_id === $current_fc_id) {
            echo "🎉 完美！映射过程正确使用了设置的履行中心ID\n";
            echo "现在重新提交产品应该不会出现履行中心ID错误\n";
        } else {
            echo "❌ 映射过程仍有问题\n";
            echo "期望: $current_fc_id\n";
            echo "实际: $mapped_fc_id\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 产品映射失败: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ 未找到分类映射，无法测试完整流程\n";
}

// === 5. 清理测试产品 ===
echo "\n=== 5. 清理测试产品 ===\n";
wp_delete_post($product_id, true);
echo "✅ 删除测试产品\n";

// === 6. 最终建议 ===
echo "\n=== 6. 最终建议 ===\n";

if (isset($mapped_fc_id) && $mapped_fc_id === $current_fc_id) {
    echo "🎉 缓存清除成功！系统现在工作正常\n\n";
    
    echo "下一步操作:\n";
    echo "1. ✅ 缓存已清除\n";
    echo "2. ✅ 设置已重新保存\n";
    echo "3. ✅ 履行中心ID处理正常\n";
    echo "4. 🎯 现在可以重新提交您的产品\n\n";
    
    echo "重要提示:\n";
    echo "- 不要使用之前失败的产品（它们可能有缓存的错误数据）\n";
    echo "- 建议创建新的产品或重新编辑现有产品\n";
    echo "- 重新保存产品后再提交到沃尔玛\n";
    
} else {
    echo "⚠️ 问题可能更复杂，需要进一步调查\n";
    echo "建议联系技术支持进行深度排查\n";
}

echo "\n=== 处理完成 ===\n";
?>
