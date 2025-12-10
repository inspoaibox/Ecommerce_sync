<?php
/**
 * 检查确切的代码执行路径
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

echo "=== 检查确切的代码执行路径 ===\n\n";

$product_id = 13917;

// 1. 检查是否有"图片补足-4张"的日志
echo "=== 检查图片补足日志 ===\n";

$stmt = $pdo->prepare("
    SELECT * FROM wp_woo_walmart_sync_logs 
    WHERE action = '图片补足-4张' 
    AND request LIKE ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute(["%{$product_id}%"]);
$补足日志 = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($补足日志)) {
    echo "✅ 找到图片补足日志:\n";
    foreach ($补足日志 as $log) {
        echo "时间: {$log['created_at']}\n";
        echo "状态: {$log['status']}\n";
        echo "消息: {$log['message']}\n";
        
        $request_data = json_decode($log['request'], true);
        if ($request_data) {
            echo "原始数量: " . ($request_data['original_count'] ?? '未知') . "\n";
            echo "最终数量: " . ($request_data['final_count'] ?? '未知') . "\n";
            echo "占位符1: " . ($request_data['placeholder_1'] ?? '未知') . "\n";
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到'图片补足-4张'日志\n";
    echo "这说明第287行的条件判断没有成立\n";
}

// 2. 检查是否有"产品图片字段"的日志
echo "\n=== 检查产品图片字段日志 ===\n";

$stmt = $pdo->prepare("
    SELECT * FROM wp_woo_walmart_sync_logs 
    WHERE action = '产品图片字段' 
    AND request LIKE ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute(["%{$product_id}%"]);
$字段日志 = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($字段日志)) {
    echo "✅ 找到产品图片字段日志:\n";
    foreach ($字段日志 as $log) {
        echo "时间: {$log['created_at']}\n";
        echo "状态: {$log['status']}\n";
        echo "消息: {$log['message']}\n";
        
        $request_data = json_decode($log['request'], true);
        if ($request_data) {
            echo "原始图片数量: " . ($request_data['original_images_count'] ?? '未知') . "\n";
            echo "最终图片数量: " . ($request_data['final_images_count'] ?? '未知') . "\n";
            echo "使用占位符: " . ($request_data['placeholder_used'] ? '是' : '否') . "\n";
            echo "满足沃尔玛要求: " . ($request_data['meets_walmart_requirement'] ? '是' : '否') . "\n";
        }
        echo "---\n";
    }
} else {
    echo "❌ 没有找到'产品图片字段'日志\n";
    echo "这说明第342行的日志记录没有执行\n";
}

// 3. 检查"产品图片获取"日志
echo "\n=== 检查产品图片获取日志 ===\n";

$stmt = $pdo->prepare("
    SELECT * FROM wp_woo_walmart_sync_logs 
    WHERE action = '产品图片获取' 
    AND request LIKE ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute(["%{$product_id}%"]);
$获取日志 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($获取日志) {
    echo "✅ 找到产品图片获取日志:\n";
    echo "时间: {$获取日志['created_at']}\n";
    
    $request_data = json_decode($获取日志['request'], true);
    if ($request_data) {
        echo "additional_images_count: " . ($request_data['additional_images_count'] ?? '未知') . "\n";
        echo "remote_gallery_count: " . ($request_data['remote_gallery_count'] ?? '未知') . "\n";
        
        if (isset($request_data['additional_images'])) {
            $additional_images = $request_data['additional_images'];
            echo "实际additional_images数量: " . count($additional_images) . "\n";
            
            // 这个数量应该是4，如果是4，那么第287行的条件应该成立
            if (count($additional_images) == 4) {
                echo "✅ additional_images = 4张，第287行条件应该成立\n";
                echo "但没有找到'图片补足-4张'日志，说明有其他问题\n";
            } else {
                echo "❌ additional_images ≠ 4张，第287行条件不成立\n";
            }
        }
    }
} else {
    echo "❌ 没有找到'产品图片获取'日志\n";
}

// 4. 检查所有相关的日志，看看代码执行到哪里停止了
echo "\n=== 检查所有相关日志 ===\n";

$stmt = $pdo->prepare("
    SELECT action, status, created_at, message 
    FROM wp_woo_walmart_sync_logs 
    WHERE request LIKE ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute(["%{$product_id}%"]);
$所有日志 = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($所有日志)) {
    echo "最近的相关日志:\n";
    foreach ($所有日志 as $log) {
        echo "{$log['created_at']} - {$log['action']} ({$log['status']}): {$log['message']}\n";
    }
} else {
    echo "没有找到任何相关日志\n";
}

// 5. 检查占位符配置
echo "\n=== 检查占位符配置 ===\n";

$stmt = $pdo->prepare("
    SELECT option_value FROM wp_options 
    WHERE option_name = 'woo_walmart_placeholder_image_1'
");
$stmt->execute();
$placeholder_1 = $stmt->fetchColumn();

echo "占位符1: " . ($placeholder_1 ?: '(空)') . "\n";

if ($placeholder_1) {
    $is_valid_url = filter_var($placeholder_1, FILTER_VALIDATE_URL);
    echo "URL有效性: " . ($is_valid_url ? '有效' : '无效') . "\n";
    
    if (!$is_valid_url) {
        echo "❌ 占位符1 URL无效，这可能是第290行条件不成立的原因\n";
    }
}

// 6. 分析可能的问题
echo "\n=== 问题分析 ===\n";

if (empty($补足日志) && empty($字段日志)) {
    echo "❌ 关键发现：映射器的图片处理部分根本没有执行\n";
    echo "可能的原因:\n";
    echo "1. 映射器的map()方法没有被调用\n";
    echo "2. map()方法在图片处理之前就返回或异常了\n";
    echo "3. 使用了不同的代码路径\n";
} else if (empty($补足日志) && !empty($字段日志)) {
    echo "❌ 图片字段日志存在，但补足日志不存在\n";
    echo "说明第287行的条件判断没有成立\n";
    echo "需要检查$original_count的实际值\n";
}

?>
