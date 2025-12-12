<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 检查批量同步日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找最近的批量产品名称同步日志
echo "1. 查找最近的批量产品名称同步日志:\n";
$batch_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE action LIKE '%批量产品名称同步%' 
    ORDER BY created_at DESC 
    LIMIT 10
");

foreach ($batch_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "状态: {$log->status}\n";
    
    if ($log->action === '批量产品名称同步-Feed调用') {
        echo "这是Feed调用日志\n";
        $request_data = json_decode($log->request, true);
        if ($request_data && isset($request_data['product_data'])) {
            echo "产品数量: " . count($request_data['product_data']) . "\n";
            echo "第一个产品: " . json_encode($request_data['product_data'][0] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    if ($log->action === '批量产品名称同步-Feed响应') {
        echo "这是Feed响应日志\n";
        $response_data = json_decode($log->request, true);
        if ($response_data && isset($response_data['api_result'])) {
            $api_result = $response_data['api_result'];
            echo "API结果: " . json_encode($api_result, JSON_UNESCAPED_UNICODE) . "\n";
            
            if (isset($api_result['feedId'])) {
                echo "Feed ID: {$api_result['feedId']}\n";
            }
        }
    }
    
    echo "---\n";
}

// 查找最近的文件上传日志
echo "\n2. 查找最近的文件上传日志:\n";
$upload_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE action LIKE '%文件上传%' 
    ORDER BY created_at DESC 
    LIMIT 5
");

foreach ($upload_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    
    if ($log->action === '文件上传方法-开始') {
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "端点: " . ($request_data['endpoint'] ?? '未知') . "\n";
            echo "文件名: " . ($request_data['filename'] ?? '未知') . "\n";
            echo "数据大小: " . ($request_data['data_size'] ?? '未知') . " 字节\n";
        }
    }
    
    echo "---\n";
}

// 查找Feed ID为185845E141DE5890BF76FEDCE600B011@AXkBCgA的相关日志
echo "\n3. 查找特定Feed ID的相关日志:\n";
$feed_id = '185845E141DE5890BF76FEDCE600B011@AXkBCgA';
$feed_logs = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $logs_table 
    WHERE request LIKE %s OR response LIKE %s
    ORDER BY created_at DESC 
    LIMIT 5
", '%' . $feed_id . '%', '%' . $feed_id . '%'));

foreach ($feed_logs as $log) {
    echo "时间: {$log->created_at}\n";
    echo "操作: {$log->action}\n";
    echo "状态: {$log->status}\n";
    
    // 检查是否包含实际发送的数据
    if (strpos($log->request, 'MPItemFeedHeader') !== false) {
        echo "包含MPItemFeedHeader数据\n";
        $request_data = json_decode($log->request, true);
        if ($request_data && isset($request_data['MPItemFeedHeader'])) {
            $header = $request_data['MPItemFeedHeader'];
            echo "实际发送的Header: " . json_encode($header, JSON_UNESCAPED_UNICODE) . "\n";
            
            // 检查问题字段
            if (isset($header['subset'])) {
                echo "⚠️ 发现subset字段: " . json_encode($header['subset']) . "\n";
            }
            if (!isset($header['businessUnit'])) {
                echo "⚠️ 缺少businessUnit字段\n";
            } else {
                echo "✅ businessUnit: " . $header['businessUnit'] . "\n";
            }
        }
    }
    
    echo "---\n";
}

// 检查是否有其他地方在修改MPItemFeedHeader
echo "\n4. 检查是否有其他地方在修改MPItemFeedHeader:\n";

// 检查make_file_upload_request方法是否有修改逻辑
$api_auth_file = 'includes/class-api-key-auth.php';
$content = file_get_contents($api_auth_file);

echo "检查make_file_upload_request方法:\n";
if (strpos($content, 'subset') !== false) {
    echo "⚠️ make_file_upload_request方法中发现subset引用\n";
    
    // 查找具体位置
    $lines = explode("\n", $content);
    foreach ($lines as $line_num => $line) {
        if (strpos($line, 'subset') !== false) {
            echo "  第" . ($line_num + 1) . "行: " . trim($line) . "\n";
        }
    }
} else {
    echo "✅ make_file_upload_request方法中没有subset引用\n";
}

// 检查是否有其他地方在处理JSON数据
echo "\n检查JSON处理逻辑:\n";
if (strpos($content, 'wp_json_encode') !== false) {
    echo "✅ 使用wp_json_encode进行JSON编码\n";
} else {
    echo "⚠️ 可能使用其他JSON编码方法\n";
}

echo "\n=== 检查完成 ===\n";
echo "如果日志显示实际发送的Header包含subset字段或缺少businessUnit，\n";
echo "说明问题不在bulk_update_product_info方法，而在其他地方\n";
?>
