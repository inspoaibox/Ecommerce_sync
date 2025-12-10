<?php
/**
 * 获取床架分类的正确规范
 */

// 加载WordPress环境
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';
require_once 'includes/class-api-key-auth.php';

echo "=== 获取床架分类的正确规范 ===\n";

// 创建API认证实例
$api_auth = new Woo_Walmart_API_Key_Auth();

// 1. 首先获取所有分类
echo "1. 获取所有分类...\n";
$categories_result = $api_auth->make_request('/v3/utilities/taxonomy');

if (is_wp_error($categories_result)) {
    echo "❌ 获取分类失败: " . $categories_result->get_error_message() . "\n";
} else {
    echo "✅ 分类API调用成功\n";
    
    // 查找床架相关的分类
    if (isset($categories_result['payload'])) {
        $categories = $categories_result['payload'];
        echo "找到 " . count($categories) . " 个分类\n";
        
        // 搜索床架相关分类
        $bed_categories = [];
        foreach ($categories as $category) {
            if (isset($category['name'])) {
                $name = strtolower($category['name']);
                if (strpos($name, 'bed') !== false || strpos($name, 'frame') !== false || strpos($name, 'furniture') !== false) {
                    $bed_categories[] = $category;
                    echo "找到相关分类: " . $category['name'] . "\n";
                }
            }
        }
        
        if (!empty($bed_categories)) {
            echo "\n=== 床架相关分类 ===\n";
            foreach ($bed_categories as $category) {
                echo "分类名称: " . $category['name'] . "\n";
                if (isset($category['id'])) {
                    echo "分类ID: " . $category['id'] . "\n";
                }
                echo "---\n";
            }
        }
    }
}

// 2. 尝试获取床架的具体规范
echo "\n2. 尝试获取床架规范...\n";

// 尝试不同的可能分类名称
$possible_names = [
    'bed frames',
    'Bed Frames',
    'bed_frames',
    'BED_FRAMES',
    'furniture',
    'Furniture',
    'home',
    'Home',
    'bedroom',
    'Bedroom'
];

foreach ($possible_names as $category_name) {
    echo "尝试分类: $category_name\n";
    
    $spec_data = [
        'feedType' => 'MP_ITEM',
        'version' => '5.0',
        'productTypes' => [$category_name]
    ];
    
    $spec_result = $api_auth->make_request('/v3/items/spec', 'POST', $spec_data);
    
    if (is_wp_error($spec_result)) {
        echo "  ❌ 失败: " . $spec_result->get_error_message() . "\n";
    } else {
        echo "  ✅ 成功获取规范!\n";
        
        // 保存结果
        $filename = "bed_frames_spec_" . str_replace(' ', '_', $category_name) . "_" . date('Ymd_His') . ".json";
        file_put_contents($filename, json_encode($spec_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  📁 规范已保存到: $filename\n";
        
        // 显示基本信息
        if (isset($spec_result['payload'])) {
            echo "  📋 规范包含字段数: " . count($spec_result['payload']) . "\n";
        }
        
        break; // 找到一个有效的就停止
    }
}

echo "\n=== 完成 ===\n";
?>
