<?php
/**
 * 测试批量同步功能实现
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 测试批量同步功能实现 ===\n\n";

// 1. 检查前端页面修改
echo "1. 检查前端页面修改:\n";

$page_file = plugin_dir_path(__FILE__) . 'admin/sku-batch-sync.php';
if (file_exists($page_file)) {
    $content = file_get_contents($page_file);
    
    $frontend_checks = [
        'start-batch-sync-btn' => '批量同步按钮',
        'executeBatchFeedSync' => '批量Feed同步函数',
        'processBatchSyncResult' => '批量同步结果处理函数',
        'walmart_batch_sync_products' => '批量同步AJAX action',
        '5个以上产品使用批量同步' => '使用建议文字'
    ];
    
    foreach ($frontend_checks as $element => $description) {
        $exists = strpos($content, $element) !== false;
        echo "  {$description}: " . ($exists ? '✅ 存在' : '❌ 缺失') . "\n";
    }
} else {
    echo "  ❌ 页面文件不存在\n";
}

// 2. 检查后端函数实现
echo "\n2. 检查后端函数实现:\n";

$backend_functions = [
    'execute_walmart_batch_feed_sync' => '批量Feed同步主函数',
    'validate_product_for_batch_sync' => '产品验证函数',
    'build_batch_feed_data' => '批量Feed数据构建函数',
    'record_batch_feed_status' => '批量Feed状态记录函数'
];

foreach ($backend_functions as $function => $description) {
    if (function_exists($function)) {
        echo "  {$description}: ✅ 存在\n";
    } else {
        echo "  {$description}: ❌ 缺失\n";
    }
}

// 3. 检查AJAX处理器
echo "\n3. 检查AJAX处理器:\n";

if (has_action('wp_ajax_walmart_batch_sync_products')) {
    echo "  批量同步AJAX处理器: ✅ 已注册\n";
} else {
    echo "  批量同步AJAX处理器: ❌ 未注册\n";
}

// 4. 测试产品验证函数
echo "\n4. 测试产品验证函数:\n";

if (function_exists('validate_product_for_batch_sync')) {
    // 测试一个真实产品
    $test_product_id = 25926; // W1191S00043
    $test_product = wc_get_product($test_product_id);
    
    if ($test_product) {
        echo "  测试产品: {$test_product->get_name()}\n";
        echo "  SKU: {$test_product->get_sku()}\n";
        
        $validation_errors = validate_product_for_batch_sync($test_product, false);
        
        if (empty($validation_errors)) {
            echo "  验证结果: ✅ 通过验证\n";
        } else {
            echo "  验证结果: ❌ 验证失败\n";
            foreach ($validation_errors as $error) {
                echo "    - {$error}\n";
            }
        }
    } else {
        echo "  ❌ 测试产品不存在\n";
    }
} else {
    echo "  ❌ 验证函数不存在\n";
}

// 5. 测试批量Feed数据构建
echo "\n5. 测试批量Feed数据构建:\n";

if (function_exists('build_batch_feed_data')) {
    $test_products = [];
    
    // 获取几个测试产品
    $test_product_ids = [25926]; // 可以添加更多产品ID
    
    foreach ($test_product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $test_products[] = $product;
        }
    }
    
    if (!empty($test_products)) {
        echo "  测试产品数量: " . count($test_products) . "\n";
        
        try {
            $batch_data = build_batch_feed_data($test_products, false);
            
            if ($batch_data) {
                echo "  批量Feed构建: ✅ 成功\n";
                echo "  Feed结构检查:\n";
                
                if (isset($batch_data['MPItemFeedHeader'])) {
                    echo "    - Header: ✅ 存在\n";
                    echo "    - Request ID: " . ($batch_data['MPItemFeedHeader']['requestId'] ?? '缺失') . "\n";
                } else {
                    echo "    - Header: ❌ 缺失\n";
                }
                
                if (isset($batch_data['MPItem']) && is_array($batch_data['MPItem'])) {
                    echo "    - Items: ✅ 存在 (" . count($batch_data['MPItem']) . "个)\n";
                } else {
                    echo "    - Items: ❌ 缺失\n";
                }
                
            } else {
                echo "  批量Feed构建: ❌ 失败\n";
            }
            
        } catch (Exception $e) {
            echo "  批量Feed构建: ❌ 异常 - " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ❌ 没有有效的测试产品\n";
    }
} else {
    echo "  ❌ 构建函数不存在\n";
}

// 6. 检查数据库表
echo "\n6. 检查数据库表:\n";

global $wpdb;
$feed_table = $wpdb->prefix . 'walmart_feed_status';

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$feed_table}'") == $feed_table;
echo "  Feed状态表: " . ($table_exists ? '✅ 存在' : '❌ 不存在') . "\n";

if ($table_exists) {
    $columns = $wpdb->get_results("DESCRIBE {$feed_table}");
    $column_names = array_column($columns, 'Field');
    
    $required_columns = ['feed_id', 'product_id', 'status', 'sync_type'];
    foreach ($required_columns as $col) {
        $exists = in_array($col, $column_names);
        echo "    - {$col}列: " . ($exists ? '✅' : '❌') . "\n";
    }
}

echo "\n=== 功能完整性检查 ===\n";

$completeness_score = 0;
$total_checks = 8;

// 检查各个组件
$checks = [
    '前端批量同步按钮' => strpos(file_get_contents($page_file), 'start-batch-sync-btn') !== false,
    '前端批量同步函数' => strpos(file_get_contents($page_file), 'executeBatchFeedSync') !== false,
    'AJAX处理器' => has_action('wp_ajax_walmart_batch_sync_products'),
    '后端主函数' => function_exists('execute_walmart_batch_feed_sync'),
    '产品验证函数' => function_exists('validate_product_for_batch_sync'),
    'Feed构建函数' => function_exists('build_batch_feed_data'),
    '状态记录函数' => function_exists('record_batch_feed_status'),
    '数据库表' => $table_exists
];

foreach ($checks as $check => $result) {
    if ($result) {
        $completeness_score++;
        echo "✅ {$check}\n";
    } else {
        echo "❌ {$check}\n";
    }
}

$percentage = round(($completeness_score / $total_checks) * 100);
echo "\n完整性评分: {$completeness_score}/{$total_checks} ({$percentage}%)\n";

if ($percentage >= 90) {
    echo "🎉 批量同步功能实现完整，可以进行测试！\n";
} elseif ($percentage >= 70) {
    echo "⚠️ 批量同步功能基本完整，但有部分组件缺失\n";
} else {
    echo "❌ 批量同步功能实现不完整，需要继续开发\n";
}

echo "\n=== 使用说明 ===\n";
echo "1. 访问SKU批量同步页面\n";
echo "2. 输入5个以上的SKU（建议使用批量同步）\n";
echo "3. 点击验证SKU按钮\n";
echo "4. 选择'🚀 开始批量同步'按钮\n";
echo "5. 系统将把所有产品打包成一个Feed提交给Walmart\n";
echo "6. 查看同步结果和Feed状态\n";

?>
