<?php
/**
 * 验证我们的修改是否真的导致了问题
 * 通过临时回滚修改来测试
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 修改影响验证 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n\n";

// WordPress环境加载
if (!defined('ABSPATH')) {
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

// 备份当前文件
$mapper_file = 'includes/class-product-mapper.php';
$temp_backup = 'includes/class-product-mapper.php.temp_backup.' . date('Ymd_His');

if (!copy($mapper_file, $temp_backup)) {
    die("❌ 无法创建临时备份\n");
}

echo "✅ 已创建临时备份: {$temp_backup}\n";

// 读取当前文件内容
$content = file_get_contents($mapper_file);

// ============================================
// 步骤1: 测试当前版本（有我们的修改）
// ============================================
echo "\n【步骤1: 测试当前版本】\n";
echo str_repeat("-", 50) . "\n";

require_once 'includes/class-product-mapper.php';

// 测试产品
$test_sku = 'W18B9X011F8';
$product_id = wc_get_product_id_by_sku($test_sku);

if (!$product_id) {
    echo "❌ 找不到测试产品\n";
} else {
    $product = wc_get_product($product_id);
    echo "测试产品: {$product->get_name()}\n";
    
    $mapper = new Woo_Walmart_Product_Mapper();
    $reflection = new ReflectionClass($mapper);
    $generate_method = $reflection->getMethod('generate_special_attribute_value');
    $generate_method->setAccessible(true);
    
    // 测试几个关键字段
    $test_fields = ['sofa_and_loveseat_design', 'brand', 'mainImageUrl', 'productName'];
    $current_results = [];
    
    foreach ($test_fields as $field) {
        try {
            $result = $generate_method->invoke($mapper, $field, $product, 1);
            $current_results[$field] = [
                'success' => true,
                'result' => $result,
                'type' => gettype($result)
            ];
            echo "✅ {$field}: " . gettype($result) . "\n";
        } catch (Exception $e) {
            $current_results[$field] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            echo "❌ {$field}: " . $e->getMessage() . "\n";
        }
    }
}

// ============================================
// 步骤2: 临时回滚修改
// ============================================
echo "\n【步骤2: 临时回滚修改】\n";
echo str_repeat("-", 50) . "\n";

// 移除我们添加的case分支
$original_content = $content;

// 移除 sofaandloveseatdesign case
$original_content = preg_replace(
    '/\s*case \'sofaandloveseatdesign\':\s*\n/',
    '',
    $original_content
);

// 移除 sofabedsize case
$original_content = preg_replace(
    '/\s*case \'sofabedsize\':\s*\n/',
    '',
    $original_content
);

// 写入回滚版本
if (!file_put_contents($mapper_file, $original_content)) {
    die("❌ 无法写入回滚版本\n");
}

echo "✅ 已临时回滚修改\n";

// 清除类缓存，重新加载
if (class_exists('Woo_Walmart_Product_Mapper')) {
    // PHP无法真正卸载类，但我们可以重新包含文件
    include_once $mapper_file;
}

// ============================================
// 步骤3: 测试回滚版本
// ============================================
echo "\n【步骤3: 测试回滚版本】\n";
echo str_repeat("-", 50) . "\n";

if ($product_id) {
    // 创建新的mapper实例
    $mapper_rollback = new Woo_Walmart_Product_Mapper();
    $reflection_rollback = new ReflectionClass($mapper_rollback);
    $generate_method_rollback = $reflection_rollback->getMethod('generate_special_attribute_value');
    $generate_method_rollback->setAccessible(true);
    
    $rollback_results = [];
    
    foreach ($test_fields as $field) {
        try {
            $result = $generate_method_rollback->invoke($mapper_rollback, $field, $product, 1);
            $rollback_results[$field] = [
                'success' => true,
                'result' => $result,
                'type' => gettype($result)
            ];
            echo "✅ {$field}: " . gettype($result) . "\n";
        } catch (Exception $e) {
            $rollback_results[$field] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            echo "❌ {$field}: " . $e->getMessage() . "\n";
        }
    }
}

// ============================================
// 步骤4: 恢复修改
// ============================================
echo "\n【步骤4: 恢复修改】\n";
echo str_repeat("-", 50) . "\n";

if (!file_put_contents($mapper_file, $content)) {
    die("❌ 无法恢复原始修改\n");
}

echo "✅ 已恢复原始修改\n";

// ============================================
// 步骤5: 对比结果
// ============================================
echo "\n【步骤5: 结果对比】\n";
echo str_repeat("-", 50) . "\n";

if (isset($current_results) && isset($rollback_results)) {
    $differences_found = false;
    
    foreach ($test_fields as $field) {
        $current = $current_results[$field] ?? null;
        $rollback = $rollback_results[$field] ?? null;
        
        echo "\n字段: {$field}\n";
        
        if (!$current || !$rollback) {
            echo "  ⚠️ 数据不完整，无法对比\n";
            continue;
        }
        
        // 对比成功状态
        if ($current['success'] !== $rollback['success']) {
            echo "  ❌ 成功状态不同: 修改后=" . ($current['success'] ? '成功' : '失败') . 
                 ", 回滚后=" . ($rollback['success'] ? '成功' : '失败') . "\n";
            $differences_found = true;
        }
        
        // 对比结果类型
        if ($current['success'] && $rollback['success']) {
            if ($current['type'] !== $rollback['type']) {
                echo "  ❌ 结果类型不同: 修改后={$current['type']}, 回滚后={$rollback['type']}\n";
                $differences_found = true;
            } else {
                echo "  ✅ 结果类型相同: {$current['type']}\n";
            }
        }
        
        // 对比错误信息
        if (!$current['success'] && !$rollback['success']) {
            if ($current['error'] !== $rollback['error']) {
                echo "  ❌ 错误信息不同\n";
                echo "    修改后: {$current['error']}\n";
                echo "    回滚后: {$rollback['error']}\n";
                $differences_found = true;
            } else {
                echo "  ✅ 错误信息相同\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    if ($differences_found) {
        echo "❌ **发现差异！我们的修改确实影响了字段处理**\n";
    } else {
        echo "✅ **未发现差异！问题可能不是由我们的修改引起的**\n";
    }
    echo str_repeat("=", 50) . "\n";
}

// 清理临时备份
unlink($temp_backup);
echo "\n✅ 已清理临时备份文件\n";

echo "\n=== 验证完成 ===\n";
?>
