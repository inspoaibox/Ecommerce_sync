<?php
/**
 * 分析shape字段的配置和处理逻辑
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 分析shape字段的配置和处理逻辑 ===\n\n";

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\canda.localhost';
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';

echo "✅ WordPress环境加载成功\n\n";

require_once 'includes/class-product-mapper.php';

// 创建映射器实例
$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);

$generate_method = $reflection->getMethod('generate_special_attribute_value');
$generate_method->setAccessible(true);

echo "=== 1. shape字段基本信息 ===\n\n";

echo "📋 根据newleimu.json的字段定义:\n";
echo "  - 字段名: shape\n";
echo "  - 是否必需: ✅ true (在某些分类中)\n";
echo "  - 数据类型: string\n";
echo "  - 最小长度: 1字符\n";
echo "  - 最大长度: 200字符\n";
echo "  - 描述: The physical shape of the item\n";
echo "  - 分组: Visible\n\n";

echo "🎯 标准形状示例:\n";
$standard_shapes = ['Angled', 'Oval', 'Rectangle', 'Round', 'Square'];
foreach ($standard_shapes as $shape) {
    echo "  - {$shape}\n";
}

echo "\n=== 2. 检查系统中的shape字段处理 ===\n\n";

// 模拟产品类
class TestProduct {
    private $name, $description, $short_description, $attributes;
    
    public function __construct($name, $description, $short_description, $attributes = []) {
        $this->name = $name;
        $this->description = $description;
        $this->short_description = $short_description;
        $this->attributes = $attributes;
    }
    
    public function get_name() { return $this->name; }
    public function get_description() { return $this->description; }
    public function get_short_description() { return $this->short_description; }
    public function get_attribute($attr) { return $this->attributes[$attr] ?? ''; }
}

echo "🔍 测试通用shape字段处理:\n";

$test_product = new TestProduct(
    'Round Coffee Table',
    'This beautiful round table features a circular design.',
    'Modern round table',
    ['Shape' => 'Round']
);

try {
    $shape_result = $generate_method->invoke($mapper, 'shape', $test_product, 1);
    echo "  通用shape字段结果: " . json_encode($shape_result, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "  ❌ 通用shape字段处理失败: " . $e->getMessage() . "\n";
}

echo "\n🔍 测试tableshape字段处理:\n";

try {
    $table_shape_result = $generate_method->invoke($mapper, 'tableshape', $test_product, 1);
    echo "  tableshape字段结果: " . json_encode($table_shape_result, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "  ❌ tableshape字段处理失败: " . $e->getMessage() . "\n";
}

echo "\n=== 3. 分析tableshape的实现逻辑 ===\n\n";

echo "📝 tableshape字段的处理逻辑:\n";
echo "1. 从产品名称、描述、简短描述中提取内容\n";
echo "2. 使用关键词匹配识别桌子形状\n";
echo "3. 返回数组格式的形状值\n";
echo "4. 默认值: ['Free Form']\n\n";

echo "🎨 支持的桌子形状映射:\n";
$table_shape_patterns = [
    'Round' => ['round', 'circular', 'circle'],
    'Square' => ['square', 'squared'],
    'Rectangle' => ['rectangle', 'rectangular', 'oblong'],
    'Oval' => ['oval', 'elliptical'],
    'Curved' => ['curved', 'curved edge', 'rounded edge'],
    'Semicircle' => ['semicircle', 'half circle', 'semi-circle'],
    'U-Shape' => ['u-shape', 'u shape', 'horseshoe'],
    'Octagon' => ['octagon', 'octagonal', '8-sided'],
    'Free Form' => ['free form', 'freeform', 'irregular', 'organic']
];

foreach ($table_shape_patterns as $shape => $keywords) {
    echo "  {$shape}: " . implode(', ', $keywords) . "\n";
}

echo "\n=== 4. 测试tableshape的形状识别 ===\n\n";

$shape_test_cases = [
    ['name' => 'Round Dining Table', 'expected' => 'Round'],
    ['name' => 'Square Coffee Table', 'expected' => 'Square'],
    ['name' => 'Rectangular Office Desk', 'expected' => 'Rectangle'],
    ['name' => 'Oval Kitchen Table', 'expected' => 'Oval'],
    ['name' => 'Curved Edge Table', 'expected' => 'Curved'],
    ['name' => 'Semicircle Console Table', 'expected' => 'Semicircle'],
    ['name' => 'U-Shape Conference Table', 'expected' => 'U-Shape'],
    ['name' => 'Octagonal Dining Table', 'expected' => 'Octagon'],
    ['name' => 'Modern Table', 'expected' => 'Free Form'] // 默认值
];

echo "形状识别测试结果:\n";
foreach ($shape_test_cases as $test) {
    $test_product = new TestProduct($test['name'], '', '', []);
    
    try {
        $result = $generate_method->invoke($mapper, 'tableshape', $test_product, 1);
        $detected_shape = is_array($result) ? $result[0] : $result;
        
        $status = ($detected_shape === $test['expected']) ? '✅' : '❌';
        echo "  {$status} '{$test['name']}' -> '{$detected_shape}' (期望: '{$test['expected']}')\n";
    } catch (Exception $e) {
        echo "  ❌ '{$test['name']}' -> 异常: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 5. 检查通用shape字段的默认处理 ===\n\n";

echo "🔍 检查morenzhi.php中是否有shape字段处理:\n";

$morenzhi_file = 'morenzhi.php';
if (file_exists($morenzhi_file)) {
    echo "✅ morenzhi.php文件存在\n";
    
    // 检查文件内容中是否包含shape相关处理
    $morenzhi_content = file_get_contents($morenzhi_file);
    
    if (strpos($morenzhi_content, 'shape') !== false) {
        echo "✅ morenzhi.php中包含shape相关处理\n";
        
        // 尝试调用morenzhi.php的处理函数
        require_once $morenzhi_file;
        
        if (function_exists('handle_auto_generate_field')) {
            $test_product = new TestProduct('Round Table', '', '', []);
            $morenzhi_result = handle_auto_generate_field($test_product, 'shape');
            echo "  morenzhi.php处理结果: " . json_encode($morenzhi_result, JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo "❌ morenzhi.php中未找到shape相关处理\n";
    }
} else {
    echo "❌ morenzhi.php文件不存在\n";
}

echo "\n=== 6. 实际产品测试 ===\n\n";

// 测试实际产品
global $wpdb;
$test_product_id = $wpdb->get_var("
    SELECT p.ID 
    FROM {$wpdb->posts} p 
    WHERE p.post_type = 'product' 
    AND p.post_status = 'publish' 
    ORDER BY p.ID DESC 
    LIMIT 1
");

if ($test_product_id) {
    $product = wc_get_product($test_product_id);
    echo "实际产品测试: {$product->get_name()} (ID: {$test_product_id})\n\n";
    
    // 检查产品是否有Shape属性
    $shape_attributes = ['Shape', 'shape', 'Product Shape', 'Item Shape'];
    $found_shape_attr = false;
    
    echo "检查形状相关属性:\n";
    foreach ($shape_attributes as $attr) {
        $value = $product->get_attribute($attr);
        if (!empty($value)) {
            echo "  ✅ {$attr}: {$value}\n";
            $found_shape_attr = true;
        } else {
            echo "  ❌ {$attr}: (空)\n";
        }
    }
    
    if (!$found_shape_attr) {
        echo "  ⚠️ 产品没有设置形状属性\n";
    }
    
    echo "\n测试字段生成:\n";
    
    // 测试通用shape字段
    try {
        $shape_result = $generate_method->invoke($mapper, 'shape', $product, 1);
        echo "  shape字段: " . json_encode($shape_result, JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Exception $e) {
        echo "  ❌ shape字段生成失败: " . $e->getMessage() . "\n";
    }
    
    // 测试tableshape字段
    try {
        $table_shape_result = $generate_method->invoke($mapper, 'tableshape', $product, 1);
        echo "  tableshape字段: " . json_encode($table_shape_result, JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Exception $e) {
        echo "  ❌ tableshape字段生成失败: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 7. 分类映射中的shape字段配置 ===\n\n";

// 检查分类映射中是否配置了shape字段
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

$shape_mappings = $wpdb->get_results("
    SELECT wc_category_id, walmart_category_path, walmart_attributes 
    FROM $map_table 
    WHERE walmart_attributes LIKE '%shape%'
");

if (!empty($shape_mappings)) {
    echo "✅ 找到包含shape字段的分类映射:\n";
    
    foreach ($shape_mappings as $mapping) {
        $category = get_term($mapping->wc_category_id);
        echo "  分类: {$category->name} (ID: {$mapping->wc_category_id})\n";
        echo "  沃尔玛分类: {$mapping->walmart_category_path}\n";
        
        $attributes = json_decode($mapping->walmart_attributes, true);
        if (isset($attributes['name'])) {
            $shape_indices = array_keys($attributes['name'], 'shape');
            if (!empty($shape_indices)) {
                foreach ($shape_indices as $index) {
                    echo "    shape字段配置 (索引: {$index}):\n";
                    $config_keys = ['type', 'source', 'default_value', 'wc_attribute'];
                    foreach ($config_keys as $key) {
                        if (isset($attributes[$key][$index])) {
                            echo "      {$key}: " . json_encode($attributes[$key][$index], JSON_UNESCAPED_UNICODE) . "\n";
                        }
                    }
                }
            }
        }
        echo "\n";
    }
} else {
    echo "❌ 未找到包含shape字段的分类映射\n";
}

echo "=== 8. 总结 ===\n\n";

echo "🎯 shape字段处理现状:\n\n";

echo "✅ 已实现的功能:\n";
echo "  - tableshape字段: 专门用于桌子类产品的形状识别\n";
echo "  - 智能关键词匹配: 从产品名称和描述中提取形状信息\n";
echo "  - 默认值处理: 无法识别时返回'Free Form'\n";
echo "  - 数组格式输出: 符合API要求\n\n";

echo "❌ 缺失的功能:\n";
echo "  - 通用shape字段: 没有针对所有产品类型的通用形状处理\n";
echo "  - 产品属性支持: 没有从Shape属性中获取值的逻辑\n";
echo "  - 默认值配置: 没有为通用shape字段设置默认值\n\n";

echo "💡 建议:\n";
echo "  1. 添加通用shape字段处理逻辑\n";
echo "  2. 支持从产品属性中获取形状信息\n";
echo "  3. 为不同产品类型设置合适的默认形状值\n";
echo "  4. 扩展形状关键词识别范围\n";

echo "\n=== 分析完成 ===\n";
?>
