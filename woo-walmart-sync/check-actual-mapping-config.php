<?php
/**
 * 检查实际的映射配置
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际的映射配置 ===\n\n";

// 查找报错产品的实际分类映射
$error_skus = ['W116465061', 'N771P254005L'];

global $wpdb;

foreach ($error_skus as $sku) {
    echo "=== 产品 {$sku} ===\n";
    
    // 1. 找到产品
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if (!$product_id) {
        echo "❌ 未找到产品\n\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    echo "产品ID: {$product_id}\n";
    echo "产品名称: {$product->get_name()}\n";
    
    // 2. 获取产品分类
    $category_ids = $product->get_category_ids();
    echo "分类ID: " . implode(', ', $category_ids) . "\n";
    
    // 3. 模拟映射过程，看看实际使用的映射配置
    require_once 'includes/class-product-mapper.php';
    
    try {
        $mapper = new Woo_Walmart_Product_Mapper();
        
        // 使用反射获取映射配置
        $reflection = new ReflectionClass($mapper);
        
        // 模拟获取映射配置的过程
        $mapping_table = $wpdb->prefix . 'woo_walmart_category_mapping';
        
        // 查找所有可能的映射
        $all_mappings = $wpdb->get_results("SELECT * FROM {$mapping_table}");
        
        $found_mapping = null;
        
        foreach ($all_mappings as $mapping) {
            // 检查直接映射
            if (in_array($mapping->local_category_id, $category_ids)) {
                $found_mapping = $mapping;
                echo "✅ 找到直接映射: {$mapping->walmart_category_path}\n";
                break;
            }
            
            // 检查共享映射
            if (!empty($mapping->local_category_ids)) {
                $shared_ids = json_decode($mapping->local_category_ids, true);
                if (is_array($shared_ids)) {
                    $intersection = array_intersect($category_ids, array_map('intval', $shared_ids));
                    if (!empty($intersection)) {
                        $found_mapping = $mapping;
                        echo "✅ 找到共享映射: {$mapping->walmart_category_path}\n";
                        break;
                    }
                }
            }
        }
        
        if ($found_mapping) {
            echo "映射ID: {$found_mapping->id}\n";
            echo "沃尔玛分类: {$found_mapping->walmart_category_path}\n";
            
            // 4. 检查映射属性配置
            if (!empty($found_mapping->walmart_attributes)) {
                $attributes = json_decode($found_mapping->walmart_attributes, true);
                
                if ($attributes && isset($attributes['name'])) {
                    echo "\n配置的字段:\n";
                    
                    foreach ($attributes['name'] as $index => $field_name) {
                        $type = $attributes['type'][$index] ?? '';
                        $source = $attributes['source'][$index] ?? '';
                        $format = $attributes['format'][$index] ?? '';
                        
                        if (in_array(strtolower($field_name), ['seat_depth', 'arm_height'])) {
                            echo "  ✅ 字段: {$field_name}\n";
                            echo "    映射类型: {$type}\n";
                            echo "    映射源: {$source}\n";
                            echo "    格式: {$format}\n";
                            
                            // 5. 模拟字段值获取过程
                            echo "    模拟字段值获取:\n";
                            
                            $test_value = null;
                            
                            if ($type === 'default_value') {
                                $test_value = $source;
                                echo "      获取的值: '{$test_value}' (default_value)\n";
                            } elseif ($type === 'auto_generate') {
                                // 模拟auto_generate
                                $generate_method = $reflection->getMethod('generate_special_attribute_value');
                                $generate_method->setAccessible(true);
                                
                                // 设置产品类型
                                $product_type_property = $reflection->getProperty('current_product_type_id');
                                $product_type_property->setAccessible(true);
                                $product_type_property->setValue($mapper, $found_mapping->walmart_category_path);
                                
                                try {
                                    $test_value = $generate_method->invoke($mapper, $field_name, $product, 1);
                                    echo "      获取的值: " . json_encode($test_value) . " (auto_generate)\n";
                                } catch (Exception $e) {
                                    echo "      获取失败: " . $e->getMessage() . "\n";
                                }
                            } elseif ($type === 'attributes_field') {
                                $test_value = $product->get_attribute($source) ?: $source;
                                echo "      获取的值: '{$test_value}' (attributes_field)\n";
                            }
                            
                            // 6. 模拟类型转换过程
                            if ($test_value !== null) {
                                echo "    模拟类型转换:\n";
                                
                                $convert_method = $reflection->getMethod('convert_field_data_type');
                                $convert_method->setAccessible(true);
                                
                                try {
                                    $converted_value = $convert_method->invoke($mapper, $field_name, $test_value, $format ?: 'auto');
                                    echo "      转换后的值: " . json_encode($converted_value) . "\n";
                                    echo "      转换后类型: " . gettype($converted_value) . "\n";
                                    
                                    if (is_array($converted_value) && isset($converted_value['measure']) && isset($converted_value['unit'])) {
                                        echo "      ✅ 转换成功！生成了measurement_object格式\n";
                                    } else {
                                        echo "      ❌ 转换失败！仍然是原始格式\n";
                                        echo "      ❌ 这就是为什么API收到错误格式的原因！\n";
                                    }
                                } catch (Exception $e) {
                                    echo "      转换异常: " . $e->getMessage() . "\n";
                                }
                            }
                            
                            echo "\n";
                        }
                    }
                } else {
                    echo "❌ 映射属性格式错误\n";
                }
            } else {
                echo "❌ 没有映射属性配置\n";
            }
        } else {
            echo "❌ 没有找到映射配置\n";
            echo "这可能解释了为什么字段没有正确处理\n";
        }
        
    } catch (Exception $e) {
        echo "映射测试失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
}

echo "=== 总结 ===\n";
echo "通过以上测试，我们应该能看到：\n";
echo "1. 字段在分类映射中是如何配置的\n";
echo "2. 映射类型是什么（default_value、auto_generate等）\n";
echo "3. 获取的字段值是什么\n";
echo "4. 类型转换是否成功\n";
echo "5. 如果转换失败，失败的原因是什么\n";

?>
