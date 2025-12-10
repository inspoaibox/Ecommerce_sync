<?php
/**
 * 专门诊断 sofa_and_loveseat_design 字段在映射过程中的处理情况
 * 重点检查字段生成、空值检查、数据类型转换等关键环节
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== sofa_and_loveseat_design 字段处理诊断 ===\n";
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

// 获取失败的产品
$failed_sku = 'W714P357249';
$product_id = wc_get_product_id_by_sku($failed_sku);

if (!$product_id) {
    die("❌ 找不到SKU为 {$failed_sku} 的产品\n");
}

$product = wc_get_product($product_id);
echo "✅ 找到产品: {$product->get_name()} (ID: {$product_id})\n\n";

// 获取产品的分类映射
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

$product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
echo "产品分类ID: " . implode(', ', $product_categories) . "\n";

$mapping_found = false;
$attribute_rules = null;
$walmart_category_name = null;

foreach ($product_categories as $cat_id) {
    // 直接映射查询
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
    
    // 共享映射查询
    $shared_mappings = $wpdb->get_results(
        "SELECT * FROM $map_table WHERE local_category_ids IS NOT NULL AND local_category_ids != ''"
    );
    
    foreach ($shared_mappings as $mapping) {
        $local_ids = json_decode($mapping->local_category_ids, true) ?: [];
        if (in_array($cat_id, array_map('intval', $local_ids))) {
            $mapping_found = true;
            $attribute_rules = json_decode($mapping->walmart_attributes, true);
            $walmart_category_name = $mapping->walmart_category_path;
            echo "✅ 找到共享映射: {$walmart_category_name}\n";
            break 2;
        }
    }
}

if (!$mapping_found) {
    die("❌ 没有找到分类映射\n");
}

// 检查字段配置
$field_index = null;
$field_config = null;

if (is_array($attribute_rules) && isset($attribute_rules['name'])) {
    $field_index = array_search('sofa_and_loveseat_design', $attribute_rules['name']);
    if ($field_index !== false) {
        $field_config = [
            'name' => $attribute_rules['name'][$field_index],
            'type' => $attribute_rules['type'][$field_index] ?? 'N/A',
            'source' => $attribute_rules['source'][$field_index] ?? 'N/A',
            'format' => $attribute_rules['format'][$field_index] ?? 'N/A'
        ];
        echo "✅ 字段已配置在分类映射中\n";
        echo "配置详情: " . json_encode($field_config, JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        die("❌ 字段未配置在分类映射中\n");
    }
} else {
    die("❌ 分类映射数据格式异常\n");
}

// ============================================
// 核心诊断：模拟字段处理过程
// ============================================
echo "【核心诊断：模拟字段处理过程】\n";
echo str_repeat("-", 80) . "\n";

$mapper = new Woo_Walmart_Product_Mapper();

// 步骤1: 字段生成
echo "步骤1: 字段生成\n";
try {
    $reflection = new ReflectionClass($mapper);
    $generate_method = $reflection->getMethod('generate_special_attribute_value');
    $generate_method->setAccessible(true);
    
    $generated_value = $generate_method->invoke($mapper, 'sofa_and_loveseat_design', $product, 1);
    
    echo "生成结果: " . json_encode($generated_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "结果类型: " . gettype($generated_value) . "\n";
    echo "是否为null: " . (is_null($generated_value) ? 'YES' : 'NO') . "\n";
    
    if (is_array($generated_value)) {
        echo "数组长度: " . count($generated_value) . "\n";
        echo "是否为空数组: " . (empty($generated_value) ? 'YES' : 'NO') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 字段生成失败: " . $e->getMessage() . "\n";
    exit;
}

// 步骤2: 数据类型转换
echo "\n步骤2: 数据类型转换\n";
try {
    $convert_method = $reflection->getMethod('convert_field_data_type');
    $convert_method->setAccessible(true);
    
    $converted_value = $convert_method->invoke($mapper, 'sofa_and_loveseat_design', $generated_value, null);
    
    echo "转换前: " . json_encode($generated_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "转换后: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "转换后类型: " . gettype($converted_value) . "\n";
    echo "是否为null: " . (is_null($converted_value) ? 'YES' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "❌ 数据类型转换失败: " . $e->getMessage() . "\n";
    $converted_value = $generated_value; // 使用原值继续
}

// 步骤3: 空值检查
echo "\n步骤3: 空值检查\n";
try {
    $empty_check_method = $reflection->getMethod('is_empty_field_value');
    $empty_check_method->setAccessible(true);
    
    $is_null = is_null($converted_value);
    $is_empty = $empty_check_method->invoke($mapper, $converted_value);
    
    echo "值: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "is_null(): " . ($is_null ? 'YES' : 'NO') . "\n";
    echo "is_empty_field_value(): " . ($is_empty ? 'YES' : 'NO') . "\n";
    
    $should_include = !$is_null && !$is_empty;
    echo "应该包含在API中: " . ($should_include ? 'YES' : 'NO') . "\n";
    
    if (!$should_include) {
        echo "🚨 **这就是问题所在！字段被空值检查过滤掉了！**\n";
        
        // 详细分析为什么被认为是空值
        if ($is_null) {
            echo "原因: 字段值为null\n";
        } elseif ($is_empty) {
            echo "原因: 字段值被is_empty_field_value()判定为空\n";
            
            // 查看is_empty_field_value的具体逻辑
            echo "\n分析is_empty_field_value()逻辑:\n";
            if (is_array($converted_value)) {
                echo "- 值是数组\n";
                echo "- 数组长度: " . count($converted_value) . "\n";
                echo "- empty()结果: " . (empty($converted_value) ? 'true' : 'false') . "\n";
                
                if (!empty($converted_value)) {
                    echo "- 数组内容: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n";
                    foreach ($converted_value as $i => $item) {
                        echo "  [{$i}]: '{$item}' (长度: " . strlen($item) . ")\n";
                    }
                }
            } elseif (is_string($converted_value)) {
                echo "- 值是字符串\n";
                echo "- 字符串长度: " . strlen($converted_value) . "\n";
                echo "- trim()后长度: " . strlen(trim($converted_value)) . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ 空值检查失败: " . $e->getMessage() . "\n";
}

// 步骤4: 完整映射测试
echo "\n步骤4: 完整映射测试\n";
try {
    $full_mapping = $mapper->map($product, $walmart_category_name, '123456789012', $attribute_rules, 1);
    
    // 检查字段是否在最终结果中
    $visible_fields = $full_mapping['MPItem'][0]['Visible'][$walmart_category_name] ?? [];
    $orderable_fields = $full_mapping['MPItem'][0]['Orderable'] ?? [];
    
    if (isset($visible_fields['sofa_and_loveseat_design'])) {
        echo "✅ 字段出现在Visible中\n";
        echo "最终值: " . json_encode($visible_fields['sofa_and_loveseat_design'], JSON_UNESCAPED_UNICODE) . "\n";
    } elseif (isset($orderable_fields['sofa_and_loveseat_design'])) {
        echo "✅ 字段出现在Orderable中\n";
        echo "最终值: " . json_encode($orderable_fields['sofa_and_loveseat_design'], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ 字段未出现在最终API数据中\n";
        echo "🚨 **这确认了问题：字段在处理过程中被过滤掉了！**\n";
    }
    
    // 显示所有Visible字段用于对比
    echo "\n所有Visible字段:\n";
    foreach ($visible_fields as $field_name => $field_value) {
        $display_value = is_array($field_value) ? '[数组]' : (strlen($field_value) > 50 ? substr($field_value, 0, 50) . '...' : $field_value);
        echo "  - {$field_name}: {$display_value}\n";
    }
    
} catch (Exception $e) {
    echo "❌ 完整映射测试失败: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【诊断总结】\n";
echo str_repeat("=", 80) . "\n\n";

echo "基于以上详细诊断，问题的根本原因是：\n\n";

if (isset($should_include) && !$should_include) {
    echo "🎯 **字段被空值检查逻辑过滤掉了**\n";
    echo "   - 字段生成正常\n";
    echo "   - 数据类型转换正常\n";
    echo "   - 但在Line 526的空值检查中被过滤：\n";
    echo "     `if ( ! is_null( \$value ) && ! \$this->is_empty_field_value( \$value ) )`\n\n";
    
    echo "🔧 **需要检查的问题**：\n";
    echo "1. is_empty_field_value() 方法的逻辑是否正确\n";
    echo "2. sofa_and_loveseat_design 字段的默认值是否被正确处理\n";
    echo "3. 数组格式的字段是否被错误判定为空\n\n";
} else {
    echo "🎯 **字段处理正常，问题可能在其他环节**\n";
    echo "   - 需要检查API请求构建过程\n";
    echo "   - 需要检查数据序列化过程\n";
    echo "   - 需要检查网络传输过程\n\n";
}

echo "📝 **建议的解决步骤**：\n";
echo "1. 检查 is_empty_field_value() 方法的实现\n";
echo "2. 确认 sofa_and_loveseat_design 字段的默认值机制\n";
echo "3. 在远程服务器上运行此诊断脚本\n";
echo "4. 检查同步日志中的详细字段处理信息\n\n";

echo "=== 诊断完成 ===\n";
?>
