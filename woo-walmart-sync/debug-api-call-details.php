<?php
/**
 * 深入调试API调用细节
 * 找出API响应的具体问题
 */

require_once dirname(__FILE__) . '/../../../wp-config.php';

echo "=== 深入调试API调用细节 ===\n\n";

// 使用少量产品进行测试
global $wpdb;
$products = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish' 
    ORDER BY ID DESC 
    LIMIT 5
");

if (count($products) < 5) {
    echo "❌ 产品数量不足\n";
    exit;
}

$product_ids = array_column($products, 'ID');
echo "测试产品ID: " . implode(', ', $product_ids) . "\n\n";

// 1. 构建Feed数据
echo "1. 构建Feed数据:\n";
try {
    $batch_builder = new Walmart_Batch_Feed_Builder();
    $reflection = new ReflectionClass($batch_builder);
    $build_method = $reflection->getMethod('build_batch_feed_data');
    $build_method->setAccessible(true);
    
    $feed_data = $build_method->invoke($batch_builder, $product_ids);
    
    if (empty($feed_data['MPItem'])) {
        echo "❌ Feed数据构建失败 - MPItem为空\n";
        exit;
    }
    
    echo "✅ Feed数据构建成功\n";
    echo "MPItem数量: " . count($feed_data['MPItem']) . "\n";
    echo "数据大小: " . strlen(json_encode($feed_data)) . " 字节\n";
    
} catch (Exception $e) {
    echo "❌ Feed构建异常: " . $e->getMessage() . "\n";
    exit;
}

// 2. 测试API认证
echo "\n2. 测试API认证:\n";
try {
    $api_auth = new Woo_Walmart_API_Key_Auth();
    
    // 获取访问令牌
    $reflection = new ReflectionClass($api_auth);
    $token_method = $reflection->getMethod('get_access_token');
    $token_method->setAccessible(true);
    
    $access_token = $token_method->invoke($api_auth);
    
    if (!$access_token) {
        echo "❌ 访问令牌获取失败\n";
        exit;
    }
    
    echo "✅ 访问令牌获取成功\n";
    echo "令牌长度: " . strlen($access_token) . "\n";
    
} catch (Exception $e) {
    echo "❌ API认证异常: " . $e->getMessage() . "\n";
    exit;
}

// 3. 模拟API调用（详细版）
echo "\n3. 模拟API调用:\n";

// 启用详细的错误报告
$old_error_reporting = error_reporting(E_ALL);
$old_display_errors = ini_get('display_errors');
ini_set('display_errors', 1);

try {
    echo "开始API调用...\n";
    $api_start_time = microtime(true);
    
    // 调用API
    $response = $api_auth->make_file_upload_request('/v3/feeds?feedType=MP_ITEM', $feed_data, 'test_feed.json');
    
    $api_time = round((microtime(true) - $api_start_time) * 1000, 2);
    echo "API调用完成，耗时: {$api_time}ms\n";
    
    // 详细分析响应
    echo "\n=== API响应分析 ===\n";
    
    if (is_wp_error($response)) {
        echo "❌ WP_Error响应\n";
        echo "错误代码: " . $response->get_error_code() . "\n";
        echo "错误消息: " . $response->get_error_message() . "\n";
        echo "错误数据: " . print_r($response->get_error_data(), true) . "\n";
        
    } elseif ($response === null) {
        echo "❌ NULL响应\n";
        echo "可能原因: JSON解析失败或API无响应\n";
        
    } elseif ($response === false) {
        echo "❌ FALSE响应\n";
        echo "可能原因: API调用失败\n";
        
    } elseif (is_string($response)) {
        echo "❌ 字符串响应\n";
        echo "响应长度: " . strlen($response) . "\n";
        echo "响应内容（前500字符）: " . substr($response, 0, 500) . "\n";
        
        // 尝试解析为JSON
        $json_data = json_decode($response, true);
        if ($json_data) {
            echo "✅ 字符串可以解析为JSON\n";
            echo "JSON内容: " . print_r($json_data, true) . "\n";
        } else {
            echo "❌ 字符串不是有效JSON\n";
            echo "JSON错误: " . json_last_error_msg() . "\n";
        }
        
    } elseif (is_array($response)) {
        echo "✅ 数组响应\n";
        echo "数组键: " . implode(', ', array_keys($response)) . "\n";
        
        if (isset($response['feedId'])) {
            echo "✅ 包含feedId: " . $response['feedId'] . "\n";
            echo "🎉 这是成功的响应格式！\n";
        } else {
            echo "❌ 不包含feedId字段\n";
            echo "完整响应: " . print_r($response, true) . "\n";
        }
        
    } else {
        echo "❌ 未知响应类型\n";
        echo "类型: " . gettype($response) . "\n";
        echo "内容: " . print_r($response, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ API调用异常: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
} finally {
    // 恢复错误报告设置
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
}

// 4. 检查最近的API日志
echo "\n4. 检查最近的API日志:\n";
$recent_logs = $wpdb->get_results("
    SELECT action, status, request, response, created_at 
    FROM {$wpdb->prefix}woo_walmart_sync_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($recent_logs) {
    foreach ($recent_logs as $log) {
        echo "  [{$log->created_at}] {$log->action} - {$log->status}\n";
        
        if ($log->response) {
            $response_data = json_decode($log->response, true);
            if ($response_data && isset($response_data['feedId'])) {
                echo "    ✅ 日志中有feedId: {$response_data['feedId']}\n";
            } else {
                echo "    响应: " . substr($log->response, 0, 100) . "...\n";
            }
        }
        echo "    ---\n";
    }
} else {
    echo "  没有找到最近10分钟的日志\n";
}

// 5. 总结分析
echo "\n=== 问题分析总结 ===\n";
echo "基于详细的API调用测试，可能的问题:\n\n";

echo "1. **API响应格式问题**:\n";
echo "   - API返回了字符串而不是数组\n";
echo "   - API返回了错误信息而不是成功响应\n";
echo "   - JSON解析失败\n\n";

echo "2. **网络/连接问题**:\n";
echo "   - API调用超时\n";
echo "   - 网络连接中断\n";
echo "   - 服务器响应异常\n\n";

echo "3. **认证问题**:\n";
echo "   - 访问令牌过期或无效\n";
echo "   - API密钥配置错误\n";
echo "   - 权限不足\n\n";

echo "4. **数据问题**:\n";
echo "   - Feed数据格式不符合API要求\n";
echo "   - 产品数据缺少必需字段\n";
echo "   - 数据大小超过限制\n\n";

echo "**下一步建议**:\n";
echo "- 检查API响应的具体内容和格式\n";
echo "- 验证API认证配置\n";
echo "- 测试更小的数据集\n";
echo "- 检查Walmart API文档的最新要求\n";

echo "\n=== 调试完成 ===\n";
