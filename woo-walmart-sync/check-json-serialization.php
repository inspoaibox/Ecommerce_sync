<?php
/**
 * 检查JSON序列化是否正确处理assembledProductWeight
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查JSON序列化问题 ===\n\n";

$test_product_id = 25926;
$product = wc_get_product($test_product_id);

if (!$product) {
    echo "❌ 测试产品不存在\n";
    exit;
}

echo "产品: {$product->get_name()}\n\n";

// 1. 获取完整的映射数据
echo "1. 获取完整的映射数据:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 获取分类映射
$categories = $product->get_category_ids();
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

if (!$walmart_category) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

$upc = get_post_meta($product->get_id(), '_walmart_upc', true) ?: '123456789012';
$fulfillment_lag_time = 1;

$walmart_data = $mapper->map($product, $walmart_category, $upc, $attribute_rules, $fulfillment_lag_time);

if (!$walmart_data) {
    echo "❌ 映射失败\n";
    exit;
}

// 2. 检查映射数据中的assembledProductWeight
echo "2. 检查映射数据中的assembledProductWeight:\n";

if (isset($walmart_data['MPItem'][0]['Visible'][$walmart_category]['assembledProductWeight'])) {
    $weight_data = $walmart_data['MPItem'][0]['Visible'][$walmart_category]['assembledProductWeight'];
    echo "映射数据中的assembledProductWeight:\n";
    var_dump($weight_data);
    
    if (is_array($weight_data) && isset($weight_data['measure']) && isset($weight_data['unit'])) {
        echo "✅ 映射数据格式正确\n";
    } else {
        echo "❌ 映射数据格式错误\n";
    }
} else {
    echo "❌ 映射数据中没有assembledProductWeight字段\n";
    
    // 显示所有可用字段
    if (isset($walmart_data['MPItem'][0]['Visible'][$walmart_category])) {
        $visible_fields = array_keys($walmart_data['MPItem'][0]['Visible'][$walmart_category]);
        echo "可用字段: " . implode(', ', array_slice($visible_fields, 0, 10)) . "\n";
    }
    exit;
}

// 3. 测试JSON序列化
echo "\n3. 测试JSON序列化:\n";

$json_string = json_encode($walmart_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($json_string === false) {
    echo "❌ JSON序列化失败: " . json_last_error_msg() . "\n";
    exit;
}

echo "✅ JSON序列化成功\n";
echo "JSON大小: " . strlen($json_string) . " 字节\n";

// 4. 检查JSON中的assembledProductWeight
echo "\n4. 检查JSON中的assembledProductWeight:\n";

// 查找assembledProductWeight在JSON中的位置
$weight_pattern = '/"assembledProductWeight":\s*({[^}]+})/';
if (preg_match($weight_pattern, $json_string, $matches)) {
    echo "✅ 在JSON中找到assembledProductWeight:\n";
    echo $matches[1] . "\n";
    
    // 解析这部分JSON
    $weight_json = json_decode($matches[1], true);
    if ($weight_json) {
        echo "解析结果:\n";
        foreach ($weight_json as $key => $value) {
            echo "  {$key}: {$value} (" . gettype($value) . ")\n";
        }
        
        if (isset($weight_json['measure']) && isset($weight_json['unit'])) {
            echo "✅ JSON中包含完整的measure和unit\n";
        } else {
            echo "❌ JSON中缺少measure或unit\n";
        }
    } else {
        echo "❌ 无法解析assembledProductWeight的JSON\n";
    }
} else {
    echo "❌ 在JSON中没有找到assembledProductWeight\n";
}

// 5. 模拟API请求数据
echo "\n5. 模拟API请求数据:\n";

// 检查实际发送给API的数据格式
$api_data = $walmart_data;

// 模拟API请求的JSON
$api_json = json_encode($api_data, JSON_UNESCAPED_UNICODE);
echo "API请求JSON大小: " . strlen($api_json) . " 字节\n";

// 再次检查API JSON中的assembledProductWeight
if (preg_match($weight_pattern, $api_json, $api_matches)) {
    echo "✅ API JSON中包含assembledProductWeight:\n";
    echo $api_matches[1] . "\n";
    
    $api_weight_json = json_decode($api_matches[1], true);
    if ($api_weight_json && isset($api_weight_json['measure']) && isset($api_weight_json['unit'])) {
        echo "✅ API JSON格式正确\n";
        
        // 检查数据类型
        echo "API JSON中的数据类型:\n";
        echo "  measure: " . gettype($api_weight_json['measure']) . " ({$api_weight_json['measure']})\n";
        echo "  unit: " . gettype($api_weight_json['unit']) . " ({$api_weight_json['unit']})\n";
        
        // 验证是否符合API规范
        if (is_numeric($api_weight_json['measure']) && in_array($api_weight_json['unit'], ['lb', 'oz'])) {
            echo "✅ 数据符合Walmart API规范\n";
        } else {
            echo "❌ 数据不符合Walmart API规范\n";
        }
    } else {
        echo "❌ API JSON格式错误\n";
    }
} else {
    echo "❌ API JSON中没有assembledProductWeight\n";
}

// 6. 检查是否有数据转换问题
echo "\n6. 检查数据转换问题:\n";

$original_weight = $weight_data;
$json_weight = json_decode(json_encode($weight_data), true);

echo "原始数据:\n";
var_dump($original_weight);
echo "JSON转换后:\n";
var_dump($json_weight);

if ($original_weight === $json_weight) {
    echo "✅ JSON转换没有改变数据\n";
} else {
    echo "❌ JSON转换改变了数据\n";
    echo "差异:\n";
    print_r(array_diff_assoc($original_weight, $json_weight));
}

echo "\n=== 结论 ===\n";
echo "如果所有测试都显示数据正确，那么问题可能在于:\n";
echo "1. Walmart API的处理逻辑\n";
echo "2. Walmart后台的显示逻辑\n";
echo "3. API请求头或其他参数问题\n";
echo "4. Walmart对某些字段的特殊处理\n";

?>
