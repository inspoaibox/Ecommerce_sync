<?php
/**
 * 检查get_attributes_from_database函数
 * 找出函数的具体实现和数据来源
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 检查get_attributes_from_database函数 ===\n";
echo "执行时间: " . date('Y-m-d H:i:s') . "\n";

// 自动检测WordPress路径
$wp_path = '';
$current_dir = __DIR__;
for ($i = 0; $i < 5; $i++) {
    $test_path = $current_dir . str_repeat('/..', $i);
    if (file_exists($test_path . '/wp-config.php')) {
        $wp_path = realpath($test_path);
        break;
    }
}

require_once $wp_path . '/wp-config.php';
require_once $wp_path . '/wp-load.php';

echo "✅ WordPress加载成功\n\n";

// 1. 检查函数是否存在
echo "1. 检查函数定义:\n";

if (function_exists('get_attributes_from_database')) {
    echo "✅ 函数存在: get_attributes_from_database\n";
    
    // 使用反射获取函数信息
    $reflection = new ReflectionFunction('get_attributes_from_database');
    echo "函数定义文件: " . $reflection->getFileName() . "\n";
    echo "函数起始行: " . $reflection->getStartLine() . "\n";
    echo "函数结束行: " . $reflection->getEndLine() . "\n";
    
    // 获取函数源码（如果可能）
    $file_content = file_get_contents($reflection->getFileName());
    $lines = explode("\n", $file_content);
    $function_lines = array_slice($lines, $reflection->getStartLine() - 1, 
                                 $reflection->getEndLine() - $reflection->getStartLine() + 1);
    
    echo "\n函数源码:\n";
    echo "```php\n";
    foreach ($function_lines as $line_num => $line) {
        $actual_line = $reflection->getStartLine() + $line_num;
        echo sprintf("%4d: %s\n", $actual_line, $line);
    }
    echo "```\n\n";
    
} else {
    echo "❌ 函数不存在: get_attributes_from_database\n";
    echo "可能在类方法中定义，或者在其他文件中\n\n";
}

// 2. 搜索函数定义
echo "2. 搜索函数定义:\n";

$plugin_dir = __DIR__;
$search_files = [
    $plugin_dir . '/woo-walmart-sync.php',
    $plugin_dir . '/includes/class-product-mapper.php',
    $plugin_dir . '/includes/class-api-key-auth.php'
];

// 添加includes目录下的所有PHP文件
$includes_dir = $plugin_dir . '/includes';
if (is_dir($includes_dir)) {
    $files = glob($includes_dir . '/*.php');
    $search_files = array_merge($search_files, $files);
}

$found_definitions = [];
foreach ($search_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'get_attributes_from_database') !== false) {
            echo "✅ 发现引用: " . basename($file) . "\n";
            
            // 查找函数定义行
            $lines = explode("\n", $content);
            foreach ($lines as $line_num => $line) {
                if (strpos($line, 'function get_attributes_from_database') !== false) {
                    echo "  函数定义在第 " . ($line_num + 1) . " 行\n";
                    echo "  定义: " . trim($line) . "\n";
                    $found_definitions[] = [
                        'file' => $file,
                        'line' => $line_num + 1,
                        'definition' => trim($line)
                    ];
                }
            }
        }
    }
}

if (empty($found_definitions)) {
    echo "❌ 未找到函数定义\n";
} else {
    echo "\n发现 " . count($found_definitions) . " 个函数定义\n";
}

// 3. 测试函数调用
echo "\n3. 测试函数调用:\n";

$test_categories = [
    'Television Stands',
    'Benches',
    'Accent Cabinets',
    'Bed Frames',
    'Dining Tables'
];

foreach ($test_categories as $category) {
    echo "测试分类: {$category}\n";
    
    try {
        if (function_exists('get_attributes_from_database')) {
            $result = get_attributes_from_database($category);
            
            if (empty($result)) {
                echo "  ❌ 无数据\n";
            } else {
                echo "  ✅ 返回 " . count($result) . " 个字段\n";
                
                // 显示前3个字段
                $count = 0;
                foreach ($result as $attr) {
                    if ($count >= 3) break;
                    $name = is_array($attr) ? ($attr['attributeName'] ?? 'Unknown') : 'Unknown';
                    echo "    - {$name}\n";
                    $count++;
                }
                
                // 如果字段数量异常多，这就是问题所在
                if (count($result) > 80) {
                    echo "  🎯 发现问题！字段数量异常: " . count($result) . "\n";
                    echo "  这可能就是100个字段的来源\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "  ❌ 调用失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// 4. 检查数据库表
echo "4. 检查可能的数据库表:\n";

global $wpdb;

$possible_tables = [
    $wpdb->prefix . 'walmart_attributes',
    $wpdb->prefix . 'walmart_category_attributes',
    $wpdb->prefix . 'walmart_specs',
    $wpdb->prefix . 'walmart_category_specs'
];

foreach ($possible_tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "✅ 表存在: {$table} ({$count} 条记录)\n";
        
        // 显示表结构
        $columns = $wpdb->get_results("DESCRIBE {$table}");
        echo "  表结构:\n";
        foreach ($columns as $column) {
            echo "    - {$column->Field} ({$column->Type})\n";
        }
        echo "\n";
    } else {
        echo "❌ 表不存在: {$table}\n";
    }
}

echo "\n=== 检查完成 ===\n";
echo "如果发现get_attributes_from_database函数返回异常多的字段，\n";
echo "那就是问题的根源，需要清理该函数使用的数据源。\n";
?>
