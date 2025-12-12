<?php
// 检查Feed的实际状态

// 尝试加载WordPress
$wp_load_paths = [
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '../wp-load.php'
];

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!function_exists('get_option')) {
    die('请通过WordPress环境访问此脚本');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Feed状态检查 ===\n\n";

// 目标Feed ID
$feed_ids = [
    '1861B138A35454308C110B242AFE82B5@AXkBCgA',
    '1861B140EF5B55A08A28BADFB042474B@AXkBCgA'
];

// 初始化API认证
$api_auth = new Woo_Walmart_API_Key_Auth();

foreach ($feed_ids as $i => $feed_id) {
    echo "🔍 检查Feed " . ($i + 1) . ": $feed_id\n";
    echo str_repeat('-', 80) . "\n";
    
    // 调用API检查Feed状态
    $endpoint = "/v3/feeds/{$feed_id}?includeDetails=true";
    $result = $api_auth->make_request($endpoint);
    
    if (is_wp_error($result)) {
        echo "❌ API调用失败: " . $result->get_error_message() . "\n\n";
        continue;
    }
    
    if (empty($result)) {
        echo "❌ API返回空结果\n\n";
        continue;
    }
    
    echo "✅ API调用成功\n";
    echo "Feed状态信息:\n";
    
    // 显示基本状态信息
    $feed_status = $result['feedStatus'] ?? '未知';
    echo "  feedStatus: $feed_status\n";
    
    if (isset($result['itemsReceived'])) {
        echo "  itemsReceived: {$result['itemsReceived']}\n";
    }
    if (isset($result['itemsSucceeded'])) {
        echo "  itemsSucceeded: {$result['itemsSucceeded']}\n";
    }
    if (isset($result['itemsFailed'])) {
        echo "  itemsFailed: {$result['itemsFailed']}\n";
    }
    if (isset($result['itemsProcessing'])) {
        echo "  itemsProcessing: {$result['itemsProcessing']}\n";
    }
    
    // 分析状态
    echo "\n状态分析:\n";
    $items_received = $result['itemsReceived'] ?? 0;
    $items_succeeded = $result['itemsSucceeded'] ?? 0;
    $items_failed = $result['itemsFailed'] ?? 0;
    $items_processing = $result['itemsProcessing'] ?? 0;
    
    $total_processed = $items_succeeded + $items_failed;
    echo "  总接收: $items_received\n";
    echo "  已处理: $total_processed\n";
    echo "  处理中: $items_processing\n";
    
    if ($items_processing > 0) {
        echo "  ⚠️ 还有商品在处理中，Feed未完全完成\n";
    } elseif ($total_processed === $items_received && $items_received > 0) {
        echo "  ✅ 所有商品都已处理完成\n";
        echo "  💡 这个Feed应该被标记为完成状态\n";
    } else {
        echo "  ❓ 状态不明确\n";
    }
    
    // 显示详细的商品状态（如果有）
    if (isset($result['itemDetails']['itemIngestionStatus']) && is_array($result['itemDetails']['itemIngestionStatus'])) {
        echo "\n商品详细状态统计:\n";
        $status_counts = [];
        foreach ($result['itemDetails']['itemIngestionStatus'] as $item) {
            $status = $item['ingestionStatus'] ?? '未知';
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
        }
        
        foreach ($status_counts as $status => $count) {
            echo "  $status: $count 个商品\n";
        }
        
        // 检查是否所有商品都已处理完成
        $unfinished_statuses = ['INPROGRESS', 'PROCESSING'];
        $has_unfinished = false;
        foreach ($unfinished_statuses as $unfinished_status) {
            if (isset($status_counts[$unfinished_status]) && $status_counts[$unfinished_status] > 0) {
                $has_unfinished = true;
                break;
            }
        }
        
        if (!$has_unfinished) {
            echo "  ✅ 所有商品都已完成处理（SUCCESS或ERROR状态）\n";
            echo "  💡 这个批次应该被更新为COMPLETED状态\n";
        } else {
            echo "  ⚠️ 还有商品在处理中\n";
        }
    }
    
    echo "\n" . str_repeat('=', 80) . "\n\n";
}

// 手动触发状态更新测试
if (isset($_GET['update']) && $_GET['update'] === '1') {
    echo "🔄 手动触发批次状态更新...\n\n";
    
    $sync = new Woo_Walmart_Product_Sync();
    $sync->check_batch_feed_statuses();
    
    echo "✅ 批次状态更新已触发\n";
    echo "💡 请刷新队列管理页面查看结果\n";
} else {
    echo "💡 添加 ?update=1 参数来手动触发批次状态更新\n";
}

echo "\n=== 检查完成 ===\n";
?>
