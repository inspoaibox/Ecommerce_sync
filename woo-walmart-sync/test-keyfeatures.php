<?php
/**
 * 测试keyFeatures生成逻辑
 * 访问方式：http://your-domain/wp-content/plugins/woo-walmart-sync/test-keyfeatures.php
 */

// 加载WordPress环境
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php', 
    __DIR__ . '/../../../../../wp-load.php'
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
    die('无法找到WordPress。请确保此文件在正确的插件目录中。');
}

// 检查权限
if (!current_user_can('manage_options')) {
    wp_die(__('您没有权限执行此操作。'));
}

// 引入必要的类
if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', plugin_dir_path(__FILE__));
}
require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>keyFeatures生成逻辑测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .product-section { border: 1px solid #ddd; margin: 20px 0; padding: 15px; }
        .description-box { background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0; }
        .feature-list { background: #fff; border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
        .extracted-features { color: #0073aa; }
        .error { color: red; }
        .warning { color: orange; }
        .success { color: green; }
    </style>
</head>
<body>

<h1>keyFeatures生成逻辑测试</h1>

<?php
// 测试特定产品
$test_product_ids = [257, 252]; // 从之前的测试结果中获取的产品ID

foreach ($test_product_ids as $product_id) {
    $product = wc_get_product($product_id);
    if (!$product) {
        echo "<p class='error'>产品 ID {$product_id} 不存在</p>\n";
        continue;
    }
    
    echo "<div class='product-section'>\n";
    echo "<h2>产品: " . esc_html($product->get_name()) . "</h2>\n";
    echo "<p><strong>ID:</strong> {$product->get_id()} | <strong>SKU:</strong> {$product->get_sku()}</p>\n";
    
    // 获取产品描述信息
    $short_description = $product->get_short_description();
    $description = $product->get_description();
    $title = $product->get_name();
    
    echo "<h3>产品信息：</h3>\n";
    echo "<p><strong>产品标题:</strong> " . esc_html($title) . "</p>\n";
    echo "<p><strong>简短描述长度:</strong> " . strlen($short_description) . " 字符</p>\n";
    echo "<p><strong>详细描述长度:</strong> " . strlen($description) . " 字符</p>\n";
    
    if (!empty($short_description)) {
        echo "<h3>简短描述内容:</h3>\n";
        echo "<div class='description-box'>\n";
        echo "<pre style='white-space: pre-wrap; font-family: Arial, sans-serif; font-size: 12px;'>" . esc_html(substr($short_description, 0, 800)) . "</pre>\n";
        if (strlen($short_description) > 800) {
            echo "<p><em>... (内容已截断)</em></p>\n";
        }
        echo "</div>\n";
    }
    
    // 创建产品映射器实例并测试keyFeatures生成
    $mapper = new Woo_Walmart_Product_Mapper();
    
    // 使用反射来访问私有方法
    $reflection = new ReflectionClass($mapper);
    $generate_key_features_method = $reflection->getMethod('generate_key_features');
    $generate_key_features_method->setAccessible(true);
    
    echo "<h3>生成的keyFeatures:</h3>\n";
    
    try {
        $key_features = $generate_key_features_method->invoke($mapper, $product);
        
        if (!empty($key_features) && is_array($key_features)) {
            echo "<div class='feature-list'>\n";
            echo "<ol>\n";
            foreach ($key_features as $index => $feature) {
                echo "<li style='margin-bottom: 8px; line-height: 1.4;'>" . esc_html($feature) . "</li>\n";
            }
            echo "</ol>\n";
            echo "<p class='success'><strong>总计:</strong> " . count($key_features) . " 个特色</p>\n";
            echo "</div>\n";
        } else {
            echo "<p class='error'>未生成任何keyFeatures</p>\n";
        }
        
        // 测试从简短描述提取的方法
        if (!empty($short_description)) {
            $extract_method = $reflection->getMethod('extract_features_from_short_description');
            $extract_method->setAccessible(true);
            $extracted_features = $extract_method->invoke($mapper, $short_description);
            
            echo "<h3>从简短描述提取的特色:</h3>\n";
            if (!empty($extracted_features)) {
                echo "<div class='feature-list'>\n";
                echo "<ol>\n";
                foreach ($extracted_features as $feature) {
                    echo "<li style='margin-bottom: 8px; line-height: 1.4;' class='extracted-features'>" . esc_html($feature) . "</li>\n";
                }
                echo "</ol>\n";
                echo "<p class='success'><strong>从简短描述提取:</strong> " . count($extracted_features) . " 个特色</p>\n";
                echo "</div>\n";
            } else {
                echo "<p class='warning'>从简短描述中未提取到特色，将使用原有逻辑</p>\n";
            }
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>测试出错: " . esc_html($e->getMessage()) . "</p>\n";
    }
    
    echo "</div>\n";
}
?>

<div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
<h2>测试总结</h2>
<p><strong>新的keyFeatures生成逻辑：</strong></p>
<ol>
<li><strong>优先级1:</strong> 从产品简短描述中提取项目符号列表</li>
<li><strong>优先级2:</strong> 如果简短描述特色不足3个，使用原有的智能生成逻辑</li>
<li><strong>优先级3:</strong> 如果仍不足3个，使用通用后备特色</li>
<li><strong>限制:</strong> 最多6个特色，去重处理</li>
</ol>

<p><strong>支持的项目符号格式：</strong></p>
<ul>
<li>* 星号开头</li>
<li>• 圆点开头</li>
<li>- 短横线开头</li>
<li>1. 数字开头</li>
</ul>
</div>

<p><strong>测试完成！</strong></p>

</body>
</html>
