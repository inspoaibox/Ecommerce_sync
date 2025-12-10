<?php
/**
 * 测试当前Feed数据提交到Walmart API
 * 查看具体的错误信息
 */

require_once(__DIR__ . '/../../../wp-load.php');
require_once(__DIR__ . '/includes/class-product-mapper.php');

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧪 Walmart CA API 测试同步</h1>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; }
    h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
    pre { background: #f8f8f8; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #0066cc; white-space: pre-wrap; word-wrap: break-word; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #0066cc; color: white; }
</style>";

// 获取测试产品
$product_id = 47;
$product = wc_get_product($product_id);

if (!$product) {
    echo "<p class='error'>产品不存在</p>";
    exit;
}

echo "<div class='section'>";
echo "<h2>📦 测试产品</h2>";
echo "<p><strong>ID:</strong> {$product_id}</p>";
echo "<p><strong>Name:</strong> {$product->get_name()}</p>";
echo "<p><strong>SKU:</strong> {$product->get_sku()}</p>";
echo "</div>";

// 生成Feed数据
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$product_cat_ids = $product->get_category_ids();

$mapped_category = null;
foreach ($product_cat_ids as $cat_id) {
    $mapping = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$map_table} WHERE wc_category_id = %d",
        $cat_id
    ), ARRAY_A);

    if ($mapping) {
        $mapped_category = $mapping;
        break;
    }
}

if (!$mapped_category) {
    echo "<p class='error'>未找到分类映射</p>";
    exit;
}

// 加载属性规则
$attribute_rules = !empty($mapped_category['walmart_attributes'])
    ? json_decode($mapped_category['walmart_attributes'], true)
    : null;

if (empty($attribute_rules) || !isset($attribute_rules['name'])) {
    echo "<p class='error'>未找到属性映射规则</p>";
    exit;
}

// 生成Feed
$mapper = new Woo_Walmart_Product_Mapper();
$walmart_data = $mapper->map(
    $product,
    $mapped_category['walmart_category_path'],
    '123456789012',  // 测试UPC
    $attribute_rules,
    1,
    'CA'  // 加拿大市场
);

echo "<div class='section'>";
echo "<h2>📄 生成的Feed数据</h2>";
echo "<pre>" . json_encode($walmart_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";
echo "<p><strong>JSON大小:</strong> " . number_format(strlen(json_encode($walmart_data))) . " bytes</p>";
echo "</div>";

// 提交到Walmart API
echo "<div class='section'>";
echo "<h2>🚀 提交到Walmart CA API</h2>";

// 获取API配置
$client_id = get_option('woo_walmart_client_id');
$client_secret = get_option('woo_walmart_client_secret');
$business_unit = get_option('woo_walmart_business_unit', 'WALMART_CA');

if (empty($client_id) || empty($client_secret)) {
    echo "<p class='error'>✗ API凭据未配置</p>";
    echo "</div>";
    exit;
}

echo "<p><strong>Business Unit:</strong> {$business_unit}</p>";

// 使用API认证类提交Feed
require_once(__DIR__ . '/includes/class-api-key-auth.php');
require_once(__DIR__ . '/includes/class-multi-market-config.php');

$api_auth = new Woo_Walmart_API_Key_Auth();

try {
    // 获取正确的feedType
    $feed_type = Woo_Walmart_Multi_Market_Config::get_market_feed_type('CA', 'item');

    echo "<p><strong>Feed Type:</strong> {$feed_type}</p>";

    // 调用Feed API
    $response = $api_auth->make_file_upload_request("/v3/feeds?feedType={$feed_type}", $walmart_data, 'item_feed.json');

    if (is_wp_error($response)) {
        echo "<p class='error'>✗ API调用失败</p>";
        echo "<p><strong>错误代码:</strong> " . $response->get_error_code() . "</p>";
        echo "<p><strong>错误信息:</strong> " . htmlspecialchars($response->get_error_message()) . "</p>";
        echo "</div>";
        exit;
    }

    echo "<h3>✅ API响应</h3>";
    echo "<pre>" . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

    if (isset($response['feedId'])) {
        echo "<p class='success'>✓ Feed提交成功！</p>";
        echo "<p><strong>Feed ID:</strong> {$response['feedId']}</p>";

        // 等待5秒后查询状态
        echo "<p>等待5秒后查询Feed状态...</p>";
        flush();
        sleep(5);

        $status_response = $api_auth->make_request("/v3/feeds/{$response['feedId']}", 'GET');

        if (is_wp_error($status_response)) {
            echo "<p class='error'>✗ 状态查询失败: " . $status_response->get_error_message() . "</p>";
        } else {
            echo "<h3>📊 Feed状态</h3>";
            echo "<pre>" . json_encode($status_response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";

            if (isset($status_response['feedStatus']) && $status_response['feedStatus'] === 'ERROR') {
                echo "<p class='error'>✗ Feed处理失败</p>";

                if (isset($status_response['ingestionErrors']['ingestionError'])) {
                    echo "<h4>错误详情：</h4>";
                    echo "<table>";
                    echo "<tr><th>类型</th><th>代码</th><th>字段</th><th>描述</th></tr>";

                    $errors = $status_response['ingestionErrors']['ingestionError'];
                    if (!isset($errors[0])) {
                        $errors = [$errors];
                    }

                    foreach ($errors as $error) {
                        echo "<tr>";
                        echo "<td>" . ($error['type'] ?? 'N/A') . "</td>";
                        echo "<td>" . ($error['code'] ?? 'N/A') . "</td>";
                        echo "<td>" . ($error['field'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($error['description'] ?? 'N/A') . "</td>";
                        echo "</tr>";
                    }

                    echo "</table>";
                }
            } elseif (isset($status_response['feedStatus']) && $status_response['feedStatus'] === 'PROCESSED') {
                echo "<p class='success'>✓ Feed处理成功！</p>";

                if (isset($status_response['itemsSucceeded']) && $status_response['itemsSucceeded'] > 0) {
                    echo "<p class='success'>✓ {$status_response['itemsSucceeded']} 个产品同步成功！</p>";
                }

                if (isset($status_response['itemsFailed']) && $status_response['itemsFailed'] > 0) {
                    echo "<p class='error'>✗ {$status_response['itemsFailed']} 个产品同步失败</p>";
                }
            } elseif (isset($status_response['feedStatus'])) {
                echo "<p class='warning'>⏳ Feed状态: {$status_response['feedStatus']}</p>";
                echo "<p>Feed可能仍在处理中，请稍后手动查询状态</p>";
            }
        }
    }

} catch (Exception $e) {
    echo "<p class='error'>✗ API调用失败</p>";
    echo "<p><strong>错误信息:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>";

echo "<div class='section' style='text-align: center; color: #666;'>";
echo "<p>测试时间: " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";
