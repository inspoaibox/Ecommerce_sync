<?php
/**
 * 测试基于Walmart分类名称的features字段提取
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 测试Walmart分类名称features字段提取 ===\n\n";

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

echo "找到 " . count($products) . " 个测试产品\n\n";

// 初始化Mapper
$mapper = new Woo_Walmart_Product_Mapper();

// 测试每个产品
foreach ($products as $product_data) {
    $product = wc_get_product($product_data->ID);
    if (!$product) continue;
    
    echo "=== 产品 {$product->get_id()}: " . substr($product->get_name(), 0, 50) . "... ===\n";
    
    // 1. 检查产品的本地分类
    $local_categories = wp_get_post_terms($product->get_id(), 'product_cat');
    echo "本地分类:\n";
    if ($local_categories) {
        foreach ($local_categories as $cat) {
            echo "  - {$cat->name} (ID: {$cat->term_id})\n";
        }
    } else {
        echo "  无本地分类\n";
    }
    
    // 2. 检查Walmart分类映射
    echo "\nWalmart分类映射:\n";
    $reflection = new ReflectionClass($mapper);
    $method = $reflection->getMethod('get_product_walmart_categories');
    $method->setAccessible(true);
    
    $walmart_categories = $method->invoke($mapper, $product);
    if ($walmart_categories) {
        foreach ($walmart_categories as $walmart_cat) {
            echo "  - {$walmart_cat}\n";
        }
    } else {
        echo "  无Walmart分类映射\n";
    }
    
    // 3. 测试真实的features提取
    echo "\n真实features提取:\n";
    $real_features = $mapper->generate_special_attribute_value($product, 'features');
    if ($real_features) {
        echo "  ✅ 提取到 " . count($real_features) . " 个特性:\n";
        foreach ($real_features as $feature) {
            echo "    - {$feature}\n";
        }
    } else {
        echo "  ❌ 无匹配的特性\n";
    }
    
    // 4. 测试模拟Bed Frames分类
    echo "\n模拟Bed Frames分类测试:\n";
    $simulated_features = $mapper->test_extract_features_bed_frames($product);
    if ($simulated_features) {
        echo "  ✅ 模拟提取到 " . count($simulated_features) . " 个特性:\n";
        foreach ($simulated_features as $feature) {
            echo "    - {$feature}\n";
        }
    } else {
        echo "  ❌ 模拟测试无匹配\n";
    }
    
    echo "  ---\n\n";
}

// 测试分类映射表数据
echo "=== 检查分类映射表数据 ===\n";
$mapping_data = $wpdb->get_results("
    SELECT local_category_id, walmart_category_path, COUNT(*) as count
    FROM {$wpdb->prefix}walmart_category_map 
    GROUP BY local_category_id, walmart_category_path
    ORDER BY count DESC
    LIMIT 10
");

if ($mapping_data) {
    echo "前10个分类映射:\n";
    foreach ($mapping_data as $mapping) {
        $local_cat = get_term($mapping->local_category_id, 'product_cat');
        $local_name = $local_cat ? $local_cat->name : '未知';
        
        // 提取Walmart分类名称
        $path_parts = explode(' > ', $mapping->walmart_category_path);
        $walmart_category = trim(end($path_parts));
        
        echo "  本地: {$local_name} (ID: {$mapping->local_category_id}) -> Walmart: {$walmart_category}\n";
        echo "    完整路径: {$mapping->walmart_category_path}\n";
        echo "    产品数量: {$mapping->count}\n";
        echo "    ---\n";
    }
} else {
    echo "❌ 没有找到分类映射数据\n";
}

// 测试特定的Walmart分类
echo "\n=== 测试特定Walmart分类配置 ===\n";
$test_categories = ['Bed Frames', 'Accent Cabinets', 'Dining Tables'];

foreach ($test_categories as $test_category) {
    echo "测试分类: {$test_category}\n";
    
    // 检查是否有产品映射到这个分类
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT local_category_id) 
        FROM {$wpdb->prefix}walmart_category_map 
        WHERE walmart_category_path LIKE %s
    ", '%' . $test_category));
    
    echo "  映射的本地分类数量: {$count}\n";
    
    if ($count > 0) {
        // 获取一个测试产品
        $test_product_id = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->prefix}walmart_category_map wcm ON tr.term_taxonomy_id = wcm.local_category_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND wcm.walmart_category_path LIKE %s
            LIMIT 1
        ", '%' . $test_category));
        
        if ($test_product_id) {
            $test_product = wc_get_product($test_product_id);
            echo "  测试产品: {$test_product->get_name()}\n";
            
            $test_features = $mapper->test_extract_features_walmart_category($test_product, $test_category);
            if ($test_features) {
                echo "  ✅ 提取特性: " . implode(', ', $test_features) . "\n";
            } else {
                echo "  ❌ 无匹配特性\n";
            }
        }
    }
    echo "  ---\n";
}

echo "\n=== 配置验证 ===\n";
echo "当前支持的Walmart分类配置:\n";
echo "- Bed Frames: 7个特性选项\n";
echo "  * Adjustable Height, Wireless Remote, Heavy Duty\n";
echo "  * Center Supports, USB Port, Headboard Compatible, Massaging\n";

echo "\n✅ 修改完成的优势:\n";
echo "1. 跨网站兼容性 - 使用Walmart分类名称而非本地分类ID\n";
echo "2. 统一标准 - 基于Walmart官方分类体系\n";
echo "3. 易于维护 - 分类配置与具体网站无关\n";
echo "4. 扩展性强 - 可以轻松添加新的Walmart分类配置\n";

echo "\n=== 测试完成 ===\n";
