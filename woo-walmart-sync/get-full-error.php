<?php
/**
 * 提取完整的 API 错误响应
 */

// 加载 WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('无法找到 WordPress');
}
require_once($wp_load_path);

// 检查权限
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('权限不足');
}

header('Content-Type: text/plain; charset=utf-8');

global $wpdb;

echo "========================================\n";
echo "批量 Feed 提交 - 完整错误日志\n";
echo "========================================\n\n";

// 获取最新的 5 条批量 Feed 日志
$logs = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs
     WHERE action = '批量Feed提交'
     ORDER BY created_at DESC
     LIMIT 5"
);

if (empty($logs)) {
    echo "没有找到批量Feed提交日志\n";
    exit;
}

foreach ($logs as $index => $log) {
    echo "【日志 " . ($index + 1) . "】\n";
    echo str_repeat("-", 70) . "\n";
    echo "时间: {$log->created_at}\n";
    echo "状态: {$log->status}\n";
    echo "消息: {$log->message}\n\n";

    echo "请求内容:\n";
    if (!empty($log->request)) {
        $request = json_decode($log->request, true);
        if ($request) {
            echo json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        } else {
            echo $log->request . "\n\n";
        }
    } else {
        echo "(空)\n\n";
    }

    echo "响应内容:\n";
    if (!empty($log->response)) {
        // 尝试解析为 JSON
        $response = json_decode($log->response, true);
        if ($response) {
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            // 如果不是 JSON，直接输出
            echo $log->response . "\n";
        }
    } else {
        echo "(null 或空)\n";
    }

    echo "\n" . str_repeat("=", 70) . "\n\n";
}

// 同时获取文件上传日志
echo "\n\n========================================\n";
echo "API 文件上传 - 完整日志\n";
echo "========================================\n\n";

$upload_logs = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs
     WHERE action = 'API请求-文件上传'
     ORDER BY created_at DESC
     LIMIT 3"
);

if (!empty($upload_logs)) {
    foreach ($upload_logs as $index => $log) {
        echo "【上传日志 " . ($index + 1) . "】\n";
        echo str_repeat("-", 70) . "\n";
        echo "时间: {$log->created_at}\n";
        echo "状态: {$log->status}\n\n";

        echo "响应:\n";
        if (!empty($log->response)) {
            $response = json_decode($log->response, true);
            if ($response) {
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo substr($log->response, 0, 1000) . "\n";
            }
        } else {
            echo "(null 或空)\n";
        }
        echo "\n" . str_repeat("=", 70) . "\n\n";
    }
}

// 获取最新的请求头检查日志
echo "\n\n========================================\n";
echo "请求头检查 - 最新日志\n";
echo "========================================\n\n";

$header_logs = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}woo_walmart_sync_logs
     WHERE action = '批量Feed上传-请求头检查'
     ORDER BY created_at DESC
     LIMIT 1"
);

if (!empty($header_logs)) {
    $log = $header_logs[0];
    echo "时间: {$log->created_at}\n\n";

    $request = json_decode($log->request, true);
    if ($request && isset($request['headers'])) {
        echo "实际发送的请求头:\n";
        echo str_repeat("-", 70) . "\n";
        foreach ($request['headers'] as $key => $value) {
            $display_value = $value;
            if ($key === 'WM_SEC.ACCESS_TOKEN') {
                $display_value = substr($value, 0, 50) . '...';
            }
            echo str_pad($key, 30) . ": " . $display_value . "\n";
        }
    }
}

echo "\n\n完成\n";
