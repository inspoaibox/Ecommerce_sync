<?php
// 测试批次详情AJAX功能

require_once '../../../wp-load.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== 测试批次详情AJAX功能（修复后）===\n\n";

// 模拟AJAX请求
$_POST['batch_id'] = 'BATCH_20250903061604_1994';
$_POST['type'] = 'success';
$_POST['nonce'] = wp_create_nonce('batch_details_nonce');

echo "模拟AJAX请求:\n";
echo "  batch_id: {$_POST['batch_id']}\n";
echo "  type: {$_POST['type']}\n";
echo "  nonce: {$_POST['nonce']}\n\n";

// 检查函数是否存在
if (function_exists('handle_get_batch_details')) {
    echo "✅ handle_get_batch_details 函数存在\n\n";
    
    // 捕获输出
    ob_start();
    
    try {
        handle_get_batch_details();
        $output = ob_get_clean();
        
        echo "AJAX函数输出:\n";
        echo $output . "\n";
        
        // 尝试解析JSON
        $json_data = json_decode($output, true);
        if ($json_data !== null) {
            echo "✅ 输出是有效的JSON\n";
            echo "成功状态: " . ($json_data['success'] ? '是' : '否') . "\n";
            if (isset($json_data['data']['items'])) {
                echo "商品数量: " . count($json_data['data']['items']) . "\n";
            }
        } else {
            echo "❌ 输出不是有效的JSON\n";
            echo "JSON错误: " . json_last_error_msg() . "\n";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ 函数执行出错: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ handle_get_batch_details 函数不存在\n";
}

// 测试其他类型
echo "\n" . str_repeat('-', 50) . "\n";
echo "测试失败类型:\n";

$_POST['type'] = 'failed';
ob_start();

try {
    handle_get_batch_details();
    $output = ob_get_clean();
    
    $json_data = json_decode($output, true);
    if ($json_data !== null) {
        echo "✅ 失败类型查询成功\n";
        if (isset($json_data['data']['items'])) {
            echo "失败商品数量: " . count($json_data['data']['items']) . "\n";
        }
    } else {
        echo "❌ 失败类型查询返回无效JSON\n";
        echo "输出: " . substr($output, 0, 200) . "...\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ 失败类型查询出错: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
?>
