<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试共享映射查询功能 ===\n\n";

global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

// 1. 查看所有映射记录
echo "1. 查看所有映射记录:\n";
$all_mappings = $wpdb->get_results("SELECT * FROM $map_table ORDER BY id");

foreach ($all_mappings as $mapping) {
    echo "映射ID: {$mapping->id}\n";
    echo "  WC分类ID: {$mapping->wc_category_id}\n";
    echo "  WC分类名: {$mapping->wc_category_name}\n";
    echo "  Walmart分类: {$mapping->walmart_category_path}\n";
    
    if (isset($mapping->local_category_ids) && !empty($mapping->local_category_ids)) {
        $local_ids = json_decode($mapping->local_category_ids, true);
        echo "  共享分类IDs: " . implode(', ', $local_ids) . "\n";
    } else {
        echo "  共享分类IDs: 无（使用旧格式）\n";
    }
    echo "---\n";
}

// 2. 检查Shoe Racks映射的详细信息
echo "\n2. 检查Shoe Racks映射详情:\n";
$shoe_racks_mapping = $wpdb->get_row("SELECT * FROM $map_table WHERE walmart_category_path = 'Shoe Racks'");
if ($shoe_racks_mapping) {
    echo "Shoe Racks映射详情:\n";
    echo "  映射ID: {$shoe_racks_mapping->id}\n";
    echo "  主分类ID: {$shoe_racks_mapping->wc_category_id}\n";
    echo "  分类名: {$shoe_racks_mapping->wc_category_name}\n";

    if (!empty($shoe_racks_mapping->local_category_ids)) {
        $local_ids = json_decode($shoe_racks_mapping->local_category_ids, true);
        echo "  共享分类IDs: " . implode(', ', $local_ids) . "\n";

        // 检查每个分类的详情
        foreach ($local_ids as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                echo "    - ID {$cat_id}: {$term->name} (商品数: {$term->count})\n";
            } else {
                echo "    - ID {$cat_id}: 分类不存在或错误\n";
            }
        }
    } else {
        echo "  共享分类IDs: 无\n";
    }
} else {
    echo "未找到Shoe Racks映射\n";
}

// 3. 测试特定分类的查询
echo "\n3. 测试分类查询:\n";
$test_category_ids = [100, 101]; // 测试Luggage & Luggage Sets的共享映射

foreach ($test_category_ids as $cat_id) {
    echo "测试分类ID: {$cat_id}\n";
    
    // 方法1: 直接查询（旧格式）
    $direct_result = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, wc_category_name, walmart_attributes, local_category_ids FROM $map_table WHERE wc_category_id = %d", 
        $cat_id
    ));
    
    if ($direct_result) {
        echo "  直接查询: 找到映射 -> {$direct_result->walmart_category_path}\n";
    } else {
        echo "  直接查询: 未找到\n";
        
        // 方法2: 共享映射查询（新格式）
        $shared_result = $wpdb->get_row($wpdb->prepare(
            "SELECT walmart_category_path, wc_category_name, walmart_attributes, local_category_ids 
             FROM $map_table 
             WHERE local_category_ids IS NOT NULL 
             AND JSON_CONTAINS(local_category_ids, %s)", 
            json_encode(strval($cat_id))
        ));
        
        if ($shared_result) {
            echo "  共享查询: 找到映射 -> {$shared_result->walmart_category_path}\n";
            $local_ids = json_decode($shared_result->local_category_ids, true);
            echo "  共享分类: " . implode(', ', $local_ids) . "\n";
        } else {
            echo "  共享查询: 未找到\n";
        }
    }
    echo "\n";
}

// 4. 测试具体商品的分类映射
echo "4. 测试具体商品的分类映射:\n";

// 获取分类100和101下的商品
$products_cat_100 = get_posts([
    'post_type' => 'product',
    'posts_per_page' => 2,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => 100
        ]
    ]
]);

$products_cat_101 = get_posts([
    'post_type' => 'product',
    'posts_per_page' => 2,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => 101
        ]
    ]
]);

echo "分类100下的商品: " . count($products_cat_100) . "个\n";
echo "分类101下的商品: " . count($products_cat_101) . "个\n";

$test_products = [];
if (!empty($products_cat_100)) {
    $test_products[] = ['id' => $products_cat_100[0]->ID, 'cat' => 100];
}
if (!empty($products_cat_101)) {
    $test_products[] = ['id' => $products_cat_101[0]->ID, 'cat' => 101];
}

foreach ($test_products as $test_info) {
    $product = wc_get_product($test_info['id']);
    if ($product) {
        echo "\n--- 测试商品 (分类{$test_info['cat']}) ---\n";
        echo "商品ID: {$test_info['id']}\n";
        echo "商品名: {$product->get_name()}\n";
        echo "SKU: {$product->get_sku()}\n";

        $product_cat_ids = wp_get_post_terms($test_info['id'], 'product_cat', ['fields' => 'ids']);
        echo "商品分类IDs: " . implode(', ', $product_cat_ids) . "\n";

        if (!empty($product_cat_ids)) {
            $main_cat_id = $product_cat_ids[0];
            echo "主分类ID: {$main_cat_id}\n";

            // 使用修复后的查询逻辑
            $mapped_category_data = $wpdb->get_row($wpdb->prepare(
                "SELECT walmart_category_path, wc_category_name, walmart_attributes, local_category_ids FROM $map_table WHERE wc_category_id = %d",
                $main_cat_id
            ));

            if (!$mapped_category_data) {
                echo "直接查询未找到，尝试共享映射查询...\n";
                $mapped_category_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT walmart_category_path, wc_category_name, walmart_attributes, local_category_ids
                     FROM $map_table
                     WHERE local_category_ids IS NOT NULL
                     AND JSON_CONTAINS(local_category_ids, %s)",
                    json_encode(strval($main_cat_id))
                ));
            }

            if ($mapped_category_data) {
                echo "✅ 找到映射: {$mapped_category_data->walmart_category_path}\n";

                // 解析属性规则
                $attribute_rules = json_decode($mapped_category_data->walmart_attributes, true);
                if ($attribute_rules && isset($attribute_rules['name'])) {
                    echo "配置的属性数量: " . count($attribute_rules['name']) . "\n";
                    echo "前3个属性:\n";
                    for ($i = 0; $i < min(3, count($attribute_rules['name'])); $i++) {
                        $name = $attribute_rules['name'][$i] ?? '';
                        $type = $attribute_rules['type'][$i] ?? '';
                        $source = $attribute_rules['source'][$i] ?? '';
                        echo "  - {$name} ({$type}): {$source}\n";
                    }
                } else {
                    echo "❌ 属性规则解析失败或为空\n";
                }
            } else {
                echo "❌ 未找到映射\n";
            }
        }
    }
}

// 5. 调试JSON_CONTAINS查询
echo "\n5. 调试JSON_CONTAINS查询:\n";
$luggage_mapping = $wpdb->get_row("SELECT * FROM $map_table WHERE walmart_category_path = 'Luggage & Luggage Sets'");
if ($luggage_mapping) {
    echo "Luggage映射的local_category_ids: {$luggage_mapping->local_category_ids}\n";
    $local_ids = json_decode($luggage_mapping->local_category_ids, true);
    echo "解析后的数组: " . print_r($local_ids, true) . "\n";

    // 测试不同的JSON_CONTAINS查询
    $test_id = 101;
    echo "测试查询分类ID: {$test_id}\n";

    // 方法1: 字符串格式
    $query1 = $wpdb->prepare(
        "SELECT id FROM $map_table WHERE JSON_CONTAINS(local_category_ids, %s)",
        json_encode(strval($test_id))
    );
    echo "查询1 (字符串): {$query1}\n";
    $result1 = $wpdb->get_var($query1);
    echo "结果1: " . ($result1 ? $result1 : '无') . "\n";

    // 方法2: 数字格式
    $query2 = $wpdb->prepare(
        "SELECT id FROM $map_table WHERE JSON_CONTAINS(local_category_ids, %s)",
        json_encode($test_id)
    );
    echo "查询2 (数字): {$query2}\n";
    $result2 = $wpdb->get_var($query2);
    echo "结果2: " . ($result2 ? $result2 : '无') . "\n";

    // 方法3: LIKE查询作为备用
    $query3 = $wpdb->prepare(
        "SELECT id FROM $map_table WHERE local_category_ids LIKE %s",
        '%"' . $test_id . '"%'
    );
    echo "查询3 (LIKE): {$query3}\n";
    $result3 = $wpdb->get_var($query3);
    echo "结果3: " . ($result3 ? $result3 : '无') . "\n";
}

echo "\n=== 测试完成 ===\n";
?>
