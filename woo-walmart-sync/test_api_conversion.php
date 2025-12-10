<?php
/**
 * 测试API规范转换
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试API规范转换 ===\n\n";

// 获取测试产品
$product_id = 6479;
$product = wc_get_product($product_id);

if (!$product) {
    echo "❌ 产品不存在\n";
    exit;
}

echo "产品: {$product->get_name()}\n\n";

// 获取分类映射
global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';
$mapping = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $map_table WHERE wc_category_id = %d ORDER BY id DESC LIMIT 1",
    100
));

if (!$mapping) {
    echo "❌ 没有找到分类映射\n";
    exit;
}

echo "沃尔玛分类: {$mapping->walmart_category_path}\n\n";

// 创建产品映射器并测试转换
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射调用私有方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('convert_field_data_type');
$method->setAccessible(true);

// 设置产品类型ID
$product_type_property = $reflection->getProperty('current_product_type_id');
$product_type_property->setAccessible(true);
$product_type_property->setValue($mapper, $mapping->walmart_category_path);

// 测试出错的字段
$test_cases = [
    ['field' => 'luggage_lock_type', 'value' => 'TSA Lock'],
    ['field' => 'luggageStyle', 'value' => 'Hardside'],
    ['field' => 'season', 'value' => 'All-Season'],
    ['field' => 'netContent', 'value' => '1'],
    ['field' => 'isProp65WarningRequired', 'value' => '']
];

foreach ($test_cases as $test) {
    echo "测试字段: {$test['field']}\n";
    echo "  输入值: '{$test['value']}'\n";
    
    try {
        $result = $method->invoke($mapper, $test['field'], $test['value'], 'auto');
        echo "  输出值: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        echo "  输出类型: " . gettype($result) . "\n";
    } catch (Exception $e) {
        echo "  ❌ 转换失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 检查调试日志
echo "检查调试日志:\n\n";

$debug_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}walmart_sync_logs 
     WHERE action IN ('API规范转换尝试', 'API规范不可用', '字段类型转换')
     ORDER BY created_at DESC LIMIT 10"
));

if (empty($debug_logs)) {
    echo "❌ 没有找到调试日志\n";
} else {
    foreach ($debug_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "动作: {$log->action}\n";
        echo "消息: {$log->message}\n";
        if (!empty($log->request_data)) {
            $data = json_decode($log->request_data, true);
            echo "数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
}

echo "=== 测试完成 ===\n";
