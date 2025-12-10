<?php
/**
 * 完整检查产品分类映射关系
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 完整检查产品分类映射关系 ===\n\n";

$target_sku = 'B081S00179';

// 获取产品
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $target_sku
));

echo "产品ID: {$product_id}\n";
echo "SKU: {$target_sku}\n\n";

// 1. 获取产品的所有分类（包括父分类）
$categories = wp_get_post_terms($product_id, 'product_cat');
echo "1. 产品分类详情:\n";
foreach ($categories as $cat) {
    echo "  分类ID: {$cat->term_id}\n";
    echo "  分类名: {$cat->name}\n";
    echo "  分类别名: {$cat->slug}\n";
    
    // 获取父分类
    $parent_id = $cat->parent;
    if ($parent_id) {
        $parent = get_term($parent_id, 'product_cat');
        echo "  父分类: {$parent->name} (ID: {$parent_id})\n";
    }
    echo "  ---\n";
}

// 2. 检查所有可能的映射表名
echo "\n2. 检查数据库中所有可能的映射表:\n";

$all_tables = $wpdb->get_results("SHOW TABLES");
$mapping_tables = [];

foreach ($all_tables as $table) {
    $table_name = array_values((array)$table)[0];
    if (strpos($table_name, 'walmart') !== false && 
        (strpos($table_name, 'category') !== false || strpos($table_name, 'mapping') !== false)) {
        $mapping_tables[] = $table_name;
        echo "  找到映射表: {$table_name}\n";
    }
}

if (empty($mapping_tables)) {
    echo "  ❌ 没有找到任何Walmart映射表\n";
}

// 3. 检查每个映射表的内容
foreach ($mapping_tables as $table) {
    echo "\n3. 检查表 {$table}:\n";
    
    // 显示表结构
    $columns = $wpdb->get_results("DESCRIBE {$table}");
    echo "  表结构: ";
    foreach ($columns as $col) {
        echo $col->Field . "({$col->Type}) ";
    }
    echo "\n";
    
    // 检查表中的记录数
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    echo "  记录总数: {$count}\n";
    
    if ($count > 0) {
        // 查找当前产品分类的映射
        foreach ($categories as $cat) {
            $mappings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE wc_category_id = %d",
                $cat->term_id
            ));
            
            if (!empty($mappings)) {
                echo "  ✅ 找到分类 {$cat->name} ({$cat->term_id}) 的映射:\n";
                foreach ($mappings as $mapping) {
                    foreach ($mapping as $key => $value) {
                        echo "    {$key}: {$value}\n";
                    }
                    echo "    ---\n";
                }
            } else {
                echo "  ❌ 分类 {$cat->name} ({$cat->term_id}) 没有映射\n";
            }
        }
        
        // 显示表中的前5条记录作为样本
        echo "  表中前5条记录样本:\n";
        $samples = $wpdb->get_results("SELECT * FROM {$table} LIMIT 5");
        foreach ($samples as $sample) {
            echo "    ";
            foreach ($sample as $key => $value) {
                echo "{$key}:{$value} ";
            }
            echo "\n";
        }
    }
}

// 4. 检查产品的所有meta数据
echo "\n4. 检查产品的所有相关meta数据:\n";

$all_meta = get_post_meta($product_id);
foreach ($all_meta as $key => $values) {
    if (strpos($key, 'walmart') !== false || strpos($key, 'category') !== false) {
        echo "  {$key}: " . implode(', ', $values) . "\n";
    }
}

// 5. 检查同步历史记录
echo "\n5. 检查同步历史记录:\n";

$sync_records = $wpdb->get_results($wpdb->prepare(
    "SELECT action, level, message, details, created_at FROM {$wpdb->prefix}woo_walmart_sync_logs 
     WHERE product_id = %d 
     AND (message LIKE '%分类%' OR message LIKE '%category%' OR details LIKE '%walmart_category%')
     ORDER BY created_at DESC 
     LIMIT 10",
    $product_id
));

if (!empty($sync_records)) {
    foreach ($sync_records as $record) {
        echo "  [{$record->created_at}] {$record->action}: {$record->message}\n";
        
        if (!empty($record->details)) {
            $details = json_decode($record->details, true);
            if ($details && isset($details['walmart_category'])) {
                echo "    使用的Walmart分类: {$details['walmart_category']}\n";
            }
        }
    }
} else {
    echo "  没有找到分类相关的同步记录\n";
}

// 6. 尝试调用实际的分类获取逻辑
echo "\n6. 尝试调用实际的分类获取逻辑:\n";

try {
    // 检查是否有分类获取函数
    if (function_exists('get_walmart_category_for_product')) {
        $walmart_category = get_walmart_category_for_product($product_id);
        echo "  get_walmart_category_for_product(): {$walmart_category}\n";
    }
    
    // 检查产品映射器的分类获取
    require_once 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    
    $reflection = new ReflectionClass($mapper);
    $methods = $reflection->getMethods();
    
    foreach ($methods as $method) {
        if (strpos($method->getName(), 'category') !== false) {
            echo "  映射器中的分类相关方法: {$method->getName()}\n";
        }
    }
    
} catch (Exception $e) {
    echo "  检查分类获取逻辑时出错: {$e->getMessage()}\n";
}

echo "\n=== 完整检查结果 ===\n";
echo "现在我们有了完整的分类映射信息，可以准确判断问题所在\n";

?>
