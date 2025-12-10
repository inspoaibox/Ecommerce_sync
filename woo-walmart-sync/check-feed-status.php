<?php
/**
 * 查询Feed状态
 * 用于查询之前提交的Feed处理结果
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-api-key-auth.php');

header('Content-Type: text/html; charset=utf-8');

echo "<h1>📊 Feed状态查询</h1>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
    h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #0066cc; white-space: pre-wrap; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: top; }
    th { background: #0066cc; color: white; }
</style>";

// 从URL参数获取Feed ID，或使用最新的
$feed_id = $_GET['feed_id'] ?? '1879B88118445D5083FF96680E594F62@Ae0BCgA';

echo "<div class='section'>";
echo "<h2>🔍 查询参数</h2>";
echo "<p><strong>Feed ID:</strong> {$feed_id}</p>";
echo "</div>";

// 查询状态
$api_auth = new Woo_Walmart_API_Key_Auth();

try {
    echo "<div class='section'>";
    echo "<h2>⏳ 正在查询...</h2>";

    $status_response = $api_auth->make_request("/v3/feeds/{$feed_id}", 'GET');

    if (is_wp_error($status_response)) {
        echo "<p class='error'>✗ 查询失败</p>";
        echo "<p><strong>错误:</strong> " . htmlspecialchars($status_response->get_error_message()) . "</p>";
    } else {
        echo "<h2>📊 Feed状态</h2>";
        echo "<pre>" . json_encode($status_response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

        if (isset($status_response['feedStatus'])) {
            $status = $status_response['feedStatus'];

            if ($status === 'ERROR') {
                echo "<p class='error'>✗ Feed处理失败</p>";

                if (isset($status_response['ingestionErrors']['ingestionError'])) {
                    echo "<h3>错误详情</h3>";
                    echo "<table>";
                    echo "<tr><th>类型</th><th>代码</th><th>字段</th><th>描述</th></tr>";

                    $errors = $status_response['ingestionErrors']['ingestionError'];
                    if (!isset($errors[0])) {
                        $errors = [$errors];
                    }

                    foreach ($errors as $error) {
                        echo "<tr>";
                        echo "<td>" . ($error['type'] ?? 'N/A') . "</td>";
                        echo "<td><strong>" . ($error['code'] ?? 'N/A') . "</strong></td>";
                        echo "<td>" . ($error['field'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($error['description'] ?? 'N/A') . "</td>";
                        echo "</tr>";
                    }

                    echo "</table>";
                }
            } elseif ($status === 'PROCESSED') {
                echo "<p class='success'>✓ Feed处理成功！</p>";

                echo "<table>";
                echo "<tr><th>统计项</th><th>数量</th></tr>";
                echo "<tr><td>已接收</td><td>" . ($status_response['itemsReceived'] ?? 0) . "</td></tr>";
                echo "<tr><td>成功</td><td class='success'>" . ($status_response['itemsSucceeded'] ?? 0) . "</td></tr>";
                echo "<tr><td>失败</td><td class='error'>" . ($status_response['itemsFailed'] ?? 0) . "</td></tr>";
                echo "<tr><td>处理中</td><td>" . ($status_response['itemsProcessing'] ?? 0) . "</td></tr>";
                echo "</table>";

                // 显示成功的产品
                if (isset($status_response['itemDetails']['itemIngestionStatus'])) {
                    $items = $status_response['itemDetails']['itemIngestionStatus'];
                    if (!empty($items)) {
                        echo "<h3>产品详情</h3>";
                        echo "<table>";
                        echo "<tr><th>SKU</th><th>状态</th><th>Product ID</th></tr>";

                        foreach ($items as $item) {
                            $item_status = $item['ingestionStatus'] ?? 'N/A';
                            $status_class = ($item_status === 'SUCCESS') ? 'success' : 'error';

                            echo "<tr>";
                            echo "<td>" . ($item['sku'] ?? 'N/A') . "</td>";
                            echo "<td class='{$status_class}'>{$item_status}</td>";
                            echo "<td>" . ($item['wpid'] ?? 'N/A') . "</td>";
                            echo "</tr>";
                        }

                        echo "</table>";
                    }
                }
            } elseif ($status === 'INPROGRESS') {
                echo "<p class='warning'>⏳ Feed正在处理中...</p>";
                echo "<p>请等待几分钟后刷新页面查看结果</p>";
            } else {
                echo "<p class='warning'>⏳ Feed状态: {$status}</p>";
            }
        }
    }

    echo "</div>";

} catch (Exception $e) {
    echo "<p class='error'>✗ 异常错误</p>";
    echo "<p><strong>错误:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<div class='section' style='text-align: center; color: #666;'>";
echo "<p>查询时间: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>提示: 在URL后添加 ?feed_id=YOUR_FEED_ID 查询特定Feed</p>";
echo "</div>";
