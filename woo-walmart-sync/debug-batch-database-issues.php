<?php
/**
 * 调试批量处理时的数据库问题
 * 检查UPC分配、数据库连接等问题
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 调试批量处理数据库问题 ===\n\n";

// 模拟大批量处理（100个产品）
global $wpdb;

// 1. 检查UPC池状态
echo "1. 检查UPC池状态:\n";
$total_upc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}walmart_upc_pool");
$used_upc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}walmart_upc_pool WHERE is_used = 1");
$available_upc = $total_upc - $used_upc;

echo "  总UPC数量: {$total_upc}\n";
echo "  已用UPC: {$used_upc}\n";
echo "  可用UPC: {$available_upc}\n";

if ($available_upc < 100) {
    echo "  ⚠️  可用UPC不足100个，可能导致批量处理失败\n";
}

// 2. 获取100个真实的产品ID进行测试
echo "\n2. 获取测试产品:\n";
$test_products = $wpdb->get_results("
    SELECT ID, post_title 
    FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    ORDER BY ID DESC 
    LIMIT 100
");

echo "  找到产品数量: " . count($test_products) . "\n";

if (count($test_products) < 100) {
    echo "  ⚠️  产品数量不足100个\n";
    $test_products = array_slice($test_products, 0, min(50, count($test_products)));
    echo "  调整为测试 " . count($test_products) . " 个产品\n";
}

// 3. 模拟批量UPC分配过程
echo "\n3. 模拟批量UPC分配:\n";
$upc_assignment_failures = 0;
$upc_assignment_successes = 0;
$assignment_times = [];

foreach ($test_products as $index => $product_data) {
    $product_id = $product_data->ID;
    
    if ($index % 20 == 0) {
        echo "  处理进度: " . ($index + 1) . "/" . count($test_products) . "\n";
    }
    
    $start_time = microtime(true);
    
    // 检查是否已有UPC
    $existing_upc = $wpdb->get_var($wpdb->prepare(
        "SELECT upc_code FROM {$wpdb->prefix}walmart_upc_pool WHERE product_id = %d AND is_used = 1",
        $product_id
    ));
    
    if ($existing_upc) {
        $upc_assignment_successes++;
        $assignment_times[] = (microtime(true) - $start_time) * 1000;
        continue;
    }
    
    // 尝试分配新UPC
    $available_upc = $wpdb->get_row(
        "SELECT id, upc_code FROM {$wpdb->prefix}walmart_upc_pool WHERE is_used = 0 LIMIT 1"
    );
    
    if ($available_upc) {
        // 模拟分配（不实际执行，避免污染数据）
        $upc_assignment_successes++;
    } else {
        $upc_assignment_failures++;
        echo "    ❌ 产品 {$product_id} UPC分配失败 - 无可用UPC\n";
    }
    
    $assignment_times[] = (microtime(true) - $start_time) * 1000;
    
    // 检查是否有数据库错误
    if ($wpdb->last_error) {
        echo "    ❌ 数据库错误: " . $wpdb->last_error . "\n";
    }
}

echo "  UPC分配成功: {$upc_assignment_successes}\n";
echo "  UPC分配失败: {$upc_assignment_failures}\n";
echo "  平均分配时间: " . round(array_sum($assignment_times) / count($assignment_times), 2) . "ms\n";
echo "  最长分配时间: " . round(max($assignment_times), 2) . "ms\n";

// 4. 检查分类映射覆盖率
echo "\n4. 检查分类映射覆盖率:\n";
$products_with_mapping = 0;
$products_without_mapping = 0;
$mapping_check_times = [];

foreach (array_slice($test_products, 0, 50) as $product_data) {
    $product_id = $product_data->ID;
    
    $start_time = microtime(true);
    
    // 获取产品分类
    $product_categories = wp_get_post_terms($product_id, 'product_cat');
    $has_mapping = false;
    
    foreach ($product_categories as $category) {
        $cat_id = $category->term_id;
        
        $mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT walmart_category_path FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
            $cat_id
        ));
        
        if ($mapping) {
            $has_mapping = true;
            break;
        }
    }
    
    if ($has_mapping) {
        $products_with_mapping++;
    } else {
        $products_without_mapping++;
    }
    
    $mapping_check_times[] = (microtime(true) - $start_time) * 1000;
}

echo "  有分类映射: {$products_with_mapping}\n";
echo "  无分类映射: {$products_without_mapping}\n";
echo "  平均查询时间: " . round(array_sum($mapping_check_times) / count($mapping_check_times), 2) . "ms\n";

// 5. 检查数据库连接状态
echo "\n5. 检查数据库连接状态:\n";
$db_status = $wpdb->get_results("SHOW STATUS LIKE 'Threads_%'");
foreach ($db_status as $status) {
    echo "  {$status->Variable_name}: {$status->Value}\n";
}

// 6. 模拟内存使用情况
echo "\n6. 内存使用情况:\n";
echo "  当前内存: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "  峰值内存: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
echo "  内存限制: " . ini_get('memory_limit') . "\n";

// 7. 预测批量处理结果
echo "\n7. 批量处理预测:\n";
$predicted_success_rate = ($upc_assignment_successes / count($test_products)) * ($products_with_mapping / 50);
echo "  预测成功率: " . round($predicted_success_rate * 100, 1) . "%\n";

if ($predicted_success_rate < 0.8) {
    echo "  ⚠️  成功率较低，可能导致'没有有效的商品数据'错误\n";
    
    if ($upc_assignment_failures > 0) {
        echo "    主要原因: UPC分配失败\n";
    }
    
    if ($products_without_mapping > 0) {
        echo "    主要原因: 分类映射缺失\n";
    }
} else {
    echo "  ✅ 预测成功率较高\n";
}

echo "\n=== 调试完成 ===\n";
