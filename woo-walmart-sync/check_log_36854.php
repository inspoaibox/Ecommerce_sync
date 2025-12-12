<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 检查日志 #36854 的详细内容 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 获取特定日志记录
$log = $wpdb->get_row("SELECT * FROM $logs_table WHERE id = 36854");

if (!$log) {
    echo "❌ 没有找到日志 #36854\n";
    exit;
}

echo "日志信息:\n";
echo "ID: {$log->id}\n";
echo "时间: {$log->created_at}\n";
echo "操作: {$log->action}\n";
echo "状态: {$log->status}\n";
echo "消息: {$log->message}\n\n";

echo "=== 请求数据 ===\n";
if ($log->request) {
    $request_data = json_decode($log->request, true);
    if ($request_data) {
        echo "请求数据结构:\n";
        echo json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // 特别检查MPItemFeedHeader
        if (isset($request_data['MPItemFeedHeader'])) {
            $header = $request_data['MPItemFeedHeader'];
            echo "🎯 发现MPItemFeedHeader:\n";
            echo json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // 详细分析每个字段
            echo "\n字段分析:\n";
            foreach ($header as $key => $value) {
                echo "  {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            }
            
            // 检查问题字段
            echo "\n问题检查:\n";
            if (isset($header['subset'])) {
                echo "❌ 发现subset字段: " . json_encode($header['subset']) . "\n";
            } else {
                echo "✅ 没有subset字段\n";
            }
            
            if (!isset($header['businessUnit'])) {
                echo "❌ 缺少businessUnit字段\n";
            } else {
                echo "✅ businessUnit: " . $header['businessUnit'] . "\n";
            }
        }
        
        // 检查MPItem数据
        if (isset($request_data['MPItem'])) {
            $items = $request_data['MPItem'];
            echo "\n📦 MPItem数据:\n";
            echo "商品数量: " . count($items) . "\n";
            
            if (!empty($items)) {
                echo "第一个商品的SKU: " . ($items[0]['sku'] ?? '未知') . "\n";
                
                // 检查第一个商品的结构
                echo "第一个商品结构:\n";
                echo json_encode($items[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    } else {
        echo "❌ 无法解析请求数据JSON\n";
        echo "原始请求数据:\n";
        echo $log->request . "\n";
    }
} else {
    echo "❌ 没有请求数据\n";
}

echo "\n=== 响应数据 ===\n";
if ($log->response) {
    $response_data = json_decode($log->response, true);
    if ($response_data) {
        echo "响应数据:\n";
        echo json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ 无法解析响应数据JSON\n";
        echo "原始响应数据:\n";
        echo $log->response . "\n";
    }
} else {
    echo "❌ 没有响应数据\n";
}

echo "\n=== 分析结论 ===\n";
echo "如果这个日志包含实际发送的数据，我们就能确定问题的根源\n";
?>
