<?php
/**
 * 检查失败SKU的实际图片情况
 * 查看这些产品的图片处理日志和实际图片数量
 */

// 加载WordPress环境
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php', 
    __DIR__ . '/../../../../../wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('无法找到WordPress。');
}

// 检查权限
if (!current_user_can('manage_options')) {
    wp_die(__('您没有权限执行此操作。'));
}

echo "<h1>检查失败SKU的实际图片情况</h1>";

// 失败的SKU列表
$failed_skus = [
    'B202P222191',
    'B202S00513', 
    'B202S00514',
    'B202S00492',
    'B202S00493'
];

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

echo "<style>
.sku-section { border: 1px solid #ccc; margin: 20px 0; padding: 15px; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
.info { color: blue; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>";

foreach ($failed_skus as $sku) {
    echo "<div class='sku-section'>";
    echo "<h2>SKU: {$sku}</h2>";
    
    // 1. 首先找到这个SKU对应的产品ID
    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
    ", $sku));
    
    if (!$product_id) {
        echo "<p class='error'>❌ 未找到SKU对应的产品</p>";
        echo "</div>";
        continue;
    }
    
    echo "<p class='info'>📦 产品ID: {$product_id}</p>";
    
    // 2. 获取产品对象，检查实际图片
    $product = wc_get_product($product_id);
    if (!$product) {
        echo "<p class='error'>❌ 无法获取产品对象</p>";
        echo "</div>";
        continue;
    }
    
    // 3. 检查产品的实际图片情况
    echo "<h3>📸 实际图片情况</h3>";
    
    // 主图
    $main_image_id = $product->get_image_id();
    $main_image_url = $main_image_id ? wp_get_attachment_url($main_image_id) : '';
    echo "<p><strong>主图:</strong> " . ($main_image_url ? "✅ 有 ({$main_image_url})" : "❌ 无") . "</p>";
    
    // 图库图片
    $gallery_image_ids = $product->get_gallery_image_ids();
    echo "<p><strong>图库图片数量:</strong> " . count($gallery_image_ids) . "</p>";
    
    if (!empty($gallery_image_ids)) {
        echo "<ul>";
        foreach ($gallery_image_ids as $index => $image_id) {
            $image_url = wp_get_attachment_url($image_id);
            echo "<li>图片" . ($index + 1) . ": {$image_url}</li>";
        }
        echo "</ul>";
    }
    
    // 远程图库（如果有）
    $remote_gallery_urls = get_post_meta($product_id, '_remote_gallery_urls', true);
    if (is_array($remote_gallery_urls) && !empty($remote_gallery_urls)) {
        echo "<p><strong>远程图库图片数量:</strong> " . count($remote_gallery_urls) . "</p>";
        echo "<ul>";
        foreach ($remote_gallery_urls as $index => $url) {
            echo "<li>远程图片" . ($index + 1) . ": {$url}</li>";
        }
        echo "</ul>";
    }
    
    // 4. 查找这个产品的图片处理日志
    echo "<h3>📋 图片处理日志</h3>";
    
    // 查找图片相关的日志
    $image_logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$logs_table} 
        WHERE (action LIKE '%图片%' OR action LIKE '%产品图片%') 
        AND request LIKE %s
        ORDER BY created_at DESC 
        LIMIT 10
    ", '%' . $product_id . '%'));
    
    if (!empty($image_logs)) {
        echo "<table>";
        echo "<tr><th>时间</th><th>操作</th><th>状态</th><th>详情</th></tr>";
        foreach ($image_logs as $log) {
            echo "<tr>";
            echo "<td>{$log->created_at}</td>";
            echo "<td>{$log->action}</td>";
            echo "<td>{$log->status}</td>";
            
            // 解析日志详情
            $request_data = json_decode($log->request, true);
            if ($request_data) {
                $details = [];
                if (isset($request_data['original_count'])) {
                    $details[] = "原始数量: {$request_data['original_count']}";
                }
                if (isset($request_data['final_count'])) {
                    $details[] = "最终数量: {$request_data['final_count']}";
                }
                if (isset($request_data['placeholder_used'])) {
                    $details[] = "使用占位符: " . ($request_data['placeholder_used'] ? '是' : '否');
                }
                if (isset($request_data['meets_walmart_requirement'])) {
                    $details[] = "满足要求: " . ($request_data['meets_walmart_requirement'] ? '是' : '否');
                }
                echo "<td>" . implode(', ', $details) . "</td>";
            } else {
                echo "<td>{$log->message}</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ 未找到图片处理日志</p>";
    }
    
    // 5. 查找最近的产品映射日志
    echo "<h3>🔄 产品映射日志</h3>";
    
    $mapping_logs = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$logs_table} 
        WHERE action LIKE '%产品映射%' 
        AND request LIKE %s
        ORDER BY created_at DESC 
        LIMIT 5
    ", '%' . $sku . '%'));
    
    if (!empty($mapping_logs)) {
        foreach ($mapping_logs as $log) {
            echo "<h4>{$log->action} - {$log->created_at}</h4>";
            $request_data = json_decode($log->request, true);
            if ($request_data && isset($request_data['additionalImages'])) {
                $additional_images = $request_data['additionalImages'];
                echo "<p><strong>最终副图数量:</strong> " . count($additional_images) . "</p>";
                if (!empty($additional_images)) {
                    echo "<ul>";
                    foreach ($additional_images as $index => $url) {
                        echo "<li>副图" . ($index + 1) . ": {$url}</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='error'>❌ 副图数组为空！</p>";
                }
            }
        }
    } else {
        echo "<p class='warning'>⚠️ 未找到产品映射日志</p>";
    }
    
    echo "</div>";
}

// 6. 检查占位符配置
echo "<div class='sku-section'>";
echo "<h2>🖼️ 占位符配置检查</h2>";

$placeholder_1 = get_option('woo_walmart_placeholder_image_1', '');
$placeholder_2 = get_option('woo_walmart_placeholder_image_2', '');

echo "<p><strong>占位符图片1:</strong> " . ($placeholder_1 ?: '未设置') . "</p>";
if (!empty($placeholder_1)) {
    echo "<p>URL验证: " . (filter_var($placeholder_1, FILTER_VALIDATE_URL) ? '✅ 有效' : '❌ 无效') . "</p>";
}

echo "<p><strong>占位符图片2:</strong> " . ($placeholder_2 ?: '未设置') . "</p>";
if (!empty($placeholder_2)) {
    echo "<p>URL验证: " . (filter_var($placeholder_2, FILTER_VALIDATE_URL) ? '✅ 有效' : '❌ 无效') . "</p>";
}

echo "</div>";

?>
