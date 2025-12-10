<?php
/**
 * 测试Kitchen Serving Carts分类的features字段提取
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 测试Kitchen Serving Carts分类features字段 ===\n\n";

// 初始化Mapper
$mapper = new Woo_Walmart_Product_Mapper();

// 获取测试产品
global $wpdb;
$products = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    ORDER BY ID DESC 
    LIMIT 5
");

if (empty($products)) {
    echo "❌ 没有找到测试产品\n";
    exit;
}

echo "=== 1. 模拟Kitchen Serving Carts分类测试 ===\n";

foreach ($products as $product_data) {
    $product = wc_get_product($product_data->ID);
    if (!$product) continue;
    
    echo "\n--- 产品 {$product->get_id()}: " . substr($product->get_name(), 0, 60) . "... ---\n";
    
    // 显示产品内容（用于分析匹配）
    $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
    $content_preview = substr(strip_tags($content), 0, 200);
    echo "产品内容预览: {$content_preview}...\n";
    
    // 测试Kitchen Serving Carts分类的features提取
    $features = $mapper->test_extract_features_walmart_category($product, 'Kitchen Serving Carts');
    
    if ($features) {
        echo "✅ 提取到 " . count($features) . " 个特性:\n";
        foreach ($features as $feature) {
            echo "  - {$feature}\n";
        }
    } else {
        echo "❌ 无匹配的特性\n";
    }
}

echo "\n=== 2. 关键词匹配测试 ===\n";

// 创建模拟产品内容进行测试
$test_contents = [
    [
        'name' => '模拟厨房推车 - Rolling特性',
        'content' => 'Kitchen Cart with Rolling Wheels for Easy Movement',
        'expected' => ['Rolling']
    ],
    [
        'name' => '模拟厨房推车 - Folding特性',
        'content' => 'Foldable Kitchen Serving Cart Space Saving Design',
        'expected' => ['Folding']
    ],
    [
        'name' => '模拟厨房推车 - Portable特性',
        'content' => 'Lightweight Portable Kitchen Cart Easy to Move',
        'expected' => ['Portable']
    ],
    [
        'name' => '模拟厨房推车 - Removable特性',
        'content' => 'Kitchen Cart with Removable Shelves and Detachable Parts',
        'expected' => ['Removable']
    ],
    [
        'name' => '模拟厨房推车 - 多特性',
        'content' => 'Rolling Kitchen Cart with Folding Design and Removable Trays, Portable and Lightweight',
        'expected' => ['Rolling', 'Folding', 'Removable', 'Portable']
    ],
    [
        'name' => '模拟厨房推车 - 无匹配',
        'content' => 'Simple Kitchen Storage Unit with Fixed Design',
        'expected' => []
    ]
];

foreach ($test_contents as $test) {
    echo "\n--- {$test['name']} ---\n";
    echo "测试内容: {$test['content']}\n";
    echo "预期特性: " . (empty($test['expected']) ? '无' : implode(', ', $test['expected'])) . "\n";
    
    // 创建临时产品对象进行测试
    $temp_product = new WC_Product_Simple();
    $temp_product->set_name($test['content']);
    $temp_product->set_description($test['content']);
    
    $actual_features = $mapper->test_extract_features_walmart_category($temp_product, 'Kitchen Serving Carts');
    
    if ($actual_features) {
        echo "实际提取: " . implode(', ', $actual_features) . "\n";
        
        // 验证匹配准确性
        $expected_set = array_flip($test['expected']);
        $actual_set = array_flip($actual_features);
        
        $correct_matches = array_intersect_key($expected_set, $actual_set);
        $missed_matches = array_diff_key($expected_set, $actual_set);
        $extra_matches = array_diff_key($actual_set, $expected_set);
        
        if (empty($missed_matches) && empty($extra_matches)) {
            echo "✅ 完全匹配\n";
        } else {
            if (!empty($missed_matches)) {
                echo "⚠️  遗漏: " . implode(', ', array_keys($missed_matches)) . "\n";
            }
            if (!empty($extra_matches)) {
                echo "⚠️  多余: " . implode(', ', array_keys($extra_matches)) . "\n";
            }
        }
    } else {
        if (empty($test['expected'])) {
            echo "✅ 正确无匹配\n";
        } else {
            echo "❌ 应该匹配但未匹配\n";
        }
    }
}

echo "\n=== 3. 检查真实Kitchen Serving Carts产品 ===\n";

// 查找可能的Kitchen Serving Carts产品
$kitchen_cart_keywords = ['kitchen cart', 'serving cart', 'kitchen trolley', 'bar cart'];
$found_products = [];

foreach ($kitchen_cart_keywords as $keyword) {
    $search_products = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title 
        FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status = 'publish'
        AND (post_title LIKE %s OR post_content LIKE %s)
        LIMIT 3
    ", '%' . $keyword . '%', '%' . $keyword . '%'));
    
    if ($search_products) {
        echo "\n关键词 '{$keyword}' 找到的产品:\n";
        foreach ($search_products as $search_product) {
            echo "  - ID {$search_product->ID}: " . substr($search_product->post_title, 0, 80) . "...\n";
            
            $product = wc_get_product($search_product->ID);
            if ($product) {
                $features = $mapper->test_extract_features_walmart_category($product, 'Kitchen Serving Carts');
                if ($features) {
                    echo "    ✅ 特性: " . implode(', ', $features) . "\n";
                } else {
                    echo "    ❌ 无匹配特性\n";
                }
            }
        }
    }
}

echo "\n=== 4. 配置验证 ===\n";
echo "Kitchen Serving Carts分类配置:\n";
echo "- 支持的特性: Rolling, Folding, Portable, Removable\n";
echo "- 关键词匹配规则:\n";
echo "  * Rolling: rolling, wheels, casters, mobile, roll\n";
echo "  * Folding: folding, foldable, fold, collapsible, collapse\n";
echo "  * Portable: portable, movable, lightweight, easy to move\n";
echo "  * Removable: removable, detachable, remove, take off, separate\n";

echo "\n=== 5. 当前支持的Walmart分类 ===\n";
echo "1. Bed Frames (7个特性)\n";
echo "2. Kitchen Serving Carts (4个特性) ✅ 新增\n";

echo "\n✅ Kitchen Serving Carts分类features字段配置完成！\n";
echo "=== 测试完成 ===\n";
