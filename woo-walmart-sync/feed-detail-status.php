<?php
/**
 * 查询Feed详细状态和Item错误
 */
require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-api-key-auth.php');

header('Content-Type: text/plain; charset=utf-8');

$feed_id = $_GET['feed_id'] ?? '1879E37C01445DA0ABE32805CF0BC38F@Ae0BCgA';

echo "查询Feed详细状态: $feed_id\n";
echo str_repeat("=", 70) . "\n\n";

$api_auth = new Woo_Walmart_API_Key_Auth();

// 1. 基本状态
$response = $api_auth->make_request("/v3/feeds/{$feed_id}", 'GET');

if (is_wp_error($response)) {
    echo "错误: " . $response->get_error_message() . "\n";
    exit;
}

echo "📊 Feed状态:\n";
echo "  - Status: " . $response['feedStatus'] . "\n";
echo "  - itemsReceived: " . $response['itemsReceived'] . "\n";
echo "  - itemsSucceeded: " . $response['itemsSucceeded'] . "\n";
echo "  - itemsFailed: " . $response['itemsFailed'] . "\n\n";

// 2. 尝试获取itemIngestionStatus详情
if (!empty($response['itemDetails']['itemIngestionStatus'])) {
    echo "📋 Item详情:\n";
    foreach ($response['itemDetails']['itemIngestionStatus'] as $item) {
        echo "  SKU: " . ($item['sku'] ?? 'N/A') . "\n";
        echo "  Status: " . ($item['ingestionStatus'] ?? 'N/A') . "\n";
        if (!empty($item['ingestionErrors'])) {
            echo "  Errors:\n";
            foreach ($item['ingestionErrors']['ingestionError'] as $err) {
                echo "    - Code: " . ($err['code'] ?? 'N/A') . "\n";
                echo "      Field: " . ($err['field'] ?? 'N/A') . "\n";
                echo "      Desc: " . ($err['description'] ?? 'N/A') . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "⚠️ itemIngestionStatus 为空\n\n";

    // 尝试带参数获取详情
    echo "尝试带参数获取详情...\n";
    $response2 = $api_auth->make_request("/v3/feeds/{$feed_id}?includeDetails=true", 'GET');

    if (!is_wp_error($response2)) {
        echo json_encode($response2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
