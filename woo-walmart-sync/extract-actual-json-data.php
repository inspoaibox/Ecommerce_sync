<?php
/**
 * 提取实际发送给沃尔玛的JSON数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 提取实际发送的JSON数据 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找最近的API响应-文件上传日志
$upload_log = $wpdb->get_row("
    SELECT * FROM {$logs_table} 
    WHERE action = 'API响应-文件上传' 
    AND created_at >= '2025-08-10 15:00:00'
    ORDER BY created_at DESC 
    LIMIT 1
");

if (!$upload_log) {
    echo "❌ 未找到最近的文件上传日志\n";
    exit;
}

echo "找到文件上传日志: {$upload_log->created_at}\n";
echo "状态: {$upload_log->status}\n\n";

// 解析请求数据
$request_data = json_decode($upload_log->request, true);
if (!$request_data) {
    echo "❌ 无法解析请求数据\n";
    exit;
}

echo "=== 分析上传的JSON数据 ===\n";

// 检查是否是数组格式的多个产品
if (is_array($request_data) && isset($request_data[0])) {
    echo "多产品数据，产品数量: " . count($request_data) . "\n\n";
    
    $failed_skus = ['B202P222191', 'B202S00513', 'B202S00514', 'B202S00492', 'B202S00493'];
    
    foreach ($request_data as $index => $item) {
        if (!isset($item['MPItem']['Visible'])) {
            continue;
        }
        
        foreach ($item['MPItem']['Visible'] as $category => $data) {
            if (!isset($data['sku']) || !in_array($data['sku'], $failed_skus)) {
                continue;
            }
            
            $sku = $data['sku'];
            echo "=== 产品 " . ($index + 1) . " - SKU: {$sku} ===\n";
            
            // 检查主图
            if (isset($data['mainImageUrl'])) {
                echo "✅ 主图: {$data['mainImageUrl']}\n";
            } else {
                echo "❌ 缺少主图\n";
            }
            
            // 重点检查副图
            if (isset($data['productSecondaryImageURL'])) {
                $images = $data['productSecondaryImageURL'];
                echo "副图数量: " . count($images) . "\n";
                
                if (count($images) < 5) {
                    echo "❌ 副图不足5张！实际发送的副图:\n";
                    foreach ($images as $i => $url) {
                        echo "  " . ($i + 1) . ". {$url}\n";
                        
                        // 检查URL格式
                        if (!filter_var($url, FILTER_VALIDATE_URL)) {
                            echo "     ❌ URL格式无效\n";
                        } else {
                            echo "     ✅ URL格式有效\n";
                        }
                    }
                } else {
                    echo "✅ 副图数量充足 ({$count}张)\n";
                    echo "前3张副图:\n";
                    for ($i = 0; $i < min(3, count($images)); $i++) {
                        echo "  " . ($i + 1) . ". {$images[$i]}\n";
                    }
                    if (count($images) > 3) {
                        echo "  ... (还有" . (count($images) - 3) . "张)\n";
                    }
                }
            } else {
                echo "❌ 完全缺少productSecondaryImageURL字段！\n";
                echo "这就是问题所在！\n";
            }
            
            // 检查其他可能相关的字段
            $image_related_fields = ['additionalProductAttribute', 'swatchImages', 'productHighlights'];
            foreach ($image_related_fields as $field) {
                if (isset($data[$field])) {
                    echo "包含字段: {$field}\n";
                }
            }
            
            echo "\n" . str_repeat("-", 50) . "\n\n";
        }
    }
} else {
    echo "单产品数据或格式不符合预期\n";
    echo "数据结构: " . json_encode(array_keys($request_data), JSON_UNESCAPED_UNICODE) . "\n";
}

// 检查整个JSON的大小和结构
echo "=== JSON数据统计 ===\n";
$json_string = json_encode($request_data, JSON_UNESCAPED_UNICODE);
echo "JSON总大小: " . strlen($json_string) . " 字节\n";
echo "JSON格式验证: " . (json_last_error() === JSON_ERROR_NONE ? '✅ 有效' : '❌ 无效') . "\n";

// 统计productSecondaryImageURL字段的出现次数
$secondary_image_count = substr_count($json_string, 'productSecondaryImageURL');
echo "productSecondaryImageURL字段出现次数: {$secondary_image_count}\n";

if ($secondary_image_count === 0) {
    echo "❌ 关键发现：整个JSON中完全没有productSecondaryImageURL字段！\n";
    echo "这说明问题出现在数据构建阶段，而不是图片数量不足。\n";
}

?>
