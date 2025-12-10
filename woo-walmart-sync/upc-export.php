<?php
/**
 * UPC池数据导出处理文件
 * 独立处理UPC导出请求，避免HTML输出
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
$nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
if (!wp_verify_nonce($nonce, 'walmart_export_upc')) {
    wp_die(__('安全验证失败。'));
}

// 获取导出类型
$export_type = isset($_GET['export_type']) ? sanitize_text_field($_GET['export_type']) : 'all';
$search_term = isset($_GET['search_term']) ? (sanitize_text_field($_GET['search_term']) ?: '') : '';
$search_type = isset($_GET['search_type']) ? (sanitize_text_field($_GET['search_type']) ?: 'upc') : 'upc';

// 执行导出
walmart_export_upc_data($export_type, $search_term, $search_type);

/**
 * 导出UPC数据
 */
function walmart_export_upc_data($export_type, $search_term = '', $search_type = 'upc') {
    // 确保参数不为null
    $export_type = $export_type ?: 'all';
    $search_term = $search_term ?: '';
    $search_type = $search_type ?: 'upc';
    global $wpdb;
    $table_name = $wpdb->prefix . 'walmart_upc_pool';

    // 检查表是否存在
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        wp_die(__('UPC池表不存在。'));
    }

    // 构建查询条件
    $where_conditions = ['1=1'];
    $where_values = [];

    switch ($export_type) {
        case 'used':
            $where_conditions[] = 'is_used = 1';
            $filename_suffix = 'used';
            break;
        case 'available':
            $where_conditions[] = 'is_used = 0';
            $filename_suffix = 'available';
            break;
        case 'filtered':
            if (!empty($search_term)) {
                if ($search_type === 'sku') {
                    // SKU搜索：需要关联产品表
                    $where_conditions[] = $wpdb->prepare(
                        "product_id IN (SELECT ID FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                         WHERE pm.meta_key = '_sku' AND pm.meta_value LIKE %s)",
                        '%' . $wpdb->esc_like($search_term) . '%'
                    );
                } else {
                    // UPC搜索（默认）
                    $where_conditions[] = $wpdb->prepare("upc_code LIKE %s", '%' . $wpdb->esc_like($search_term) . '%');
                }
                $filename_suffix = 'filtered-' . ($search_type ?: 'upc') . '-' . sanitize_file_name($search_term ?: 'empty');
            } else {
                $filename_suffix = 'all';
            }
            break;
        default: // 'all'
            $filename_suffix = 'all';
            break;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // 执行查询
    $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY id DESC";
    $upcs = $wpdb->get_results($query);

    if (empty($upcs)) {
        wp_die(__('没有找到符合条件的UPC数据。'));
    }

    // 记录导出操作
    woo_walmart_sync_log('UPC数据导出', '信息', [
        'export_type' => $export_type,
        'search_term' => $search_term,
        'search_type' => $search_type,
        'total_upcs' => count($upcs),
        'user_id' => get_current_user_id()
    ], "开始导出 " . count($upcs) . " 个UPC数据，类型: " . ($export_type ?: 'unknown'));

    // 生成文件名
    $filename = 'walmart-upc-' . ($filename_suffix ?: 'export') . '-' . date('Y-m-d-H-i-s') . '.csv';

    // 输出CSV
    output_upc_csv($upcs, $filename);
}

/**
 * 输出UPC CSV文件
 */
function output_upc_csv($upcs, $filename) {
    // 确保filename不为null
    $filename = $filename ?: 'walmart-upc-export.csv';
    // 清除所有输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }

    // 设置HTTP头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // 创建文件句柄
    $output = fopen('php://output', 'w');

    // 添加BOM以支持Excel正确显示中文
    fwrite($output, "\xEF\xBB\xBF");

    // CSV头部
    $headers = [
        'ID',
        'UPC码',
        '状态',
        '关联产品ID',
        '关联SKU',
        '产品名称',
        '使用时间',
        '创建时间'
    ];

    // 写入CSV头部
    fputcsv($output, $headers);

    // 写入UPC数据
    foreach ($upcs as $upc) {
        // 获取产品信息
        $product_sku = '';
        $product_name = '';
        if ($upc->product_id) {
            $product = wc_get_product($upc->product_id);
            if ($product) {
                $product_sku = $product->get_sku() ?: '';
                $product_name = $product->get_name() ?: '';
            }
        }

        $row = [
            $upc->id ?: '',
            $upc->upc_code ?: '',
            $upc->is_used ? '已使用' : '可用',
            $upc->product_id ?: '',
            $product_sku,
            $product_name,
            $upc->used_at ?: '',
            date('Y-m-d H:i:s') // 创建时间（当前时间作为导出时间）
        ];

        fputcsv($output, $row);
    }

    fclose($output);

    // 记录导出完成
    woo_walmart_sync_log('UPC数据导出完成', '成功', [
        'total_upcs' => count($upcs),
        'filename' => $filename,
        'user_id' => get_current_user_id()
    ], "成功导出 " . count($upcs) . " 个UPC数据到文件: " . ($filename ?: 'unknown'));

    exit;
}
