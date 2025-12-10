<?php
/**
 * 调试API响应格式问题
 * 检查API返回的具体格式和内容
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 调试API响应格式问题 ===\n\n";

// 1. 检查最近的API调用日志
echo "1. 检查最近的API调用日志:\n";
global $wpdb;

$recent_logs = $wpdb->get_results("
    SELECT action, level, message, data, created_at 
    FROM {$wpdb->prefix}woo_walmart_sync_logs 
    WHERE (action LIKE '%API%' OR action LIKE '%Feed%' OR action LIKE '%批量%')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY created_at DESC 
    LIMIT 10
");

if ($recent_logs) {
    foreach ($recent_logs as $log) {
        echo "  [{$log->created_at}] {$log->action} - {$log->level}\n";
        echo "    消息: {$log->message}\n";
        
        if ($log->data) {
            $log_data = json_decode($log->data, true);
            if ($log_data) {
                // 检查是否包含API响应
                if (isset($log_data['feedId'])) {
                    echo "    ✅ 包含feedId: {$log_data['feedId']}\n";
                }
                
                if (isset($log_data['response'])) {
                    echo "    响应数据: " . substr(json_encode($log_data['response'], JSON_UNESCAPED_UNICODE), 0, 200) . "...\n";
                }
                
                // 检查错误信息
                if (isset($log_data['error'])) {
                    echo "    ❌ 错误: {$log_data['error']}\n";
                }
            }
        }
        echo "    ---\n";
    }
} else {
    echo "  没有找到最近1小时的相关日志\n";
}

// 2. 检查最近的批次记录和对应的Feed ID
echo "\n2. 检查最近的批次记录:\n";
$recent_batches = $wpdb->get_results("
    SELECT batch_id, feed_id, status, error_message, product_count, created_at 
    FROM {$wpdb->prefix}walmart_batch_feeds 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($recent_batches) {
    foreach ($recent_batches as $batch) {
        echo "  批次: {$batch->batch_id}\n";
        echo "    状态: {$batch->status}\n";
        echo "    Feed ID: " . ($batch->feed_id ?: '无') . "\n";
        echo "    产品数量: {$batch->product_count}\n";
        echo "    错误消息: " . ($batch->error_message ?: '无') . "\n";
        echo "    创建时间: {$batch->created_at}\n";
        
        // 分析矛盾情况
        if ($batch->status === 'ERROR' && !empty($batch->feed_id)) {
            echo "    ⚠️  矛盾情况: 状态为ERROR但有Feed ID！\n";
        } elseif ($batch->status === 'SUBMITTED' && empty($batch->feed_id)) {
            echo "    ⚠️  矛盾情况: 状态为SUBMITTED但没有Feed ID！\n";
        }
        echo "    ---\n";
    }
} else {
    echo "  没有找到最近2小时的批次记录\n";
}

// 3. 模拟API响应解析测试
echo "\n3. 模拟API响应解析测试:\n";

// 测试各种可能的API响应格式
$test_responses = [
    'success_standard' => ['feedId' => 'TEST123456789'],
    'success_different_key' => ['feed_id' => 'TEST123456789'],
    'success_nested' => ['data' => ['feedId' => 'TEST123456789']],
    'success_string' => 'TEST123456789',
    'error_string' => 'Error: Invalid request',
    'error_array' => ['error' => 'Invalid request'],
    'empty_array' => [],
    'null_response' => null,
    'wp_error' => new WP_Error('api_error', 'Connection failed')
];

foreach ($test_responses as $test_name => $response) {
    echo "  测试响应: {$test_name}\n";
    
    // 模拟批量处理中的判断逻辑
    if (is_wp_error($response)) {
        echo "    结果: ❌ WP_Error - " . $response->get_error_message() . "\n";
    } elseif (is_array($response) && !empty($response['feedId'])) {
        echo "    结果: ✅ 成功 - Feed ID: {$response['feedId']}\n";
    } else {
        echo "    结果: ❌ 失败 - 不符合预期格式\n";
        echo "    类型: " . gettype($response) . "\n";
        if (is_array($response)) {
            echo "    键: " . implode(', ', array_keys($response)) . "\n";
        } elseif (is_string($response)) {
            echo "    内容: " . substr($response, 0, 100) . "\n";
        }
    }
    echo "    ---\n";
}

// 4. 检查API认证状态
echo "\n4. 检查API认证状态:\n";
try {
    $api_auth = new Woo_Walmart_API_Key_Auth();
    echo "  ✅ API认证类实例化成功\n";
    
    // 检查配置
    $client_id = get_option('woo_walmart_client_id');
    $client_secret = get_option('woo_walmart_client_secret');
    $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
    
    echo "  Business Unit: {$business_unit}\n";
    echo "  Client ID: " . ($client_id ? '已配置' : '未配置') . "\n";
    echo "  Client Secret: " . ($client_secret ? '已配置' : '未配置') . "\n";
    
    // 尝试获取访问令牌
    $reflection = new ReflectionClass($api_auth);
    $token_method = $reflection->getMethod('get_access_token');
    $token_method->setAccessible(true);
    
    $token = $token_method->invoke($api_auth);
    if ($token) {
        echo "  ✅ 访问令牌获取成功 (长度: " . strlen($token) . ")\n";
    } else {
        echo "  ❌ 访问令牌获取失败\n";
    }
    
} catch (Exception $e) {
    echo "  ❌ API认证检查失败: " . $e->getMessage() . "\n";
}

// 5. 分析可能的问题
echo "\n5. 问题分析:\n";
echo "基于以上检查，可能的问题包括:\n\n";

echo "**响应格式问题:**\n";
echo "- API返回的格式可能不是标准的 ['feedId' => 'xxx'] 格式\n";
echo "- 可能使用了不同的键名 (如 'feed_id' 而不是 'feedId')\n";
echo "- 响应可能被包装在其他结构中\n\n";

echo "**网络/超时问题:**\n";
echo "- 大批量数据上传时网络超时\n";
echo "- API响应被截断或损坏\n";
echo "- 响应解析时JSON格式错误\n\n";

echo "**状态记录问题:**\n";
echo "- Feed ID实际生成了，但状态更新失败\n";
echo "- 数据库事务问题导致状态不一致\n\n";

echo "**建议检查:**\n";
echo "1. 查看最近的API调用日志，确认实际响应格式\n";
echo "2. 检查是否有Feed ID生成但状态为ERROR的记录\n";
echo "3. 考虑添加更详细的响应格式日志记录\n";

echo "\n=== 调试完成 ===\n";
