<?php
/**
 * 检查实际问题：为什么 sofa_and_loveseat_design 字段没有被传递
 * 模拟完整的映射流程，找出字段在哪一步被过滤掉
 */

// 自动检测 WordPress 根目录
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("错误：无法找到 WordPress。\n");
}

echo "=== 检查 sofa_and_loveseat_design 字段实际问题 ===\n\n";

if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

global $wpdb;

// ============================================
// 步骤1: 获取分类映射配置
// ============================================
echo "【步骤1: 获取分类映射配置】\n";
echo str_repeat("-", 80) . "\n";

// 支持命令行参数指定分类映射 ID
$mapping_id = isset($argv[1]) ? intval($argv[1]) : null;

if ($mapping_id) {
    echo "使用指定的分类映射 ID: {$mapping_id}\n";
    $mapping = $wpdb->get_row($wpdb->prepare("
        SELECT *
        FROM {$wpdb->prefix}walmart_category_map
        WHERE id = %d
    ", $mapping_id));
} else {
    echo "自动查找沙发相关的分类映射...\n";
    $mapping = $wpdb->get_row("
        SELECT *
        FROM {$wpdb->prefix}walmart_category_map
        WHERE walmart_category_path LIKE '%Sofa%' OR walmart_category_path LIKE '%Couch%'
        LIMIT 1
    ");
}

if (!$mapping) {
    echo "❌ 找不到沙发相关的分类映射\n";
    echo "使用方法: php diagnose-actual-problem.php [分类映射ID]\n";
    echo "例如: php diagnose-actual-problem.php 144\n\n";

    // 显示所有可用的分类映射
    $all_mappings = $wpdb->get_results("
        SELECT id, wc_category_name, walmart_category_path
        FROM {$wpdb->prefix}walmart_category_map
        LIMIT 10
    ");

    if (!empty($all_mappings)) {
        echo "可用的分类映射（前10个）:\n";
        foreach ($all_mappings as $m) {
            echo "  ID {$m->id}: {$m->wc_category_name} → {$m->walmart_category_path}\n";
        }
        echo "\n";
    }

    exit;
}

echo "分类 ID: {$mapping->id}\n";
echo "本地分类 ID: " . ($mapping->wc_category_id ?? $mapping->local_category_id ?? '(未知)') . "\n";
echo "Walmart分类: {$mapping->walmart_category_path}\n\n";

$attributes = json_decode($mapping->walmart_attributes, true);

if (!is_array($attributes)) {
    echo "❌ walmart_attributes 不是有效的 JSON\n\n";
    exit;
}

// 查找 sofa_and_loveseat_design 字段配置
$field_config = null;
foreach ($attributes as $attr) {
    if (isset($attr['name']) && $attr['name'] === 'sofa_and_loveseat_design') {
        $field_config = $attr;
        break;
    }
}

if (!$field_config) {
    echo "❌ 字段未在分类映射中配置\n";
    echo "这就是问题所在！需要在分类映射页面重置属性。\n\n";
    exit;
}

echo "✅ 找到字段配置:\n";
echo "  名称: {$field_config['name']}\n";
echo "  类型: {$field_config['type']}\n";
echo "  来源: " . ($field_config['source'] ?? '(空)') . "\n\n";

if ($field_config['type'] !== 'auto_generate') {
    echo "⚠️ 警告：字段类型不是 'auto_generate'，而是 '{$field_config['type']}'\n";
    echo "这可能导致字段不会被自动生成！\n\n";
}

// ============================================
// 步骤2: 获取测试产品
// ============================================
echo "【步骤2: 获取测试产品】\n";
echo str_repeat("-", 80) . "\n";

// 查找使用此分类的产品
$category_id = $mapping->wc_category_id ?? $mapping->local_category_id ?? null;

if ($category_id) {
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT object_id
        FROM {$wpdb->prefix}term_relationships
        WHERE term_taxonomy_id = %d
        LIMIT 1
    ", $category_id));
} else {
    $product_id = null;
}

if (!$product_id) {
    echo "⚠️ 没有找到使用此分类的产品，创建测试产品\n";
    $product = new WC_Product_Simple();
    $product->set_name('Test Sofa');
    $product->set_description('Modern comfortable sofa');
    $product->set_sku('TEST-SOFA-001');
    echo "创建测试产品: {$product->get_name()}\n\n";
} else {
    $product = wc_get_product($product_id);
    echo "使用产品 ID: {$product_id}\n";
    echo "产品名称: {$product->get_name()}\n";
    echo "产品 SKU: {$product->get_sku()}\n\n";
}

// ============================================
// 步骤3: 测试字段生成
// ============================================
echo "【步骤3: 测试字段生成】\n";
echo str_repeat("-", 80) . "\n";

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// 调用 generate_special_attribute_value
$method_generate = $reflection->getMethod('generate_special_attribute_value');
$method_generate->setAccessible(true);

echo "调用 generate_special_attribute_value('sofa_and_loveseat_design', product, 1)\n";

try {
    $generated_value = $method_generate->invoke($mapper, 'sofa_and_loveseat_design', $product, 1);
    echo "返回值: " . json_encode($generated_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "类型: " . gettype($generated_value) . "\n";
    
    if (is_null($generated_value)) {
        echo "❌ 返回值为 null！\n";
        echo "   这会导致字段被过滤掉（Line 526 的条件）\n\n";
    } elseif (is_array($generated_value) && empty($generated_value)) {
        echo "❌ 返回值为空数组！\n";
        echo "   这会被 is_empty_field_value() 判断为空（Line 2079）\n\n";
    } else {
        echo "✅ 返回值正常\n\n";
    }
} catch (Exception $e) {
    echo "❌ 调用失败: {$e->getMessage()}\n\n";
    $generated_value = null;
}

// ============================================
// 步骤4: 测试类型转换
// ============================================
echo "【步骤4: 测试类型转换】\n";
echo str_repeat("-", 80) . "\n";

$method_convert = $reflection->getMethod('convert_field_data_type');
$method_convert->setAccessible(true);

echo "调用 convert_field_data_type('sofa_and_loveseat_design', value, null)\n";
echo "输入值: " . json_encode($generated_value, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $converted_value = $method_convert->invoke($mapper, 'sofa_and_loveseat_design', $generated_value, null);
    echo "输出值: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "类型: " . gettype($converted_value) . "\n";
    
    if (is_null($converted_value)) {
        echo "❌ 转换后为 null！\n";
        echo "   这会导致字段被过滤掉（Line 526 的条件）\n\n";
    } elseif (is_array($converted_value) && empty($converted_value)) {
        echo "❌ 转换后为空数组！\n";
        echo "   这会被 is_empty_field_value() 判断为空（Line 2079）\n\n";
    } else {
        echo "✅ 转换后正常\n\n";
    }
} catch (Exception $e) {
    echo "❌ 调用失败: {$e->getMessage()}\n\n";
    $converted_value = null;
}

// ============================================
// 步骤5: 测试空值检查
// ============================================
echo "【步骤5: 测试空值检查】\n";
echo str_repeat("-", 80) . "\n";

$method_is_empty = $reflection->getMethod('is_empty_field_value');
$method_is_empty->setAccessible(true);

echo "调用 is_empty_field_value(value)\n";
echo "输入值: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $is_empty = $method_is_empty->invoke($mapper, $converted_value);
    echo "结果: " . ($is_empty ? 'true (空值)' : 'false (非空)') . "\n";
    
    if ($is_empty) {
        echo "❌ 被判断为空值！\n";
        echo "   字段会在 Line 526 被过滤掉，不会添加到映射数据中\n\n";
    } else {
        echo "✅ 被判断为非空值\n";
        echo "   字段会通过 Line 526 的检查\n\n";
    }
} catch (Exception $e) {
    echo "❌ 调用失败: {$e->getMessage()}\n\n";
    $is_empty = true;
}

// ============================================
// 步骤6: 测试 Line 526 的完整条件
// ============================================
echo "【步骤6: 测试 Line 526 的完整条件】\n";
echo str_repeat("-", 80) . "\n";

echo "Line 526 的条件: if ( ! is_null( \$value ) && ! \$this->is_empty_field_value( \$value ) )\n\n";

$condition1 = !is_null($converted_value);
$condition2 = !$is_empty;

echo "条件1: ! is_null(\$value) = " . ($condition1 ? 'true' : 'false') . "\n";
echo "条件2: ! is_empty_field_value(\$value) = " . ($condition2 ? 'true' : 'false') . "\n";
echo "最终结果: " . ($condition1 && $condition2 ? 'true (字段会被添加)' : 'false (字段会被过滤)') . "\n\n";

if (!($condition1 && $condition2)) {
    echo "❌ 字段会被过滤掉！\n";
    echo "   这就是为什么字段没有被传递到 API 的原因\n\n";
    
    if (!$condition1) {
        echo "原因：值为 null\n";
    }
    if (!$condition2) {
        echo "原因：值被判断为空\n";
    }
} else {
    echo "✅ 字段会通过检查，被添加到映射数据中\n\n";
}

// ============================================
// 步骤7: 测试完整映射流程
// ============================================
echo "【步骤7: 测试完整映射流程】\n";
echo str_repeat("-", 80) . "\n";

echo "调用 map_product_to_walmart_format(product, 1)\n";

$method_map = $reflection->getMethod('map_product_to_walmart_format');
$method_map->setAccessible(true);

try {
    $walmart_data = $method_map->invoke($mapper, $product, 1);
    
    // 检查字段是否存在
    $walmart_category = $mapping->walmart_category_path;
    
    if (isset($walmart_data['MPItem'][0]['Visible'][$walmart_category]['sofa_and_loveseat_design'])) {
        $final_value = $walmart_data['MPItem'][0]['Visible'][$walmart_category]['sofa_and_loveseat_design'];
        echo "✅ 字段存在于最终映射数据中\n";
        echo "路径: MPItem[0]['Visible']['{$walmart_category}']['sofa_and_loveseat_design']\n";
        echo "值: " . json_encode($final_value, JSON_UNESCAPED_UNICODE) . "\n\n";
        
        echo "🎉 字段映射成功！如果同步还是失败，问题可能在其他地方。\n\n";
    } else {
        echo "❌ 字段不存在于最终映射数据中\n";
        echo "路径: MPItem[0]['Visible']['{$walmart_category}']['sofa_and_loveseat_design']\n\n";
        
        echo "检查 Visible 部分的所有字段:\n";
        if (isset($walmart_data['MPItem'][0]['Visible'][$walmart_category])) {
            $visible_fields = array_keys($walmart_data['MPItem'][0]['Visible'][$walmart_category]);
            echo "总共 " . count($visible_fields) . " 个字段:\n";
            foreach (array_slice($visible_fields, 0, 20) as $field) {
                echo "  - {$field}\n";
            }
            if (count($visible_fields) > 20) {
                echo "  ... 还有 " . (count($visible_fields) - 20) . " 个字段\n";
            }
            echo "\n";
            
            if (!in_array('sofa_and_loveseat_design', $visible_fields)) {
                echo "❌ sofa_and_loveseat_design 不在字段列表中\n";
                echo "   这就是问题所在！\n\n";
            }
        } else {
            echo "❌ Visible['{$walmart_category}'] 部分不存在\n\n";
        }
    }
} catch (Exception $e) {
    echo "❌ 映射失败: {$e->getMessage()}\n";
    echo "堆栈跟踪:\n{$e->getTraceAsString()}\n\n";
}

// ============================================
// 总结
// ============================================
echo str_repeat("=", 80) . "\n";
echo "【问题诊断总结】\n";
echo str_repeat("=", 80) . "\n\n";

if (!$field_config) {
    echo "🔴 **根本原因：字段未在分类映射中配置**\n\n";
    echo "解决方案：\n";
    echo "1. 在分类映射页面点击「重置属性」\n";
    echo "2. 确认 sofa_and_loveseat_design 出现在字段列表中\n";
    echo "3. 确认类型为「自动生成」\n";
    echo "4. 保存配置\n\n";
} elseif ($field_config['type'] !== 'auto_generate') {
    echo "🔴 **根本原因：字段类型配置错误**\n\n";
    echo "当前类型: {$field_config['type']}\n";
    echo "应该是: auto_generate\n\n";
    echo "解决方案：\n";
    echo "1. 在分类映射页面找到 sofa_and_loveseat_design 字段\n";
    echo "2. 将类型改为「自动生成」\n";
    echo "3. 保存配置\n\n";
} elseif (isset($is_empty) && $is_empty) {
    echo "🔴 **根本原因：字段值被判断为空**\n\n";
    echo "生成的值: " . json_encode($generated_value, JSON_UNESCAPED_UNICODE) . "\n";
    echo "转换后的值: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n\n";
    echo "可能的原因：\n";
    echo "1. generate_special_attribute_value 返回了 null\n";
    echo "2. convert_field_data_type 返回了 null 或空数组\n";
    echo "3. 代码逻辑有问题\n\n";
    echo "建议：\n";
    echo "1. 检查 extract_sofa_loveseat_design 方法的实现\n";
    echo "2. 检查 convert_field_data_type 中的 sofa_and_loveseat_design case\n";
    echo "3. 确认默认值逻辑是否正确\n\n";
} elseif (isset($walmart_data) && !isset($walmart_data['MPItem'][0]['Visible'][$walmart_category]['sofa_and_loveseat_design'])) {
    echo "🔴 **根本原因：字段在映射过程中被过滤或丢失**\n\n";
    echo "可能的原因：\n";
    echo "1. 产品的分类没有正确关联到分类映射\n";
    echo "2. 映射逻辑中有其他过滤条件\n";
    echo "3. 字段名称大小写不匹配\n\n";
    echo "建议：\n";
    echo "1. 检查产品是否属于正确的分类\n";
    echo "2. 查看同步日志中的详细信息\n";
    echo "3. 检查 map_product_to_walmart_format 方法的完整逻辑\n\n";
} else {
    echo "✅ **字段映射正常**\n\n";
    echo "如果同步还是失败，可能的原因：\n";
    echo "1. API 请求发送时字段被过滤\n";
    echo "2. 其他必填字段缺失导致整个请求失败\n";
    echo "3. 网络或 API 问题\n\n";
    echo "建议：\n";
    echo "1. 查看完整的同步日志\n";
    echo "2. 检查 API 响应中的详细错误信息\n";
    echo "3. 确认其他必填字段都已配置\n\n";
}

echo "诊断完成！\n";
?>

