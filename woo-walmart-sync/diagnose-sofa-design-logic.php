<?php
/**
 * 诊断 sofa_and_loveseat_design 字段的代码逻辑
 * 不依赖具体产品，检查代码本身的问题
 */

require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 诊断 sofa_and_loveseat_design 字段代码逻辑 ===\n\n";

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

// ============================================
// 检查1: 字段是否在 v5_common_attributes 中定义
// ============================================
echo "【检查1: v5_common_attributes 配置】\n";
echo str_repeat("-", 80) . "\n";

// 读取 woo-walmart-sync.php 文件
$main_file = WOO_WALMART_SYNC_PATH . 'woo-walmart-sync.php';
$content = file_get_contents($main_file);

if (strpos($content, "'attributeName' => 'sofa_and_loveseat_design'") !== false) {
    echo "✅ sofa_and_loveseat_design 已在 v5_common_attributes 中定义\n";
} else {
    echo "❌ sofa_and_loveseat_design 未在 v5_common_attributes 中定义\n";
}

if (strpos($content, "'sofa_and_loveseat_design'") !== false) {
    echo "✅ sofa_and_loveseat_design 已在 autoGenerateFields 数组中\n";
} else {
    echo "❌ sofa_and_loveseat_design 未在 autoGenerateFields 数组中\n";
}

echo "\n";

// ============================================
// 检查2: generate_special_attribute_value 方法中的 case
// ============================================
echo "【检查2: generate_special_attribute_value 方法】\n";
echo str_repeat("-", 80) . "\n";

$mapper_file = WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
$mapper_content = file_get_contents($mapper_file);

if (preg_match("/case\s+'sofa_and_loveseat_design':/", $mapper_content)) {
    echo "✅ generate_special_attribute_value 中有 sofa_and_loveseat_design case\n";
    
    // 提取相关代码
    if (preg_match("/(case\s+'sofa_and_loveseat_design':.*?return.*?;)/s", $mapper_content, $matches)) {
        echo "代码片段:\n";
        echo $matches[1] . "\n";
    }
} else {
    echo "❌ generate_special_attribute_value 中没有 sofa_and_loveseat_design case\n";
}

echo "\n";

// ============================================
// 检查3: extract_sofa_loveseat_design 方法是否存在
// ============================================
echo "【检查3: extract_sofa_loveseat_design 方法】\n";
echo str_repeat("-", 80) . "\n";

if (preg_match("/private\s+function\s+extract_sofa_loveseat_design/", $mapper_content)) {
    echo "✅ extract_sofa_loveseat_design 方法存在\n";
    
    // 检查方法是否返回默认值
    if (preg_match("/return\s+\['Mid-Century Modern'\];/", $mapper_content)) {
        echo "✅ 方法包含默认值返回逻辑\n";
    } else {
        echo "⚠️ 方法可能缺少默认值返回逻辑\n";
    }
} else {
    echo "❌ extract_sofa_loveseat_design 方法不存在\n";
}

echo "\n";

// ============================================
// 检查4: convert_field_data_type 方法中的处理
// ============================================
echo "【检查4: convert_field_data_type 方法】\n";
echo str_repeat("-", 80) . "\n";

if (preg_match("/case\s+'sofa_and_loveseat_design':/", $mapper_content)) {
    echo "✅ convert_field_data_type 中有 sofa_and_loveseat_design case\n";
    
    // 检查是否有默认值处理
    if (preg_match("/return\s+\['Mid-Century Modern'\];/", $mapper_content)) {
        echo "✅ 类型转换包含默认值逻辑\n";
    }
} else {
    echo "❌ convert_field_data_type 中没有 sofa_and_loveseat_design case\n";
}

echo "\n";

// ============================================
// 检查5: 测试字段生成逻辑
// ============================================
echo "【检查5: 测试字段生成逻辑】\n";
echo str_repeat("-", 80) . "\n";

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

// 创建测试产品
$test_cases = [
    [
        'name' => '空产品（无任何描述）',
        'title' => '',
        'description' => '',
        'expected' => ['Mid-Century Modern']
    ],
    [
        'name' => '包含 Mid-Century 关键词',
        'title' => 'Mid-Century Modern Sofa',
        'description' => '',
        'expected' => ['Mid-Century Modern']
    ],
    [
        'name' => '包含 Tuxedo 关键词',
        'title' => 'Tuxedo Style Loveseat',
        'description' => '',
        'expected' => ['Tuxedo']
    ],
    [
        'name' => '无匹配关键词',
        'title' => 'Simple Sofa',
        'description' => 'A basic sofa',
        'expected' => ['Mid-Century Modern']
    ]
];

$method = $reflection->getMethod('extract_sofa_loveseat_design');
$method->setAccessible(true);

foreach ($test_cases as $test) {
    $product = new WC_Product_Simple();
    $product->set_name($test['title']);
    $product->set_description($test['description']);
    
    $result = $method->invoke($mapper, $product);
    
    echo "测试: {$test['name']}\n";
    echo "  输入: {$test['title']}\n";
    echo "  期望: " . json_encode($test['expected'], JSON_UNESCAPED_UNICODE) . "\n";
    echo "  结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    
    if ($result === $test['expected']) {
        echo "  ✅ 通过\n";
    } else {
        echo "  ❌ 失败\n";
    }
    echo "\n";
}

// ============================================
// 检查6: 可能导致字段缺失的原因分析
// ============================================
echo "【检查6: 可能导致字段缺失的原因分析】\n";
echo str_repeat("-", 80) . "\n";

echo "可能的原因：\n\n";

echo "1. ❓ 分类映射配置问题\n";
echo "   - 产品的本地分类没有映射到 Walmart 分类\n";
echo "   - 分类映射表中没有配置 sofa_and_loveseat_design 字段\n";
echo "   - 解决方案：在分类映射页面重置属性，确保字段被添加\n\n";

echo "2. ❓ 字段被过滤掉\n";
echo "   - 字段生成返回了 null 或空值\n";
echo "   - 字段在映射过程中被过滤掉了\n";
echo "   - 解决方案：检查 map_product_to_walmart_format 方法中的过滤逻辑\n\n";

echo "3. ❓ 代码版本不同步\n";
echo "   - 另一个服务器的代码版本较旧\n";
echo "   - 没有包含 sofa_and_loveseat_design 字段的代码\n";
echo "   - 解决方案：更新另一个服务器的代码\n\n";

echo "4. ❓ 产品数据问题\n";
echo "   - 产品标题和描述为空\n";
echo "   - 无法提取任何关键词\n";
echo "   - 但应该返回默认值 ['Mid-Century Modern']\n\n";

echo "5. ❓ 字段映射类型错误\n";
echo "   - 字段的映射类型不是 'auto_generate'\n";
echo "   - 可能被设置为其他类型（如 'default_value'）\n";
echo "   - 解决方案：检查分类映射中的字段配置\n\n";

// ============================================
// 检查7: 查看实际的分类映射配置
// ============================================
echo "【检查7: 查看本地的分类映射配置】\n";
echo str_repeat("-", 80) . "\n";

global $wpdb;
$query = "
    SELECT id, wc_category_name, walmart_category_path, walmart_attributes
    FROM {$wpdb->prefix}walmart_category_map
    WHERE walmart_category_path LIKE '%Sofa%' OR walmart_category_path LIKE '%Couch%'
    LIMIT 5
";

$mappings = $wpdb->get_results($query);

if (!empty($mappings)) {
    echo "找到 " . count($mappings) . " 个沙发相关的分类映射:\n\n";
    
    foreach ($mappings as $mapping) {
        echo "分类: {$mapping->wc_category_name}\n";
        echo "Walmart分类: {$mapping->walmart_category_path}\n";
        
        $attributes = json_decode($mapping->walmart_attributes, true);
        $has_field = false;
        
        if (is_array($attributes)) {
            foreach ($attributes as $attr) {
                if (isset($attr['name']) && $attr['name'] === 'sofa_and_loveseat_design') {
                    $has_field = true;
                    echo "✅ 已配置 sofa_and_loveseat_design\n";
                    echo "   类型: {$attr['type']}\n";
                    echo "   来源: {$attr['source']}\n";
                    break;
                }
            }
        }
        
        if (!$has_field) {
            echo "❌ 未配置 sofa_and_loveseat_design\n";
            echo "   ⚠️ 需要在分类映射页面重置属性\n";
        }
        
        echo "\n";
    }
} else {
    echo "❌ 没有找到沙发相关的分类映射\n";
    echo "   这可能是问题的根源！\n\n";
}

// ============================================
// 总结
// ============================================
echo str_repeat("=", 80) . "\n";
echo "【诊断总结】\n";
echo str_repeat("=", 80) . "\n\n";

echo "根据诊断结果，最可能的原因是：\n\n";

echo "🔴 **分类映射配置问题**\n";
echo "   - 产品的分类映射表中没有配置 sofa_and_loveseat_design 字段\n";
echo "   - 即使代码中有字段定义，如果分类映射中没有配置，字段也不会被传递\n\n";

echo "✅ **解决方案**：\n";
echo "   1. 登录到另一个服务器的 WordPress 后台\n";
echo "   2. 进入 Walmart 同步插件的分类映射页面\n";
echo "   3. 找到沙发相关的分类映射\n";
echo "   4. 点击「重置属性」按钮，重新加载字段\n";
echo "   5. 确保 sofa_and_loveseat_design 字段出现在字段列表中\n";
echo "   6. 设置字段类型为「自动生成」\n";
echo "   7. 保存配置\n";
echo "   8. 重新同步产品\n\n";

echo "📝 **验证步骤**：\n";
echo "   1. 在另一个服务器上运行此诊断脚本\n";
echo "   2. 检查「检查7」的输出，确认字段已配置\n";
echo "   3. 查看同步日志，确认字段被传递到 API\n\n";

?>

