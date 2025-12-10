<?php
/**
 * 精确调试占位符补足问题
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 精确调试占位符补足问题 ===\n\n";

// 1. 验证占位符配置
echo "=== 验证占位符配置 ===\n";
$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "占位符1: {$placeholder_1}\n";
echo "占位符2: {$placeholder_2}\n";

// 验证URL
$url1_valid = filter_var($placeholder_1, FILTER_VALIDATE_URL);
$url2_valid = filter_var($placeholder_2, FILTER_VALIDATE_URL);

echo "占位符1 URL验证: " . ($url1_valid ? '有效' : '无效') . "\n";
echo "占位符2 URL验证: " . ($url2_valid ? '有效' : '无效') . "\n";

// 2. 模拟完整的占位符补足逻辑
echo "\n=== 模拟占位符补足逻辑 ===\n";

// 模拟从日志获取的4张副图
$additional_images = [
    'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/86f7f67da1ced2fd26ad28bf523c0e93.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=c7af116ed7080bbd069db297638c592c',
    'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/f5dfa246cd1fe8f6545f163df0169355.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=f2003b891d7643278515c800393a0835',
    'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/9f8c4f8d2db725799f2860ef011ff216.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=a60f3d3bcde450381adb82d7aa2f798c',
    'https://b2bfiles1.gigab2b.cn/image/wkseller/52243/8b5796ca13249c0d870725b8d8782094.jpg?x-cc=20&x-cu=24361&x-ct=1754722800&x-cs=6d204bdaeddbe87d6857dcce712ee01b'
];

echo "原始副图数量: " . count($additional_images) . "\n";

// 去重处理（第274行）
$additional_images = array_unique($additional_images);
$original_count = count($additional_images);

echo "去重后数量: {$original_count}\n";

// 第287行条件判断
echo "\n=== 第287行条件判断 ===\n";
echo "\$original_count == 4: " . ($original_count == 4 ? 'true' : 'false') . "\n";

if ($original_count == 4) {
    echo "✅ 进入4张补足逻辑\n";
    
    // 第289行：获取占位符
    echo "\n=== 第289行：获取占位符 ===\n";
    $placeholder_1_runtime = get_option('woo_walmart_placeholder_image_1', '');
    echo "\$placeholder_1 = '{$placeholder_1_runtime}'\n";
    
    // 第290行条件判断
    echo "\n=== 第290行条件判断 ===\n";
    $condition1 = !empty($placeholder_1_runtime);
    $condition2 = filter_var($placeholder_1_runtime, FILTER_VALIDATE_URL);
    
    echo "!empty(\$placeholder_1): " . ($condition1 ? 'true' : 'false') . "\n";
    echo "filter_var(\$placeholder_1, FILTER_VALIDATE_URL): " . ($condition2 ? 'true' : 'false') . "\n";
    echo "完整条件: " . ($condition1 && $condition2 ? 'true' : 'false') . "\n";
    
    if ($condition1 && $condition2) {
        echo "✅ 第290行条件成立，执行补足逻辑\n";
        
        // 第291行：添加占位符
        echo "\n=== 第291行：添加占位符 ===\n";
        echo "添加前数量: " . count($additional_images) . "\n";
        $additional_images[] = $placeholder_1_runtime;
        echo "添加后数量: " . count($additional_images) . "\n";
        
        // 第293行：应该记录日志
        echo "\n=== 第293行：应该记录的日志 ===\n";
        echo "操作: 图片补足-4张\n";
        echo "状态: 成功\n";
        echo "数据:\n";
        echo "  original_count: 4\n";
        echo "  final_count: " . count($additional_images) . "\n";
        echo "  placeholder_1: {$placeholder_1_runtime}\n";
        echo "消息: 副图4张，添加占位符图片1补足至5张\n";
        
        echo "\n✅ 补足成功，最终应该有5张副图\n";
        
        // 验证最终结果
        if (count($additional_images) >= 5) {
            echo "✅ 满足沃尔玛5张副图要求\n";
        } else {
            echo "❌ 仍然不满足5张副图要求\n";
        }
        
    } else {
        echo "❌ 第290行条件不成立\n";
        if (!$condition1) {
            echo "原因: 占位符为空\n";
        }
        if (!$condition2) {
            echo "原因: URL验证失败\n";
            echo "可能的问题:\n";
            echo "1. URL包含特殊字符\n";
            echo "2. filter_var函数问题\n";
            echo "3. URL格式不符合PHP标准\n";
        }
    }
} else {
    echo "❌ 第287行条件不成立，\$original_count = {$original_count}\n";
}

// 3. 检查是否有其他阻止因素
echo "\n=== 检查其他可能的阻止因素 ===\n";

// 检查woo_walmart_sync_log函数
if (function_exists('woo_walmart_sync_log')) {
    echo "✅ woo_walmart_sync_log函数存在\n";
} else {
    echo "❌ woo_walmart_sync_log函数不存在\n";
}

// 检查数据库表
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'");

if ($table_exists) {
    echo "✅ 日志表存在\n";
} else {
    echo "❌ 日志表不存在\n";
}

// 4. 写入测试日志验证日志功能
echo "\n=== 测试日志功能 ===\n";

$test_data = [
    'original_count' => 4,
    'final_count' => 5,
    'placeholder_1' => $placeholder_1
];

woo_walmart_sync_log('测试-图片补足-4张', '成功', $test_data, '测试占位符补足逻辑', 13917);

// 验证测试日志是否写入成功
$test_log = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$logs_table} 
    WHERE action = '测试-图片补足-4张' 
    AND request LIKE %s
    ORDER BY created_at DESC 
    LIMIT 1
", '%13917%'));

if ($test_log) {
    echo "✅ 测试日志写入成功\n";
    echo "时间: {$test_log->created_at}\n";
    echo "消息: {$test_log->message}\n";
} else {
    echo "❌ 测试日志写入失败\n";
}

// 5. 总结分析
echo "\n=== 总结分析 ===\n";

if ($original_count == 4 && $condition1 && $condition2) {
    echo "✅ 所有条件都满足，占位符补足逻辑应该执行\n";
    echo "❌ 但实际没有'图片补足-4张'日志\n";
    echo "\n可能的原因:\n";
    echo "1. 映射器在实际执行时被中断\n";
    echo "2. 批量处理使用了不同的代码路径\n";
    echo "3. 存在异常处理阻止了日志记录\n";
    echo "4. 映射器被多次调用，最终使用的数据来自未补足的调用\n";
} else {
    echo "❌ 条件不满足，占位符补足逻辑不会执行\n";
}

?>
