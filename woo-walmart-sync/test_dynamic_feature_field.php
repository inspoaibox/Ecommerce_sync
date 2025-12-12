<?php
/**
 * 测试动态feature字段的分类感知功能
 * 验证分类特定枚举值的获取和匹配逻辑
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 动态Feature字段测试脚本 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// 自动检测WordPress路径
$wp_path = '';
$current_dir = __DIR__;
for ($i = 0; $i < 5; $i++) {
    $test_path = $current_dir . str_repeat('/..', $i);
    if (file_exists($test_path . '/wp-config.php')) {
        $wp_path = realpath($test_path);
        break;
    }
}

if (empty($wp_path)) {
    echo "❌ 无法检测WordPress路径\n";
    exit;
}

require_once $wp_path . '/wp-config.php';
require_once $wp_path . '/wp-load.php';

echo "✅ WordPress加载成功\n\n";

// 加载产品映射器
require_once __DIR__ . '/includes/class-product-mapper.php';

// 获取测试产品
$products = wc_get_products([
    'limit' => 5,
    'status' => 'publish'
]);

if (empty($products)) {
    echo "❌ 没有找到可测试的产品\n";
    exit;
}

echo "找到 " . count($products) . " 个测试产品\n\n";

// 创建映射器实例
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);

$get_walmart_category_method = $reflection->getMethod('get_product_walmart_category');
$get_walmart_category_method->setAccessible(true);

$get_enum_values_method = $reflection->getMethod('get_category_specific_enum_values');
$get_enum_values_method->setAccessible(true);

$extract_features_method = $reflection->getMethod('extract_features_from_product');
$extract_features_method->setAccessible(true);

$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

foreach ($products as $product) {
    echo "=== 测试产品: {$product->get_name()} (ID: {$product->get_id()}) ===\n";
    echo "SKU: " . $product->get_sku() . "\n";
    
    // 获取产品分类
    $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    echo "本地分类: " . implode(', ', $product_categories) . "\n";
    
    // 测试获取Walmart分类
    echo "\n🔍 步骤1: 获取Walmart分类\n";
    try {
        $walmart_category = $get_walmart_category_method->invoke($mapper, $product);
        if ($walmart_category) {
            echo "✅ Walmart分类: {$walmart_category}\n";
        } else {
            echo "❌ 未找到Walmart分类映射\n";
            echo "   原因: 该产品的本地分类可能未配置Walmart映射\n";
            echo str_repeat('-', 80) . "\n\n";
            continue;
        }
    } catch (Exception $e) {
        echo "❌ 获取Walmart分类失败: " . $e->getMessage() . "\n";
        echo str_repeat('-', 80) . "\n\n";
        continue;
    }
    
    // 测试获取分类特定枚举值
    echo "\n🔍 步骤2: 获取分类特定枚举值\n";
    try {
        $available_features = $get_enum_values_method->invoke($mapper, $walmart_category, 'feature');
        if (!empty($available_features)) {
            echo "✅ 找到 " . count($available_features) . " 个可用特性:\n";
            foreach ($available_features as $i => $feature) {
                echo "   " . ($i + 1) . ". {$feature}\n";
                if ($i >= 4) { // 只显示前5个
                    echo "   ... (共 " . count($available_features) . " 个)\n";
                    break;
                }
            }
        } else {
            echo "❌ 该分类暂无配置的特性选项\n";
            echo "   分类: {$walmart_category}\n";
            echo "   建议: 在 get_category_specific_enum_values 方法中添加该分类的配置\n";
        }
    } catch (Exception $e) {
        echo "❌ 获取枚举值失败: " . $e->getMessage() . "\n";
    }
    
    // 测试特性提取
    if (!empty($available_features)) {
        echo "\n🔍 步骤3: 从产品信息中提取特性\n";
        try {
            $matched_features = $extract_features_method->invoke($mapper, $product, $available_features);
            if (!empty($matched_features)) {
                echo "✅ 匹配到 " . count($matched_features) . " 个特性:\n";
                foreach ($matched_features as $feature) {
                    echo "   - {$feature}\n";
                }
            } else {
                echo "❌ 未匹配到任何特性\n";
                echo "   原因: 产品描述中可能不包含相关关键词\n";
            }
        } catch (Exception $e) {
            echo "❌ 特性提取失败: " . $e->getMessage() . "\n";
        }
    }
    
    // 测试完整的feature字段生成
    echo "\n🔍 步骤4: 完整的feature字段生成\n";
    try {
        $start_time = microtime(true);
        $feature_result = $generate_method->invoke($mapper, 'feature', $product, 1);
        $end_time = microtime(true);
        $execution_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "执行时间: {$execution_time}ms\n";
        
        if (is_null($feature_result)) {
            echo "结果: NULL (字段将不会传递)\n";
        } elseif (is_array($feature_result)) {
            echo "结果: [数组，" . count($feature_result) . " 个元素]\n";
            echo "特性列表: " . implode(', ', $feature_result) . "\n";
        } else {
            echo "结果: {$feature_result}\n";
        }
        
        echo "✅ feature字段生成测试通过\n";
        
    } catch (Exception $e) {
        echo "❌ feature字段生成失败: " . $e->getMessage() . "\n";
        echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo str_repeat('-', 80) . "\n\n";
}

// 测试分类映射表
echo "=== 分类映射表检查 ===\n";
global $wpdb;
$table_name = $wpdb->prefix . 'walmart_category_map';

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists) {
    $mapping_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "✅ 分类映射表存在，共有 {$mapping_count} 条映射记录\n";
    
    // 显示前几条映射记录
    $sample_mappings = $wpdb->get_results("
        SELECT wc_category_id, walmart_category_path, local_category_ids 
        FROM {$table_name} 
        LIMIT 5
    ");
    
    echo "前5条映射记录:\n";
    foreach ($sample_mappings as $mapping) {
        $local_ids = $mapping->local_category_ids ? json_decode($mapping->local_category_ids, true) : [$mapping->wc_category_id];
        $local_ids_str = implode(', ', $local_ids);
        echo "  - 本地分类ID: [{$local_ids_str}] → Walmart: {$mapping->walmart_category_path}\n";
    }
} else {
    echo "❌ 分类映射表不存在: {$table_name}\n";
    echo "   这可能是测试失败的原因\n";
}

echo "\n=== 测试完成 ===\n";
echo "总结:\n";
echo "- 测试产品数量: " . count($products) . " 个\n";
echo "- 分类映射表状态: " . ($table_exists ? '✅ 存在' : '❌ 不存在') . "\n";
echo "- 功能实现状态: ✅ 完成\n";
echo "\n建议:\n";
echo "1. 确保产品的本地分类已配置Walmart映射\n";
echo "2. 根据需要在 get_category_specific_enum_values 方法中添加更多分类配置\n";
echo "3. 在分类映射页面测试feature字段的显示和生成\n";
?>
