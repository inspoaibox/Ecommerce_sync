<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 完整流程调试 ===\n\n";

// 测试分类101下的商品
$products_cat_101 = get_posts([
    'post_type' => 'product', 
    'posts_per_page' => 1,
    'tax_query' => [
        [
            'taxonomy' => 'product_cat',
            'field' => 'term_id', 
            'terms' => 101
        ]
    ]
]);

if (empty($products_cat_101)) {
    echo "❌ 没有找到分类101下的商品\n";
    exit;
}

$product_id = $products_cat_101[0]->ID;
$product = wc_get_product($product_id);

echo "测试商品: {$product->get_name()}\n";
echo "商品ID: {$product_id}\n";
echo "SKU: {$product->get_sku()}\n";

$product_cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
echo "商品分类IDs: " . implode(', ', $product_cat_ids) . "\n\n";

// 1. 模拟分类映射查找过程
echo "=== 1. 分类映射查找过程 ===\n";
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

$mapped_category_data = null;
foreach ($product_cat_ids as $cat_id) {
    echo "检查分类ID: {$cat_id}\n";
    
    // 直接查询
    $direct_result = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, wc_category_name, walmart_attributes, local_category_ids FROM $map_table WHERE wc_category_id = %d", 
        $cat_id
    ));
    
    if ($direct_result) {
        echo "  ✅ 直接查询找到: {$direct_result->walmart_category_path}\n";
        $mapped_category_data = $direct_result;
        break;
    }
    
    // 共享映射查询（数字格式）
    $shared_result = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, wc_category_name, walmart_attributes, local_category_ids 
         FROM $map_table 
         WHERE local_category_ids IS NOT NULL 
         AND JSON_CONTAINS(local_category_ids, %s)", 
        json_encode($cat_id)
    ));
    
    if ($shared_result) {
        echo "  ✅ 共享查询找到: {$shared_result->walmart_category_path}\n";
        $mapped_category_data = $shared_result;
        break;
    }
    
    echo "  ❌ 未找到映射\n";
}

if (!$mapped_category_data) {
    echo "❌ 所有分类都没有找到映射\n";
    exit;
}

// 2. 分类名称查找过程
echo "\n=== 2. 分类名称查找过程 ===\n";
$walmart_category_id = $mapped_category_data->walmart_category_path;
echo "映射的分类ID: {$walmart_category_id}\n";

$walmart_category_name = '';
$walmart_categories_list = get_transient('walmart_api_categories');

if (!empty($walmart_categories_list)) {
    echo "缓存中有分类列表\n";
    foreach($walmart_categories_list as $cat) {
        if ($cat['categoryId'] === $walmart_category_id) {
            $walmart_category_name = $cat['categoryName'];
            break;
        }
    }
} else {
    echo "缓存中没有分类列表\n";
}

if (empty($walmart_category_name)) {
    $walmart_category_name = $walmart_category_id;
    echo "使用ID作为分类名称: {$walmart_category_name}\n";
} else {
    echo "找到分类名称: {$walmart_category_name}\n";
}

// 3. 属性规则解析
echo "\n=== 3. 属性规则解析 ===\n";
$attribute_rules = !empty($mapped_category_data->walmart_attributes) ? json_decode($mapped_category_data->walmart_attributes, true) : null;

if (is_array($attribute_rules) && isset($attribute_rules['name'])) {
    echo "✅ 属性规则解析成功，共 " . count($attribute_rules['name']) . " 个属性\n";
    echo "前3个属性:\n";
    for ($i = 0; $i < min(3, count($attribute_rules['name'])); $i++) {
        $name = $attribute_rules['name'][$i] ?? '';
        $type = $attribute_rules['type'][$i] ?? '';
        $source = $attribute_rules['source'][$i] ?? '';
        echo "  - {$name} ({$type}): {$source}\n";
    }
} else {
    echo "❌ 属性规则解析失败\n";
    $attribute_rules = ['name' => [], 'type' => [], 'source' => []];
}

// 4. 模拟产品映射器调用
echo "\n=== 4. 产品映射器调用 ===\n";
echo "传入映射器的参数:\n";
echo "  - walmart_category_name: {$walmart_category_name}\n";
echo "  - attribute_rules count: " . count($attribute_rules['name']) . "\n";

// 加载映射器
require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 模拟UPC
$upc = '763436530000';
$fulfillment_lag_time = 1;

echo "\n开始映射...\n";
$mapped_data = $mapper->map($product, $walmart_category_name, $upc, $attribute_rules, $fulfillment_lag_time);

echo "映射完成！\n";

// 检查映射器返回值
echo "映射器返回值类型: " . gettype($mapped_data) . "\n";
if (is_array($mapped_data)) {
    echo "返回数组的键: " . implode(', ', array_keys($mapped_data)) . "\n";
} else {
    echo "返回值: " . print_r($mapped_data, true) . "\n";
}

// 5. 检查最终数据结构
echo "\n=== 5. 最终数据结构检查 ===\n";

// 检查MPItem结构
if (isset($mapped_data['MPItem']) && is_array($mapped_data['MPItem']) && !empty($mapped_data['MPItem'])) {
    $first_item = $mapped_data['MPItem'][0];
    echo "MPItem结构的键: " . implode(', ', array_keys($first_item)) . "\n";

    if (isset($first_item['Visible'])) {
        $visible_keys = array_keys($first_item['Visible']);
        echo "Visible部分的键: " . implode(', ', $visible_keys) . "\n";

        foreach ($visible_keys as $key) {
            echo "分类键 '{$key}' 下的字段数量: " . count($first_item['Visible'][$key]) . "\n";

            // 显示前5个字段
            $fields = array_keys($first_item['Visible'][$key]);
            echo "前5个字段: " . implode(', ', array_slice($fields, 0, 5)) . "\n";

            // 检查是否包含错误的属性
            $error_attributes = ['Skateboard', 'Baby', 'Power', 'Microphone', 'Eyeglass'];
            $suspicious_fields = [];
            foreach ($first_item['Visible'][$key] as $field_name => $field_value) {
                foreach ($error_attributes as $error_attr) {
                    if (stripos($field_name, $error_attr) !== false) {
                        $suspicious_fields[] = $field_name;
                    }
                }
            }

            if (!empty($suspicious_fields)) {
                echo "  ⚠️  发现可疑字段: " . implode(', ', $suspicious_fields) . "\n";
            } else {
                echo "  ✅ 没有发现可疑字段\n";
            }
        }
    } else {
        echo "❌ MPItem中没有Visible部分\n";
    }
} else {
    echo "❌ 没有MPItem结构\n";
}

echo "\n=== 调试完成 ===\n";
?>
