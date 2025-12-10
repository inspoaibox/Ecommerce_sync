<?php
// 测试重新同步之前失败的产品

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

echo "=== 测试重新同步失败的产品 ===\n\n";

// 之前失败的SKU
$failed_skus = ['B2726S00512', 'B2741S00266'];

foreach ($failed_skus as $sku) {
    echo "🔄 重新测试SKU: $sku\n";
    
    global $wpdb;
    $product_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
        $sku
    ));
    
    if (!$product_id) {
        echo "❌ 产品未找到\n\n";
        continue;
    }
    
    $product = wc_get_product($product_id);
    
    echo "产品信息:\n";
    echo "  ID: $product_id\n";
    echo "  名称: " . $product->get_name() . "\n";
    echo "  SKU: $sku\n";
    
    // 测试关键字段的生成
    echo "\n关键字段测试:\n";
    if (function_exists('handle_auto_generate_field')) {
        $test_fields = [
            'fulfillmentCenterID',
            'fulfillmentLagTime',
            'releaseDate',
            'startDate',
            'endDate'
        ];
        
        foreach ($test_fields as $field) {
            $value = handle_auto_generate_field($product, $field);
            echo "  $field: " . ($value ?: '空值') . "\n";
        }
    }
    
    // 模拟生成完整的产品数据（简化版）
    echo "\n模拟产品数据生成:\n";
    $mock_data = [
        'sku' => $sku,
        'fulfillmentCenterID' => handle_auto_generate_field($product, 'fulfillmentCenterID'),
        'fulfillmentLagTime' => handle_auto_generate_field($product, 'fulfillmentLagTime'),
        'releaseDate' => handle_auto_generate_field($product, 'releaseDate'),
        'startDate' => handle_auto_generate_field($product, 'startDate'),
        'endDate' => handle_auto_generate_field($product, 'endDate')
    ];
    
    echo "  生成的数据: " . json_encode($mock_data, JSON_UNESCAPED_UNICODE) . "\n";
    
    // 检查是否所有必需字段都有值
    $missing_fields = [];
    foreach ($mock_data as $field => $value) {
        if (empty($value) && $field !== 'fulfillmentLagTime') { // fulfillmentLagTime可以为0
            $missing_fields[] = $field;
        }
    }
    
    if (empty($missing_fields)) {
        echo "✅ 所有关键字段都有值，应该可以成功同步\n";
    } else {
        echo "❌ 缺少字段: " . implode(', ', $missing_fields) . "\n";
    }
    
    echo "\n" . str_repeat('-', 80) . "\n\n";
}

echo "=== 建议 ===\n";
echo "1. 现在可以尝试重新同步这些失败的产品\n";
echo "2. 在WordPress后台找到这些产品，重新提交到沃尔玛\n";
echo "3. 观察是否还会出现履行中心ID错误\n";
echo "4. 如果还有其他错误，请提供新的错误日志\n\n";

echo "=== 完成 ===\n";
?>
