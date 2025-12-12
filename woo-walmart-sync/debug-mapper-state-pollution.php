<?php
/**
 * 测试 Mapper 状态污染问题
 * 模拟批量处理时的状态污染
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 测试 Mapper 状态污染问题 ===\n\n";

// 使用多个真实的产品ID
$test_product_ids = [17801, 17800, 17799];

echo "测试产品ID: " . implode(', ', $test_product_ids) . "\n\n";

// 模拟批量处理逻辑
$mapper = new Woo_Walmart_Product_Mapper(); // 重用同一个实例
$items = [];
$failed_products = [];

foreach ($test_product_ids as $index => $product_id) {
    echo "=== 处理产品 {$product_id} (第" . ($index + 1) . "个) ===\n";
    
    $product = wc_get_product($product_id);
    if (!$product) {
        echo "❌ 产品不存在\n";
        $failed_products[] = $product_id;
        continue;
    }
    
    echo "产品名称: " . substr($product->get_name(), 0, 50) . "...\n";
    echo "SKU: {$product->get_sku()}\n";
    
    // 获取UPC
    global $wpdb;
    $upc = $wpdb->get_var($wpdb->prepare(
        "SELECT upc_code FROM {$wpdb->prefix}walmart_upc_pool WHERE product_id = %d AND is_used = 1",
        $product_id
    ));
    
    if (!$upc) {
        echo "❌ 没有UPC\n";
        $failed_products[] = $product_id;
        continue;
    }
    
    echo "UPC: {$upc}\n";
    
    // 获取分类映射
    $product_categories = wp_get_post_terms($product->get_id(), 'product_cat');
    $category_mapping = null;
    
    foreach ($product_categories as $category) {
        $cat_id = $category->term_id;
        
        $mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT walmart_category_path, walmart_attributes FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
            $cat_id
        ));
        
        if ($mapping) {
            $category_mapping = [
                'walmart_category' => $mapping->walmart_category_path,
                'attributes' => json_decode($mapping->walmart_attributes, true) ?: []
            ];
            break;
        }
    }
    
    if (!$category_mapping) {
        echo "❌ 没有分类映射\n";
        $failed_products[] = $product_id;
        continue;
    }
    
    echo "Walmart分类: {$category_mapping['walmart_category']}\n";
    
    // 使用反射检查Mapper的内部状态
    $reflection = new ReflectionClass($mapper);
    $current_upc_prop = $reflection->getProperty('current_upc');
    $current_upc_prop->setAccessible(true);
    $current_product_type_prop = $reflection->getProperty('current_product_type_id');
    $current_product_type_prop->setAccessible(true);
    
    echo "调用前 - Mapper内部状态:\n";
    echo "  current_upc: " . ($current_upc_prop->getValue($mapper) ?: 'null') . "\n";
    echo "  current_product_type_id: " . ($current_product_type_prop->getValue($mapper) ?: 'null') . "\n";
    
    // 调用 map 方法
    try {
        $start_time = microtime(true);
        
        $item_data = $mapper->map(
            $product,
            $category_mapping['walmart_category'],
            $upc,
            $category_mapping['attributes'],
            get_option('woo_walmart_fulfillment_lag_time', 2)
        );
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        echo "调用后 - Mapper内部状态:\n";
        echo "  current_upc: " . ($current_upc_prop->getValue($mapper) ?: 'null') . "\n";
        echo "  current_product_type_id: " . ($current_product_type_prop->getValue($mapper) ?: 'null') . "\n";
        echo "  执行时间: {$execution_time}ms\n";
        
        if ($item_data && !empty($item_data['MPItem'])) {
            echo "✅ 映射成功\n";
            echo "  MPItem数量: " . count($item_data['MPItem']) . "\n";
            echo "  数据大小: " . strlen(json_encode($item_data)) . " 字节\n";
            
            // 检查返回的UPC是否正确
            $returned_upc = $item_data['MPItem'][0]['Orderable']['productIdentifiers']['productId'] ?? 'N/A';
            echo "  返回的UPC: {$returned_upc}\n";
            
            if ($returned_upc !== $upc) {
                echo "  ⚠️  UPC不匹配！期望: {$upc}, 实际: {$returned_upc}\n";
            }
            
            $items[] = $item_data['MPItem'][0];
        } else {
            echo "❌ 映射失败\n";
            if ($item_data) {
                echo "  返回数据: " . json_encode($item_data, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "  返回值为null\n";
            }
            $failed_products[] = $product_id;
        }
        
    } catch (Exception $e) {
        echo "❌ 映射异常: " . $e->getMessage() . "\n";
        echo "  文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed_products[] = $product_id;
    }
    
    echo "当前内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
    echo "---\n\n";
}

echo "=== 批量处理结果 ===\n";
echo "成功处理: " . count($items) . " 个产品\n";
echo "失败产品: " . count($failed_products) . " 个\n";

if (!empty($failed_products)) {
    echo "失败的产品ID: " . implode(', ', $failed_products) . "\n";
}

// 模拟最终的批量Feed数据构建
if (!empty($items)) {
    $feed_data = [
        'MPItemFeedHeader' => [
            'businessUnit' => get_option('woo_walmart_business_unit', 'WALMART_US'),
            'locale' => 'en',
            'version' => '5.0.20241118-04_39_24-api'
        ],
        'MPItem' => $items
    ];
    
    echo "\n=== 最终Feed数据 ===\n";
    echo "MPItem数量: " . count($feed_data['MPItem']) . "\n";
    echo "总数据大小: " . strlen(json_encode($feed_data)) . " 字节\n";
    
    // 检查是否会触发"没有有效的商品数据"错误
    if (empty($feed_data['MPItem'])) {
        echo "❌ 会触发'没有有效的商品数据可以同步'错误\n";
    } else {
        echo "✅ 不会触发错误，数据正常\n";
    }
} else {
    echo "\n❌ 没有成功处理任何产品，会触发'没有有效的商品数据可以同步'错误\n";
}

echo "\n=== 测试完成 ===\n";
echo "峰值内存使用: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
