<?php
/**
 * 手动更新数据表结构
 * 用于修复分类映射表缺失字段的问题
 */

// 安全检查
if (!defined('ABSPATH')) {
    require_once '../../../wp-config.php';
    require_once '../../../wp-load.php';
}

// 权限检查
if (!current_user_can('manage_options')) {
    wp_die('权限不足');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>更新数据表结构</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: #00a32a; background: #f0f8f0; padding: 10px; border-left: 4px solid #00a32a; }
        .error { color: #d63638; background: #fdf0f0; padding: 10px; border-left: 4px solid #d63638; }
        .info { color: #0073aa; background: #f0f6fc; padding: 10px; border-left: 4px solid #0073aa; }
        .warning { color: #b32d2e; background: #fcf2f2; padding: 10px; border-left: 4px solid #b32d2e; }
        .code { background: #f1f1f1; padding: 10px; font-family: monospace; white-space: pre-wrap; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .button { background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 5px; }
        .button:hover { background: #005a87; }
        .button.danger { background: #d63638; }
        .button.danger:hover { background: #b32d2e; }
    </style>
</head>
<body>

<h1>🔧 数据表结构更新工具</h1>

<div class="info">
    <strong>说明：</strong>此工具用于修复分类映射表缺失字段的问题，解决"新增本地类目"功能加载失败的问题。
</div>

<?php

global $wpdb;
$category_map_table = $wpdb->prefix . 'walmart_category_map';

// 处理更新请求
if (isset($_POST['update_structure'])) {
    echo "<h2>🚀 开始更新表结构</h2>";
    
    // 检查表是否存在
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$category_map_table}'") === $category_map_table;
    
    if (!$table_exists) {
        echo "<div class='error'>❌ 分类映射表不存在，请先激活插件创建基础表结构。</div>";
    } else {
        echo "<div class='info'>✅ 分类映射表存在，开始检查字段...</div>";
        
        // 获取当前字段
        $columns = $wpdb->get_results("DESCRIBE {$category_map_table}");
        $existing_columns = array_column($columns, 'Field');
        
        echo "<h3>当前表结构：</h3>";
        echo "<table>";
        echo "<tr><th>字段名</th><th>类型</th><th>是否为空</th><th>默认值</th><th>备注</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column->Field}</td>";
            echo "<td>{$column->Type}</td>";
            echo "<td>" . ($column->Null === 'YES' ? 'YES' : 'NO') . "</td>";
            echo "<td>" . ($column->Default ?: 'NULL') . "</td>";
            echo "<td>{$column->Comment}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 检查缺失字段
        $required_columns = ['local_category_ids', 'created_at', 'updated_at'];
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (empty($missing_columns)) {
            echo "<div class='success'>✅ 所有必需字段都已存在，无需更新。</div>";
        } else {
            echo "<div class='warning'>⚠️ 发现缺失字段：" . implode(', ', $missing_columns) . "</div>";
            
            // 执行字段添加
            $success_count = 0;
            $error_count = 0;
            
            foreach ($missing_columns as $column) {
                echo "<h4>添加字段：{$column}</h4>";
                
                $sql = '';
                switch ($column) {
                    case 'local_category_ids':
                        $sql = "ALTER TABLE {$category_map_table} ADD COLUMN local_category_ids longtext DEFAULT NULL COMMENT '共享映射的本地分类ID数组(JSON格式)' AFTER walmart_attributes";
                        break;
                    case 'created_at':
                        $sql = "ALTER TABLE {$category_map_table} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP";
                        break;
                    case 'updated_at':
                        $sql = "ALTER TABLE {$category_map_table} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                        break;
                }
                
                if ($sql) {
                    echo "<div class='code'>{$sql}</div>";
                    
                    $result = $wpdb->query($sql);
                    
                    if ($result === false) {
                        echo "<div class='error'>❌ 添加失败：" . $wpdb->last_error . "</div>";
                        $error_count++;
                    } else {
                        echo "<div class='success'>✅ 添加成功</div>";
                        $success_count++;
                    }
                }
            }
            
            // 添加索引
            echo "<h4>添加索引</h4>";
            $index_sql = "ALTER TABLE {$category_map_table} ADD INDEX walmart_category_path (walmart_category_path(100))";
            echo "<div class='code'>{$index_sql}</div>";
            
            $wpdb->query($index_sql); // 忽略索引添加错误（可能已存在）
            
            // 总结
            echo "<h3>更新总结</h3>";
            echo "<div class='info'>";
            echo "✅ 成功添加字段：{$success_count} 个<br>";
            if ($error_count > 0) {
                echo "❌ 添加失败字段：{$error_count} 个<br>";
            }
            echo "</div>";
            
            // 验证更新结果
            echo "<h3>验证更新结果</h3>";
            $columns_after = $wpdb->get_results("DESCRIBE {$category_map_table}");
            $existing_columns_after = array_column($columns_after, 'Field');
            $missing_columns_after = array_diff($required_columns, $existing_columns_after);
            
            if (empty($missing_columns_after)) {
                echo "<div class='success'>🎉 所有字段更新完成！现在可以正常使用"新增本地类目"功能了。</div>";
            } else {
                echo "<div class='error'>❌ 仍有字段缺失：" . implode(', ', $missing_columns_after) . "</div>";
            }
        }
        
        // 调用插件的表结构更新函数
        if (function_exists('woo_walmart_sync_update_table_structure')) {
            echo "<h3>调用插件更新函数</h3>";
            try {
                woo_walmart_sync_update_table_structure();
                echo "<div class='success'>✅ 插件表结构更新函数执行完成</div>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ 插件更新函数执行失败：" . $e->getMessage() . "</div>";
            }
        }
    }
    
    echo "<hr>";
    echo "<a href='?' class='button'>刷新页面查看最新状态</a>";
    echo "<a href='../../../wp-admin/admin.php?page=walmart-category-mapping' class='button'>前往分类映射页面测试</a>";
    
} else {
    // 显示当前状态
    echo "<h2>📊 当前表结构状态</h2>";
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$category_map_table}'") === $category_map_table;
    
    if (!$table_exists) {
        echo "<div class='error'>❌ 分类映射表不存在</div>";
        echo "<p>请先激活插件以创建基础表结构。</p>";
    } else {
        echo "<div class='success'>✅ 分类映射表存在</div>";
        
        // 检查字段
        $columns = $wpdb->get_results("DESCRIBE {$category_map_table}");
        $existing_columns = array_column($columns, 'Field');
        
        echo "<h3>当前字段列表：</h3>";
        echo "<ul>";
        foreach ($existing_columns as $column) {
            echo "<li>{$column}</li>";
        }
        echo "</ul>";
        
        // 检查缺失字段
        $required_columns = ['local_category_ids', 'created_at', 'updated_at'];
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (empty($missing_columns)) {
            echo "<div class='success'>✅ 所有必需字段都已存在</div>";
            echo "<p>表结构完整，"新增本地类目"功能应该可以正常工作。</p>";
        } else {
            echo "<div class='warning'>⚠️ 缺少以下字段：</div>";
            echo "<ul>";
            foreach ($missing_columns as $column) {
                echo "<li><strong>{$column}</strong></li>";
            }
            echo "</ul>";
            echo "<p>这些字段缺失会导致"新增本地类目"功能无法正常工作。</p>";
        }
        
        // 显示更新按钮
        if (!empty($missing_columns)) {
            echo "<form method='post'>";
            echo "<input type='hidden' name='update_structure' value='1'>";
            echo "<input type='submit' value='🔧 立即修复表结构' class='button' onclick='return confirm(\"确定要更新表结构吗？这个操作是安全的，不会影响现有数据。\")'>";
            echo "</form>";
        }
    }
    
    // 显示相关链接
    echo "<hr>";
    echo "<h3>相关链接</h3>";
    echo "<a href='../../../wp-admin/admin.php?page=walmart-category-mapping' class='button'>分类映射页面</a>";
    echo "<a href='../../../wp-admin/admin.php?page=walmart-sync-logs' class='button'>同步日志</a>";
}

?>

</body>
</html>
