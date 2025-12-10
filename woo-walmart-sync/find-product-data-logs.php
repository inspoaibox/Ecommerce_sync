<?php
/**
 * 查找包含实际产品数据的日志
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 查找包含实际产品数据的日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$failed_skus = ['B202P222191', 'B202S00513', 'B202S00514', 'B202S00492', 'B202S00493'];

// 查找包含这些SKU的所有日志
foreach ($failed_skus as $sku) {
    echo "=== 查找SKU: {$sku} 的相关日志 ===\n";
    
    $sku_logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$logs_table} 
        WHERE request LIKE %s 
        AND created_at >= '2025-08-10 15:00:00'
        ORDER BY created_at DESC 
        LIMIT 5
    ", '%' . $sku . '%'));
    
    foreach ($sku_logs as $log) {
        echo "时间: {$log->created_at} - {$log->action} ({$log->status})\n";
        
        // 检查是否包含产品映射数据
        if (strpos($log->action, '产品映射') !== false) {
            echo "✅ 这是产品映射日志\n";
            
            $request_data = json_decode($log->request, true);
            if ($request_data) {
                // 检查MPItem结构
                if (isset($request_data['MPItem']['Visible'])) {
                    foreach ($request_data['MPItem']['Visible'] as $category => $data) {
                        echo "分类: {$category}\n";
                        
                        if (isset($data['sku']) && $data['sku'] === $sku) {
                            echo "✅ 找到匹配的SKU\n";
                            
                            // 检查图片字段
                            if (isset($data['productSecondaryImageURL'])) {
                                $images = $data['productSecondaryImageURL'];
                                echo "副图数量: " . count($images) . "\n";
                                
                                if (count($images) < 5) {
                                    echo "❌ 副图不足5张！\n";
                                    foreach ($images as $i => $url) {
                                        echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
                                    }
                                } else {
                                    echo "✅ 副图充足 ({$count}张)\n";
                                }
                            } else {
                                echo "❌ 缺少productSecondaryImageURL字段！\n";
                            }
                            
                            // 检查主图
                            if (isset($data['mainImageUrl'])) {
                                echo "✅ 有主图\n";
                            } else {
                                echo "❌ 缺少主图\n";
                            }
                        }
                    }
                }
            }
        }
        
        echo "---\n";
    }
    
    echo "\n";
}

// 查找最近的批量数据构建日志
echo "=== 查找批量数据构建日志 ===\n";

$batch_build_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE (action LIKE '%批量%' OR action LIKE '%数据构建%' OR action LIKE '%Feed构建%')
    AND created_at >= '2025-08-10 15:00:00'
    AND request LIKE '%MPItem%'
    ORDER BY created_at DESC 
    LIMIT 3
");

foreach ($batch_build_logs as $log) {
    echo "时间: {$log->created_at} - {$log->action}\n";
    
    $request_data = json_decode($log->request, true);
    if ($request_data && is_array($request_data)) {
        echo "数据类型: " . (isset($request_data[0]) ? '数组' : '对象') . "\n";
        
        if (isset($request_data[0])) {
            echo "产品数量: " . count($request_data) . "\n";
            
            // 检查第一个产品
            $first_item = $request_data[0];
            if (isset($first_item['MPItem']['Visible'])) {
                foreach ($first_item['MPItem']['Visible'] as $category => $data) {
                    if (isset($data['sku'])) {
                        echo "第一个产品SKU: {$data['sku']}\n";
                        
                        if (isset($data['productSecondaryImageURL'])) {
                            echo "第一个产品副图数量: " . count($data['productSecondaryImageURL']) . "\n";
                        } else {
                            echo "❌ 第一个产品缺少副图字段\n";
                        }
                        break;
                    }
                }
            }
        }
    }
    
    echo "---\n";
}

// 最后，直接查看最近的文件上传开始日志，看看实际的数据大小
echo "=== 文件上传数据大小分析 ===\n";

$upload_start_log = $wpdb->get_row("
    SELECT * FROM {$logs_table} 
    WHERE action = '文件上传方法-开始'
    AND created_at >= '2025-08-10 15:00:00'
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($upload_start_log) {
    echo "文件上传时间: {$upload_start_log->created_at}\n";
    
    $request_data = json_decode($upload_start_log->request, true);
    if ($request_data && isset($request_data['data_size'])) {
        $data_size = $request_data['data_size'];
        echo "数据大小: {$data_size} 字节\n";
        
        // 估算产品数量（每个产品大约8-12KB）
        $estimated_products = round($data_size / 10000);
        echo "估算产品数量: 约 {$estimated_products} 个\n";
        
        if ($data_size < 20000) {
            echo "⚠️ 数据大小偏小，可能存在数据丢失\n";
        }
    }
}

?>
