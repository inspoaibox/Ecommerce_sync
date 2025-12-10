<?php
/**
 * 简化分析8张图片逻辑
 */

// 直接连接数据库
$host = 'localhost';
$dbname = 'test_localhost';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

echo "=== 直接分析8张图片逻辑 ===\n\n";

$product_id = 13917;

// 1. 检查产品的图片meta数据
echo "=== 产品图片Meta数据 ===\n";

$stmt = $pdo->prepare("
    SELECT meta_key, meta_value 
    FROM wp_postmeta 
    WHERE post_id = ? 
    AND (meta_key LIKE '%image%' OR meta_key LIKE '%gallery%' OR meta_key LIKE '%thumbnail%' OR meta_key LIKE '%remote%')
    ORDER BY meta_key
");
$stmt->execute([$product_id]);
$meta_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($meta_data as $meta) {
    echo "{$meta['meta_key']}: ";
    if (strlen($meta['meta_value']) > 100) {
        echo substr($meta['meta_value'], 0, 100) . "...";
    } else {
        echo $meta['meta_value'];
    }
    echo "\n";
}

// 2. 分析图库ID
$gallery_meta = null;
$remote_gallery_meta = null;

foreach ($meta_data as $meta) {
    if ($meta['meta_key'] === '_product_image_gallery') {
        $gallery_meta = $meta['meta_value'];
    }
    if ($meta['meta_key'] === '_remote_gallery_urls') {
        $remote_gallery_meta = $meta['meta_value'];
    }
}

echo "\n=== 图库分析 ===\n";

if ($gallery_meta) {
    $gallery_ids = explode(',', $gallery_meta);
    echo "图库ID数量: " . count($gallery_ids) . "\n";
    echo "图库IDs: " . implode(', ', $gallery_ids) . "\n";
    
    // 检查这些ID是否存在于posts表
    $valid_ids = 0;
    foreach ($gallery_ids as $gid) {
        $gid = trim($gid);
        if (!empty($gid)) {
            $stmt = $pdo->prepare("SELECT ID FROM wp_posts WHERE ID = ?");
            $stmt->execute([$gid]);
            if ($stmt->fetch()) {
                $valid_ids++;
            } else {
                echo "ID {$gid} 在posts表中不存在\n";
            }
        }
    }
    echo "有效的图库ID数量: {$valid_ids}\n";
} else {
    echo "没有图库数据\n";
}

if ($remote_gallery_meta) {
    $remote_urls = json_decode($remote_gallery_meta, true);
    if (is_array($remote_urls)) {
        echo "远程图库数量: " . count($remote_urls) . "\n";
        foreach ($remote_urls as $i => $url) {
            echo "远程图{$i+1}: " . substr($url, 0, 80) . "...\n";
        }
    } else {
        echo "远程图库数据格式异常: {$remote_gallery_meta}\n";
    }
} else {
    echo "没有远程图库数据\n";
}

// 3. 计算总数
$gallery_count = $gallery_meta ? count(explode(',', $gallery_meta)) : 0;
$remote_count = 0;

if ($remote_gallery_meta) {
    $remote_urls = json_decode($remote_gallery_meta, true);
    if (is_array($remote_urls)) {
        $remote_count = count($remote_urls);
    }
}

$total_count = $gallery_count + $remote_count;

echo "\n=== 总计分析 ===\n";
echo "图库图片: {$gallery_count}张\n";
echo "远程图库: {$remote_count}张\n";
echo "理论总数: {$total_count}张\n";

if ($total_count == 8) {
    echo "✅ 确认8张图片来源: {$gallery_count} + {$remote_count} = 8\n";
} else {
    echo "❓ 总数不是8张，而是{$total_count}张\n";
}

// 4. 分析为什么最终只有4张
echo "\n=== 分析最终只有4张的原因 ===\n";

if ($gallery_count == 4 && $remote_count == 4) {
    echo "假设1: 图库的4张图片ID无效，只有远程的4张有效\n";
    
    // 检查图库ID的有效性
    if ($gallery_meta) {
        $gallery_ids = explode(',', $gallery_meta);
        $invalid_count = 0;
        
        foreach ($gallery_ids as $gid) {
            $gid = trim($gid);
            if (strpos($gid, 'remote_') === 0) {
                echo "图库ID {$gid} 是remote_类型，wp_get_attachment_url()会返回空\n";
                $invalid_count++;
            }
        }
        
        if ($invalid_count == count($gallery_ids)) {
            echo "✅ 确认：所有图库ID都是remote_类型，无法获取URL\n";
            echo "只有远程图库的{$remote_count}张图片有效\n";
            echo "这就是为什么最终只有4张的原因！\n";
        }
    }
}

// 5. 检查日志中的处理记录
echo "\n=== 检查处理日志 ===\n";

$stmt = $pdo->prepare("
    SELECT action, status, created_at, message 
    FROM wp_woo_walmart_sync_logs 
    WHERE request LIKE ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute(["%{$product_id}%"]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($logs)) {
    foreach ($logs as $log) {
        echo "{$log['created_at']} - {$log['action']} ({$log['status']}): {$log['message']}\n";
    }
} else {
    echo "没有找到相关日志\n";
}

?>
