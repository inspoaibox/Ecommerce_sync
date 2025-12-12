<?php
/**
 * 调试日志写入问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试日志写入问题 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 检查表结构
echo "=== 检查日志表结构 ===\n";
$table_structure = $wpdb->get_results("DESCRIBE {$logs_table}");

if ($table_structure) {
    echo "日志表字段:\n";
    foreach ($table_structure as $field) {
        echo "- {$field->Field} ({$field->Type})\n";
    }
} else {
    echo "❌ 无法获取表结构\n";
}

// 2. 测试直接数据库写入
echo "\n=== 测试直接数据库写入 ===\n";

$test_data = [
    'original_count' => 4,
    'final_count' => 5,
    'placeholder_1' => 'https://i5.walmartimages.com/asr/22de76ca-ad8a-4e01-ac5e-43509558d4cc.1d6b8a68e81c76c4ab38533f60cce2dc.png'
];

$insert_result = $wpdb->insert(
    $logs_table,
    [
        'action' => '直接测试-图片补足',
        'status' => '成功',
        'request' => wp_json_encode($test_data),
        'response' => '',
        'message' => '直接数据库写入测试',
        'product_id' => 13917,
        'created_at' => current_time('mysql')
    ],
    ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
);

if ($insert_result) {
    echo "✅ 直接数据库写入成功\n";
    echo "插入ID: " . $wpdb->insert_id . "\n";
} else {
    echo "❌ 直接数据库写入失败\n";
    echo "错误: " . $wpdb->last_error . "\n";
}

// 3. 检查woo_walmart_sync_log函数的实现
echo "\n=== 检查woo_walmart_sync_log函数 ===\n";

if (function_exists('woo_walmart_sync_log')) {
    echo "✅ 函数存在\n";
    
    // 查看函数定义
    $reflection = new ReflectionFunction('woo_walmart_sync_log');
    echo "函数文件: " . $reflection->getFileName() . "\n";
    echo "函数行号: " . $reflection->getStartLine() . "-" . $reflection->getEndLine() . "\n";
    
    // 测试函数调用并捕获错误
    echo "\n=== 测试函数调用 ===\n";
    
    try {
        $result = woo_walmart_sync_log(
            '函数测试-图片补足', 
            '成功', 
            $test_data, 
            '测试woo_walmart_sync_log函数', 
            13917
        );
        
        echo "函数调用返回: " . var_export($result, true) . "\n";
        
        // 检查是否写入成功
        $check_log = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$logs_table} 
            WHERE action = '函数测试-图片补足' 
            ORDER BY created_at DESC 
            LIMIT 1
        "));
        
        if ($check_log) {
            echo "✅ 函数调用写入成功\n";
            echo "日志ID: {$check_log->id}\n";
            echo "时间: {$check_log->created_at}\n";
        } else {
            echo "❌ 函数调用写入失败\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 函数调用异常: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "❌ 函数调用错误: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ 函数不存在\n";
}

// 4. 检查最近的实际日志记录
echo "\n=== 检查最近的实际日志 ===\n";

$recent_logs = $wpdb->get_results("
    SELECT action, status, created_at, message 
    FROM {$logs_table} 
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($recent_logs)) {
    echo "最近5条日志:\n";
    foreach ($recent_logs as $log) {
        echo "- {$log->created_at} | {$log->action} | {$log->status} | {$log->message}\n";
    }
} else {
    echo "❌ 没有找到任何日志记录\n";
}

// 5. 检查产品13917的相关日志
echo "\n=== 检查产品13917的日志 ===\n";

$product_logs = $wpdb->get_results($wpdb->prepare("
    SELECT action, status, created_at, message 
    FROM {$logs_table} 
    WHERE product_id = %d 
    ORDER BY created_at DESC 
    LIMIT 10
", 13917));

if (!empty($product_logs)) {
    echo "产品13917的日志:\n";
    foreach ($product_logs as $log) {
        echo "- {$log->created_at} | {$log->action} | {$log->status} | {$log->message}\n";
    }
} else {
    echo "❌ 没有找到产品13917的日志\n";
}

// 6. 分析问题
echo "\n=== 问题分析 ===\n";

if ($insert_result && !$check_log) {
    echo "❌ 直接数据库写入成功，但woo_walmart_sync_log函数写入失败\n";
    echo "说明问题出在woo_walmart_sync_log函数的实现上\n";
} else if (!$insert_result) {
    echo "❌ 直接数据库写入都失败，说明数据库连接或表结构有问题\n";
} else if ($check_log) {
    echo "✅ 日志写入功能正常\n";
    echo "那么为什么实际执行时没有'图片补足-4张'日志？\n";
    echo "可能的原因:\n";
    echo "1. 映射器在实际执行时没有到达第293行\n";
    echo "2. 映射器执行过程中发生了异常\n";
    echo "3. 批量处理使用了不同的映射器实例\n";
}

?>
