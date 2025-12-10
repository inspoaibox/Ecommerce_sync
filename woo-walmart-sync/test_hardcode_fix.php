<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试硬编码修复 ===\n\n";

// 获取分类101下的商品进行测试
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

// 1. 测试修复后的分类映射逻辑
echo "=== 1. 测试修复后的分类映射逻辑 ===\n";

// 直接实现分类映射逻辑
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

$product_categories = wp_get_post_terms($product_id, 'product_cat');
$category_mapping = null;

foreach ($product_categories as $category) {
    $cat_id = $category->term_id;
    echo "检查分类ID: {$cat_id} ({$category->name})\n";

    // 首先尝试直接查询（兼容旧格式）
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, walmart_attributes FROM $map_table WHERE wc_category_id = %d",
        $cat_id
    ));

    if ($mapping) {
        echo "  ✅ 直接查询找到映射: {$mapping->walmart_category_path}\n";
        $category_mapping = [
            'walmart_category' => $mapping->walmart_category_path,
            'attributes' => json_decode($mapping->walmart_attributes, true) ?: []
        ];
        break;
    }

    // 如果没有找到，查询共享映射（新格式）
    $shared_mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, walmart_attributes
         FROM $map_table
         WHERE local_category_ids IS NOT NULL
         AND JSON_CONTAINS(local_category_ids, %s)",
        json_encode($cat_id)
    ));

    if ($shared_mapping) {
        echo "  ✅ 共享查询找到映射: {$shared_mapping->walmart_category_path}\n";
        $category_mapping = [
            'walmart_category' => $shared_mapping->walmart_category_path,
            'attributes' => json_decode($shared_mapping->walmart_attributes, true) ?: []
        ];
        break;
    }

    echo "  ❌ 未找到映射\n";
}

if ($category_mapping) {
    echo "\n✅ 最终找到分类映射:\n";
    echo "  Walmart分类: {$category_mapping['walmart_category']}\n";
    echo "  属性数量: " . count($category_mapping['attributes']) . "\n";

    if ($category_mapping['walmart_category'] === 'home_other') {
        echo "  ⚠️  仍然返回硬编码的home_other\n";
    } else {
        echo "  ✅ 返回正确的映射分类\n";
    }
} else {
    echo "\n❌ 没有找到分类映射（应该返回null，不再使用home_other）\n";
}

// 2. 测试完整的同步流程
echo "\n=== 2. 测试完整的同步流程 ===\n";

// 清理之前的日志
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$wpdb->query($wpdb->prepare(
    "DELETE FROM $logs_table WHERE product_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
    $product_id
));

// 加载同步类
require_once 'includes/class-product-sync.php';
$sync_service = new Woo_Walmart_Product_Sync();

echo "开始同步...\n";

try {
    $result = $sync_service->initiate_sync($product_id);
    
    if ($result['success']) {
        echo "✅ 同步成功: {$result['message']}\n";
    } else {
        echo "❌ 同步失败: {$result['message']}\n";
    }
    
    // 检查最近的日志
    echo "\n--- 最近的同步日志 ---\n";
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table WHERE product_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY created_at DESC LIMIT 5",
        $product_id
    ));
    
    foreach ($logs as $log) {
        echo "[{$log->created_at}] {$log->action}\n";
        if (!empty($log->request) && strlen($log->request) < 300) {
            $request_data = json_decode($log->request, true);
            if ($request_data) {
                // 检查是否包含home_other
                $request_str = json_encode($request_data, JSON_UNESCAPED_UNICODE);
                if (strpos($request_str, 'home_other') !== false) {
                    echo "  ⚠️  发现home_other: " . $request_str . "\n";
                } else {
                    echo "  ✅ 没有home_other\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ 同步异常: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
?>
