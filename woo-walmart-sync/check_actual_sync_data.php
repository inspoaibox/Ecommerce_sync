<?php
/**
 * 检查实际发送给沃尔玛的同步数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际发送给沃尔玛的同步数据 ===\n\n";

$product_id = 20345;
$product = wc_get_product($product_id);

echo "产品: {$product->get_name()}\n\n";

// 1. 重新生成完整的同步数据
echo "1. 生成完整的同步数据:\n";

require_once 'includes/class-product-sync.php';
$sync = new Woo_Walmart_Product_Sync();

// 使用反射调用私有方法来获取实际的同步数据
$reflection = new ReflectionClass($sync);

// 获取prepare_product_data方法
if ($reflection->hasMethod('prepare_product_data')) {
    $prepare_method = $reflection->getMethod('prepare_product_data');
    $prepare_method->setAccessible(true);
    
    try {
        $sync_data = $prepare_method->invoke($sync, $product);
        
        echo "✅ 成功生成同步数据\n";
        
        // 查找尺寸字段
        $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
        
        if (isset($sync_data['MPItem'][0]['Visible'])) {
            $visible = $sync_data['MPItem'][0]['Visible'];
            
            foreach ($visible as $category => $fields) {
                echo "\n分类: {$category}\n";
                
                foreach ($dimension_fields as $field) {
                    if (isset($fields[$field])) {
                        $value = $fields[$field];
                        echo "  {$field}: " . json_encode($value, JSON_UNESCAPED_UNICODE);
                        
                        if (is_array($value) && isset($value['measure']) && isset($value['unit'])) {
                            echo " ✅ 有单位\n";
                        } else {
                            echo " ❌ 无单位 (类型: " . gettype($value) . ")\n";
                            echo "    ⚠️ 这就是沃尔玛后台显示'select'的原因！\n";
                        }
                    } else {
                        echo "  {$field}: 未找到\n";
                    }
                }
            }
        }
        
        // 2. 检查完整的同步数据结构
        echo "\n2. 检查完整的同步数据结构:\n";
        echo "同步数据大小: " . strlen(json_encode($sync_data)) . " 字节\n";
        
        // 保存完整的同步数据到文件以便检查
        $sync_data_json = json_encode($sync_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents('sync_data_debug.json', $sync_data_json);
        echo "✅ 完整同步数据已保存到 sync_data_debug.json\n";
        
    } catch (Exception $e) {
        echo "❌ 生成同步数据失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ prepare_product_data方法不存在\n";
}

// 3. 检查数据转换过程
echo "\n3. 检查数据转换过程:\n";

// 获取分类映射
$map_table = $wpdb->prefix . 'walmart_category_map';
$category_ids = $product->get_category_ids();
$main_cat_id = $category_ids[0];

$mapped_data = $wpdb->get_row($wpdb->prepare(
    "SELECT walmart_category_path, walmart_attributes FROM {$map_table} WHERE wc_category_id = %d",
    $main_cat_id
));

if ($mapped_data) {
    $attribute_rules = json_decode($mapped_data->walmart_attributes, true);
    
    require_once 'includes/class-product-mapper.php';
    $mapper = new Woo_Walmart_Product_Mapper();
    
    // 使用反射调用convert_field_data_type方法
    $convert_method = $reflection->getMethod('convert_field_data_type');
    $convert_method->setAccessible(true);
    
    $dimension_fields = ['assembledProductHeight', 'assembledProductWeight', 'assembledProductWidth'];
    
    foreach ($dimension_fields as $field) {
        echo "\n--- 测试字段转换: {$field} ---\n";
        
        // 先获取映射器生成的原始值
        $mapper_reflection = new ReflectionClass($mapper);
        $generate_method = $mapper_reflection->getMethod('generate_special_attribute_value');
        $generate_method->setAccessible(true);
        
        // 设置产品类型
        $property = $mapper_reflection->getProperty('current_product_type_id');
        $property->setAccessible(true);
        $property->setValue($mapper, 'Beds');
        
        $raw_value = $generate_method->invoke($mapper, $field, $product, 1);
        echo "映射器生成的原始值: " . json_encode($raw_value, JSON_UNESCAPED_UNICODE) . "\n";
        
        // 然后测试数据类型转换
        $field_index = array_search($field, $attribute_rules['name']);
        if ($field_index !== false) {
            $format_override = isset($attribute_rules['format'][$field_index]) ? $attribute_rules['format'][$field_index] : null;
            
            $converted_value = $convert_method->invoke($mapper, $field, $raw_value, $format_override);
            echo "数据类型转换后: " . json_encode($converted_value, JSON_UNESCAPED_UNICODE) . "\n";
            
            if ($raw_value !== $converted_value) {
                echo "⚠️ 数据在转换过程中发生了变化！\n";
                
                if (is_array($raw_value) && isset($raw_value['measure']) && isset($raw_value['unit']) &&
                    (!is_array($converted_value) || !isset($converted_value['measure']) || !isset($converted_value['unit']))) {
                    echo "❌ 单位信息在数据类型转换中丢失了！\n";
                }
            } else {
                echo "✅ 数据转换过程中保持不变\n";
            }
        }
    }
}

echo "\n=== 检查完成 ===\n";
echo "请检查 sync_data_debug.json 文件中的完整同步数据。\n";
?>
