<?php
/**
 * 修复数据库属性表
 * 清理wp_walmart_product_attributes表中的错误数据
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 修复数据库属性表脚本 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n";
echo "⚠️ 警告：此脚本将清理wp_walmart_product_attributes表中的数据\n";
echo "建议先备份数据库！\n\n";

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

require_once $wp_path . '/wp-config.php';
require_once $wp_path . '/wp-load.php';

echo "✅ WordPress加载成功\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'walmart_product_attributes';

// 1. 检查表是否存在
echo "1. 检查数据库表:\n";

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    echo "❌ 表不存在: {$table_name}\n";
    echo "这很奇怪，函数引用了这个表但表不存在\n";
    exit;
}

echo "✅ 表存在: {$table_name}\n";

// 2. 分析表结构和数据
echo "\n2. 分析表结构和数据:\n";

$table_info = $wpdb->get_results("DESCRIBE {$table_name}");
echo "表结构:\n";
foreach ($table_info as $column) {
    echo "  - {$column->Field} ({$column->Type})\n";
}

$total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "\n总记录数: {$total_records}\n";

// 3. 分析问题分类的数据
echo "\n3. 分析问题分类的数据:\n";

$problem_categories = [
    'Television Stands' => 99,
    'Benches' => 100,
    'Accent Cabinets' => 88
];

foreach ($problem_categories as $category => $expected_count) {
    $actual_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE product_type_id = %s",
        $category
    ));
    
    echo "分类: {$category}\n";
    echo "  数据库记录: {$actual_count} 个\n";
    echo "  函数返回: {$expected_count} 个\n";
    
    if ($actual_count > 0) {
        // 显示前5个属性名
        $sample_attrs = $wpdb->get_results($wpdb->prepare(
            "SELECT attribute_name, attribute_type, is_required 
             FROM {$table_name} 
             WHERE product_type_id = %s 
             ORDER BY attribute_name 
             LIMIT 5",
            $category
        ));
        
        echo "  前5个属性:\n";
        foreach ($sample_attrs as $attr) {
            $required = $attr->is_required ? '必填' : '可选';
            echo "    - {$attr->attribute_name} ({$attr->attribute_type}, {$required})\n";
        }
    }
    echo "\n";
}

// 4. 提供清理选项
echo "4. 清理选项:\n";
echo "请选择清理方式:\n";
echo "A. 清理所有数据（推荐）- 让系统重新从API获取\n";
echo "B. 只清理问题分类的数据\n";
echo "C. 备份后清理所有数据\n";
echo "D. 只查看，不清理\n\n";

// 由于这是自动脚本，我们选择最安全的方式：备份后清理
echo "执行选项C：备份后清理所有数据\n\n";

// 5. 备份数据
echo "5. 备份现有数据:\n";

$backup_table = $table_name . '_backup_' . date('Ymd_His');
$backup_result = $wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$table_name}");

if ($backup_result !== false) {
    $backup_count = $wpdb->get_var("SELECT COUNT(*) FROM {$backup_table}");
    echo "✅ 备份成功: {$backup_table} ({$backup_count} 条记录)\n";
} else {
    echo "❌ 备份失败: " . $wpdb->last_error . "\n";
    echo "为安全起见，停止清理操作\n";
    exit;
}

// 6. 清理数据
echo "\n6. 清理数据:\n";

$deleted_count = $wpdb->query("DELETE FROM {$table_name}");
echo "✅ 删除了 {$deleted_count} 条记录\n";

// 验证清理结果
$remaining_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "剩余记录数: {$remaining_count}\n";

if ($remaining_count == 0) {
    echo "✅ 表已完全清空\n";
} else {
    echo "⚠️ 仍有记录残留\n";
}

// 7. 清理相关缓存
echo "\n7. 清理相关缓存:\n";

// 清理所有属性缓存
$cache_cleared = $wpdb->query("
    DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_walmart_attributes_%' 
    OR option_name LIKE '_transient_timeout_walmart_attributes_%'
");

echo "✅ 清理了 {$cache_cleared} 个缓存记录\n";

wp_cache_flush();
echo "✅ 刷新了所有WordPress缓存\n";

// 8. 测试修复效果
echo "\n8. 测试修复效果:\n";

foreach ($problem_categories as $category => $old_count) {
    echo "测试分类: {$category}\n";
    
    try {
        $result = get_attributes_from_database($category);
        $new_count = count($result);
        
        echo "  修复前: {$old_count} 个字段\n";
        echo "  修复后: {$new_count} 个字段\n";
        
        if ($new_count == 0) {
            echo "  ✅ 已清理，系统将从API重新获取\n";
        } else {
            echo "  ⚠️ 仍有数据，可能需要进一步检查\n";
        }
    } catch (Exception $e) {
        echo "  ❌ 测试失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== 修复完成 ===\n";
echo "重要提醒:\n";
echo "1. 数据已备份到: {$backup_table}\n";
echo "2. 如果需要恢复，可以执行: INSERT INTO {$table_name} SELECT * FROM {$backup_table}\n";
echo "3. 请立即测试重置属性功能:\n";
echo "   - 进入分类映射页面\n";
echo "   - 选择Television Stands、Benches或Accent Cabinets\n";
echo "   - 点击'重置属性'按钮\n";
echo "   - 检查字段数量是否恢复正常（约50-70个）\n";
echo "4. 系统现在会从Walmart API重新获取最新规范\n";
?>
