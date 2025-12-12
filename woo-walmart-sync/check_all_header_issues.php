<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 检查所有MPItemFeedHeader相关问题 ===\n\n";

// 搜索所有可能的废弃字段
$plugin_dir = 'D:/phpstudy_pro/WWW/test.localhost/wp-content/plugins/woo-walmart-sync';

function search_deprecated_fields($dir) {
    $deprecated_fields = [
        'sellingChannel',
        'processMode', 
        'subset',
        'subCategory'
    ];
    
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    $findings = [];
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $relative_path = str_replace($dir . '/', '', $file);
        
        // 跳过测试文件
        if (strpos($relative_path, 'test_') === 0 || 
            strpos($relative_path, 'check_') === 0 ||
            strpos($relative_path, 'debug_') === 0 ||
            strpos($relative_path, 'find_') === 0) {
            continue;
        }
        
        foreach ($deprecated_fields as $field) {
            // 查找字段定义（作为键）
            if (preg_match("/['\"]" . $field . "['\"]\\s*=>/", $content) ||
                preg_match("/['\"]" . $field . "['\"]\\s*:/", $content)) {
                
                $lines = explode("\n", $content);
                foreach ($lines as $line_num => $line) {
                    if (preg_match("/['\"]" . $field . "['\"]\\s*[=>:]/", $line)) {
                        $findings[] = [
                            'file' => $relative_path,
                            'line' => $line_num + 1,
                            'field' => $field,
                            'content' => trim($line),
                            'context' => 'definition'
                        ];
                    }
                }
            }
        }
    }
    
    return $findings;
}

$findings = search_deprecated_fields($plugin_dir);

echo "搜索废弃字段结果:\n\n";

if (empty($findings)) {
    echo "✅ 没有找到废弃字段的使用\n";
} else {
    $grouped_findings = [];
    foreach ($findings as $finding) {
        $grouped_findings[$finding['file']][] = $finding;
    }
    
    foreach ($grouped_findings as $file => $file_findings) {
        echo "=== 文件: {$file} ===\n";
        
        foreach ($file_findings as $finding) {
            echo "第{$finding['line']}行 [{$finding['field']}]: {$finding['content']}\n";
        }
        
        echo "\n";
    }
}

// 特别检查所有MPItemFeedHeader的创建
echo "\n=== 检查所有MPItemFeedHeader创建位置 ===\n";

function find_mpitem_headers($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    $headers = [];
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $relative_path = str_replace($dir . '/', '', $file);
        
        // 跳过测试文件
        if (strpos($relative_path, 'test_') === 0 || 
            strpos($relative_path, 'check_') === 0 ||
            strpos($relative_path, 'debug_') === 0 ||
            strpos($relative_path, 'find_') === 0) {
            continue;
        }
        
        // 查找MPItemFeedHeader的创建
        if (strpos($content, 'MPItemFeedHeader') !== false) {
            $lines = explode("\n", $content);
            foreach ($lines as $line_num => $line) {
                if (strpos($line, 'MPItemFeedHeader') !== false && 
                    (strpos($line, '=>') !== false || strpos($line, ':') !== false)) {
                    
                    $headers[] = [
                        'file' => $relative_path,
                        'line' => $line_num + 1,
                        'content' => trim($line)
                    ];
                }
            }
        }
    }
    
    return $headers;
}

$headers = find_mpitem_headers($plugin_dir);

foreach ($headers as $header) {
    echo "文件: {$header['file']}, 第{$header['line']}行\n";
    echo "  {$header['content']}\n\n";
}

// 验证修复结果
echo "\n=== 验证修复结果 ===\n";

// 检查build_batch_feed_data方法
$main_file = $plugin_dir . '/woo-walmart-sync.php';
$content = file_get_contents($main_file);

echo "检查build_batch_feed_data方法修复情况:\n";

// 查找build_batch_feed_data方法的return语句
if (preg_match('/private function build_batch_feed_data.*?return \[(.*?)\];/s', $content, $matches)) {
    $return_content = $matches[1];
    
    // 检查是否包含businessUnit
    if (strpos($return_content, 'businessUnit') !== false) {
        echo "✅ 包含businessUnit字段\n";
    } else {
        echo "❌ 缺少businessUnit字段\n";
    }
    
    // 检查是否移除了废弃字段
    $deprecated_fields = ['sellingChannel', 'processMode', 'subset', 'subCategory'];
    $found_deprecated = [];
    
    foreach ($deprecated_fields as $field) {
        if (strpos($return_content, $field) !== false) {
            $found_deprecated[] = $field;
        }
    }
    
    if (empty($found_deprecated)) {
        echo "✅ 已移除所有废弃字段\n";
    } else {
        echo "❌ 仍包含废弃字段: " . implode(', ', $found_deprecated) . "\n";
    }
    
} else {
    echo "❌ 无法找到build_batch_feed_data方法的return语句\n";
}

echo "\n=== 检查完成 ===\n";
echo "如果所有检查都通过，批量同步的header问题应该已经修复\n";
?>
