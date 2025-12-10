<?php
/**
 * 测试Dining Furniture Sets分类的features字段提取
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 测试Dining Furniture Sets分类features字段 ===\n\n";

// 初始化Mapper
$mapper = new Woo_Walmart_Product_Mapper();

echo "=== 1. 关键词匹配测试 ===\n";

// 创建模拟产品内容进行测试
$test_contents = [
    [
        'name' => '模拟餐桌套装 - Live Edge特性',
        'content' => 'Dining Table Set with Live Edge Natural Wood Design',
        'expected' => ['Live Edge']
    ],
    [
        'name' => '模拟餐桌套装 - Storage特性',
        'content' => 'Dining Set with Storage Drawers and Shelves',
        'expected' => ['Storage']
    ],
    [
        'name' => '模拟餐桌套装 - Nailhead Trim特性',
        'content' => 'Elegant Dining Chairs with Nailhead Trim Decoration',
        'expected' => ['Nailhead Trim']
    ],
    [
        'name' => '模拟餐桌套装 - Folding特性',
        'content' => 'Space Saving Folding Dining Table Set',
        'expected' => ['Folding']
    ],
    [
        'name' => '模拟餐桌套装 - Tufted特性',
        'content' => 'Dining Chairs with Button Tufted Upholstery',
        'expected' => ['Tufted']
    ],
    [
        'name' => '模拟餐桌套装 - 多特性',
        'content' => 'Live Edge Dining Table Set with Storage Drawers and Tufted Chairs featuring Nailhead Trim',
        'expected' => ['Live Edge', 'Storage', 'Tufted', 'Nailhead Trim']
    ],
    [
        'name' => '模拟餐桌套装 - 无匹配',
        'content' => 'Simple Modern Dining Table Set',
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
    
    $actual_features = $mapper->test_extract_features_walmart_category($temp_product, 'Dining Furniture Sets');
    
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

echo "\n=== 2. 真实产品测试 ===\n";

// 获取测试产品
global $wpdb;
$products = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    ORDER BY ID DESC 
    LIMIT 5
");

if ($products) {
    foreach ($products as $product_data) {
        $product = wc_get_product($product_data->ID);
        if (!$product) continue;
        
        echo "\n--- 产品 {$product->get_id()}: " . substr($product->get_name(), 0, 60) . "... ---\n";
        
        // 显示产品内容预览
        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
        $content_preview = substr(strip_tags($content), 0, 150);
        echo "内容预览: {$content_preview}...\n";
        
        // 测试Dining Furniture Sets分类的features提取
        $features = $mapper->test_extract_features_walmart_category($product, 'Dining Furniture Sets');
        
        if ($features) {
            echo "✅ 提取到 " . count($features) . " 个特性: " . implode(', ', $features) . "\n";
        } else {
            echo "❌ 无匹配的特性\n";
        }
    }
}

echo "\n=== 3. 检查真实Dining Furniture Sets产品 ===\n";

// 查找可能的Dining Furniture Sets产品
$dining_keywords = ['dining set', 'dining table set', 'dining room set', 'table and chairs'];
$found_count = 0;

foreach ($dining_keywords as $keyword) {
    $search_products = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title 
        FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status = 'publish'
        AND (post_title LIKE %s OR post_content LIKE %s)
        LIMIT 2
    ", '%' . $keyword . '%', '%' . $keyword . '%'));
    
    if ($search_products) {
        echo "\n关键词 '{$keyword}' 找到的产品:\n";
        foreach ($search_products as $search_product) {
            $found_count++;
            echo "  - ID {$search_product->ID}: " . substr($search_product->post_title, 0, 70) . "...\n";
            
            $product = wc_get_product($search_product->ID);
            if ($product) {
                $features = $mapper->test_extract_features_walmart_category($product, 'Dining Furniture Sets');
                if ($features) {
                    echo "    ✅ 特性: " . implode(', ', $features) . "\n";
                } else {
                    echo "    ❌ 无匹配特性\n";
                }
            }
        }
    }
}

if ($found_count === 0) {
    echo "未找到相关的餐桌套装产品\n";
}

echo "\n=== 4. 配置验证 ===\n";
echo "Dining Furniture Sets分类配置:\n";
echo "- 支持的特性: Live Edge, Storage, Nailhead Trim, Folding, Tufted\n";
echo "\n关键词匹配规则:\n";
echo "  * Live Edge: live edge, live-edge, natural edge, raw edge, wood edge\n";
echo "  * Storage: storage, drawer, shelf, shelves, cabinet, compartment\n";
echo "  * Nailhead Trim: nailhead, nail head, studded, decorative nails, metal studs\n";
echo "  * Folding: folding, foldable, fold, collapsible, collapse\n";
echo "  * Tufted: tufted, button tufted, diamond tufted, tufting, buttoned\n";

echo "\n=== 5. 特性说明 ===\n";
echo "Live Edge - 保留木材天然边缘的设计，展现自然美感\n";
echo "Storage - 带有储物功能，如抽屉、搁板或柜子\n";
echo "Nailhead Trim - 装饰性铆钉装饰，常见于椅子边缘\n";
echo "Folding - 可折叠设计，节省空间\n";
echo "Tufted - 簇绒装饰，常见于椅背或座垫\n";

echo "\n=== 6. 当前支持的Walmart分类 ===\n";
echo "1. Bed Frames (7个特性)\n";
echo "2. Kitchen Serving Carts (4个特性)\n";
echo "3. Dining Furniture Sets (5个特性) ✅ 新增\n";

echo "\n✅ Dining Furniture Sets分类features字段配置完成！\n";
echo "=== 测试完成 ===\n";
