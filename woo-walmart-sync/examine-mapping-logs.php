<?php
/**
 * 详细检查产品映射日志的内容
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 详细检查产品映射日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找最近的产品映射-最终数据结构日志
$mapping_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品映射-最终数据结构'
    AND created_at >= '2025-08-10 15:20:00'
    ORDER BY created_at DESC 
    LIMIT 5
");

echo "找到 " . count($mapping_logs) . " 条产品映射日志\n\n";

foreach ($mapping_logs as $log) {
    echo "=== 时间: {$log->created_at} ===\n";
    
    $request_data = json_decode($log->request, true);
    if (!$request_data) {
        echo "❌ 无法解析JSON数据\n";
        echo "原始数据长度: " . strlen($log->request) . "\n";
        echo "原始数据前200字符: " . substr($log->request, 0, 200) . "...\n";
        continue;
    }
    
    // 检查MPItem结构
    if (isset($request_data['MPItem']['Visible'])) {
        $visible_data = $request_data['MPItem']['Visible'];
        
        foreach ($visible_data as $category => $data) {
            echo "分类: {$category}\n";
            
            if (isset($data['sku'])) {
                echo "SKU: {$data['sku']}\n";
            }
            
            // 重点检查图片字段
            if (isset($data['productSecondaryImageURL'])) {
                $images = $data['productSecondaryImageURL'];
                echo "✅ 有productSecondaryImageURL字段\n";
                echo "副图数量: " . count($images) . "\n";
                
                if (count($images) < 5) {
                    echo "❌ 副图不足5张！实际副图:\n";
                    foreach ($images as $i => $url) {
                        echo "  " . ($i + 1) . ". " . $url . "\n";
                    }
                } else {
                    echo "✅ 副图充足\n";
                    echo "前3张副图:\n";
                    for ($i = 0; $i < min(3, count($images)); $i++) {
                        echo "  " . ($i + 1) . ". " . $images[$i] . "\n";
                    }
                }
            } else {
                echo "❌ 缺少productSecondaryImageURL字段！\n";
                
                // 列出所有可用字段
                echo "可用字段: " . implode(', ', array_keys($data)) . "\n";
            }
            
            // 检查主图
            if (isset($data['mainImageUrl'])) {
                echo "✅ 主图: " . substr($data['mainImageUrl'], 0, 60) . "...\n";
            } else {
                echo "❌ 缺少主图\n";
            }
        }
    } else {
        echo "❌ 没有Visible数据结构\n";
        echo "可用的顶级字段: " . implode(', ', array_keys($request_data)) . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

// 如果没有找到映射日志，查找其他相关日志
if (empty($mapping_logs)) {
    echo "没有找到产品映射日志，查找其他相关日志...\n";
    
    $other_logs = $wpdb->get_results("
        SELECT * FROM {$logs_table} 
        WHERE action LIKE '%产品%' 
        AND created_at >= '2025-08-10 15:00:00'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    foreach ($other_logs as $log) {
        echo "{$log->created_at} - {$log->action} ({$log->status})\n";
    }
}

// 检查是否有图片处理的错误日志
echo "\n=== 检查图片处理错误 ===\n";

$error_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE (status = '错误' OR status = '警告')
    AND (action LIKE '%图片%' OR message LIKE '%图片%')
    AND created_at >= '2025-08-10 15:00:00'
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($error_logs)) {
    foreach ($error_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        echo "消息: {$log->message}\n";
        echo "---\n";
    }
} else {
    echo "没有找到图片相关的错误日志\n";
}

?>
