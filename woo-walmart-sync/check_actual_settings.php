<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际设置值 ===\n\n";

// 1. 读取您实际设置的值
echo "1. 您的实际设置:\n";
$actual_fc_id = get_option('woo_walmart_fulfillment_center_id', '');
$actual_shipping = get_option('woo_walmart_shipping_template', '');
$actual_api_version = get_option('woo_walmart_api_version', '');
$actual_business_unit = get_option('woo_walmart_business_unit', '');

echo "履行中心ID: " . ($actual_fc_id ?: '未设置') . "\n";
echo "运输模板: " . ($actual_shipping ?: '未设置') . "\n";
echo "API版本: " . ($actual_api_version ?: '未设置') . "\n";
echo "业务单元: " . ($actual_business_unit ?: '未设置') . "\n\n";

// 2. 验证这些值是否会被正确使用
echo "2. 映射器中的实际调用:\n";

require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 获取测试产品
$product_id = 6203;
$product = wc_get_product($product_id);

if ($product) {
    // 获取分类映射
    global $wpdb;
    $map_table = $wpdb->prefix . 'walmart_category_map';
    $product_cat_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    $main_cat_id = $product_cat_ids[0];
    
    $mapped_category_data = $wpdb->get_row($wpdb->prepare(
        "SELECT walmart_category_path, walmart_attributes FROM $map_table WHERE wc_category_id = %d", 
        $main_cat_id
    ));
    
    if ($mapped_category_data) {
        $attribute_rules = json_decode($mapped_category_data->walmart_attributes, true);
        
        // 执行完整映射
        $walmart_data = $mapper->map(
            $product, 
            $mapped_category_data->walmart_category_path, 
            '123456789012', 
            $attribute_rules, 
            1
        );
        
        // 检查实际生成的数据
        $orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];
        $visible = $walmart_data['MPItem'][0]['Visible'][$mapped_category_data->walmart_category_path] ?? [];
        
        echo "实际生成的数据:\n";
        
        // fulfillmentCenterID
        if (isset($orderable['fulfillmentCenterID'])) {
            $generated_fc_id = $orderable['fulfillmentCenterID'];
            echo "✅ fulfillmentCenterID: '{$generated_fc_id}'\n";
            
            if ($generated_fc_id === $actual_fc_id) {
                echo "   ✅ 正确使用了您设置的值: {$actual_fc_id}\n";
            } elseif ($generated_fc_id === 'WFS' && empty($actual_fc_id)) {
                echo "   ⚠️ 使用了默认值'WFS'（您的设置为空）\n";
            } else {
                echo "   ❌ 值不匹配！设置: '{$actual_fc_id}', 生成: '{$generated_fc_id}'\n";
            }
        } else {
            echo "❌ fulfillmentCenterID: 缺失\n";
        }
        
        // shippingTemplate
        if (isset($visible['shippingTemplate'])) {
            $generated_shipping = $visible['shippingTemplate'];
            echo "✅ shippingTemplate: '{$generated_shipping}'\n";
            
            if ($generated_shipping === $actual_shipping) {
                echo "   ✅ 正确使用了您设置的值: {$actual_shipping}\n";
            } else {
                echo "   ❌ 值不匹配！设置: '{$actual_shipping}', 生成: '{$generated_shipping}'\n";
            }
        } else {
            if (!empty($actual_shipping)) {
                echo "❌ shippingTemplate: 缺失（您设置了'{$actual_shipping}'但未生成）\n";
            } else {
                echo "✅ shippingTemplate: 未设置（符合预期）\n";
            }
        }
        
        // externalProductIdentifier
        if (isset($orderable['externalProductIdentifier'])) {
            $ext_id = $orderable['externalProductIdentifier'];
            echo "✅ externalProductIdentifier: " . json_encode($ext_id) . "\n";
            
            if (is_array($ext_id) && !empty($ext_id)) {
                $first_item = $ext_id[0];
                if (isset($first_item['externalProductId'])) {
                    $product_sku = $product->get_sku();
                    $truncated_sku = substr($product_sku, 0, 10);
                    $generated_id = $first_item['externalProductId'];
                    
                    echo "   原始SKU: '{$product_sku}' (长度: " . strlen($product_sku) . ")\n";
                    echo "   截断后: '{$generated_id}' (长度: " . strlen($generated_id) . ")\n";
                    
                    if ($generated_id === $truncated_sku) {
                        echo "   ✅ 正确截断到10字符\n";
                    } else {
                        echo "   ❌ 截断逻辑有问题\n";
                    }
                }
            }
        }
        
        echo "\n3. 问题诊断:\n";
        
        if (empty($actual_fc_id)) {
            echo "⚠️ 履行中心ID为空，请检查设置页面是否正确保存\n";
        }
        
        if (empty($actual_shipping)) {
            echo "⚠️ 运输模板为空，如果您设置了值但这里显示为空，说明保存有问题\n";
        }
        
        // 检查数据库中的实际值
        echo "\n4. 数据库直接查询:\n";
        $db_fc_id = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'woo_walmart_fulfillment_center_id'");
        $db_shipping = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'woo_walmart_shipping_template'");
        
        echo "数据库中的履行中心ID: " . ($db_fc_id ?: '不存在') . "\n";
        echo "数据库中的运输模板: " . ($db_shipping ?: '不存在') . "\n";
        
        if ($db_fc_id !== $actual_fc_id) {
            echo "❌ get_option()和数据库查询结果不一致！\n";
        }
        
    } else {
        echo "❌ 未找到分类映射\n";
    }
} else {
    echo "❌ 测试产品不存在\n";
}

echo "\n=== 解决方案 ===\n";
echo "如果设置值不正确，请检查:\n";
echo "1. 设置页面是否正确保存（点击'保存设置'按钮）\n";
echo "2. 是否有缓存插件影响选项读取\n";
echo "3. 数据库权限是否正常\n";
?>
