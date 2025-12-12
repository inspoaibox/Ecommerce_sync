<?php
/**
 * 解析数组格式的产品数据结构
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 解析数组格式的产品数据 ===\n\n";

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

// 检查MPItem是否是数组
if (isset($request_data['MPItem']) && is_array($request_data['MPItem'])) {
    $mp_items = $request_data['MPItem'];
    echo "MPItem是数组格式，包含 " . count($mp_items) . " 个产品\n\n";
    
    $failed_skus = ['B202P222191', 'B202S00513', 'B202S00514', 'B202S00492', 'B202S00493'];
    
    foreach ($mp_items as $index => $item) {
        echo "=== 产品 " . ($index + 1) . " ===\n";
        
        if (isset($item['Visible'])) {
            $visible = $item['Visible'];
            
            foreach ($visible as $category => $data) {
                echo "分类: {$category}\n";
                
                if (isset($data['sku'])) {
                    $sku = $data['sku'];
                    echo "SKU: {$sku}\n";
                    
                    // 只分析失败的SKU
                    if (in_array($sku, $failed_skus)) {
                        echo "🔍 这是失败的SKU，详细分析:\n";
                        
                        // 检查主图
                        if (isset($data['mainImageUrl'])) {
                            echo "✅ 主图: " . substr($data['mainImageUrl'], 0, 60) . "...\n";
                        } else {
                            echo "❌ 缺少主图\n";
                        }
                        
                        // 重点检查副图
                        if (isset($data['productSecondaryImageURL'])) {
                            $images = $data['productSecondaryImageURL'];
                            echo "✅ 有productSecondaryImageURL字段\n";
                            echo "副图数量: " . count($images) . "\n";
                            
                            if (count($images) < 5) {
                                echo "❌ 副图不足5张！这就是问题所在！\n";
                                echo "实际发送的副图:\n";
                                foreach ($images as $i => $url) {
                                    echo "  " . ($i + 1) . ". " . $url . "\n";
                                }
                                
                                // 分析为什么副图不足
                                echo "\n🔍 副图不足原因分析:\n";
                                if (count($images) === 0) {
                                    echo "- 完全没有副图，可能是图片获取失败\n";
                                } else if (count($images) < 3) {
                                    echo "- 副图少于3张，系统没有进行补足\n";
                                } else if (count($images) === 3 || count($images) === 4) {
                                    echo "- 副图为3-4张，占位符补足可能失败\n";
                                }
                            } else {
                                echo "✅ 副图充足 (" . count($images) . "张)\n";
                            }
                        } else {
                            echo "❌ 完全缺少productSecondaryImageURL字段！\n";
                            echo "这是最严重的问题 - 字段根本没有被创建\n";
                        }
                        
                        // 列出所有字段
                        echo "所有字段: " . implode(', ', array_keys($data)) . "\n";
                        
                        echo "\n" . str_repeat("-", 50) . "\n";
                    }
                }
            }
        } else {
            echo "❌ 产品没有Visible数据\n";
        }
        
        echo "\n";
    }
} else {
    echo "❌ MPItem不是预期的数组格式\n";
    if (isset($request_data['MPItem'])) {
        echo "MPItem类型: " . gettype($request_data['MPItem']) . "\n";
        if (is_array($request_data['MPItem'])) {
            echo "MPItem键: " . implode(', ', array_keys($request_data['MPItem'])) . "\n";
        }
    }
}

// 统计信息
echo "\n=== 统计信息 ===\n";
$json_string = json_encode($request_data, JSON_UNESCAPED_UNICODE);
echo "JSON总大小: " . strlen($json_string) . " 字节\n";

$secondary_image_count = substr_count($json_string, 'productSecondaryImageURL');
echo "productSecondaryImageURL字段出现次数: {$secondary_image_count}\n";

// 分析每个出现的副图字段
if ($secondary_image_count > 0) {
    echo "\n🔍 分析副图字段内容:\n";
    
    // 使用正则表达式提取所有副图数组
    if (preg_match_all('/"productSecondaryImageURL":\s*(\[[^\]]*\])/', $json_string, $matches)) {
        foreach ($matches[1] as $i => $array_str) {
            $images = json_decode($array_str, true);
            if (is_array($images)) {
                echo "副图字段 " . ($i + 1) . ": " . count($images) . " 张图片\n";
                if (count($images) < 5) {
                    echo "  ❌ 不足5张\n";
                }
            }
        }
    }
}

?>
