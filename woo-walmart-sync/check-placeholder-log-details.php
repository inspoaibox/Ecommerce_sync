<?php
/**
 * 检查图片补足日志的详细内容
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查图片补足日志的详细内容 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

// 1. 获取最近的"图片补足-4张"日志详情
echo "=== 图片补足-4张日志详情 ===\n";

$placeholder_logs = $wpdb->get_results("
    SELECT id, created_at, status, request, response 
    FROM {$logs_table} 
    WHERE action = '图片补足-4张' 
    ORDER BY id DESC 
    LIMIT 3
");

foreach ($placeholder_logs as $log) {
    echo "=== 日志ID: {$log->id} | 时间: {$log->created_at} ===\n";
    echo "状态: {$log->status}\n";
    
    $request_data = json_decode($log->request, true);
    if ($request_data) {
        echo "请求数据:\n";
        foreach ($request_data as $key => $value) {
            if ($key === 'placeholder_1') {
                echo "  {$key}: " . substr($value, 0, 50) . "...\n";
            } else {
                echo "  {$key}: {$value}\n";
            }
        }
    }
    
    if (!empty($log->response)) {
        echo "响应: {$log->response}\n";
    }
    echo "\n";
}

// 2. 获取对应的"产品图片字段"日志详情
echo "=== 产品图片字段日志详情 ===\n";

$field_logs = $wpdb->get_results("
    SELECT id, created_at, status, request, response 
    FROM {$logs_table} 
    WHERE action = '产品图片字段' 
    ORDER BY id DESC 
    LIMIT 3
");

foreach ($field_logs as $log) {
    echo "=== 日志ID: {$log->id} | 时间: {$log->created_at} ===\n";
    echo "状态: {$log->status}\n";
    
    $request_data = json_decode($log->request, true);
    if ($request_data) {
        echo "请求数据:\n";
        foreach ($request_data as $key => $value) {
            if ($key === 'additionalImages' && is_array($value)) {
                echo "  {$key}: 数组(" . count($value) . "张图片)\n";
                foreach ($value as $i => $url) {
                    echo "    [" . ($i + 1) . "]: " . substr($url, 0, 60) . "...\n";
                }
            } else {
                echo "  {$key}: {$value}\n";
            }
        }
    }
    echo "\n";
}

// 3. 检查最终发送给沃尔玛的数据
echo "=== 最终发送数据检查 ===\n";

$final_data_log = $wpdb->get_row("
    SELECT id, created_at, request 
    FROM {$logs_table} 
    WHERE action = '产品映射-最终数据结构' 
    ORDER BY id DESC 
    LIMIT 1
");

if ($final_data_log) {
    echo "最终数据结构日志ID: {$final_data_log->id} | 时间: {$final_data_log->created_at}\n";
    
    $final_data = json_decode($final_data_log->request, true);
    if ($final_data && isset($final_data['MPItem'])) {
        $mp_items = $final_data['MPItem'];
        
        if (is_array($mp_items) && !empty($mp_items)) {
            foreach ($mp_items as $index => $item) {
                if (isset($item['Visible'])) {
                    foreach ($item['Visible'] as $category => $data) {
                        echo "\n产品 " . ($index + 1) . " - 分类: {$category}\n";
                        
                        if (isset($data['sku'])) {
                            echo "SKU: {$data['sku']}\n";
                        }
                        
                        if (isset($data['productSecondaryImageURL'])) {
                            $secondary_images = $data['productSecondaryImageURL'];
                            echo "副图数量: " . count($secondary_images) . "\n";
                            
                            if (count($secondary_images) < 5) {
                                echo "❌ 副图不足5张！实际副图:\n";
                                foreach ($secondary_images as $i => $url) {
                                    echo "  " . ($i + 1) . ". " . substr($url, 0, 80) . "...\n";
                                }
                            } else {
                                echo "✅ 副图充足 (" . count($secondary_images) . "张)\n";
                            }
                        } else {
                            echo "❌ 缺少productSecondaryImageURL字段\n";
                        }
                    }
                }
            }
        }
    }
} else {
    echo "❌ 没有找到最终数据结构日志\n";
}

// 4. 检查文件上传的数据
echo "\n=== 文件上传数据检查 ===\n";

$upload_log = $wpdb->get_row("
    SELECT id, created_at, request 
    FROM {$logs_table} 
    WHERE action = '文件上传方法-开始' 
    ORDER BY id DESC 
    LIMIT 1
");

if ($upload_log) {
    echo "文件上传日志ID: {$upload_log->id} | 时间: {$upload_log->created_at}\n";
    
    $upload_data = json_decode($upload_log->request, true);
    if ($upload_data) {
        echo "文件名: " . ($upload_data['filename'] ?? '未知') . "\n";
        echo "数据大小: " . ($upload_data['data_size'] ?? '未知') . " 字节\n";
    }
} else {
    echo "❌ 没有找到文件上传日志\n";
}

// 5. 分析问题
echo "\n=== 问题分析 ===\n";

if (!empty($placeholder_logs) && !empty($field_logs)) {
    echo "✅ 占位符补足和图片字段处理都有日志记录\n";
    echo "需要检查最终发送给沃尔玛的数据是否真的包含5张副图\n";
} else {
    echo "❌ 缺少关键的日志记录\n";
}

?>
