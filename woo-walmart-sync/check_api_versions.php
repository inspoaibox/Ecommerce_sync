<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查API版本差异 ===\n\n";

// 1. 检查代码中的版本信息
echo "1. 代码中的版本信息:\n";

// 查看API调用中的版本参数
$api_files = [
    'includes/class-api-key-auth.php',
    'includes/class-product-sync.php',
    'includes/class-product-mapper.php'
];

foreach ($api_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // 查找版本相关信息
        if (preg_match_all('/feedType.*MP_ITEM.*version.*[0-9.]+/i', $content, $matches)) {
            echo "文件: {$file}\n";
            foreach ($matches[0] as $match) {
                echo "  版本信息: {$match}\n";
            }
        }
        
        // 查找lagTime和fulfillmentLagTime的使用
        if (strpos($content, 'lagTime') !== false) {
            echo "文件: {$file}\n";
            if (strpos($content, 'fulfillmentLagTime') !== false) {
                echo "  ✓ 包含 fulfillmentLagTime\n";
            }
            if (preg_match('/[^a-zA-Z]lagTime[^a-zA-Z]/', $content)) {
                echo "  ✓ 包含 lagTime\n";
            }
        }
    }
}

// 2. 检查沃尔玛官方文档或API响应中的版本信息
echo "\n2. API调用中的版本信息:\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找API调用日志中的版本信息
$api_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE action = 'API请求' 
    AND (request LIKE '%feedType%' OR request LIKE '%version%')
    ORDER BY created_at DESC 
    LIMIT 5
");

foreach ($api_logs as $log) {
    echo "时间: {$log->created_at}\n";
    
    // 查找feedType和version
    if (preg_match('/feedType[=:]([^&\s]+)/', $log->request, $matches)) {
        echo "feedType: {$matches[1]}\n";
    }
    if (preg_match('/version[=:]([^&\s]+)/', $log->request, $matches)) {
        echo "version: {$matches[1]}\n";
    }
    
    echo "---\n";
}

// 3. 检查错误响应中的版本提示
echo "\n3. API错误响应中的版本信息:\n";

$error_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE (status = '错误' OR status = 'error') 
    AND response LIKE '%fulfillmentLagTime%'
    ORDER BY created_at DESC 
    LIMIT 3
");

foreach ($error_logs as $log) {
    echo "错误时间: {$log->created_at}\n";
    
    // 查找版本相关的错误信息
    if (preg_match('/version.*[0-9.]+/i', $log->response, $matches)) {
        echo "版本信息: {$matches[0]}\n";
    }
    
    // 查找字段相关的错误描述
    if (preg_match('/fulfillmentLagTime.*?description["\']:\s*["\']([^"\']+)["\']/', $log->response, $matches)) {
        echo "fulfillmentLagTime错误: {$matches[1]}\n";
    }
    
    echo "---\n";
}

// 4. 对比lagTime和fulfillmentLagTime的使用场景
echo "\n4. 字段使用场景分析:\n";

// 查看mapper中的字段处理
require_once 'includes/class-product-mapper.php';
$mapper_content = file_get_contents('includes/class-product-mapper.php');

echo "在product-mapper.php中:\n";

// 查找fulfillmentLagTime的使用
if (preg_match_all("/['\"]fulfillmentLagTime['\"].*?=>/", $mapper_content, $matches)) {
    echo "fulfillmentLagTime使用位置:\n";
    foreach ($matches[0] as $match) {
        echo "  - {$match}\n";
    }
}

// 查找lagTime的处理
if (preg_match_all("/case\s+['\"].*lagtime.*['\"]:/i", $mapper_content, $matches)) {
    echo "lagTime处理位置:\n";
    foreach ($matches[0] as $match) {
        echo "  - {$match}\n";
    }
}

// 5. 检查沃尔玛API规范文档
echo "\n5. 检查API规范版本:\n";

// 查看是否有保存的API规范文件
$spec_files = glob('*spec*.json');
foreach ($spec_files as $file) {
    echo "规范文件: {$file}\n";
    $spec_content = file_get_contents($file);
    $spec_data = json_decode($spec_content, true);
    
    if ($spec_data) {
        // 查找版本信息
        if (isset($spec_data['version'])) {
            echo "  版本: {$spec_data['version']}\n";
        }
        
        // 查找字段定义
        $spec_text = json_encode($spec_data);
        if (strpos($spec_text, 'fulfillmentLagTime') !== false) {
            echo "  ✓ 包含 fulfillmentLagTime 字段\n";
        }
        if (strpos($spec_text, '"lagTime"') !== false) {
            echo "  ✓ 包含 lagTime 字段\n";
        }
    }
    echo "---\n";
}

// 6. 总结版本差异
echo "\n=== 版本差异总结 ===\n";
echo "可能的情况:\n";
echo "1. 旧版本API使用 'lagTime' 字段\n";
echo "2. 新版本API使用 'fulfillmentLagTime' 字段\n";
echo "3. 或者两个字段在不同部分使用:\n";
echo "   - lagTime: Visible部分的产品属性\n";
echo "   - fulfillmentLagTime: Orderable部分的履行属性\n";
echo "4. 当前代码可能混用了两个版本的字段名\n";

// 7. 检查当前使用的API版本
echo "\n7. 当前API调用版本:\n";
require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();

// 查看最近的API调用URL
$recent_api_logs = $wpdb->get_results("
    SELECT request FROM $logs_table 
    WHERE action = 'API请求' 
    ORDER BY created_at DESC 
    LIMIT 3
");

foreach ($recent_api_logs as $log) {
    if (preg_match('/POST\s+([^\s]+)/', $log->request, $matches)) {
        echo "API端点: {$matches[1]}\n";
    }
    if (preg_match('/feedType=([^&\s]+)/', $log->request, $matches)) {
        echo "feedType: {$matches[1]}\n";
    }
}
?>
