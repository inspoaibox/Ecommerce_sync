<?php
/**
 * 追踪同步流程，找到字段来源
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 追踪同步流程 ===\n\n";

// 1. 检查最近的Feed提交日志，看看实际发送的数据
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

echo "=== 查找最近的Feed提交日志 ===\n";

$feed_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE (action LIKE '%Feed%' OR action LIKE '%文件上传%' OR action LIKE '%批量%')
    AND (request LIKE '%seat_depth%' OR request LIKE '%arm_height%')
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($feed_logs)) {
    foreach ($feed_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "状态: {$log->status}\n";
        
        if (!empty($log->request)) {
            $request_data = json_decode($log->request, true);
            if ($request_data) {
                // 查找seat_depth和arm_height字段
                $json_str = json_encode($request_data, JSON_UNESCAPED_UNICODE);
                
                if (strpos($json_str, 'seat_depth') !== false) {
                    echo "✅ 包含seat_depth字段\n";
                    
                    // 提取seat_depth的值
                    if (preg_match('/"seat_depth":\s*"([^"]*)"/', $json_str, $matches)) {
                        echo "  seat_depth值: '{$matches[1]}' (字符串)\n";
                        echo "  ❌ 这就是问题！发送的是字符串而不是JSONObject\n";
                    } else if (preg_match('/"seat_depth":\s*({[^}]+})/', $json_str, $matches)) {
                        echo "  seat_depth值: {$matches[1]} (对象)\n";
                        echo "  ✅ 正确的JSONObject格式\n";
                    }
                }
                
                if (strpos($json_str, 'arm_height') !== false) {
                    echo "✅ 包含arm_height字段\n";
                    
                    // 提取arm_height的值
                    if (preg_match('/"arm_height":\s*"([^"]*)"/', $json_str, $matches)) {
                        echo "  arm_height值: '{$matches[1]}' (字符串)\n";
                        echo "  ❌ 这就是问题！发送的是字符串而不是JSONObject\n";
                    } else if (preg_match('/"arm_height":\s*({[^}]+})/', $json_str, $matches)) {
                        echo "  arm_height值: {$matches[1]} (对象)\n";
                        echo "  ✅ 正确的JSONObject格式\n";
                    }
                }
            }
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到包含这些字段的Feed日志\n";
}

// 2. 检查最近的API错误日志
echo "\n=== 查找最近的API错误日志 ===\n";

$error_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE status = '错误' 
    AND (message LIKE '%seat_depth%' OR message LIKE '%arm_height%' OR response LIKE '%seat_depth%' OR response LIKE '%arm_height%')
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($error_logs)) {
    foreach ($error_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->response)) {
            $response_data = json_decode($log->response, true);
            if ($response_data && isset($response_data['errors'])) {
                echo "API错误详情:\n";
                foreach ($response_data['errors'] as $error) {
                    if (isset($error['field']) && in_array($error['field'], ['seat_depth', 'arm_height'])) {
                        echo "  字段: {$error['field']}\n";
                        echo "  错误: {$error['description']}\n";
                        echo "  错误代码: {$error['code']}\n";
                    }
                }
            }
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到相关的API错误日志\n";
}

// 3. 检查产品映射过程的日志
echo "\n=== 查找产品映射过程的日志 ===\n";

$mapping_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table} 
    WHERE action LIKE '%映射%' 
    AND (request LIKE '%seat_depth%' OR request LIKE '%arm_height%' OR message LIKE '%seat_depth%' OR message LIKE '%arm_height%')
    ORDER BY created_at DESC 
    LIMIT 5
");

if (!empty($mapping_logs)) {
    foreach ($mapping_logs as $log) {
        echo "时间: {$log->created_at}\n";
        echo "操作: {$log->action}\n";
        echo "消息: {$log->message}\n";
        
        if (!empty($log->request)) {
            $request_data = json_decode($log->request, true);
            if ($request_data) {
                if (isset($request_data['seat_depth'])) {
                    echo "  seat_depth: " . json_encode($request_data['seat_depth']) . "\n";
                }
                if (isset($request_data['arm_height'])) {
                    echo "  arm_height: " . json_encode($request_data['arm_height']) . "\n";
                }
            }
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到映射过程的相关日志\n";
}

// 4. 检查是否有硬编码的字段添加
echo "\n=== 检查可能的硬编码字段来源 ===\n";

// 检查是否有全局的必填字段配置
$required_fields_option = get_option('walmart_required_fields', '');
if (!empty($required_fields_option)) {
    echo "✅ 找到全局必填字段配置:\n";
    echo $required_fields_option . "\n";
} else {
    echo "❌ 没有全局必填字段配置\n";
}

// 检查是否有默认字段配置
$default_fields_option = get_option('walmart_default_fields', '');
if (!empty($default_fields_option)) {
    echo "✅ 找到默认字段配置:\n";
    echo $default_fields_option . "\n";
} else {
    echo "❌ 没有默认字段配置\n";
}

// 5. 模拟产品映射过程，看看字段是从哪里来的
echo "\n=== 模拟产品映射过程 ===\n";

$test_sku = 'W116465061';
$product_id = $wpdb->get_var($wpdb->prepare("
    SELECT post_id FROM {$wpdb->postmeta} 
    WHERE meta_key = '_sku' AND meta_value = %s
", $test_sku));

if ($product_id) {
    $product = wc_get_product($product_id);
    
    if ($product) {
        echo "测试产品: {$product->get_name()}\n";
        
        // 尝试调用产品映射器
        require_once 'includes/class-product-mapper.php';
        
        try {
            $mapper = new Woo_Walmart_Product_Mapper();
            
            // 使用反射查看映射过程
            $reflection = new ReflectionClass($mapper);
            
            // 检查是否有默认的分类设置
            echo "检查映射器的默认设置...\n";
            
            // 模拟映射调用（不实际执行，只是看看会发生什么）
            echo "如果没有分类映射，映射器会如何处理？\n";
            
        } catch (Exception $e) {
            echo "映射器初始化失败: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== 总结 ===\n";
echo "需要找到：\n";
echo "1. seat_depth和arm_height字段是从哪里添加的\n";
echo "2. 为什么它们是字符串而不是JSONObject\n";
echo "3. 在没有分类映射的情况下，系统如何确定要发送哪些字段\n";

?>
