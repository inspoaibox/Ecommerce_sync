<?php
/**
 * 检查现有的产品分类
 * 帮助确定正确的分类ID
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 产品分类检查脚本 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// 自动检测WordPress路径
$wp_path = '';
$current_dir = __DIR__;
for ($i = 0; $i < 5; $i++) {
    $test_path = $current_dir . str_repeat('/..', $i);
    if (file_exists($test_path . '/wp-config.php')) {
        $wp_path = realpath($test_path);
        break;
    }
}

if (empty($wp_path)) {
    echo "❌ 无法检测WordPress路径\n";
    exit;
}

require_once $wp_path . '/wp-config.php';
require_once $wp_path . '/wp-load.php';

echo "✅ WordPress加载成功\n\n";

// 获取所有产品分类
$categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
    'number' => 50 // 限制显示前50个
]);

if (empty($categories) || is_wp_error($categories)) {
    echo "❌ 没有找到产品分类\n";
    exit;
}

echo "找到 " . count($categories) . " 个产品分类:\n\n";

// 按ID排序
usort($categories, function($a, $b) {
    return $a->term_id - $b->term_id;
});

echo "分类列表 (按ID排序):\n";
echo str_repeat('-', 80) . "\n";
printf("%-5s | %-30s | %-10s | %s\n", "ID", "分类名称", "商品数", "描述");
echo str_repeat('-', 80) . "\n";

foreach ($categories as $category) {
    $description = $category->description ? substr($category->description, 0, 30) . '...' : '无';
    printf("%-5d | %-30s | %-10d | %s\n", 
        $category->term_id, 
        substr($category->name, 0, 30), 
        $category->count,
        $description
    );
}

echo str_repeat('-', 80) . "\n";

// 查找商品数量较多的分类
echo "\n商品数量较多的分类 (>10个商品):\n";
$popular_categories = array_filter($categories, function($cat) {
    return $cat->count > 10;
});

if (!empty($popular_categories)) {
    // 按商品数量排序
    usort($popular_categories, function($a, $b) {
        return $b->count - $a->count;
    });
    
    foreach ($popular_categories as $category) {
        echo "  ID: {$category->term_id} | {$category->name} | {$category->count} 个商品\n";
    }
} else {
    echo "  没有找到商品数量超过10的分类\n";
}

// 查找ID接近26的分类
echo "\nID接近26的分类:\n";
$nearby_categories = array_filter($categories, function($cat) {
    return abs($cat->term_id - 26) <= 5;
});

if (!empty($nearby_categories)) {
    foreach ($nearby_categories as $category) {
        echo "  ID: {$category->term_id} | {$category->name} | {$category->count} 个商品\n";
    }
} else {
    echo "  没有找到ID接近26的分类\n";
}

// 搜索可能相关的分类名称
echo "\n可能相关的分类 (包含床、家具等关键词):\n";
$relevant_keywords = ['bed', 'frame', 'furniture', 'cabinet', 'table', 'chair', 'storage'];
$relevant_categories = [];

foreach ($categories as $category) {
    $name_lower = strtolower($category->name);
    foreach ($relevant_keywords as $keyword) {
        if (strpos($name_lower, $keyword) !== false) {
            $relevant_categories[] = $category;
            break;
        }
    }
}

if (!empty($relevant_categories)) {
    foreach ($relevant_categories as $category) {
        echo "  ID: {$category->term_id} | {$category->name} | {$category->count} 个商品\n";
    }
} else {
    echo "  没有找到相关的分类\n";
}

echo "\n=== 检查完成 ===\n";
echo "请根据上述信息选择合适的分类ID进行features字段配置\n";
echo "建议选择商品数量较多的分类进行测试\n";
?>
