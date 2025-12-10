<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 检查新Feed状态 ===\n\n";

$feed_id = '1858454B89EB552897B140D530FACE6B@AXkBCgA';

echo "Feed ID: {$feed_id}\n\n";

require_once 'includes/class-api-key-auth.php';
$api_auth = new Woo_Walmart_API_Key_Auth();

echo "正在获取Feed状态...\n";
$feed_status = $api_auth->make_request("/v3/feeds/{$feed_id}?includeDetails=true");

if (is_wp_error($feed_status)) {
    echo "❌ 获取Feed状态失败: " . $feed_status->get_error_message() . "\n";
    exit;
}

echo "Feed状态: " . ($feed_status['feedStatus'] ?? '未知') . "\n";
echo "Feed类型: " . ($feed_status['feedType'] ?? '未知') . "\n";
echo "提交时间: " . ($feed_status['feedSubmissionDate'] ?? '未知') . "\n\n";

if (isset($feed_status['itemDetails']['itemIngestionStatus'])) {
    $items = $feed_status['itemDetails']['itemIngestionStatus'];
    
    echo "商品处理状态:\n";
    foreach ($items as $item) {
        echo "SKU: {$item['sku']}\n";
        echo "状态: {$item['ingestionStatus']}\n";
        
        if (isset($item['ingestionErrors']['ingestionError'])) {
            $errors = $item['ingestionErrors']['ingestionError'];
            echo "错误数量: " . count($errors) . "\n\n";
            
            $header_errors = 0;
            $business_unit_errors = 0;
            $subset_errors = 0;
            
            foreach ($errors as $error) {
                echo "错误: {$error['field']} - {$error['description']}\n";
                
                // 统计特定错误
                if ($error['field'] === 'businessUnit') {
                    $business_unit_errors++;
                }
                if ($error['field'] === 'MPItemFeedHeader') {
                    $header_errors++;
                    if (strpos($error['description'], 'subset') !== false) {
                        $subset_errors++;
                    }
                }
            }
            
            echo "\n错误统计:\n";
            echo "businessUnit错误: {$business_unit_errors}\n";
            echo "MPItemFeedHeader错误: {$header_errors}\n";
            echo "subset相关错误: {$subset_errors}\n";
            
            if ($business_unit_errors === 0 && $subset_errors === 0) {
                echo "🎉 **好消息！没有businessUnit和subset错误了！**\n";
                echo "这说明header修复已经生效！\n";
            } else {
                echo "⚠️ 仍然有header相关错误，需要进一步调查\n";
            }
            
        } else {
            echo "✅ 没有错误！完美！\n";
        }
        echo "\n" . str_repeat("-", 50) . "\n";
    }
} else {
    echo "没有商品处理详情\n";
}

echo "\n=== 检查完成 ===\n";
?>
