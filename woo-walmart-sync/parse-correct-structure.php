<?php
/**
 * 正确解析产品映射日志的数据结构
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 正确解析产品映射数据结构 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 查找最近的产品映射日志
$mapping_log = $wpdb->get_row("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品映射-最终数据结构'
    AND created_at >= '2025-08-10 15:20:00'
    ORDER BY created_at DESC 
    LIMIT 1
");

if (!$mapping_log) {
    echo "❌ 未找到产品映射日志\n";
    exit;
}

echo "分析日志时间: {$mapping_log->created_at}\n\n";

$request_data = json_decode($mapping_log->request, true);
if (!$request_data) {
    echo "❌ 无法解析JSON数据\n";
    exit;
}

echo "数据结构分析:\n";
echo "顶级字段: " . implode(', ', array_keys($request_data)) . "\n\n";

// 检查MPItem结构
if (isset($request_data['MPItem'])) {
    $mp_item = $request_data['MPItem'];
    echo "MPItem字段: " . implode(', ', array_keys($mp_item)) . "\n";
    
    // 检查Visible部分
    if (isset($mp_item['Visible'])) {
        $visible = $mp_item['Visible'];
        echo "Visible字段: " . implode(', ', array_keys($visible)) . "\n\n";
        
        // 遍历每个分类
        foreach ($visible as $category => $data) {
            echo "=== 分类: {$category} ===\n";
            
            if (isset($data['sku'])) {
                echo "SKU: {$data['sku']}\n";
            }
            
            // 检查图片字段
            if (isset($data['productSecondaryImageURL'])) {
                $images = $data['productSecondaryImageURL'];
                echo "✅ 有productSecondaryImageURL字段\n";
                echo "副图数量: " . count($images) . "\n";
                
                if (count($images) < 5) {
                    echo "❌ 副图不足5张！实际副图:\n";
                    foreach ($images as $i => $url) {
                        echo "  " . ($i + 1) . ". " . $url . "\n";
                    }
                } else {
                    echo "✅ 副图充足 (" . count($images) . "张)\n";
                    echo "前3张副图:\n";
                    for ($i = 0; $i < min(3, count($images)); $i++) {
                        echo "  " . ($i + 1) . ". " . $images[$i] . "\n";
                    }
                    if (count($images) > 3) {
                        echo "  ... (还有" . (count($images) - 3) . "张)\n";
                    }
                }
            } else {
                echo "❌ 缺少productSecondaryImageURL字段！\n";
                echo "可用字段: " . implode(', ', array_keys($data)) . "\n";
                
                // 检查是否有其他图片相关字段
                $image_fields = array_filter(array_keys($data), function($key) {
                    return strpos(strtolower($key), 'image') !== false;
                });
                
                if (!empty($image_fields)) {
                    echo "图片相关字段: " . implode(', ', $image_fields) . "\n";
                }
            }
            
            // 检查主图
            if (isset($data['mainImageUrl'])) {
                echo "✅ 主图: " . substr($data['mainImageUrl'], 0, 60) . "...\n";
            } else {
                echo "❌ 缺少主图\n";
            }
            
            echo "\n";
        }
    } else {
        echo "❌ MPItem中没有Visible字段\n";
        echo "MPItem可用字段: " . implode(', ', array_keys($mp_item)) . "\n";
    }
} else {
    echo "❌ 没有MPItem字段\n";
}

// 检查MPItemFeedHeader
if (isset($request_data['MPItemFeedHeader'])) {
    echo "\n=== MPItemFeedHeader 信息 ===\n";
    $header = $request_data['MPItemFeedHeader'];
    foreach ($header as $key => $value) {
        echo "{$key}: {$value}\n";
    }
}

// 计算整个JSON的统计信息
echo "\n=== JSON统计信息 ===\n";
$json_string = json_encode($request_data, JSON_UNESCAPED_UNICODE);
echo "JSON总大小: " . strlen($json_string) . " 字节\n";

// 统计关键字段出现次数
$secondary_image_count = substr_count($json_string, 'productSecondaryImageURL');
$main_image_count = substr_count($json_string, 'mainImageUrl');

echo "productSecondaryImageURL出现次数: {$secondary_image_count}\n";
echo "mainImageUrl出现次数: {$main_image_count}\n";

if ($secondary_image_count === 0) {
    echo "\n❌ 关键发现：JSON中完全没有productSecondaryImageURL字段！\n";
    echo "这说明在产品映射阶段就没有生成副图字段。\n";
} else {
    echo "\n✅ JSON中包含副图字段\n";
}

?>
