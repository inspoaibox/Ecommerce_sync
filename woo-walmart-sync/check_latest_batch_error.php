<?php
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';

echo "=== 检查最新的批量Feed提交失败详情 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 查看最新的批量Feed提交失败记录
echo "1. 最新的批量Feed提交失败记录:\n";

$latest_batch_error = $wpdb->get_row("
    SELECT * FROM {$logs_table} 
    WHERE action = '批量Feed提交' AND status = '失败'
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($latest_batch_error) {
    echo "时间: {$latest_batch_error->created_at}\n";
    echo "操作: {$latest_batch_error->action}\n";
    echo "状态: {$latest_batch_error->status}\n";
    echo "产品ID: {$latest_batch_error->product_id}\n";
    
    if (!empty($latest_batch_error->request)) {
        echo "\n请求数据:\n";
        $request_data = json_decode($latest_batch_error->request, true);
        if ($request_data) {
            echo "请求数据大小: " . strlen($latest_batch_error->request) . " 字符\n";
            if (isset($request_data['MPItemFeedHeader'])) {
                echo "Feed头部: " . json_encode($request_data['MPItemFeedHeader'], JSON_UNESCAPED_UNICODE) . "\n";
            }
            if (isset($request_data['MPItem']) && is_array($request_data['MPItem'])) {
                echo "产品数量: " . count($request_data['MPItem']) . "\n";
                if (!empty($request_data['MPItem'][0])) {
                    $first_item = $request_data['MPItem'][0];
                    if (isset($first_item['Orderable']['fulfillmentCenterID'])) {
                        echo "履行中心ID: " . $first_item['Orderable']['fulfillmentCenterID'] . "\n";
                    }
                    if (isset($first_item['Orderable']['sku'])) {
                        echo "第一个产品SKU: " . $first_item['Orderable']['sku'] . "\n";
                    }
                }
            }
        } else {
            echo "请求数据: " . substr($latest_batch_error->request, 0, 500) . "...\n";
        }
    }
    
    if (!empty($latest_batch_error->response)) {
        echo "\n响应数据:\n";
        $response_data = json_decode($latest_batch_error->response, true);
        if ($response_data) {
            if (isset($response_data['error'])) {
                echo "错误详情:\n";
                foreach ($response_data['error'] as $error) {
                    echo "  代码: {$error['code']}\n";
                    echo "  描述: {$error['description']}\n";
                    echo "  信息: {$error['info']}\n";
                    if (isset($error['field'])) {
                        echo "  字段: {$error['field']}\n";
                    }
                    echo "  严重性: {$error['severity']}\n";
                    echo "  ---\n";
                }
            } else {
                echo "响应: " . json_encode($response_data, JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "响应: " . substr($latest_batch_error->response, 0, 500) . "...\n";
        }
    }
    
} else {
    echo "❌ 没有找到批量Feed提交失败的记录\n";
}

// 2. 查看同一时间的相关记录
echo "\n\n2. 同一时间的相关记录:\n";

if ($latest_batch_error) {
    $related_logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$logs_table} 
        WHERE created_at BETWEEN %s AND %s
        ORDER BY created_at DESC
    ", 
    date('Y-m-d H:i:s', strtotime($latest_batch_error->created_at) - 60),
    date('Y-m-d H:i:s', strtotime($latest_batch_error->created_at) + 60)
    ));
    
    foreach ($related_logs as $log) {
        echo "时间: {$log->created_at} | 操作: {$log->action} | 状态: {$log->status}\n";
        
        if ($log->action == 'API请求-文件上传' && !empty($log->request)) {
            echo "  API请求详情:\n";
            $request_data = json_decode($log->request, true);
            if ($request_data && isset($request_data['headers'])) {
                foreach ($request_data['headers'] as $header) {
                    if (strpos($header, 'WM_CONSUMER.CHANNEL.TYPE') !== false) {
                        echo "    Channel Type头部: {$header}\n";
                    }
                }
            }
        }
        
        if ($log->status == 'Bad Request' && !empty($log->response)) {
            $response_data = json_decode($log->response, true);
            if ($response_data && isset($response_data['error'])) {
                foreach ($response_data['error'] as $error) {
                    if (isset($error['field']) && $error['field'] == 'WM_CONSUMER.CHANNEL.TYPE') {
                        echo "  ❌ Channel Type错误: {$error['description']}\n";
                    }
                }
            }
        }
        
        echo "  ---\n";
    }
}

// 3. 检查当前的履行中心ID设置
echo "\n\n3. 当前的履行中心ID设置:\n";

$fc_id = get_option('woo_walmart_fulfillment_center_id', '');
$ca_fc_id = get_option('woo_walmart_CA_fulfillment_center_id', '');
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');

echo "通用履行中心ID: " . ($fc_id ?: '未设置') . "\n";
echo "加拿大履行中心ID: " . ($ca_fc_id ?: '未设置') . "\n";
echo "业务单元: {$business_unit}\n";

// 4. 检查Channel Type设置
echo "\n4. 当前的Channel Type设置:\n";

$channel_type_options = [
    'woo_walmart_channel_type',
    'woo_walmart_ca_channel_type', 
    'woo_walmart_consumer_channel_type'
];

foreach ($channel_type_options as $option) {
    $value = get_option($option, '');
    echo "{$option}: " . ($value ?: '未设置') . "\n";
}

echo "\n=== 检查完成 ===\n";
