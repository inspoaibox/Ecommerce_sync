<?php
/**
 * 检查实际同步日志和数据
 */

require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 检查实际同步日志和数据 ===\n\n";

$target_sku = 'B081S00179';

// 获取产品ID
global $wpdb;
$product_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
    $target_sku
));

echo "产品ID: {$product_id}\n";
echo "SKU: {$target_sku}\n\n";

// 1. 检查所有同步相关的日志表
echo "1. 检查所有同步相关的日志表:\n";

$log_tables = [];
$all_tables = $wpdb->get_results("SHOW TABLES");

foreach ($all_tables as $table) {
    $table_name = array_values((array)$table)[0];
    if (strpos($table_name, 'walmart') !== false && 
        (strpos($table_name, 'log') !== false || strpos($table_name, 'sync') !== false || strpos($table_name, 'feed') !== false)) {
        $log_tables[] = $table_name;
        echo "  找到日志表: {$table_name}\n";
    }
}

// 2. 检查每个日志表中的相关记录
foreach ($log_tables as $table) {
    echo "\n2. 检查表 {$table}:\n";
    
    // 显示表结构
    $columns = $wpdb->get_results("DESCRIBE {$table}");
    echo "  表结构: ";
    foreach ($columns as $col) {
        echo $col->Field . " ";
    }
    echo "\n";
    
    // 查找相关记录
    $has_product_id = false;
    $has_sku = false;
    $has_message = false;
    
    foreach ($columns as $col) {
        if ($col->Field === 'product_id') $has_product_id = true;
        if (strpos($col->Field, 'sku') !== false) $has_sku = true;
        if (strpos($col->Field, 'message') !== false || strpos($col->Field, 'response') !== false) $has_message = true;
    }
    
    // 构建查询
    $where_conditions = [];
    $params = [];
    
    if ($has_product_id) {
        $where_conditions[] = "product_id = %d";
        $params[] = $product_id;
    }
    
    if ($has_sku) {
        $sku_column = '';
        foreach ($columns as $col) {
            if (strpos($col->Field, 'sku') !== false) {
                $sku_column = $col->Field;
                break;
            }
        }
        if ($sku_column) {
            $where_conditions[] = "{$sku_column} = %s";
            $params[] = $target_sku;
        }
    }
    
    if (!empty($where_conditions)) {
        $where_clause = implode(' OR ', $where_conditions);
        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY id DESC LIMIT 10";
        
        $records = $wpdb->get_results($wpdb->prepare($query, ...$params));
        
        if (!empty($records)) {
            echo "  找到 " . count($records) . " 条相关记录:\n";
            
            foreach ($records as $record) {
                echo "    记录ID: " . (isset($record->id) ? $record->id : 'N/A') . "\n";
                
                // 显示关键字段
                foreach ($record as $key => $value) {
                    if (strpos($key, 'time') !== false || strpos($key, 'date') !== false) {
                        echo "      {$key}: {$value}\n";
                    } elseif (strpos($key, 'message') !== false || strpos($key, 'response') !== false || strpos($key, 'error') !== false) {
                        $display_value = strlen($value) > 200 ? substr($value, 0, 200) . '...' : $value;
                        echo "      {$key}: {$display_value}\n";
                    } elseif (strpos($key, 'status') !== false || strpos($key, 'action') !== false) {
                        echo "      {$key}: {$value}\n";
                    }
                }
                
                // 特别检查是否包含图片相关的错误
                foreach ($record as $key => $value) {
                    if (is_string($value) && (strpos($value, 'productSecondaryImageURL') !== false || 
                        strpos($value, 'requires') !== false || strpos($value, 'entries') !== false)) {
                        echo "      🎯 图片错误: {$value}\n";
                    }
                }
                
                echo "    ---\n";
            }
        } else {
            echo "  没有找到相关记录\n";
        }
    } else {
        echo "  无法构建查询条件\n";
    }
}

// 3. 检查最近的API调用记录
echo "\n3. 检查最近的API调用记录:\n";

// 查找包含API响应的记录
foreach ($log_tables as $table) {
    $api_records = $wpdb->get_results("
        SELECT * FROM {$table} 
        WHERE (message LIKE '%API%' OR message LIKE '%productSecondaryImageURL%' OR api_response LIKE '%productSecondaryImageURL%')
        ORDER BY id DESC 
        LIMIT 5
    ");
    
    if (!empty($api_records)) {
        echo "  表 {$table} 中的API相关记录:\n";
        foreach ($api_records as $record) {
            foreach ($record as $key => $value) {
                if (strpos($key, 'time') !== false || strpos($key, 'date') !== false) {
                    echo "    时间: {$value}\n";
                } elseif (strpos($key, 'message') !== false && strpos($value, 'productSecondaryImageURL') !== false) {
                    echo "    🎯 错误消息: {$value}\n";
                } elseif (strpos($key, 'response') !== false && strpos($value, 'productSecondaryImageURL') !== false) {
                    echo "    🎯 API响应: " . substr($value, 0, 300) . "...\n";
                }
            }
            echo "    ---\n";
        }
    }
}

// 4. 检查批次处理记录
echo "\n4. 检查批次处理记录:\n";

$batch_tables = [];
foreach ($all_tables as $table) {
    $table_name = array_values((array)$table)[0];
    if (strpos($table_name, 'batch') !== false) {
        $batch_tables[] = $table_name;
    }
}

foreach ($batch_tables as $table) {
    echo "  检查批次表: {$table}\n";
    
    // 查找包含该SKU的批次
    $batch_records = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$table} 
        WHERE api_response LIKE %s 
        ORDER BY created_at DESC 
        LIMIT 3
    ", '%' . $target_sku . '%'));
    
    if (!empty($batch_records)) {
        foreach ($batch_records as $record) {
            echo "    批次ID: " . (isset($record->batch_id) ? $record->batch_id : 'N/A') . "\n";
            echo "    状态: " . (isset($record->status) ? $record->status : 'N/A') . "\n";
            
            if (isset($record->api_response)) {
                $api_response = json_decode($record->api_response, true);
                if ($api_response && isset($api_response['itemDetails']['itemIngestionStatus'])) {
                    foreach ($api_response['itemDetails']['itemIngestionStatus'] as $item) {
                        if (isset($item['sku']) && $item['sku'] === $target_sku) {
                            echo "    🎯 找到SKU在批次中的状态: {$item['ingestionStatus']}\n";
                            
                            if (isset($item['ingestionErrors'])) {
                                echo "    错误详情:\n";
                                foreach ($item['ingestionErrors']['ingestionError'] as $error) {
                                    echo "      - {$error['description']}\n";
                                }
                            }
                        }
                    }
                }
            }
            echo "    ---\n";
        }
    }
}

echo "\n=== 基于真实数据的结论 ===\n";
echo "现在我们有了实际的同步日志和API响应数据\n";
echo "可以准确判断占位符填充在实际同步中是否生效\n";

?>
