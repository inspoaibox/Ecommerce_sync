<?php
/**
 * 检查实际同步数据中的assembledProductHeight字段
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际同步数据中的assembledProductHeight字段 ===\n\n";

$test_product_id = 25926; // W1191S00043
$product = wc_get_product($test_product_id);

if (!$product) {
    echo "❌ 测试产品不存在\n";
    exit;
}

echo "产品: {$product->get_name()}\n";
echo "SKU: {$product->get_sku()}\n\n";

// 1. 获取分类映射
echo "1. 获取分类映射:\n";
$categories = $product->get_category_ids();
echo "产品分类ID: " . implode(', ', $categories) . "\n";

global $wpdb;
$mapping = null;
$walmart_category = null;
$attribute_rules = null;

foreach ($categories as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
        $cat_id
    ));
    
    if ($mapping) {
        $walmart_category = $mapping->walmart_category_path;
        $attribute_rules = !empty($mapping->walmart_attributes) ? 
            json_decode($mapping->walmart_attributes, true) : [];
        break;
    }
}

if (!$mapping) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

echo "Walmart分类: {$walmart_category}\n";
echo "属性规则数量: " . count($attribute_rules) . "\n";

// 2. 检查assembledProductHeight在分类映射中的配置
echo "\n2. 检查assembledProductHeight在分类映射中的配置:\n";

if (isset($attribute_rules['name'])) {
    $height_index = array_search('assembledProductHeight', $attribute_rules['name']);
    
    if ($height_index !== false) {
        echo "✅ 找到assembledProductHeight字段配置:\n";
        echo "  索引: {$height_index}\n";
        echo "  类型: " . ($attribute_rules['type'][$height_index] ?? '未设置') . "\n";
        echo "  来源: " . ($attribute_rules['source'][$height_index] ?? '未设置') . "\n";
        echo "  格式: " . ($attribute_rules['format'][$height_index] ?? '未设置') . "\n";
    } else {
        echo "❌ 没有找到assembledProductHeight字段配置\n";
    }
} else {
    echo "❌ 分类映射中没有name配置\n";
}

// 3. 执行完整的产品映射
echo "\n3. 执行完整的产品映射:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 获取UPC
$upc = get_post_meta($product->get_id(), '_walmart_upc', true);
if (empty($upc)) {
    $upc = '123456789012'; // 测试UPC
}

$fulfillment_lag_time = 1;

echo "映射参数:\n";
echo "  产品ID: {$product->get_id()}\n";
echo "  Walmart分类: {$walmart_category}\n";
echo "  UPC: {$upc}\n";
echo "  备货时间: {$fulfillment_lag_time}\n";

$walmart_data = $mapper->map($product, $walmart_category, $upc, $attribute_rules, $fulfillment_lag_time);

// 4. 检查映射结果中的assembledProductHeight
echo "\n4. 检查映射结果中的assembledProductHeight:\n";

if ($walmart_data && isset($walmart_data['MPItem'][0]['Visible'][$walmart_category])) {
    $visible_data = $walmart_data['MPItem'][0]['Visible'][$walmart_category];
    
    if (isset($visible_data['assembledProductHeight'])) {
        $height_data = $visible_data['assembledProductHeight'];
        echo "✅ 找到assembledProductHeight字段:\n";
        echo "数据类型: " . gettype($height_data) . "\n";
        
        if (is_array($height_data)) {
            echo "数组内容:\n";
            foreach ($height_data as $key => $value) {
                echo "  {$key}: {$value}\n";
            }
            
            // 检查是否符合API要求
            if (isset($height_data['measure']) && isset($height_data['unit'])) {
                echo "✅ 包含measure和unit字段\n";
                echo "✅ 数据格式符合API要求\n";
            } else {
                echo "❌ 缺少measure或unit字段\n";
            }
        } else {
            echo "❌ 不是数组格式: {$height_data}\n";
        }
    } else {
        echo "❌ 映射结果中没有assembledProductHeight字段\n";
        
        // 显示所有可用字段
        echo "可用字段列表:\n";
        $field_count = 0;
        foreach ($visible_data as $field => $value) {
            if ($field_count < 10) { // 只显示前10个字段
                $value_preview = is_array($value) ? '[数组]' : (strlen($value) > 30 ? substr($value, 0, 30) . '...' : $value);
                echo "  {$field}: {$value_preview}\n";
                $field_count++;
            }
        }
        if (count($visible_data) > 10) {
            echo "  ... 还有 " . (count($visible_data) - 10) . " 个字段\n";
        }
    }
} else {
    echo "❌ 映射失败或没有Visible数据\n";
    
    if ($walmart_data) {
        echo "映射数据结构:\n";
        echo "  MPItem存在: " . (isset($walmart_data['MPItem']) ? '是' : '否') . "\n";
        if (isset($walmart_data['MPItem'][0])) {
            echo "  第一个Item存在: 是\n";
            echo "  Visible存在: " . (isset($walmart_data['MPItem'][0]['Visible']) ? '是' : '否') . "\n";
            if (isset($walmart_data['MPItem'][0]['Visible'])) {
                echo "  Visible分类: " . implode(', ', array_keys($walmart_data['MPItem'][0]['Visible'])) . "\n";
            }
        }
    } else {
        echo "  映射返回null\n";
    }
}

echo "\n=== 结论 ===\n";
echo "如果映射结果中assembledProductHeight字段包含正确的measure和unit，\n";
echo "那么问题可能在于:\n";
echo "1. API提交过程中数据被修改\n";
echo "2. Walmart API响应中单位信息丢失\n";
echo "3. 后台显示逻辑没有正确显示单位\n";

?>
