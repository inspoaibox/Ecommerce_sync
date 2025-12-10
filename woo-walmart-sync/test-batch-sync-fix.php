<?php
/**
 * 测试批量同步修复效果
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试批量同步修复效果 ===\n\n";

// 测试少量产品的批量同步
$test_product_ids = [20629, 20627]; // 只测试2个产品

echo "测试产品ID: " . implode(', ', $test_product_ids) . "\n";

// 验证产品
foreach ($test_product_ids as $product_id) {
    $product = wc_get_product($product_id);
    if ($product) {
        echo "产品 {$product_id}: {$product->get_name()} (SKU: {$product->get_sku()})\n";
    } else {
        echo "产品 {$product_id}: 不存在\n";
    }
}

echo "\n开始批量同步测试...\n";

// 模拟AJAX请求
$_POST['nonce'] = wp_create_nonce('sku_batch_sync_nonce');
$_POST['product_ids'] = $test_product_ids;
$_POST['force_sync'] = false;
$_POST['skip_validation'] = false;

ob_start();
handle_start_sku_batch_sync();
$output = ob_get_clean();

echo "批量同步结果:\n";
echo $output . "\n";

$response = json_decode($output, true);

if ($response && $response['success']) {
    $data = $response['data'];
    echo "✅ 批量同步请求提交成功!\n";
    echo "总数: {$data['total']}\n";
    echo "成功: " . count($data['success']) . "个\n";
    echo "失败: " . count($data['failed']) . "个\n";
    echo "跳过: " . count($data['skipped']) . "个\n";
    
    if (!empty($data['success'])) {
        echo "\n成功提交的产品:\n";
        foreach ($data['success'] as $success) {
            echo "  产品ID {$success['product_id']}: Feed ID {$success['feed_id']}\n";
            
            // 等待几秒钟然后检查Feed状态
            echo "  等待3秒后检查Feed状态...\n";
            sleep(3);
            
            // 检查Feed状态
            $api_auth = new Woo_Walmart_API_Key_Auth();
            $feed_status = $api_auth->get_feed_status($success['feed_id']);
            
            if (!is_wp_error($feed_status)) {
                echo "  Feed状态: {$feed_status['feedStatus']}\n";
                echo "  接收商品数: {$feed_status['itemsReceived']}\n";
                echo "  成功商品数: {$feed_status['itemsSucceeded']}\n";
                echo "  失败商品数: {$feed_status['itemsFailed']}\n";
                
                if ($feed_status['feedStatus'] === 'ERROR') {
                    echo "  ❌ Feed处理出错:\n";
                    if (isset($feed_status['ingestionErrors']['ingestionError'])) {
                        foreach ($feed_status['ingestionErrors']['ingestionError'] as $error) {
                            echo "    错误类型: {$error['type']}\n";
                            echo "    错误代码: {$error['code']}\n";
                            echo "    错误描述: {$error['description']}\n";
                        }
                    }
                } elseif ($feed_status['feedStatus'] === 'PROCESSED') {
                    echo "  ✅ Feed处理成功!\n";
                } else {
                    echo "  ⏳ Feed正在处理中...\n";
                }
            } else {
                echo "  ❌ 无法获取Feed状态: " . $feed_status->get_error_message() . "\n";
            }
            echo "  ---\n";
        }
    }
    
    if (!empty($data['failed'])) {
        echo "\n失败的产品:\n";
        foreach ($data['failed'] as $failed) {
            echo "  产品ID {$failed['product_id']}: {$failed['error']}\n";
        }
    }
    
} else {
    echo "❌ 批量同步失败: " . ($response['data'] ?? '未知错误') . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "修复效果对比:\n";
echo "修复前: 使用错误的V1.5格式，包含废弃字段，导致全部失败\n";
echo "修复后: 使用正确的V5.0格式，与单个产品同步一致\n";
echo "\n关键修复点:\n";
echo "1. ✅ 移除了废弃的字段 (processMode, subset, sellingChannel等)\n";
echo "2. ✅ 使用正确的版本号 (5.0.20241118-04_39_24-api)\n";
echo "3. ✅ 使用正确的businessUnit值 (WALMART_US)\n";
echo "4. ✅ 简化了MPItemFeedHeader结构\n";
echo "\n如果现在Feed状态不是ERROR，说明修复成功！\n";

?>
