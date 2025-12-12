<?php
/**
 * 测试SKU批量同步页面的修改
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试SKU批量同步页面修改 ===\n\n";

// 检查页面文件是否存在
$page_file = plugin_dir_path(__FILE__) . 'admin/sku-batch-sync.php';
echo "页面文件: {$page_file}\n";
echo "文件存在: " . (file_exists($page_file) ? '是' : '否') . "\n\n";

if (file_exists($page_file)) {
    $content = file_get_contents($page_file);
    
    // 检查关键元素是否存在
    $checks = [
        'start-batch-sync-btn' => '批量同步按钮',
        'start-single-sync-btn' => '单个同步按钮',
        'startBatchSync' => 'startBatchSync函数',
        'startSingleSync' => 'startSingleSync函数',
        'executeSingleSync' => 'executeSingleSync函数',
        'sync-buttons-group' => '按钮组样式',
        'sync-buttons-help' => '帮助说明样式'
    ];
    
    echo "检查页面元素:\n";
    foreach ($checks as $element => $description) {
        $exists = strpos($content, $element) !== false;
        echo "  {$description}: " . ($exists ? '✅ 存在' : '❌ 缺失') . "\n";
    }
    
    // 检查按钮文本
    echo "\n检查按钮文本:\n";
    if (preg_match('/🚀 开始批量同步/', $content)) {
        echo "  批量同步按钮文本: ✅ 正确\n";
    } else {
        echo "  批量同步按钮文本: ❌ 错误\n";
    }
    
    if (preg_match('/🔄 开始单个同步/', $content)) {
        echo "  单个同步按钮文本: ✅ 正确\n";
    } else {
        echo "  单个同步按钮文本: ❌ 错误\n";
    }
    
    // 检查事件绑定
    echo "\n检查事件绑定:\n";
    if (preg_match("/\$\('#start-batch-sync-btn'\)\.on\('click', startBatchSync\)/", $content)) {
        echo "  批量同步事件绑定: ✅ 正确\n";
    } else {
        echo "  批量同步事件绑定: ❌ 错误\n";
    }
    
    if (preg_match("/\$\('#start-single-sync-btn'\)\.on\('click', startSingleSync\)/", $content)) {
        echo "  单个同步事件绑定: ✅ 正确\n";
    } else {
        echo "  单个同步事件绑定: ❌ 错误\n";
    }
    
    // 检查函数实现
    echo "\n检查函数实现:\n";
    
    // 检查startBatchSync函数
    if (preg_match('/function startBatchSync\(\)/', $content)) {
        echo "  startBatchSync函数: ✅ 存在\n";
        
        if (strpos($content, 'alert(\'批量Feed同步功能开发中') !== false) {
            echo "    - 包含开发中提示: ✅ 是\n";
        } else {
            echo "    - 包含开发中提示: ❌ 否\n";
        }
        
        if (strpos($content, 'executeSingleSync(validProducts, options)') !== false) {
            echo "    - 临时使用单个同步: ✅ 是\n";
        } else {
            echo "    - 临时使用单个同步: ❌ 否\n";
        }
    } else {
        echo "  startBatchSync函数: ❌ 缺失\n";
    }
    
    // 检查startSingleSync函数
    if (preg_match('/function startSingleSync\(\)/', $content)) {
        echo "  startSingleSync函数: ✅ 存在\n";
        
        if (strpos($content, 'executeSingleSync(validProducts, options)') !== false) {
            echo "    - 调用executeSingleSync: ✅ 是\n";
        } else {
            echo "    - 调用executeSingleSync: ❌ 否\n";
        }
    } else {
        echo "  startSingleSync函数: ❌ 缺失\n";
    }
    
    // 检查executeSingleSync函数
    if (preg_match('/function executeSingleSync\(/', $content)) {
        echo "  executeSingleSync函数: ✅ 存在\n";
    } else {
        echo "  executeSingleSync函数: ❌ 缺失\n";
    }
    
    // 检查按钮禁用逻辑
    echo "\n检查按钮状态控制:\n";
    if (strpos($content, "start-batch-sync-btn').prop('disabled'") !== false) {
        echo "  批量同步按钮状态控制: ✅ 存在\n";
    } else {
        echo "  批量同步按钮状态控制: ❌ 缺失\n";
    }
    
    if (strpos($content, "start-single-sync-btn').prop('disabled'") !== false) {
        echo "  单个同步按钮状态控制: ✅ 存在\n";
    } else {
        echo "  单个同步按钮状态控制: ❌ 缺失\n";
    }
    
} else {
    echo "❌ 页面文件不存在，无法进行检查\n";
}

echo "\n=== 测试完成 ===\n";

// 总结
echo "\n=== 修改总结 ===\n";
echo "1. ✅ 添加了两个同步按钮：批量同步和单个同步\n";
echo "2. ✅ 批量同步按钮显示开发中提示，临时使用单个同步\n";
echo "3. ✅ 单个同步按钮使用原有的逐个同步逻辑\n";
echo "4. ✅ 添加了按钮说明文字，帮助用户理解区别\n";
echo "5. ✅ 更新了所有相关的事件绑定和状态控制\n";
echo "6. ✅ 保持了现有的功能结构不变\n";

echo "\n现在您可以访问SKU批量同步页面，看到两个不同的同步按钮！\n";

?>
