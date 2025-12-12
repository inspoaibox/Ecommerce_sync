<?php
/**
 * 调试批量Feed构建器
 * 深入分析为什么返回"没有有效的商品数据可以同步"
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 批量Feed构建器调试 ===\n\n";

// 1. 获取测试产品
global $wpdb;
$test_product_ids = $wpdb->get_col("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    LIMIT 5
");

if (empty($test_product_ids)) {
    echo "❌ 没有找到测试产品\n";
    exit;
}

echo "找到测试产品ID: " . implode(', ', $test_product_ids) . "\n\n";

// 2. 逐步调试批量Feed构建过程
echo "2. 逐步调试批量Feed构建过程:\n";

try {
    $batch_builder = new Walmart_Batch_Feed_Builder();
    echo "  ✅ Walmart_Batch_Feed_Builder 实例创建成功\n";
    
    // 测试单个产品的处理过程
    foreach ($test_product_ids as $index => $product_id) {
        echo "\n  --- 测试产品 {$product_id} ---\n";
        
        $product = wc_get_product($product_id);
        if (!$product) {
            echo "    ❌ 无法加载产品\n";
            continue;
        }
        
        echo "    ✅ 产品加载成功: " . $product->get_name() . "\n";
        echo "    SKU: " . ($product->get_sku() ?: '无') . "\n";
        echo "    价格: " . $product->get_price() . "\n";
        echo "    状态: " . $product->get_status() . "\n";
        
        // 测试产品验证
        $reflection = new ReflectionClass($batch_builder);
        $validate_method = $reflection->getMethod('validate_product');
        $validate_method->setAccessible(true);
        
        $is_valid = $validate_method->invoke($batch_builder, $product);
        echo "    产品验证: " . ($is_valid ? '✅ 通过' : '❌ 失败') . "\n";
        
        if (!$is_valid) {
            echo "    验证失败原因:\n";
            if (!$product->get_sku()) echo "      - 缺少SKU\n";
            if ($product->get_price() <= 0) echo "      - 价格无效 ({$product->get_price()})\n";
            if (!$product->get_name()) echo "      - 缺少产品名称\n";
            if ($product->get_status() !== 'publish') echo "      - 状态不是publish ({$product->get_status()})\n";
            continue;
        }
        
        // 测试UPC分配
        $assign_upc_method = $reflection->getMethod('assign_upc_for_product');
        $assign_upc_method->setAccessible(true);
        
        $upc = $assign_upc_method->invoke($batch_builder, $product_id);
        echo "    UPC分配: " . ($upc ? "✅ 成功 ({$upc})" : '❌ 失败') . "\n";
        
        if (!$upc) {
            // 检查UPC池状态
            $available_upc_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}walmart_upc_pool WHERE is_used = 0");
            echo "      可用UPC数量: {$available_upc_count}\n";
            
            $existing_upc = $wpdb->get_var($wpdb->prepare(
                "SELECT upc_code FROM {$wpdb->prefix}walmart_upc_pool WHERE product_id = %d AND is_used = 1",
                $product_id
            ));
            echo "      已有UPC: " . ($existing_upc ?: '无') . "\n";
            continue;
        }
        
        // 测试分类映射
        $get_category_method = $reflection->getMethod('get_category_mapping');
        $get_category_method->setAccessible(true);
        
        $category_mapping = $get_category_method->invoke($batch_builder, $product);
        echo "    分类映射: " . ($category_mapping ? '✅ 成功' : '❌ 失败') . "\n";
        
        if (!$category_mapping) {
            $product_categories = wp_get_post_terms($product->get_id(), 'product_cat');
            echo "      产品分类: " . (empty($product_categories) ? '无' : implode(', ', wp_list_pluck($product_categories, 'name'))) . "\n";
            
            $mapped_categories = $wpdb->get_results("SELECT wc_category_id, walmart_category_path FROM {$wpdb->prefix}walmart_category_map");
            echo "      已映射分类数量: " . count($mapped_categories) . "\n";
            continue;
        } else {
            echo "      Walmart分类: " . $category_mapping['walmart_category'] . "\n";
            echo "      属性数量: " . count($category_mapping['attributes']) . "\n";
        }
        
        // 测试产品映射
        echo "    测试产品映射...\n";
        $mapper = new Woo_Walmart_Product_Mapper();
        
        $item_data = $mapper->map(
            $product,
            $category_mapping['walmart_category'],
            $upc,
            $category_mapping['attributes'],
            get_option('woo_walmart_fulfillment_lag_time', 2)
        );
        
        echo "    产品映射: " . ($item_data && !empty($item_data['MPItem']) ? '✅ 成功' : '❌ 失败') . "\n";
        
        if ($item_data && !empty($item_data['MPItem'])) {
            echo "      映射数据大小: " . strlen(json_encode($item_data)) . " 字节\n";
            echo "      MPItem数量: " . count($item_data['MPItem']) . "\n";
        }
        
        // 只测试前3个产品
        if ($index >= 2) break;
    }
    
} catch (Exception $e) {
    echo "❌ 异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// 3. 测试完整的批量Feed构建
echo "\n3. 测试完整的批量Feed构建:\n";

try {
    $batch_builder = new Walmart_Batch_Feed_Builder();
    $reflection = new ReflectionClass($batch_builder);
    $build_method = $reflection->getMethod('build_batch_feed_data');
    $build_method->setAccessible(true);
    
    $feed_data = $build_method->invoke($batch_builder, array_slice($test_product_ids, 0, 3));
    
    if ($feed_data) {
        echo "  ✅ Feed数据构建成功\n";
        echo "  Header字段: " . (isset($feed_data['MPItemFeedHeader']) ? '✅ 存在' : '❌ 缺失') . "\n";
        echo "  MPItem数量: " . (isset($feed_data['MPItem']) ? count($feed_data['MPItem']) : 0) . "\n";
        
        if (isset($feed_data['MPItemFeedHeader'])) {
            echo "  Header内容: " . json_encode($feed_data['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        if (empty($feed_data['MPItem'])) {
            echo "  ⚠️ MPItem为空，这就是返回'没有有效商品数据'的原因\n";
        }
    } else {
        echo "  ❌ Feed数据构建失败\n";
    }
    
} catch (Exception $e) {
    echo "  ❌ Feed构建异常: " . $e->getMessage() . "\n";
}

// 4. 检查系统配置
echo "\n4. 检查系统配置:\n";
echo "  fulfillment_lag_time: " . get_option('woo_walmart_fulfillment_lag_time', 2) . "\n";
echo "  business_unit: " . get_option('woo_walmart_business_unit', 'WALMART_US') . "\n";

// 5. 检查UPC池状态
echo "\n5. 检查UPC池状态:\n";
$upc_stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_used = 0 THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used
    FROM {$wpdb->prefix}walmart_upc_pool
");

if ($upc_stats) {
    echo "  总UPC数量: {$upc_stats->total}\n";
    echo "  可用UPC: {$upc_stats->available}\n";
    echo "  已用UPC: {$upc_stats->used}\n";
} else {
    echo "  ❌ 无法获取UPC统计\n";
}

echo "\n=== 调试完成 ===\n";
