<?php
/**
 * 调试占位符条件判断
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试占位符条件判断 ===\n\n";

// 模拟第287-298行的执行
$original_count = 4; // 从日志确认的值

echo "=== 模拟第287行条件判断 ===\n";
echo "\$original_count = {$original_count}\n";
echo "条件(\$original_count == 4): " . ($original_count == 4 ? '成立' : '不成立') . "\n";

if ($original_count == 4) {
    echo "✅ 进入4张补足逻辑\n\n";
    
    // 模拟第289行
    echo "=== 模拟第289行：获取占位符 ===\n";
    $placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
    echo "\$placeholder_1 = '{$placeholder_1}'\n";
    echo "!empty(\$placeholder_1): " . (!empty($placeholder_1) ? 'true' : 'false') . "\n";
    
    // 模拟第290行的filter_var检查
    echo "\n=== 模拟第290行：URL验证 ===\n";
    $is_valid_url = filter_var($placeholder_1, FILTER_VALIDATE_URL);
    echo "filter_var(\$placeholder_1, FILTER_VALIDATE_URL): " . ($is_valid_url ? 'true' : 'false') . "\n";
    
    if ($is_valid_url) {
        echo "实际URL: {$is_valid_url}\n";
    } else {
        echo "❌ URL验证失败\n";
    }
    
    // 模拟完整的第290行条件
    echo "\n=== 模拟第290行完整条件 ===\n";
    $condition_result = !empty($placeholder_1) && filter_var($placeholder_1, FILTER_VALIDATE_URL);
    echo "(!empty(\$placeholder_1) && filter_var(\$placeholder_1, FILTER_VALIDATE_URL)): " . ($condition_result ? 'true' : 'false') . "\n";
    
    if ($condition_result) {
        echo "✅ 第290行条件成立，应该执行第291-297行\n";
        
        // 模拟第291行
        echo "\n=== 模拟第291行：添加占位符 ===\n";
        $additional_images = [
            'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/86f7f67da1ced2fd26ad28bf523c0e93.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=c7af116ed7080bbd069db297638c592c',
            'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/f5dfa246cd1fe8f6545f163df0169355.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=f2003b891d7643278515c800393a0835',
            'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/9f8c4f8d2db725799f2860ef011ff216.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=a60f3d3bcde450381adb82d7aa2f798c',
            'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/8b5796ca13249c0d870725b8d8782094.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=6d204bdaeddbe87d6857dcce712ee01b'
        ];
        
        echo "添加前数量: " . count($additional_images) . "\n";
        $additional_images[] = $placeholder_1;
        echo "添加后数量: " . count($additional_images) . "\n";
        
        // 模拟第293-297行的日志记录
        echo "\n=== 模拟第293行：日志记录 ===\n";
        echo "应该记录的日志数据:\n";
        echo "- original_count: {$original_count}\n";
        echo "- final_count: " . count($additional_images) . "\n";
        echo "- placeholder_1: {$placeholder_1}\n";
        echo "- 消息: 副图4张，添加占位符图片1补足至5张\n";
        
        echo "\n✅ 所有条件都满足，应该有'图片补足-4张'日志\n";
        echo "❌ 但实际没有这个日志，说明代码执行有问题\n";
        
    } else {
        echo "❌ 第290行条件不成立\n";
        
        if (empty($placeholder_1)) {
            echo "原因: 占位符为空\n";
        } else if (!filter_var($placeholder_1, FILTER_VALIDATE_URL)) {
            echo "原因: URL验证失败\n";
            echo "可能的问题:\n";
            echo "1. URL格式不正确\n";
            echo "2. 包含特殊字符\n";
            echo "3. filter_var函数的问题\n";
        }
    }
} else {
    echo "❌ 第287行条件不成立\n";
}

// 检查是否有其他可能的问题
echo "\n=== 检查其他可能问题 ===\n";

// 1. 检查woo_walmart_sync_log函数是否正常
echo "1. 测试日志函数:\n";
if (function_exists('woo_walmart_sync_log')) {
    echo "✅ woo_walmart_sync_log函数存在\n";
    
    // 尝试写一个测试日志
    woo_walmart_sync_log('测试日志', '调试', ['test' => 'value'], '测试日志记录功能');
    echo "✅ 测试日志已写入\n";
} else {
    echo "❌ woo_walmart_sync_log函数不存在\n";
}

// 2. 检查数据库连接
echo "\n2. 检查数据库:\n";
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$test_log = $wpdb->get_row("SELECT * FROM {$logs_table} WHERE action = '测试日志' ORDER BY created_at DESC LIMIT 1");
if ($test_log) {
    echo "✅ 数据库连接正常，测试日志已写入\n";
} else {
    echo "❌ 数据库连接或写入有问题\n";
}

?>
