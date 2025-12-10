<?php
/**
 * 检查队列管理页面失败商品数量不匹配的问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查队列管理页面失败商品数量问题 ===\n\n";

// 检查您提到的两个批次
$batch_ids = ['#352_6177', '#052_2020'];

global $wpdb;

// 1. 查找批量Feed相关的表
echo "1. 查找相关数据表:\n";

$tables_to_check = [
    'walmart_batch_feeds',
    'walmart_feeds', 
    'woo_walmart_sync_logs'
];

foreach ($tables_to_check as $table_name) {
    $full_table_name = $wpdb->prefix . $table_name;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") == $full_table_name;
    echo "  {$table_name}: " . ($exists ? '✅ 存在' : '❌ 不存在') . "\n";
}

// 2. 检查批量Feed表中的记录
echo "\n2. 检查批量Feed表中的记录:\n";

$batch_feeds_table = $wpdb->prefix . 'walmart_batch_feeds';
if ($wpdb->get_var("SHOW TABLES LIKE '{$batch_feeds_table}'") == $batch_feeds_table) {
    
    foreach ($batch_ids as $batch_id) {
        echo "\n--- 批次: {$batch_id} ---\n";
        
        $batch_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$batch_feeds_table} WHERE batch_id = %s",
            $batch_id
        ));
        
        if ($batch_record) {
            echo "✅ 找到批次记录:\n";
            echo "  产品数量: {$batch_record->product_count}\n";
            echo "  批次类型: {$batch_record->batch_type}\n";
            echo "  状态: {$batch_record->status}\n";
            echo "  创建时间: {$batch_record->created_at}\n";
            
            if (!empty($batch_record->feed_id)) {
                echo "  Feed ID: {$batch_record->feed_id}\n";
            }
            
            // 检查成功/失败统计
            if (!empty($batch_record->success_count) || !empty($batch_record->failed_count)) {
                echo "  成功数量: " . ($batch_record->success_count ?? 0) . "\n";
                echo "  失败数量: " . ($batch_record->failed_count ?? 0) . "\n";
            }
            
            // 如果有失败的产品列表
            if (!empty($batch_record->failed_products)) {
                $failed_products = json_decode($batch_record->failed_products, true);
                if (is_array($failed_products)) {
                    echo "  失败产品列表数量: " . count($failed_products) . "\n";
                    echo "  前10个失败SKU: " . implode(', ', array_slice($failed_products, 0, 10)) . "\n";
                }
            }
            
        } else {
            echo "❌ 没有找到批次记录\n";
        }
    }
} else {
    echo "❌ 批量Feed表不存在\n";
}

// 3. 检查Feed表中的相关记录
echo "\n3. 检查Feed表中的相关记录:\n";

$feeds_table = $wpdb->prefix . 'walmart_feeds';
if ($wpdb->get_var("SHOW TABLES LIKE '{$feeds_table}'") == $feeds_table) {
    
    // 查找large_batch_feed类型的记录
    $large_batch_feeds = $wpdb->get_results("
        SELECT feed_id, COUNT(*) as product_count, status, 
               SUM(CASE WHEN status = 'PROCESSED' THEN 1 ELSE 0 END) as success_count,
               SUM(CASE WHEN status != 'PROCESSED' THEN 1 ELSE 0 END) as failed_count,
               created_at
        FROM {$feeds_table} 
        WHERE created_at >= '2025-08-25 16:40:00' 
        AND created_at <= '2025-08-25 16:45:00'
        GROUP BY feed_id 
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    if (!empty($large_batch_feeds)) {
        echo "找到相关时间段的Feed记录:\n";
        foreach ($large_batch_feeds as $feed) {
            echo "  Feed ID: {$feed->feed_id}\n";
            echo "  产品数量: {$feed->product_count}\n";
            echo "  成功: {$feed->success_count} | 失败: {$feed->failed_count}\n";
            echo "  创建时间: {$feed->created_at}\n";
            echo "  ---\n";
        }
    } else {
        echo "❌ 没有找到相关时间段的Feed记录\n";
    }
    
} else {
    echo "❌ Feed表不存在\n";
}

// 4. 检查失败商品的具体SKU列表
echo "\n4. 检查失败商品的具体SKU列表:\n";

// 您提供的失败SKU列表
$provided_skus = [
    'W3041S00098', 'W1568P332410', 'W1825P332361', 'W2915S00025', 'W1960P325840',
    'W714S00833', 'W714S01283', 'W714S01022', 'W714S01019', 'W1568S00228',
    'W1825P332359', 'W1658P250947', 'W714S00708', 'W714S00696', 'W714S01208',
    'W714S00641', 'W714S00640', 'W1885P246363', 'W3041S00096', 'W1568P332404',
    'W714S01210', 'W1885P234641', 'W2297S00021', 'W2297P264503', 'N767P263923C',
    'W3041S00027', 'W3041S00071', 'W3041S00070', 'W3041S00069', 'W1926S00074',
    'W1926S00073', 'W834S00349', 'W714S00760', 'W2297S00017', 'W1278P360531',
    'W1568P255135', 'W1926S00075', 'W1926S00069', 'W834S00475', 'W714S01151',
    'W714S01147', 'W714S00668', 'W714S01084', 'W714S01081', 'W2297P264469',
    'W3041S00032', 'W1926S00076', 'W1926S00070', 'W2108S00126', 'W834S00495',
    'W834S00443', 'W834S00439', 'W834S00425', 'W834S00412', 'W2108S00088',
    'W714S01110', 'W3147S00006', 'W3041P272946', 'W2339P346395', 'W2108S00104',
    'W714S01111', 'W714S01107', 'W714S01163', 'W714S01159', 'W714S01031',
    'W1191S00043', 'W3622S00002', 'W2791P306821', 'W834S00457', 'W2817P271187'
];

echo "您复制的失败SKU数量: " . count($provided_skus) . "\n";
echo "队列管理显示的失败数量: 145\n";
echo "数量差异: " . (145 - count($provided_skus)) . "\n\n";

// 5. 查找可能的原因
echo "5. 查找数量不匹配的可能原因:\n";

// 检查是否有分页或限制
echo "可能的原因:\n";
echo "1. 队列管理页面可能有显示数量限制（如只显示前100个）\n";
echo "2. 复制功能可能只复制可见的SKU\n";
echo "3. 可能有分页功能，您只复制了第一页\n";
echo "4. 某些失败商品可能没有SKU或SKU为空\n";
echo "5. 统计数量和实际显示数量的逻辑可能不同\n";

// 6. 检查日志中的批量同步记录
echo "\n6. 检查日志中的批量同步记录:\n";

$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
if ($wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'") == $logs_table) {
    
    $batch_logs = $wpdb->get_results("
        SELECT * FROM {$logs_table} 
        WHERE (action LIKE '%批量%' OR action LIKE '%batch%' OR action LIKE '%large_batch%')
        AND created_at >= '2025-08-25 16:40:00' 
        AND created_at <= '2025-08-25 16:45:00'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    if (!empty($batch_logs)) {
        echo "找到批量同步日志:\n";
        foreach ($batch_logs as $log) {
            echo "  时间: {$log->created_at}\n";
            echo "  操作: {$log->action}\n";
            echo "  状态: {$log->status}\n";
            
            // 尝试从日志中提取产品数量信息
            if (!empty($log->request)) {
                $request_data = json_decode($log->request, true);
                if ($request_data && is_array($request_data)) {
                    if (isset($request_data['product_count'])) {
                        echo "  产品数量: {$request_data['product_count']}\n";
                    } elseif (is_array($request_data) && isset($request_data[0])) {
                        echo "  产品数量: " . count($request_data) . "\n";
                    }
                }
            }
            echo "  ---\n";
        }
    } else {
        echo "❌ 没有找到批量同步日志\n";
    }
} else {
    echo "❌ 日志表不存在\n";
}

echo "\n=== 结论 ===\n";
echo "需要检查队列管理页面的前端代码，确认:\n";
echo "1. 失败商品列表的显示是否有数量限制\n";
echo "2. 复制功能是否只复制当前页面可见的SKU\n";
echo "3. 是否有分页功能导致部分SKU未显示\n";
echo "4. 统计逻辑和显示逻辑是否一致\n";

?>
