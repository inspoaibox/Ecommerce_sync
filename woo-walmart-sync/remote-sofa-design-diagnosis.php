<?php
/**
 * 远程服务器 sofa_and_loveseat_design 字段诊断脚本
 * 用于检查远程服务器上该字段缺失的具体原因
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 远程服务器 sofa_and_loveseat_design 字段诊断 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
if (!defined('ABSPATH')) {
    // 尝试多个可能的WordPress路径
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

// 插件路径设置
if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}

echo "插件路径: " . WOO_WALMART_SYNC_PATH . "\n\n";

// ============================================
// 检查1: 代码版本检查
// ============================================
echo "【检查1: 代码版本检查】\n";
echo str_repeat("-", 80) . "\n";

$main_file = WOO_WALMART_SYNC_PATH . 'woo-walmart-sync.php';
if (!file_exists($main_file)) {
    echo "❌ 错误：找不到主文件 woo-walmart-sync.php\n\n";
    exit;
}

$content = file_get_contents($main_file);

// 检查字段定义
if (strpos($content, "'attributeName' => 'sofa_and_loveseat_design'") !== false) {
    echo "✅ sofa_and_loveseat_design 已在 v5_common_attributes 中定义\n";
} else {
    echo "❌ sofa_and_loveseat_design 未在 v5_common_attributes 中定义\n";
    echo "   🔧 需要更新代码到最新版本！\n\n";
    exit;
}

// 检查前端配置
$autoGenerateCount = substr_count($content, "'sofa_and_loveseat_design'");
if ($autoGenerateCount >= 3) {
    echo "✅ sofa_and_loveseat_design 已在前端配置中（找到 {$autoGenerateCount} 处引用）\n";
} else {
    echo "❌ sofa_and_loveseat_design 前端配置不完整（只找到 {$autoGenerateCount} 处引用）\n";
    echo "   🔧 需要更新前端配置！\n\n";
}

// ============================================
// 检查2: 后端实现检查
// ============================================
echo "\n【检查2: 后端实现检查】\n";
echo str_repeat("-", 80) . "\n";

$mapper_file = WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
if (!file_exists($mapper_file)) {
    echo "❌ 错误：找不到映射器文件\n\n";
    exit;
}

require_once $mapper_file;

if (!class_exists('Woo_Walmart_Product_Mapper')) {
    echo "❌ 错误：映射器类不存在\n\n";
    exit;
}

$mapper_content = file_get_contents($mapper_file);

// 检查生成方法
if (strpos($mapper_content, "case 'sofa_and_loveseat_design':") !== false) {
    echo "✅ generate_special_attribute_value 中有 sofa_and_loveseat_design case\n";
} else {
    echo "❌ generate_special_attribute_value 中没有 sofa_and_loveseat_design case\n";
    echo "   🔧 需要更新后端实现！\n\n";
    exit;
}

// 检查提取方法
if (strpos($mapper_content, 'extract_sofa_loveseat_design') !== false) {
    echo "✅ extract_sofa_loveseat_design 方法存在\n";
} else {
    echo "❌ extract_sofa_loveseat_design 方法不存在\n";
    echo "   🔧 需要更新后端实现！\n\n";
    exit;
}

// 检查数据类型转换
if (strpos($mapper_content, "case 'sofa_and_loveseat_design':") !== false && 
    strpos($mapper_content, "convert_field_data_type") !== false) {
    echo "✅ 数据类型转换逻辑存在\n";
} else {
    echo "⚠️ 数据类型转换逻辑可能缺失\n";
}

// ============================================
// 检查3: 失败产品的分类映射检查
// ============================================
echo "\n【检查3: 失败产品的分类映射检查】\n";
echo str_repeat("-", 80) . "\n";

$failed_sku = 'W714P357249';
echo "检查失败产品 SKU: {$failed_sku}\n";

// 查找产品
$product_id = wc_get_product_id_by_sku($failed_sku);
if (!$product_id) {
    echo "❌ 找不到SKU为 {$failed_sku} 的产品\n\n";
} else {
    $product = wc_get_product($product_id);
    echo "✅ 找到产品: {$product->get_name()} (ID: {$product_id})\n";
    
    // 获取产品分类
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    echo "产品分类ID: " . implode(', ', $product_categories) . "\n";
    
    // 检查分类映射
    global $wpdb;
    $map_table = $wpdb->prefix . 'walmart_category_map';
    
    $found_mapping = false;
    foreach ($product_categories as $cat_id) {
        // 直接映射查询
        $direct_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $map_table WHERE wc_category_id = %d", 
            $cat_id
        ));
        
        if ($direct_mapping) {
            $found_mapping = true;
            echo "✅ 找到直接映射: {$direct_mapping->walmart_category_path}\n";
            
            // 检查字段配置
            $attributes = json_decode($direct_mapping->walmart_attributes, true);
            $has_sofa_design = false;
            
            if (is_array($attributes) && isset($attributes['name'])) {
                $field_index = array_search('sofa_and_loveseat_design', $attributes['name']);
                if ($field_index !== false) {
                    $has_sofa_design = true;
                    echo "✅ 字段已配置在分类映射中\n";
                    echo "   类型: " . ($attributes['type'][$field_index] ?? 'N/A') . "\n";
                    echo "   来源: " . ($attributes['source'][$field_index] ?? 'N/A') . "\n";
                }
            }
            
            if (!$has_sofa_design) {
                echo "❌ 字段未配置在分类映射中\n";
                echo "   🔧 需要在分类映射页面点击「重置属性」按钮！\n";
            }
            break;
        }
        
        // 共享映射查询
        $shared_mappings = $wpdb->get_results(
            "SELECT * FROM $map_table WHERE local_category_ids IS NOT NULL AND local_category_ids != ''"
        );
        
        foreach ($shared_mappings as $mapping) {
            $local_ids = json_decode($mapping->local_category_ids, true) ?: [];
            if (in_array($cat_id, array_map('intval', $local_ids))) {
                $found_mapping = true;
                echo "✅ 找到共享映射: {$mapping->walmart_category_path}\n";
                
                // 检查字段配置（同上逻辑）
                $attributes = json_decode($mapping->walmart_attributes, true);
                $has_sofa_design = false;
                
                if (is_array($attributes) && isset($attributes['name'])) {
                    $field_index = array_search('sofa_and_loveseat_design', $attributes['name']);
                    if ($field_index !== false) {
                        $has_sofa_design = true;
                        echo "✅ 字段已配置在共享映射中\n";
                    }
                }
                
                if (!$has_sofa_design) {
                    echo "❌ 字段未配置在共享映射中\n";
                    echo "   🔧 需要在分类映射页面点击「重置属性」按钮！\n";
                }
                break 2;
            }
        }
    }
    
    if (!$found_mapping) {
        echo "❌ 没有找到任何分类映射\n";
        echo "   🔧 需要先创建分类映射！\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "【诊断总结】\n";
echo str_repeat("=", 80) . "\n\n";

echo "根据检查结果，请按以下步骤解决：\n\n";

echo "🔧 **立即解决方案**：\n";
echo "1. 登录WordPress后台\n";
echo "2. 进入「Walmart同步」→「分类映射」页面\n";
echo "3. 找到沙发相关的分类映射\n";
echo "4. 点击「重置属性」按钮 ⚠️ **这是关键步骤**\n";
echo "5. 确认 sofa_and_loveseat_design 字段出现在列表中\n";
echo "6. 确认字段类型为「自动生成」\n";
echo "7. 保存配置\n";
echo "8. 重新同步产品 {$failed_sku}\n\n";

echo "📝 **验证步骤**：\n";
echo "1. 重新运行此诊断脚本，确认「检查3」显示字段已配置\n";
echo "2. 查看同步日志，确认不再出现 IB_MISSING_ATTRIBUTE 错误\n";
echo "3. 检查API请求数据中是否包含 sofa_and_loveseat_design 字段\n\n";

echo "=== 诊断完成 ===\n";

// ============================================
// 检查4: 实际字段生成测试
// ============================================
if ($product_id && $found_mapping) {
    echo "\n【检查4: 实际字段生成测试】\n";
    echo str_repeat("-", 80) . "\n";

    try {
        $mapper = new Woo_Walmart_Product_Mapper();
        $reflection = new ReflectionClass($mapper);
        $method = $reflection->getMethod('generate_special_attribute_value');
        $method->setAccessible(true);

        $result = $method->invoke($mapper, 'sofa_and_loveseat_design', $product, 1);

        echo "字段生成测试结果:\n";
        echo "输入产品: {$product->get_name()}\n";
        echo "生成结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "结果类型: " . gettype($result) . "\n";

        if (is_array($result) && !empty($result)) {
            echo "✅ 字段生成成功\n";
        } elseif (is_null($result)) {
            echo "❌ 字段生成返回null\n";
        } else {
            echo "⚠️ 字段生成结果异常\n";
        }

    } catch (Exception $e) {
        echo "❌ 字段生成测试失败: " . $e->getMessage() . "\n";
    }
}

// ============================================
// 检查5: API数据构建测试
// ============================================
if ($product_id && $found_mapping && isset($direct_mapping)) {
    echo "\n【检查5: API数据构建测试】\n";
    echo str_repeat("-", 80) . "\n";

    try {
        $mapper = new Woo_Walmart_Product_Mapper();
        $attributes = json_decode($direct_mapping->walmart_attributes, true);

        $walmart_data = $mapper->map(
            $product,
            $direct_mapping->walmart_category_path,
            '123456789012',
            $attributes,
            1
        );

        // 检查字段是否在API数据中
        $visible = $walmart_data['MPItem'][0]['Visible'][$direct_mapping->walmart_category_path] ?? [];
        $orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];

        if (isset($visible['sofa_and_loveseat_design'])) {
            echo "✅ 在Visible中找到 sofa_and_loveseat_design 字段\n";
            echo "字段值: " . json_encode($visible['sofa_and_loveseat_design'], JSON_UNESCAPED_UNICODE) . "\n";
        } elseif (isset($orderable['sofa_and_loveseat_design'])) {
            echo "✅ 在Orderable中找到 sofa_and_loveseat_design 字段\n";
            echo "字段值: " . json_encode($orderable['sofa_and_loveseat_design'], JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "❌ 在API数据中未找到 sofa_and_loveseat_design 字段\n";
            echo "   这解释了为什么Walmart API报告字段缺失！\n";

            // 显示所有可用字段用于调试
            echo "\nVisible字段列表:\n";
            foreach (array_keys($visible) as $field) {
                echo "  - {$field}\n";
            }
        }

    } catch (Exception $e) {
        echo "❌ API数据构建测试失败: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 完整诊断结束 ===\n";
?>
