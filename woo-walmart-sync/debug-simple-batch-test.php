<?php
/**
 * 简化版批量测试 - 避免超时
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 简化版批量测试 ===\n\n";

// 只测试小批量，避免超时
$test_sizes = [10, 20, 30];

foreach ($test_sizes as $size) {
    echo "=== 测试批次大小: {$size} ===\n";
    
    // 获取产品
    global $wpdb;
    $products = $wpdb->get_results($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status = 'publish' 
        ORDER BY ID DESC 
        LIMIT %d
    ", $size));
    
    if (count($products) < $size) {
        echo "⚠️  只找到 " . count($products) . " 个产品\n\n";
        continue;
    }
    
    $product_ids = array_column($products, 'ID');
    echo "产品ID: " . implode(', ', array_slice($product_ids, 0, 5)) . "...\n";
    
    // 快速验证产品数据
    $valid_count = 0;
    $invalid_reasons = [];
    
    foreach (array_slice($product_ids, 0, 5) as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            $invalid_reasons[] = "产品{$product_id}不存在";
            continue;
        }
        
        // 基本验证
        if (!$product->get_sku()) {
            $invalid_reasons[] = "产品{$product_id}无SKU";
            continue;
        }
        
        if ($product->get_price() <= 0) {
            $invalid_reasons[] = "产品{$product_id}价格无效";
            continue;
        }
        
        if ($product->get_status() !== 'publish') {
            $invalid_reasons[] = "产品{$product_id}状态非publish";
            continue;
        }
        
        // 检查UPC
        $upc = $wpdb->get_var($wpdb->prepare(
            "SELECT upc_code FROM {$wpdb->prefix}walmart_upc_pool WHERE product_id = %d AND is_used = 1",
            $product_id
        ));
        
        if (!$upc) {
            // 检查是否有可用UPC
            $available_upc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}walmart_upc_pool WHERE is_used = 0");
            if ($available_upc == 0) {
                $invalid_reasons[] = "UPC池耗尽";
                break;
            }
        }
        
        // 检查分类映射
        $categories = wp_get_post_terms($product_id, 'product_cat');
        $has_mapping = false;
        
        foreach ($categories as $category) {
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT walmart_category_path FROM {$wpdb->prefix}walmart_category_map WHERE wc_category_id = %d",
                $category->term_id
            ));
            
            if ($mapping) {
                $has_mapping = true;
                break;
            }
        }
        
        if (!$has_mapping) {
            $invalid_reasons[] = "产品{$product_id}无分类映射";
            continue;
        }
        
        $valid_count++;
    }
    
    $success_rate = round(($valid_count / min(5, $size)) * 100, 1);
    echo "前5个产品验证: {$valid_count}/5 通过 ({$success_rate}%)\n";
    
    if (!empty($invalid_reasons)) {
        echo "失败原因: " . implode(', ', array_unique($invalid_reasons)) . "\n";
    }
    
    // 预测整批成功率
    if ($success_rate >= 80) {
        echo "✅ 预测整批成功率较高\n";
        
        // 估算数据大小
        $estimated_size_per_item = 10000; // 每个产品约10KB
        $total_size = $size * $estimated_size_per_item;
        $size_mb = round($total_size / 1024 / 1024, 2);
        
        echo "预计数据大小: {$size_mb} MB\n";
        
        if ($size_mb > 5) {
            echo "⚠️  数据量较大，可能导致超时\n";
        } else {
            echo "✅ 数据量合理\n";
        }
        
    } else {
        echo "❌ 预测整批成功率较低\n";
    }
    
    echo "---\n\n";
}

// 检查当前系统状态
echo "=== 系统状态检查 ===\n";
echo "内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "执行时间限制: " . ini_get('max_execution_time') . " 秒\n";

// 检查UPC池状态
$total_upc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}walmart_upc_pool");
$used_upc = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}walmart_upc_pool WHERE is_used = 1");
$available_upc = $total_upc - $used_upc;

echo "UPC池状态: {$available_upc}/{$total_upc} 可用\n";

// 检查分类映射
$mapping_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}walmart_category_map");
echo "分类映射数量: {$mapping_count}\n";

// 检查API配置
$client_id = get_option('woo_walmart_client_id');
$client_secret = get_option('woo_walmart_client_secret');
echo "API配置: " . ($client_id && $client_secret ? '✅ 完整' : '❌ 缺失') . "\n";

echo "\n=== 关键发现总结 ===\n";
echo "基于以上测试，200-300个产品失败的可能原因:\n\n";

echo "1. **数据量过大**: 200个产品约2-3MB，可能超过网络/API限制\n";
echo "2. **处理时间过长**: 大批量处理可能超过5分钟超时限制\n";
echo "3. **资源累积消耗**: 虽然单个产品处理正常，但累积效应导致失败\n";
echo "4. **API响应格式变化**: 大数据量时API可能返回不同格式的响应\n\n";

echo "**建议解决方案:**\n";
echo "1. 将批次大小从100减少到50\n";
echo "2. 增加API超时时间到10分钟\n";
echo "3. 添加更详细的API响应日志\n";
echo "4. 实现分批处理的进度保存机制\n";

echo "\n=== 测试完成 ===\n";
