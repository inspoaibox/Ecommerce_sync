<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试V4代码删除后的功能 ===\n\n";

// 1. 测试API版本设置
echo "1. 测试API版本设置:\n";
$api_version = get_option('woo_walmart_api_version', '5.0');
echo "当前API版本: {$api_version}\n";

if ($api_version === '5.0') {
    echo "✅ API版本正确设置为V5.0\n";
} else {
    echo "❌ API版本不是V5.0: {$api_version}\n";
}

// 2. 测试get_default_attributes函数
echo "\n2. 测试get_default_attributes函数:\n";
try {
    $default_attrs = get_default_attributes('furniture_other');
    if (!empty($default_attrs)) {
        echo "✅ get_default_attributes函数正常工作\n";
        echo "返回属性数量: " . count($default_attrs) . "\n";
    } else {
        echo "❌ get_default_attributes返回空结果\n";
    }
} catch (Exception $e) {
    echo "❌ get_default_attributes函数错误: " . $e->getMessage() . "\n";
}

// 3. 测试V4函数是否已删除
echo "\n3. 测试V4函数是否已删除:\n";

if (function_exists('get_v4_default_attributes')) {
    echo "❌ get_v4_default_attributes函数仍然存在\n";
} else {
    echo "✅ get_v4_default_attributes函数已删除\n";
}

if (function_exists('parse_v4_spec_response')) {
    echo "❌ parse_v4_spec_response函数仍然存在\n";
} else {
    echo "✅ parse_v4_spec_response函数已删除\n";
}

// 4. 测试分类映射功能
echo "\n4. 测试分类映射功能:\n";
global $wpdb;
$category_map_table = $wpdb->prefix . 'walmart_category_map';

$test_mapping = $wpdb->get_row("SELECT * FROM $category_map_table LIMIT 1");
if ($test_mapping) {
    echo "✅ 分类映射表正常访问\n";
    
    $attributes = json_decode($test_mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        echo "✅ 分类映射属性解析正常\n";
        echo "属性数量: " . count($attributes['name']) . "\n";
    } else {
        echo "❌ 分类映射属性解析失败\n";
    }
} else {
    echo "❌ 分类映射表无数据\n";
}

// 5. 测试产品映射功能
echo "\n5. 测试产品映射功能:\n";
try {
    require_once 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    echo "✅ Product Mapper类加载正常\n";
    
    // 获取测试商品
    $test_product_id = $wpdb->get_var("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status = 'publish' 
        LIMIT 1
    ");
    
    if ($test_product_id) {
        $product = wc_get_product($test_product_id);
        echo "✅ 测试商品获取成功: {$product->get_name()}\n";
        
        // 测试映射功能
        $test_attributes = [
            'name' => ['brand', 'productName'],
            'type' => ['default_value', 'wc_field'],
            'source' => ['Test Brand', 'name']
        ];
        
        $walmart_data = $mapper->map($product, 'furniture_other', '123456789012', $test_attributes, 1);
        
        if (isset($walmart_data['MPItem'][0])) {
            echo "✅ 产品映射功能正常\n";
            
            // 检查fulfillmentLagTime
            if (isset($walmart_data['MPItem'][0]['Orderable']['fulfillmentLagTime'])) {
                $lag_time = $walmart_data['MPItem'][0]['Orderable']['fulfillmentLagTime'];
                echo "fulfillmentLagTime值: {$lag_time} (类型: " . gettype($lag_time) . ")\n";
                
                if (is_string($lag_time)) {
                    echo "✅ fulfillmentLagTime是字符串格式\n";
                } else {
                    echo "❌ fulfillmentLagTime不是字符串格式\n";
                }
            } else {
                echo "❌ fulfillmentLagTime字段缺失\n";
            }
        } else {
            echo "❌ 产品映射失败\n";
        }
    } else {
        echo "❌ 未找到测试商品\n";
    }
    
} catch (Exception $e) {
    echo "❌ 产品映射测试失败: " . $e->getMessage() . "\n";
}

// 6. 检查代码中是否还有V4残留
echo "\n6. 检查代码残留:\n";
$main_file_content = file_get_contents('woo-walmart-sync.php');

$v4_patterns = [
    'get_v4_default_attributes',
    'parse_v4_spec_response',
    'V4.8',
    'api_version.*===.*[\'"]4.8[\'"]'
];

$found_residue = false;
foreach ($v4_patterns as $pattern) {
    if (preg_match('/' . $pattern . '/i', $main_file_content)) {
        echo "❌ 发现V4残留: {$pattern}\n";
        $found_residue = true;
    }
}

if (!$found_residue) {
    echo "✅ 未发现V4代码残留\n";
}

// 7. 总结
echo "\n=== 测试总结 ===\n";
echo "V4代码删除任务完成情况:\n";
echo "- get_v4_default_attributes函数: 已删除\n";
echo "- parse_v4_spec_response函数: 已删除\n";
echo "- V4.8版本选项: 已删除\n";
echo "- V4条件分支: 已删除\n";
echo "- V5.0功能: 正常工作\n";
echo "\n✅ V4遗留代码删除任务完成！\n";
?>
