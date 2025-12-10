<?php
/**
 * 检查去重处理日志
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查去重处理日志 ===\n\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$product_id = 13917;

// 1. 检查是否有"图片去重处理"日志
echo "=== 检查图片去重日志 ===\n";

$去重日志 = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '图片去重处理' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 3
", '%' . $product_id . '%'));

if (!empty($去重日志)) {
    echo "✅ 找到图片去重日志:\n";
    foreach ($去重日志 as $log) {
        echo "时间: {$log->created_at}\n";
        echo "消息: {$log->message}\n";
        
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "去重前: " . ($request_data['before_unique'] ?? '未知') . "张\n";
            echo "去重后: " . ($request_data['after_unique'] ?? '未知') . "张\n";
            echo "移除重复: " . ($request_data['removed_duplicates'] ?? '未知') . "张\n";
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到图片去重日志\n";
    echo "这说明第278行的条件判断没有成立\n";
    echo "即：去重前后数量相同，没有重复图片\n";
}

// 2. 从"产品图片获取"日志中获取详细信息
echo "\n=== 分析产品图片获取日志详情 ===\n";

$image_log = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '产品图片获取' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 1
", '%' . $product_id . '%'));

if ($image_log) {
    $request_data = json_decode($image_log->request, true);
    if ($request_data && isset($request_data['additional_images'])) {
        $additional_images = $request_data['additional_images'];
        
        echo "获取到的副图数量: " . count($additional_images) . "\n";
        echo "副图URLs:\n";
        foreach ($additional_images as $i => $url) {
            echo ($i + 1) . ". " . $url . "\n";
        }
        
        // 手动检查是否有重复
        $unique_images = array_unique($additional_images);
        $unique_count = count($unique_images);
        
        echo "\n手动去重分析:\n";
        echo "原始数量: " . count($additional_images) . "\n";
        echo "去重后数量: {$unique_count}\n";
        
        if (count($additional_images) != $unique_count) {
            echo "❌ 发现重复图片: " . (count($additional_images) - $unique_count) . "张\n";
            echo "这就是为什么\$original_count != 4的原因！\n";
            
            // 找出重复的图片
            $url_counts = array_count_values($additional_images);
            echo "重复的图片:\n";
            foreach ($url_counts as $url => $count) {
                if ($count > 1) {
                    echo "- {$url} (出现{$count}次)\n";
                }
            }
        } else {
            echo "✅ 没有重复图片\n";
            echo "但\$original_count = {$unique_count}，不等于4\n";
            echo "这就是为什么第287行条件不成立的原因！\n";
        }
        
        // 分析为什么不是4张
        if ($unique_count < 4) {
            echo "\n❌ 副图少于4张的可能原因:\n";
            echo "1. 远程图库中有无效的URL\n";
            echo "2. 图片获取过程中过滤掉了无效图片\n";
            echo "3. 某些图片没有通过filter_var()验证\n";
        }
    }
}

// 3. 检查占位符补足的其他条件
echo "\n=== 检查其他补足条件 ===\n";

if (isset($unique_count)) {
    if ($unique_count == 3) {
        echo "如果\$original_count = 3，应该执行3张补足逻辑\n";
        
        // 检查是否有"图片补足-3张"日志
        $补足3张日志 = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$logs_table} 
            WHERE action = '图片补足-3张' 
            AND request LIKE %s
            ORDER BY created_at DESC 
            LIMIT 1
        ", '%' . $product_id . '%'));
        
        if (!empty($补足3张日志)) {
            echo "✅ 找到3张补足日志\n";
        } else {
            echo "❌ 没有找到3张补足日志\n";
        }
    } else if ($unique_count < 3) {
        echo "如果\$original_count < 3，应该执行警告逻辑\n";
        
        // 检查是否有"图片不足-警告"日志
        $警告日志 = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$logs_table} 
            WHERE action = '图片不足-警告' 
            AND request LIKE %s
            ORDER BY created_at DESC 
            LIMIT 1
        ", '%' . $product_id . '%'));
        
        if (!empty($警告日志)) {
            echo "✅ 找到图片不足警告日志\n";
        } else {
            echo "❌ 没有找到图片不足警告日志\n";
        }
    }
}

// 4. 总结分析
echo "\n=== 问题总结 ===\n";

if (isset($unique_count)) {
    echo "确定的事实:\n";
    echo "1. 系统获取到副图数量: " . count($additional_images) . "张\n";
    echo "2. 去重后的数量(\$original_count): {$unique_count}张\n";
    echo "3. 第287行条件(\$original_count == 4): " . ($unique_count == 4 ? '成立' : '不成立') . "\n";
    
    if ($unique_count != 4) {
        echo "\n❌ 根本问题: \$original_count = {$unique_count}，不等于4\n";
        echo "因此第287行的补足逻辑没有执行\n";
        echo "需要检查为什么副图数量不是4张\n";
    }
}

?>
