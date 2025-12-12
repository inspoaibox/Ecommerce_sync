<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 调试属性配置问题 ===\n\n";

global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

// 1. 检查 Luggage & Luggage Sets 的属性配置
echo "1. 检查 Luggage & Luggage Sets 的属性配置:\n";
$luggage_mapping = $wpdb->get_row("SELECT * FROM $map_table WHERE walmart_category_path = 'Luggage & Luggage Sets'");

if ($luggage_mapping) {
    echo "映射ID: {$luggage_mapping->id}\n";
    echo "分类名: {$luggage_mapping->wc_category_name}\n";
    
    if (!empty($luggage_mapping->walmart_attributes)) {
        $attributes = json_decode($luggage_mapping->walmart_attributes, true);
        
        if ($attributes && isset($attributes['name'])) {
            echo "配置的属性数量: " . count($attributes['name']) . "\n\n";
            
            echo "所有属性列表:\n";
            for ($i = 0; $i < count($attributes['name']); $i++) {
                $name = $attributes['name'][$i] ?? '';
                $type = $attributes['type'][$i] ?? '';
                $source = $attributes['source'][$i] ?? '';
                $required = $attributes['required_level'][$i] ?? '';
                
                echo sprintf("%3d. %-40s | %-15s | %-20s | %s\n", 
                    $i + 1, 
                    $name, 
                    $type, 
                    $source,
                    $required
                );
                
                // 检查是否包含错误的属性名
                $error_attributes = [
                    'Skateboard', 'Baby', 'Power', 'Microphone', 'Eyeglass', 
                    'Electric', 'Radio', 'Sticky', 'Matcha', 'Taffy'
                ];
                
                foreach ($error_attributes as $error_attr) {
                    if (stripos($name, $error_attr) !== false) {
                        echo "    ⚠️  发现可疑属性: {$name}\n";
                    }
                }
            }
        } else {
            echo "❌ 属性解析失败\n";
            echo "原始数据: " . substr($luggage_mapping->walmart_attributes, 0, 200) . "...\n";
        }
    } else {
        echo "❌ 没有属性配置\n";
    }
} else {
    echo "❌ 未找到 Luggage & Luggage Sets 映射\n";
}

// 2. 检查规范服务
echo "\n\n2. 检查规范服务:\n";
require_once 'includes/class-walmart-spec-service.php';

if (class_exists('Walmart_Spec_Service')) {
    $spec_service = new Walmart_Spec_Service();
    echo "规范服务类已加载\n";
    
    // 检查是否有自动检测功能
    $reflection = new ReflectionClass('Walmart_Spec_Service');
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    echo "可用方法:\n";
    foreach ($methods as $method) {
        if (!$method->isConstructor() && !$method->isDestructor()) {
            echo "  - {$method->getName()}\n";
        }
    }
} else {
    echo "❌ 规范服务类未找到\n";
}

// 3. 检查最近的同步日志
echo "\n\n3. 检查最近的同步日志:\n";
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$recent_logs = $wpdb->get_results(
    "SELECT * FROM $logs_table 
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) 
     AND (action LIKE '%规范服务%' OR action LIKE '%属性%' OR action LIKE '%映射%')
     ORDER BY created_at DESC 
     LIMIT 10"
);

foreach ($recent_logs as $log) {
    echo "[{$log->created_at}] {$log->action}\n";
    if (!empty($log->request)) {
        $request_data = json_decode($log->request, true);
        if ($request_data) {
            echo "  数据: " . json_encode($request_data, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "---\n";
}

echo "\n=== 调试完成 ===\n";
?>
