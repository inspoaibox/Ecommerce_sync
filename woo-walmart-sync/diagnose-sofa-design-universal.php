<?php
/**
 * 诊断 sofa_and_loveseat_design 字段的代码逻辑
 * 通用版本 - 适用于任何服务器
 */

// 自动检测 WordPress 根目录
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',  // 标准插件路径
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
    die("错误：无法找到 WordPress。请确保此脚本在插件目录中运行。\n");
}

echo "=== 诊断 sofa_and_loveseat_design 字段代码逻辑 ===\n\n";
echo "服务器路径: " . __DIR__ . "\n";
echo "WordPress 路径: " . ABSPATH . "\n\n";

// 获取插件路径
if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

// ============================================
// 检查1: 字段是否在 v5_common_attributes 中定义
// ============================================
echo "【检查1: v5_common_attributes 配置】\n";
echo str_repeat("-", 80) . "\n";

$main_file = WOO_WALMART_SYNC_PATH . 'woo-walmart-sync.php';
if (!file_exists($main_file)) {
    echo "❌ 错误：找不到 woo-walmart-sync.php 文件\n";
    echo "   路径: {$main_file}\n\n";
} else {
    $content = file_get_contents($main_file);
    
    if (strpos($content, "'attributeName' => 'sofa_and_loveseat_design'") !== false) {
        echo "✅ sofa_and_loveseat_design 已在 v5_common_attributes 中定义\n";
    } else {
        echo "❌ sofa_and_loveseat_design 未在 v5_common_attributes 中定义\n";
        echo "   ⚠️ 需要更新代码！\n";
    }
    
    if (strpos($content, "'sofa_and_loveseat_design'") !== false) {
        echo "✅ sofa_and_loveseat_design 已在 autoGenerateFields 数组中\n";
    } else {
        echo "❌ sofa_and_loveseat_design 未在 autoGenerateFields 数组中\n";
        echo "   ⚠️ 需要更新代码！\n";
    }
}

echo "\n";

// ============================================
// 检查2: generate_special_attribute_value 方法中的 case
// ============================================
echo "【检查2: generate_special_attribute_value 方法】\n";
echo str_repeat("-", 80) . "\n";

$mapper_file = WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
if (!file_exists($mapper_file)) {
    echo "❌ 错误：找不到 class-product-mapper.php 文件\n\n";
} else {
    $mapper_content = file_get_contents($mapper_file);
    
    if (preg_match("/case\s+'sofa_and_loveseat_design':/", $mapper_content)) {
        echo "✅ generate_special_attribute_value 中有 sofa_and_loveseat_design case\n";
    } else {
        echo "❌ generate_special_attribute_value 中没有 sofa_and_loveseat_design case\n";
        echo "   ⚠️ 需要更新代码！\n";
    }
}

echo "\n";

// ============================================
// 检查3: extract_sofa_loveseat_design 方法是否存在
// ============================================
echo "【检查3: extract_sofa_loveseat_design 方法】\n";
echo str_repeat("-", 80) . "\n";

if (isset($mapper_content)) {
    if (preg_match("/private\s+function\s+extract_sofa_loveseat_design/", $mapper_content)) {
        echo "✅ extract_sofa_loveseat_design 方法存在\n";
        
        if (preg_match("/return\s+\['Mid-Century Modern'\];/", $mapper_content)) {
            echo "✅ 方法包含默认值返回逻辑\n";
        } else {
            echo "⚠️ 方法可能缺少默认值返回逻辑\n";
        }
    } else {
        echo "❌ extract_sofa_loveseat_design 方法不存在\n";
        echo "   ⚠️ 需要更新代码！\n";
    }
}

echo "\n";

// ============================================
// 检查4: convert_field_data_type 方法中的处理
// ============================================
echo "【检查4: convert_field_data_type 方法】\n";
echo str_repeat("-", 80) . "\n";

if (isset($mapper_content)) {
    if (preg_match("/case\s+'sofa_and_loveseat_design':/", $mapper_content)) {
        echo "✅ convert_field_data_type 中有 sofa_and_loveseat_design case\n";
    } else {
        echo "❌ convert_field_data_type 中没有 sofa_and_loveseat_design case\n";
        echo "   ⚠️ 需要更新代码！\n";
    }
}

echo "\n";

// ============================================
// 检查5: 测试字段生成逻辑
// ============================================
echo "【检查5: 测试字段生成逻辑】\n";
echo str_repeat("-", 80) . "\n";

try {
    $mapper = new Woo_Walmart_Product_Mapper();
    $reflection = new ReflectionClass($mapper);
    
    // 检查方法是否存在
    if (!$reflection->hasMethod('extract_sofa_loveseat_design')) {
        echo "❌ extract_sofa_loveseat_design 方法不存在\n";
        echo "   ⚠️ 代码版本过旧，需要更新！\n\n";
    } else {
        $method = $reflection->getMethod('extract_sofa_loveseat_design');
        $method->setAccessible(true);
        
        // 测试空产品
        $product = new WC_Product_Simple();
        $product->set_name('');
        $product->set_description('');
        
        $result = $method->invoke($mapper, $product);
        
        echo "测试: 空产品（无任何描述）\n";
        echo "  结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        
        if ($result === ['Mid-Century Modern']) {
            echo "  ✅ 通过 - 正确返回默认值\n";
        } else {
            echo "  ❌ 失败 - 应该返回 ['Mid-Century Modern']\n";
        }
    }
} catch (Exception $e) {
    echo "❌ 测试失败: {$e->getMessage()}\n";
}

echo "\n";

// ============================================
// 检查6: 查看分类映射配置
// ============================================
echo "【检查6: 查看分类映射配置】\n";
echo str_repeat("-", 80) . "\n";

global $wpdb;
$query = "
    SELECT id, wc_category_name, walmart_category_path, walmart_attributes
    FROM {$wpdb->prefix}walmart_category_map
    WHERE walmart_category_path LIKE '%Sofa%' OR walmart_category_path LIKE '%Couch%'
    LIMIT 10
";

$mappings = $wpdb->get_results($query);

if (!empty($mappings)) {
    echo "找到 " . count($mappings) . " 个沙发相关的分类映射:\n\n";
    
    $has_configured = false;
    
    foreach ($mappings as $mapping) {
        echo "分类 ID: {$mapping->id}\n";
        echo "本地分类: {$mapping->wc_category_name}\n";
        echo "Walmart分类: {$mapping->walmart_category_path}\n";
        
        $attributes = json_decode($mapping->walmart_attributes, true);
        $has_field = false;
        
        if (is_array($attributes)) {
            foreach ($attributes as $attr) {
                if (isset($attr['name']) && $attr['name'] === 'sofa_and_loveseat_design') {
                    $has_field = true;
                    $has_configured = true;
                    echo "✅ 已配置 sofa_and_loveseat_design\n";
                    echo "   类型: {$attr['type']}\n";
                    echo "   来源: " . ($attr['source'] ?? '(空)') . "\n";
                    break;
                }
            }
        }
        
        if (!$has_field) {
            echo "❌ 未配置 sofa_and_loveseat_design\n";
            echo "   🔧 需要在分类映射页面点击「重置属性」按钮\n";
        }
        
        echo "\n";
    }
    
    if (!$has_configured) {
        echo "⚠️ 警告：所有沙发分类都未配置 sofa_and_loveseat_design 字段！\n";
        echo "   这就是导致 API 报错的原因！\n\n";
    }
} else {
    echo "❌ 没有找到沙发相关的分类映射\n";
    echo "   可能原因：\n";
    echo "   1. 还没有创建沙发分类的映射\n";
    echo "   2. 分类名称不包含 'Sofa' 或 'Couch'\n\n";
    
    // 显示所有分类映射
    $all_mappings = $wpdb->get_results("
        SELECT id, wc_category_name, walmart_category_path
        FROM {$wpdb->prefix}walmart_category_map
        LIMIT 20
    ");
    
    if (!empty($all_mappings)) {
        echo "所有分类映射（前20个）:\n";
        foreach ($all_mappings as $m) {
            echo "  - {$m->wc_category_name} → {$m->walmart_category_path}\n";
        }
        echo "\n";
    }
}

// ============================================
// 总结
// ============================================
echo str_repeat("=", 80) . "\n";
echo "【诊断总结】\n";
echo str_repeat("=", 80) . "\n\n";

echo "🔍 **问题诊断结果**：\n\n";

echo "根据上述检查，最可能的原因是：\n\n";

echo "🔴 **分类映射配置问题**\n";
echo "   - 产品的分类映射表中没有配置 sofa_and_loveseat_design 字段\n";
echo "   - 即使代码中有字段定义，如果分类映射中没有配置，字段也不会被传递\n\n";

echo "✅ **解决方案**：\n\n";
echo "1️⃣ 登录 WordPress 后台\n";
echo "2️⃣ 进入「Walmart 同步」→「分类映射」页面\n";
echo "3️⃣ 找到沙发相关的分类映射（如上面列出的分类）\n";
echo "4️⃣ 点击「重置属性」按钮（⚠️ 重要！）\n";
echo "5️⃣ 系统会重新加载所有字段，包括 sofa_and_loveseat_design\n";
echo "6️⃣ 确认字段出现在列表中，类型为「自动生成」\n";
echo "7️⃣ 保存配置\n";
echo "8️⃣ 重新同步失败的产品\n\n";

echo "📝 **验证步骤**：\n\n";
echo "重置属性后，再次运行此脚本，检查「检查6」的输出\n";
echo "应该显示：✅ 已配置 sofa_and_loveseat_design\n\n";

echo "💡 **提示**：\n\n";
echo "如果「检查1-4」显示代码未更新，请先更新代码文件：\n";
echo "  - woo-walmart-sync.php\n";
echo "  - includes/class-product-mapper.php\n\n";

echo "然后再执行「重置属性」操作。\n\n";

echo "诊断完成！\n";
?>

