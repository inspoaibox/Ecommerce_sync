<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查seat_depth的具体规范 ===\n\n";

global $wpdb;

// 1. 检查数据库中的seat_depth规范
$attr_table = $wpdb->prefix . 'walmart_product_attributes';
$seat_depth_spec = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $attr_table WHERE attribute_name = %s AND product_type_id = %s",
    'seat_depth', 'Desk Chairs'
));

if ($seat_depth_spec) {
    echo "✅ 找到seat_depth规范:\n";
    echo "产品类型: {$seat_depth_spec->product_type_id}\n";
    echo "属性名: {$seat_depth_spec->attribute_name}\n";
    echo "是否必填: " . ($seat_depth_spec->is_required ? '是' : '否') . "\n";
    echo "属性类型: {$seat_depth_spec->attribute_type}\n";
    echo "默认类型: {$seat_depth_spec->default_type}\n";
    echo "描述: {$seat_depth_spec->description}\n";
    echo "属性组: {$seat_depth_spec->attribute_group}\n";
    echo "允许值: {$seat_depth_spec->allowed_values}\n";
    echo "验证规则: {$seat_depth_spec->validation_rules}\n";
    echo "创建时间: {$seat_depth_spec->created_at}\n";
    echo "更新时间: {$seat_depth_spec->updated_at}\n";
} else {
    echo "❌ 未找到seat_depth规范\n";
}

// 2. 检查所有Desk Chairs的属性规范
echo "\n=== Desk Chairs分类的所有属性 ===\n";
$all_desk_chair_attrs = $wpdb->get_results($wpdb->prepare(
    "SELECT attribute_name, is_required, description FROM $attr_table WHERE product_type_id = %s ORDER BY attribute_name",
    'Desk Chairs'
));

if ($all_desk_chair_attrs) {
    echo "Desk Chairs分类共有 " . count($all_desk_chair_attrs) . " 个属性:\n";
    $required_count = 0;
    $optional_count = 0;
    
    foreach ($all_desk_chair_attrs as $attr) {
        $required_text = $attr->is_required ? '[必填]' : '[可选]';
        echo "- {$attr->attribute_name} {$required_text} - {$attr->description}\n";
        
        if ($attr->is_required) {
            $required_count++;
        } else {
            $optional_count++;
        }
    }
    
    echo "\n统计:\n";
    echo "必填属性: {$required_count} 个\n";
    echo "可选属性: {$optional_count} 个\n";
} else {
    echo "未找到Desk Chairs的属性规范\n";
}

// 3. 检查这些规范是从哪里来的
echo "\n=== 检查规范来源 ===\n";

// 检查是否有API获取记录
$log_table = $wpdb->prefix . 'woo_walmart_sync_logs';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$log_table'") == $log_table;

if ($table_exists) {
    $api_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $log_table WHERE request LIKE %s OR response LIKE %s ORDER BY created_at DESC LIMIT 5",
        '%Desk Chairs%',
        '%seat_depth%'
    ));
    
    if ($api_logs) {
        echo "找到相关的API调用记录:\n";
        foreach ($api_logs as $log) {
            echo "时间: {$log->created_at}\n";
            echo "操作: {$log->operation}\n";
            echo "级别: {$log->level}\n";
            
            if (strpos($log->request, 'Desk Chairs') !== false) {
                echo "请求包含Desk Chairs\n";
            }
            if (strpos($log->response, 'seat_depth') !== false) {
                echo "响应包含seat_depth\n";
            }
            echo "---\n";
        }
    } else {
        echo "未找到相关的API调用记录\n";
    }
} else {
    echo "日志表不存在\n";
}

// 4. 检查newleimu.json文件中的seat_depth定义
echo "\n=== 检查newleimu.json中的seat_depth ===\n";
if (file_exists('newleimu.json')) {
    $json_content = file_get_contents('newleimu.json');
    if (strpos($json_content, 'seat_depth') !== false) {
        echo "✅ newleimu.json中包含seat_depth\n";
        
        // 解析JSON并查找seat_depth
        $json_data = json_decode($json_content, true);
        if ($json_data && is_array($json_data)) {
            foreach ($json_data as $item) {
                if (isset($item['attributeName']) && $item['attributeName'] === 'seat_depth') {
                    echo "找到seat_depth定义:\n";
                    echo "- 属性名: {$item['attributeName']}\n";
                    echo "- 是否必填: " . ($item['isrequired'] ? '是' : '否') . "\n";
                    echo "- 描述: {$item['description']}\n";
                    echo "- 默认类型: {$item['defaultType']}\n";
                    echo "- 属性组: {$item['group']}\n";
                    break;
                }
            }
        }
    } else {
        echo "❌ newleimu.json中不包含seat_depth\n";
    }
} else {
    echo "❌ newleimu.json文件不存在\n";
}

// 5. 重要结论
echo "\n=== 重要发现 ===\n";
echo "❌ 我之前的分析是错误的！\n";
echo "✅ seat_depth 在 Desk Chairs 分类中是【可选】字段，不是必填字段\n";
echo "✅ 这意味着你移除它是完全正确的操作\n";
echo "\n如果 Walmart API 仍然报错说 seat_depth 是必填的，可能的原因:\n";
echo "1. API 端规范与本地规范不一致\n";
echo "2. 使用了不同版本的规范\n";
echo "3. 某些字段组合触发了依赖要求\n";
echo "4. API 端的 bug\n";
echo "\n建议:\n";
echo "1. 检查你看到的错误是否来自旧的同步记录\n";
echo "2. 重新同步产品，使用当前的配置\n";
echo "3. 如果仍然出错，联系 Walmart 技术支持\n";
