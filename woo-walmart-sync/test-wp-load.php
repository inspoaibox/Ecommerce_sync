<?php
// 简单测试脚本：检查WordPress是否能正常加载
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>测试</title></head><body>";
echo "<h1>WordPress加载测试</h1>";

// 1. 检查当前目录
echo "<h3>1. 当前目录</h3>";
echo "<p>" . __DIR__ . "</p>";

// 2. 检查wp-load.php路径
echo "<h3>2. wp-load.php 路径检查</h3>";
$wp_load_path = __DIR__ . '/../../../wp-load.php';
echo "<p>查找路径: {$wp_load_path}</p>";

if (file_exists($wp_load_path)) {
    echo "<p style='color:green'>✅ 文件存在</p>";
    
    // 3. 尝试加载WordPress
    echo "<h3>3. 尝试加载 WordPress</h3>";
    try {
        require_once $wp_load_path;
        echo "<p style='color:green'>✅ WordPress 加载成功</p>";
        
        // 4. 测试数据库连接
        echo "<h3>4. 测试数据库连接</h3>";
        global $wpdb;
        if ($wpdb) {
            echo "<p style='color:green'>✅ 数据库对象存在</p>";
            
            // 5. 测试查询
            echo "<h3>5. 测试数据库查询</h3>";
            $table_name = $wpdb->prefix . 'walmart_categories';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            
            if ($table_exists) {
                echo "<p style='color:green'>✅ 表 {$table_name} 存在</p>";
                
                // 检查字段
                $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
                echo "<h4>表字段列表:</h4><ul>";
                foreach ($columns as $col) {
                    echo "<li>{$col->Field} ({$col->Type})</li>";
                }
                echo "</ul>";
            } else {
                echo "<p style='color:red'>❌ 表 {$table_name} 不存在</p>";
            }
        } else {
            echo "<p style='color:red'>❌ 数据库对象不存在</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ 加载失败</p>";
        echo "<p>错误: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
} else {
    echo "<p style='color:red'>❌ 文件不存在</p>";
    
    // 尝试其他可能的路径
    echo "<h4>尝试其他路径:</h4>";
    $paths = [
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../../../../wp-load.php',
        dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php'
    ];
    
    foreach ($paths as $path) {
        echo "<p>{$path}: " . (file_exists($path) ? '✅ 存在' : '❌ 不存在') . "</p>";
    }
}

echo "</body></html>";

