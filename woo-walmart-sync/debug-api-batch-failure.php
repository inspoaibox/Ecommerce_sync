<?php
/**
 * 调试API批量调用失败问题
 * 模拟不同数量的产品进行API调用测试
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 调试API批量调用失败问题 ===\n\n";

// 测试不同的批次大小
$test_sizes = [10, 50, 100, 150, 200];

foreach ($test_sizes as $size) {
    echo "=== 测试批次大小: {$size} ===\n";
    
    // 获取指定数量的产品
    global $wpdb;
    $products = $wpdb->get_results($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' 
        AND post_status = 'publish' 
        ORDER BY ID DESC 
        LIMIT %d
    ", $size));
    
    if (count($products) < $size) {
        echo "⚠️  只找到 " . count($products) . " 个产品，跳过此测试\n\n";
        continue;
    }
    
    $product_ids = array_column($products, 'ID');
    echo "产品ID范围: " . min($product_ids) . " - " . max($product_ids) . "\n";
    
    // 模拟批量Feed构建
    echo "开始构建批量Feed数据...\n";
    $start_time = microtime(true);
    
    try {
        // 使用反射调用私有方法
        $batch_builder = new Walmart_Batch_Feed_Builder();
        $reflection = new ReflectionClass($batch_builder);
        $build_method = $reflection->getMethod('build_batch_feed_data');
        $build_method->setAccessible(true);
        
        $feed_data = $build_method->invoke($batch_builder, $product_ids);
        
        $build_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (empty($feed_data['MPItem'])) {
            echo "❌ Feed构建失败 - MPItem为空\n";
            echo "  构建时间: {$build_time}ms\n";
            echo "  内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n\n";
            continue;
        }
        
        $successful_items = count($feed_data['MPItem']);
        $success_rate = round(($successful_items / $size) * 100, 1);
        
        echo "✅ Feed构建成功\n";
        echo "  构建时间: {$build_time}ms\n";
        echo "  成功产品: {$successful_items}/{$size} ({$success_rate}%)\n";
        echo "  数据大小: " . strlen(json_encode($feed_data)) . " 字节\n";
        echo "  内存使用: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
        
        // 如果成功率太低，不进行API测试
        if ($success_rate < 80) {
            echo "⚠️  成功率过低，跳过API测试\n\n";
            continue;
        }
        
        // 模拟API调用（不实际发送）
        echo "模拟API调用...\n";
        $api_start_time = microtime(true);
        
        // 计算预期的API调用时间和成功率
        $data_size_mb = strlen(json_encode($feed_data)) / 1024 / 1024;
        $estimated_upload_time = $data_size_mb * 2; // 假设每MB需要2秒
        $estimated_processing_time = $successful_items * 0.1; // 假设每个产品需要0.1秒处理
        $total_estimated_time = $estimated_upload_time + $estimated_processing_time;
        
        echo "  数据大小: " . round($data_size_mb, 2) . " MB\n";
        echo "  预计上传时间: " . round($estimated_upload_time, 1) . " 秒\n";
        echo "  预计处理时间: " . round($estimated_processing_time, 1) . " 秒\n";
        echo "  预计总时间: " . round($total_estimated_time, 1) . " 秒\n";
        
        // 判断是否可能超时
        $timeout_limit = 300; // 5分钟
        if ($total_estimated_time > $timeout_limit) {
            echo "❌ 预计会超时！(超过 {$timeout_limit} 秒)\n";
            echo "  建议: 减少批次大小或增加超时时间\n";
        } else {
            echo "✅ 预计不会超时\n";
            
            // 如果数据量不大，可以尝试真实API调用
            if ($size <= 50 && $data_size_mb < 1) {
                echo "  数据量较小，可以尝试真实API调用\n";
                
                try {
                    $api_auth = new Woo_Walmart_API_Key_Auth();
                    
                    // 只记录调用，不实际发送
                    echo "  (跳过真实API调用以避免影响生产环境)\n";
                    
                } catch (Exception $e) {
                    echo "  API调用准备失败: " . $e->getMessage() . "\n";
                }
            } else {
                echo "  数据量较大，跳过真实API调用\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ 测试异常: " . $e->getMessage() . "\n";
        echo "  文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo "  峰值内存: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
    echo "---\n\n";
    
    // 清理内存
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}

// 总结分析
echo "=== 分析总结 ===\n";
echo "基于测试结果，可能的失败原因:\n\n";

echo "1. **数据量过大导致超时**\n";
echo "   - 100个产品约 1-2MB 数据\n";
echo "   - 200个产品约 2-4MB 数据\n";
echo "   - 上传+处理时间可能超过5分钟限制\n\n";

echo "2. **API响应处理问题**\n";
echo "   - 大数据量时API响应可能不是标准JSON格式\n";
echo "   - 响应解析失败导致返回null\n\n";

echo "3. **网络连接问题**\n";
echo "   - 大文件上传时网络不稳定\n";
echo "   - 中途连接断开导致失败\n\n";

echo "4. **服务器资源限制**\n";
echo "   - 虽然PHP配置充足，但可能有其他限制\n";
echo "   - 临时文件创建/删除问题\n\n";

echo "**建议解决方案:**\n";
echo "1. 减少单批次大小 (从100改为50)\n";
echo "2. 增加API超时时间 (从300秒改为600秒)\n";
echo "3. 添加重试机制\n";
echo "4. 改进错误处理和日志记录\n";

echo "\n=== 调试完成 ===\n";
