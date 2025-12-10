<?php
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-config.php';
require_once 'D:/phpstudy_pro\WWW\test.localhost\wp-load.php';

echo "=== 查找batch_feed相关代码 ===\n\n";

// 搜索所有PHP文件中包含batch_feed.json或批量Feed的代码
$plugin_dir = 'D:/phpstudy_pro/WWW/test.localhost/wp-content/plugins/woo-walmart-sync';
$files_to_search = [];

// 递归获取所有PHP文件
function get_php_files($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

$php_files = get_php_files($plugin_dir);

echo "搜索 " . count($php_files) . " 个PHP文件...\n\n";

$search_terms = [
    'batch_feed.json',
    '批量Feed提交',
    '批量Feed记录创建',
    'batch_feed',
    'subset.*=',
    'subset.*:',
    "'subset'",
    '"subset"'
];

$findings = [];

foreach ($php_files as $file) {
    $content = file_get_contents($file);
    $relative_path = str_replace($plugin_dir . '/', '', $file);
    
    foreach ($search_terms as $term) {
        if (stripos($content, $term) !== false || preg_match('/' . $term . '/i', $content)) {
            $lines = explode("\n", $content);
            foreach ($lines as $line_num => $line) {
                if (stripos($line, $term) !== false || preg_match('/' . $term . '/i', $line)) {
                    $findings[] = [
                        'file' => $relative_path,
                        'line' => $line_num + 1,
                        'term' => $term,
                        'content' => trim($line)
                    ];
                }
            }
        }
    }
}

// 按文件分组显示结果
$grouped_findings = [];
foreach ($findings as $finding) {
    $grouped_findings[$finding['file']][] = $finding;
}

foreach ($grouped_findings as $file => $file_findings) {
    echo "=== 文件: {$file} ===\n";
    
    foreach ($file_findings as $finding) {
        echo "第{$finding['line']}行 [{$finding['term']}]: {$finding['content']}\n";
    }
    
    echo "\n";
}

if (empty($findings)) {
    echo "❌ 没有找到相关代码\n";
} else {
    echo "✅ 找到 " . count($findings) . " 个匹配项\n";
}

// 特别检查是否有其他批量同步的类或方法
echo "\n=== 检查其他批量同步类 ===\n";

$batch_classes = [];
foreach ($php_files as $file) {
    $content = file_get_contents($file);
    
    // 查找类定义
    if (preg_match_all('/class\s+([^{\s]+).*{/i', $content, $matches)) {
        foreach ($matches[1] as $class_name) {
            if (stripos($class_name, 'batch') !== false || 
                stripos($class_name, 'bulk') !== false ||
                stripos($class_name, 'feed') !== false) {
                $relative_path = str_replace($plugin_dir . '/', '', $file);
                $batch_classes[] = [
                    'file' => $relative_path,
                    'class' => $class_name
                ];
            }
        }
    }
}

if (!empty($batch_classes)) {
    echo "发现的批量相关类:\n";
    foreach ($batch_classes as $class_info) {
        echo "  {$class_info['class']} (在 {$class_info['file']})\n";
    }
} else {
    echo "没有找到其他批量相关类\n";
}

echo "\n=== 搜索完成 ===\n";
echo "如果没有找到batch_feed.json的生成代码，说明可能是在其他地方或者是动态生成的\n";
?>
