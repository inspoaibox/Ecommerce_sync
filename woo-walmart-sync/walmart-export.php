<?php
/**
 * Walmart产品数据导出处理文件
 * 独立处理导出请求，避免HTML输出
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    // 如果不是通过WordPress加载，尝试加载WordPress
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            require_once __DIR__ . '/' . $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress not found');
    }
}

// 检查权限
if (!current_user_can('manage_options')) {
    wp_die(__('您没有权限执行此操作。'));
}

// 验证nonce
$action = isset($_GET['export_action']) ? $_GET['export_action'] : '';
$nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

if ($action === 'quick_export') {
    if (!wp_verify_nonce($nonce, 'walmart_export_products')) {
        wp_die(__('安全验证失败。'));
    }
    walmart_quick_export();
} elseif ($action === 'advanced_export') {
    if (!wp_verify_nonce($nonce, 'walmart_export_products_advanced')) {
        wp_die(__('安全验证失败。'));
    }
    walmart_advanced_export($_GET);
} else {
    wp_die(__('无效的导出操作。'));
}

/**
 * 快速导出所有产品数据
 */
function walmart_quick_export() {
    global $wpdb;
    $cache_table = $wpdb->prefix . 'walmart_products_cache';

    // 检查表是否存在
    if ($wpdb->get_var("SHOW TABLES LIKE '$cache_table'") != $cache_table) {
        wp_die(__('Walmart产品缓存表不存在。'));
    }

    // 获取所有产品数据
    $products = $wpdb->get_results("SELECT * FROM {$cache_table} ORDER BY updated_at DESC");

    if (empty($products)) {
        wp_die(__('没有找到可导出的产品数据。'));
    }

    // 记录导出操作
    woo_walmart_sync_log('产品数据快速导出', '信息', [
        'total_products' => count($products),
        'user_id' => get_current_user_id()
    ], "开始快速导出 " . count($products) . " 个Walmart产品数据");

    // 输出CSV
    output_csv($products, 'walmart-products-quick-' . date('Y-m-d-H-i-s') . '.csv');
}

/**
 * 高级导出产品数据
 */
function walmart_advanced_export($options) {
    global $wpdb;
    $cache_table = $wpdb->prefix . 'walmart_products_cache';

    // 检查表是否存在
    if ($wpdb->get_var("SHOW TABLES LIKE '$cache_table'") != $cache_table) {
        wp_die(__('Walmart产品缓存表不存在。'));
    }

    // 构建查询条件
    $where_conditions = ['1=1'];
    $where_values = [];

    // 发布状态筛选
    if (!empty($options['filter_publish_status'])) {
        $where_conditions[] = "publish_status = %s";
        $where_values[] = $options['filter_publish_status'];
    }

    // 库存状态筛选
    if (!empty($options['filter_inventory_status'])) {
        switch ($options['filter_inventory_status']) {
            case 'in_stock':
                $where_conditions[] = "inventory_count > 0";
                break;
            case 'out_of_stock':
                $where_conditions[] = "inventory_count = 0";
                break;
            case 'low_stock':
                $where_conditions[] = "inventory_count BETWEEN 1 AND 10";
                break;
        }
    }

    // 时间范围筛选
    if (!empty($options['filter_date_range'])) {
        switch ($options['filter_date_range']) {
            case 'today':
                $where_conditions[] = "DATE(updated_at) = CURDATE()";
                break;
            case 'week':
                $where_conditions[] = "updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where_conditions[] = "updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'custom':
                if (!empty($options['start_date'])) {
                    $where_conditions[] = "DATE(updated_at) >= %s";
                    $where_values[] = $options['start_date'];
                }
                if (!empty($options['end_date'])) {
                    $where_conditions[] = "DATE(updated_at) <= %s";
                    $where_values[] = $options['end_date'];
                }
                break;
        }
    }

    $where_clause = implode(' AND ', $where_conditions);

    // 执行查询
    $query = "SELECT * FROM {$cache_table} WHERE {$where_clause} ORDER BY updated_at DESC";
    if (!empty($where_values)) {
        $products = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $products = $wpdb->get_results($query);
    }

    if (empty($products)) {
        wp_die(__('没有找到符合筛选条件的产品数据。'));
    }

    // 获取导出字段
    $export_fields = isset($options['export_fields']) ? $options['export_fields'] : [];
    if (empty($export_fields)) {
        // 如果没有选择字段，使用默认字段
        $export_fields = ['sku', 'product_name', 'upc', 'price', 'inventory_count', 'publish_status', 'updated_at'];
    }

    // 记录导出操作
    woo_walmart_sync_log('产品数据高级导出', '信息', [
        'total_products' => count($products),
        'export_fields' => $export_fields,
        'filters' => $options,
        'user_id' => get_current_user_id()
    ], "开始高级导出 " . count($products) . " 个Walmart产品数据");

    // 输出CSV
    output_csv($products, 'walmart-products-advanced-' . date('Y-m-d-H-i-s') . '.csv', $export_fields);
}

/**
 * 输出CSV文件
 */
function output_csv($products, $filename, $export_fields = null) {
    // 清除所有输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }

    // 设置CSV文件头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');

    // 创建文件输出流
    $output = fopen('php://output', 'w');

    // 添加BOM以支持中文
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // 字段映射
    $field_mapping = [
        'id' => 'ID',
        'sku' => 'Walmart SKU',
        'product_id' => 'Product ID',
        'product_name' => 'Product Name',
        'upc' => 'UPC',
        'price' => 'Price',
        'currency' => 'Currency',
        'inventory_count' => 'Inventory Count',
        'publish_status' => 'Publish Status',
        'lifecycle_status' => 'Lifecycle Status',
        'unpublished_reasons' => 'Unpublished Reasons',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At'
    ];

    // 如果指定了导出字段，使用指定字段，否则导出所有字段
    if ($export_fields) {
        $headers = [];
        foreach ($export_fields as $field) {
            $headers[] = isset($field_mapping[$field]) ? $field_mapping[$field] : $field;
        }
    } else {
        $headers = array_values($field_mapping);
        $export_fields = array_keys($field_mapping);
    }

    // 写入CSV头部
    fputcsv($output, $headers);

    // 写入产品数据
    foreach ($products as $product) {
        $row = [];
        foreach ($export_fields as $field) {
            $row[] = isset($product->$field) ? $product->$field : '';
        }
        fputcsv($output, $row);
    }

    fclose($output);

    // 记录导出完成
    woo_walmart_sync_log('产品数据导出完成', '成功', [
        'total_products' => count($products),
        'filename' => $filename,
        'user_id' => get_current_user_id()
    ], "成功导出 " . count($products) . " 个产品数据到文件: {$filename}");

    exit;
}
