<?php
/**
 * 诊断字段处理逻辑是否受到之前修改的影响
 * 检查多个字段的生成情况
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 字段处理影响诊断 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
if (!defined('ABSPATH')) {
    $wp_paths = [
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../../../wp-load.php',
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            echo "✅ WordPress加载成功: {$path}\n";
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("❌ 错误：无法找到WordPress。请手动修改路径。\n");
    }
}

// 加载必要的类
require_once 'includes/class-product-mapper.php';

// 测试失败的SKU
$test_sku = 'W18B9X011F8';  // 从错误日志中选择一个
$product_id = wc_get_product_id_by_sku($test_sku);

if (!$product_id) {
    die("❌ 找不到测试产品 SKU: {$test_sku}\n");
}

$product = wc_get_product($product_id);
echo "✅ 测试产品: {$product->get_name()} (ID: {$product_id})\n\n";

// 获取产品的分类映射
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

$product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
echo "产品分类ID: " . implode(', ', $product_categories) . "\n";

$mapping_found = false;
$attribute_rules = null;
$walmart_category_name = null;

foreach ($product_categories as $cat_id) {
    $direct_mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $map_table WHERE wc_category_id = %d", 
        $cat_id
    ));
    
    if ($direct_mapping) {
        $mapping_found = true;
        $attribute_rules = json_decode($direct_mapping->walmart_attributes, true);
        $walmart_category_name = $direct_mapping->walmart_category_path;
        echo "✅ 找到分类映射: {$walmart_category_name}\n";
        break;
    }
}

if (!$mapping_found) {
    die("❌ 没有找到分类映射\n");
}

// ============================================
// 测试多个字段的生成情况
// ============================================
echo "\n【字段生成测试】\n";
echo str_repeat("-", 80) . "\n";

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);
$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

// 测试字段列表（包括常见字段和我们修改的字段）
$test_fields = [
    'sofa_and_loveseat_design',  // 我们修改的字段
    'sofa_bed_size',             // 我们修改的字段
    'brand',                     // 常见字段
    'productName',               // 常见字段
    'mainImageUrl',              // 报错的字段
    'shortDescription',          // 常见字段
    'color',                     // 常见字段
    'material',                  // 常见字段
    'assembledProductHeight',    // 常见字段
    'assembledProductWidth',     // 常见字段
];

$results = [];

foreach ($test_fields as $field_name) {
    echo "\n测试字段: {$field_name}\n";
    echo str_repeat("-", 40) . "\n";
    
    try {
        // 测试字段生成
        $start_time = microtime(true);
        $result = $generate_method->invoke($mapper, $field_name, $product, 1);
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        $results[$field_name] = [
            'success' => true,
            'result' => $result,
            'type' => gettype($result),
            'execution_time' => $execution_time,
            'error' => null
        ];
        
        echo "✅ 生成成功\n";
        echo "结果类型: " . gettype($result) . "\n";
        echo "执行时间: {$execution_time}ms\n";
        
        if (is_null($result)) {
            echo "⚠️ 返回值为null\n";
        } elseif (is_array($result)) {
            echo "数组长度: " . count($result) . "\n";
            if (!empty($result)) {
                echo "示例值: " . json_encode(array_slice($result, 0, 2), JSON_UNESCAPED_UNICODE) . "\n";
            }
        } elseif (is_string($result)) {
            $display_result = strlen($result) > 50 ? substr($result, 0, 50) . '...' : $result;
            echo "字符串值: {$display_result}\n";
        } else {
            echo "值: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        }
        
    } catch (Exception $e) {
        $results[$field_name] = [
            'success' => false,
            'result' => null,
            'type' => 'error',
            'execution_time' => 0,
            'error' => $e->getMessage()
        ];
        
        echo "❌ 生成失败\n";
        echo "错误信息: " . $e->getMessage() . "\n";
        echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

// ============================================
// 完整映射测试
// ============================================
echo "\n\n【完整映射测试】\n";
echo str_repeat("-", 80) . "\n";

try {
    echo "执行完整产品映射...\n";
    $full_mapping = $mapper->map($product, $walmart_category_name, '123456789012', $attribute_rules, 1);
    
    if ($full_mapping && isset($full_mapping['MPItem'][0]['Visible'][$walmart_category_name])) {
        $visible_fields = $full_mapping['MPItem'][0]['Visible'][$walmart_category_name];
        $orderable_fields = $full_mapping['MPItem'][0]['Orderable'] ?? [];
        
        echo "✅ 完整映射成功\n";
        echo "Visible字段数量: " . count($visible_fields) . "\n";
        echo "Orderable字段数量: " . count($orderable_fields) . "\n\n";
        
        // 检查测试字段是否出现在最终结果中
        echo "字段出现情况:\n";
        foreach ($test_fields as $field_name) {
            $in_visible = isset($visible_fields[$field_name]);
            $in_orderable = isset($orderable_fields[$field_name]);
            
            if ($in_visible) {
                echo "✅ {$field_name}: 出现在Visible中\n";
            } elseif ($in_orderable) {
                echo "✅ {$field_name}: 出现在Orderable中\n";
            } else {
                echo "❌ {$field_name}: 未出现在最终结果中\n";
                
                // 检查是否是因为生成失败
                if (isset($results[$field_name]) && !$results[$field_name]['success']) {
                    echo "   原因: 字段生成失败\n";
                } elseif (isset($results[$field_name]) && is_null($results[$field_name]['result'])) {
                    echo "   原因: 字段生成返回null\n";
                } else {
                    echo "   原因: 可能被空值检查过滤\n";
                }
            }
        }
        
    } else {
        echo "❌ 完整映射失败或结果格式异常\n";
    }
    
} catch (Exception $e) {
    echo "❌ 完整映射异常: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// ============================================
// 总结分析
// ============================================
echo "\n\n【总结分析】\n";
echo str_repeat("-", 80) . "\n";

$failed_fields = [];
$null_fields = [];
$success_fields = [];

foreach ($results as $field_name => $result) {
    if (!$result['success']) {
        $failed_fields[] = $field_name;
    } elseif (is_null($result['result'])) {
        $null_fields[] = $field_name;
    } else {
        $success_fields[] = $field_name;
    }
}

echo "字段生成统计:\n";
echo "✅ 成功生成: " . count($success_fields) . " 个字段\n";
if (!empty($success_fields)) {
    echo "   " . implode(', ', $success_fields) . "\n";
}

echo "⚠️ 返回null: " . count($null_fields) . " 个字段\n";
if (!empty($null_fields)) {
    echo "   " . implode(', ', $null_fields) . "\n";
}

echo "❌ 生成失败: " . count($failed_fields) . " 个字段\n";
if (!empty($failed_fields)) {
    echo "   " . implode(', ', $failed_fields) . "\n";
}

echo "\n🔍 **问题分析**:\n";
if (!empty($failed_fields) || !empty($null_fields)) {
    echo "发现字段处理问题，可能的原因:\n";
    echo "1. switch语句结构被破坏\n";
    echo "2. case分支匹配逻辑有问题\n";
    echo "3. 字段生成方法内部异常\n";
    echo "4. 属性名转换逻辑影响了其他字段\n";
} else {
    echo "所有测试字段都能正常生成，问题可能在:\n";
    echo "1. 空值检查逻辑过于严格\n";
    echo "2. 数据类型转换有问题\n";
    echo "3. API数据构建过程中的过滤\n";
}

echo "\n=== 诊断完成 ===\n";
?>
