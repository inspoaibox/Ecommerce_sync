<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;

echo "=== 检查分类映射的数据来源 ===\n\n";

// 1. 查看分类映射表的创建时间和数据
$category_map_table = $wpdb->prefix . 'walmart_category_map';

echo "1. 分类映射表基本信息:\n";
$table_info = $wpdb->get_results("SHOW CREATE TABLE $category_map_table");
if ($table_info) {
    echo "表结构:\n";
    echo $table_info[0]->{'Create Table'} . "\n\n";
}

// 查看所有映射记录的详细信息
$all_mappings = $wpdb->get_results("SELECT * FROM $category_map_table ORDER BY id");
echo "2. 所有分类映射记录:\n";
foreach ($all_mappings as $mapping) {
    echo "ID: {$mapping->id}\n";
    echo "WC分类: {$mapping->wc_category_name} (ID: {$mapping->wc_category_id})\n";
    echo "Walmart分类: {$mapping->walmart_category_path}\n";
    
    $attributes = json_decode($mapping->walmart_attributes, true);
    if ($attributes && isset($attributes['name'])) {
        echo "属性字段: " . implode(', ', $attributes['name']) . "\n";
        
        // 检查lagTime字段的具体配置
        $lagtime_index = array_search('lagTime', $attributes['name']);
        if ($lagtime_index !== false) {
            echo "⚠️ lagTime字段配置:\n";
            echo "  - 类型: " . ($attributes['type'][$lagtime_index] ?? '未知') . "\n";
            echo "  - 源: " . ($attributes['source'][$lagtime_index] ?? '未知') . "\n";
        }
        
        $fulfillment_index = array_search('fulfillmentLagTime', $attributes['name']);
        if ($fulfillment_index !== false) {
            echo "✓ fulfillmentLagTime字段配置:\n";
            echo "  - 类型: " . ($attributes['type'][$fulfillment_index] ?? '未知') . "\n";
            echo "  - 源: " . ($attributes['source'][$fulfillment_index] ?? '未知') . "\n";
        }
    }
    echo "---\n";
}

// 3. 查看分类映射的操作日志
echo "\n3. 分类映射相关的操作日志:\n";
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$mapping_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE action LIKE '%分类映射%' OR action LIKE '%category%'
    ORDER BY created_at DESC 
    LIMIT 10
");

foreach ($mapping_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "状态: {$log->status}\n";
    
    if (!empty($log->request)) {
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "请求数据: ";
            if (isset($request_data['walmart_category'])) {
                echo "Walmart分类: {$request_data['walmart_category']}\n";
            }
            if (isset($request_data['attributes_count'])) {
                echo "属性数量: {$request_data['attributes_count']}\n";
            }
        }
    }
    echo "---\n";
}

// 4. 检查沃尔玛API获取分类属性的函数
echo "\n4. 检查沃尔玛API获取分类属性的逻辑:\n";

// 查找get_walmart_category_attributes函数的调用
echo "查找get_walmart_category_attributes AJAX处理...\n";

// 模拟调用获取默认属性
echo "\n5. 测试获取默认属性:\n";
$test_categories = ['furniture_other', 'home_other', 'Furniture'];

foreach ($test_categories as $category) {
    echo "测试分类: {$category}\n";
    
    // 调用获取默认属性的函数
    $default_attributes = woo_walmart_get_default_attributes($category);
    if ($default_attributes) {
        echo "默认属性: " . implode(', ', $default_attributes['name'] ?? []) . "\n";
        
        // 检查是否包含lagTime
        if (in_array('lagTime', $default_attributes['name'] ?? [])) {
            echo "✓ 包含lagTime字段\n";
        }
        if (in_array('fulfillmentLagTime', $default_attributes['name'] ?? [])) {
            echo "✓ 包含fulfillmentLagTime字段\n";
        }
    } else {
        echo "未找到默认属性\n";
    }
    echo "---\n";
}

// 6. 检查是否有从沃尔玛API获取的真实属性数据
echo "\n6. 检查沃尔玛API获取的属性数据:\n";

// 查看是否有API调用日志
$api_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE action = 'API请求' AND (request LIKE '%spec%' OR request LIKE '%taxonomy%')
    ORDER BY created_at DESC 
    LIMIT 5
");

foreach ($api_logs as $log) {
    echo "API调用时间: {$log->created_at}\n";
    echo "状态: {$log->status}\n";
    
    if (strpos($log->request, 'spec') !== false) {
        echo "类型: 获取分类规范\n";
    }
    if (strpos($log->request, 'taxonomy') !== false) {
        echo "类型: 获取分类法\n";
    }
    
    // 检查响应中是否包含lagTime相关信息
    if (strpos($log->response, 'lagTime') !== false) {
        echo "✓ 响应包含lagTime相关信息\n";
    }
    if (strpos($log->response, 'fulfillmentLagTime') !== false) {
        echo "✓ 响应包含fulfillmentLagTime相关信息\n";
    }
    
    echo "---\n";
}

// 7. 总结分析
echo "\n=== 总结分析 ===\n";
echo "问题分析:\n";
echo "1. 分类映射中配置的是 'lagTime' 字段\n";
echo "2. 但API错误显示的是 'fulfillmentLagTime' 字段问题\n";
echo "3. 这说明可能存在字段名称混淆或映射错误\n";
echo "4. 需要确认:\n";
echo "   - lagTime 是 Visible 部分的字段\n";
echo "   - fulfillmentLagTime 是 Orderable 部分的字段\n";
echo "   - 两者是不同的字段，用途不同\n";
?>
