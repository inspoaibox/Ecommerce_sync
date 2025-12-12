<?php
/**
 * 查询特定Feed的最终状态
 */
require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-api-key-auth.php');

header('Content-Type: text/plain; charset=utf-8');

$feed_id = $_GET['feed_id'] ?? '1879E37C01445DA0ABE32805CF0BC38F@Ae0BCgA';

echo "查询Feed状态: $feed_id\n\n";

$api_auth = new Woo_Walmart_API_Key_Auth();
$response = $api_auth->make_request("/v3/feeds/{$feed_id}", 'GET');

if (is_wp_error($response)) {
    echo "错误: " . $response->get_error_message() . "\n";
} else {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
