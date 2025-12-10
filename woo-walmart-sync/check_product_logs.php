<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

echo "=== 查看产品映射日志中的实际数据 ===\n\n";

// 查找最近的"产品映射-最终数据结构"日志
$mapping_logs = $wpdb->get_results("
    SELECT * FROM $logs_table 
    WHERE action = '产品映射-最终数据结构'
    ORDER BY created_at DESC 
    LIMIT 3
");

echo "找到产品映射日志数量: " . count($mapping_logs) . "\n\n";

foreach ($mapping_logs as $log) {
    echo "=== 日志时间: {$log->created_at} ===\n";
    echo "状态: {$log->status}\n";
    
    // 解析请求数据（包含最终的数据结构）
    $request_data = json_decode($log->request, true);
    
    if ($request_data && isset($request_data['MPItem'][0])) {
        $mp_item = $request_data['MPItem'][0];
        
        // 检查Orderable部分
        if (isset($mp_item['Orderable'])) {
            echo "\n【Orderable部分】:\n";
            $orderable = $mp_item['Orderable'];
            
            $orderable_fields = [
                'fulfillmentLagTime',
                'electronicsIndicator', 
                'chemicalAerosolPesticide',
                'batteryTechnologyType',
                'shipsInOriginalPackaging',
                'MustShipAlone',
                'IsPreorder'
            ];
            
            foreach ($orderable_fields as $field) {
                if (isset($orderable[$field])) {
                    $value = $orderable[$field];
                    $type = gettype($value);
                    echo "✓ {$field}: {$value} ({$type})\n";
                } else {
                    echo "❌ {$field}: 缺失\n";
                }
            }
        }
        
        // 检查Visible部分
        if (isset($mp_item['Visible'])) {
            $visible = $mp_item['Visible'];
            $category_name = array_keys($visible)[0] ?? 'unknown';
            
            echo "\n【Visible部分 - {$category_name}】:\n";
            
            if (isset($visible[$category_name])) {
                $visible_fields = $visible[$category_name];
                
                // 检查关键字段
                $key_visible_fields = [
                    'productName',
                    'brand',
                    'shortDescription', 
                    'keyFeatures',
                    'mainImageUrl',
                    'shippingWeight',
                    'lagTime',
                    'siteStartDate',
                    'siteEndDate',
                    'saleRestrictions'
                ];
                
                foreach ($key_visible_fields as $field) {
                    if (isset($visible_fields[$field])) {
                        $value = $visible_fields[$field];
                        if (is_array($value)) {
                            echo "✓ {$field}: [数组，长度:" . count($value) . "]\n";
                        } else {
                            $display_value = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                            echo "✓ {$field}: {$display_value}\n";
                        }
                    } else {
                        echo "❌ {$field}: 缺失\n";
                    }
                }
                
                echo "\nVisible部分总字段数: " . count($visible_fields) . "\n";
                
                // 显示所有字段名
                echo "所有字段: " . implode(', ', array_keys($visible_fields)) . "\n";
            }
        }
        
        // 检查TradeItem部分
        if (isset($mp_item['TradeItem'])) {
            echo "\n【TradeItem部分】:\n";
            $trade_item = $mp_item['TradeItem'];
            
            $trade_fields = [
                'gtin',
                'productName',
                'productDescription',
                'brand',
                'manufacturerName',
                'manufacturerPartNumber'
            ];
            
            foreach ($trade_fields as $field) {
                if (isset($trade_item[$field])) {
                    $value = $trade_item[$field];
                    $display_value = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                    echo "✓ {$field}: {$display_value}\n";
                } else {
                    echo "❌ {$field}: 缺失\n";
                }
            }
        }
        
    } else {
        echo "❌ 无法解析日志数据\n";
        echo "原始请求数据长度: " . strlen($log->request) . "\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

// 如果没有找到产品映射日志，查看其他相关日志
if (count($mapping_logs) == 0) {
    echo "没有找到产品映射日志，查看其他相关日志:\n\n";
    
    $other_logs = $wpdb->get_results("
        SELECT action, created_at, status FROM $logs_table 
        WHERE action LIKE '%产品%' OR action LIKE '%映射%'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    foreach ($other_logs as $log) {
        echo "- {$log->created_at}: {$log->action} ({$log->status})\n";
    }
}
?>
