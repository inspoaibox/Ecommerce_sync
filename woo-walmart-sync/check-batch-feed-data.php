<?php
/**
 * 检查批量Feed的实际数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW\test.localhost\wp-load.php';

echo "=== 检查批量Feed的实际数据 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找Feed ID为185A56CB90BF547085295354AD66A3C8@AXkBCgA的相关日志
$feed_id = '185A56CB90BF547085295354AD66A3C8@AXkBCgA';
echo "查找Feed ID: {$feed_id}\n\n";

// 查找批量同步的日志
$batch_logs = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE (action LIKE '%批量%' OR action LIKE '%batch%' OR action LIKE '%文件上传%')
    AND created_at >= '2025-08-10 15:00:00'
    ORDER BY created_at DESC 
    LIMIT 10
", $feed_id));

echo "找到 " . count($batch_logs) . " 条批量相关日志:\n\n";

foreach ($batch_logs as $log) {
    echo "=== 时间: {$log->created_at} - {$log->action} ===\n";
    echo "状态: {$log->status}\n";
    
    // 检查是否包含产品数据
    if (strpos($log->request, 'MPItem') !== false) {
        echo "✅ 包含产品数据\n";
        
        // 尝试解析JSON
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            // 检查是否是数组格式的多个产品
            if (isset($request_data[0]) && is_array($request_data[0])) {
                echo "多产品数据，产品数量: " . count($request_data) . "\n";
                
                // 检查每个产品
                foreach ($request_data as $index => $item) {
                    if (isset($item['MPItem']['Visible'])) {
                        foreach ($item['MPItem']['Visible'] as $category => $data) {
                            if (isset($data['sku'])) {
                                $sku = $data['sku'];
                                echo "\n产品 " . ($index + 1) . " - SKU: {$sku}\n";
                                
                                // 检查图片
                                if (isset($data['productSecondaryImageURL'])) {
                                    $images = $data['productSecondaryImageURL'];
                                    echo "副图数量: " . count($images) . "\n";
                                    
                                    if (count($images) < 5) {
                                        echo "❌ 副图不足5张！\n";
                                        foreach ($images as $i => $url) {
                                            echo "  " . ($i + 1) . ". {$url}\n";
                                        }
                                    } else {
                                        echo "✅ 副图数量充足\n";
                                        // 只显示前3张和后2张
                                        for ($i = 0; $i < min(3, count($images)); $i++) {
                                            echo "  " . ($i + 1) . ". {$images[$i]}\n";
                                        }
                                        if (count($images) > 3) {
                                            echo "  ...\n";
                                            for ($i = max(3, count($images) - 2); $i < count($images); $i++) {
                                                echo "  " . ($i + 1) . ". {$images[$i]}\n";
                                            }
                                        }
                                    }
                                } else {
                                    echo "❌ 缺少productSecondaryImageURL字段！\n";
                                }
                            }
                        }
                    }
                }
            } else if (isset($request_data['MPItem'])) {
                echo "单产品数据\n";
                // 处理单个产品的逻辑...
            }
        } else {
            echo "❌ 无法解析JSON数据\n";
            echo "数据长度: " . strlen($log->request) . "\n";
        }
    } else if (strpos($log->action, '文件上传') !== false) {
        echo "文件上传操作\n";
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            if (isset($request_data['filename'])) {
                echo "文件名: {$request_data['filename']}\n";
            }
            if (isset($request_data['data_size'])) {
                echo "数据大小: {$request_data['data_size']} 字节\n";
            }
        }
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

// 查找临时文件
echo "=== 检查临时文件 ===\n";
$temp_dir = sys_get_temp_dir();
$temp_files = glob($temp_dir . '/walmart_batch_*.json');

if (!empty($temp_files)) {
    echo "找到临时文件:\n";
    foreach ($temp_files as $file) {
        $file_time = filemtime($file);
        $file_size = filesize($file);
        echo "文件: " . basename($file) . " (大小: {$file_size} 字节, 时间: " . date('Y-m-d H:i:s', $file_time) . ")\n";
        
        // 如果文件不太大，读取内容检查
        if ($file_size < 1024 * 1024) { // 小于1MB
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if ($data && is_array($data)) {
                echo "  产品数量: " . count($data) . "\n";
                
                // 检查第一个产品的图片情况
                if (isset($data[0]['MPItem']['Visible'])) {
                    foreach ($data[0]['MPItem']['Visible'] as $category => $product_data) {
                        if (isset($product_data['sku'])) {
                            echo "  第一个产品SKU: {$product_data['sku']}\n";
                            if (isset($product_data['productSecondaryImageURL'])) {
                                echo "  副图数量: " . count($product_data['productSecondaryImageURL']) . "\n";
                            }
                        }
                    }
                }
            }
        }
    }
} else {
    echo "未找到临时文件\n";
}

?>
