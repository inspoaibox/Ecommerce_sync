<?php
/**
 * Walmart库存管理类
 * 
 * 处理WooCommerce商品库存与Walmart Marketplace的同步
 * 支持单个商品、变体商品和批量库存同步
 * 
 * @package WooWalmartSync
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooWalmartSync_Inventory_Manager {
    
    /**
     * Walmart API客户端实例
     */
    private $api_client;
    
    /**
     * 库存同步状态常量
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRYING = 'retrying';
    
    /**
     * 最大重试次数
     */
    const MAX_RETRY_ATTEMPTS = 3;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->api_client = new Woo_Walmart_API_Key_Auth();
        
        // 注册钩子
        add_action('woo_walmart_sync_product_created', array($this, 'sync_inventory_after_product_creation'), 10, 2);
        add_action('woo_walmart_sync_batch_products_created', array($this, 'sync_batch_inventory'), 10, 1);
        
        // 定时任务：重试失败的库存同步
        add_action('woo_walmart_sync_retry_failed_inventory', array($this, 'retry_failed_inventory_sync'));
        
        // 如果定时任务不存在，则创建
        if (!wp_next_scheduled('woo_walmart_sync_retry_failed_inventory')) {
            wp_schedule_event(time(), 'hourly', 'woo_walmart_sync_retry_failed_inventory');
        }
    }
    
    /**
     * 商品创建成功后立即同步库存
     * 
     * @param int $product_id WooCommerce商品ID
     * @param string $walmart_sku Walmart SKU
     */
    public function sync_inventory_after_product_creation($product_id, $walmart_sku) {
        woo_walmart_sync_log('库存同步', '信息', [
            'product_id' => $product_id,
            'walmart_sku' => $walmart_sku
        ], '开始商品创建后的库存同步');
        
        $product = wc_get_product($product_id);
        if (!$product) {
            woo_walmart_sync_log('库存同步', '错误', ['product_id' => $product_id], '商品不存在');
            return false;
        }
        
        // 处理变体商品
        if ($product->is_type('variable')) {
            return $this->sync_variable_product_inventory($product, $walmart_sku);
        } else {
            return $this->sync_single_product_inventory($product, $walmart_sku);
        }
    }
    
    /**
     * 同步单个商品库存
     * 
     * @param WC_Product $product WooCommerce商品对象
     * @param string $walmart_sku Walmart SKU
     * @return bool 同步是否成功
     */
    public function sync_single_product_inventory($product, $walmart_sku) {
        $product_id = $product->get_id();
        $stock_quantity = $product->get_stock_quantity();
        
        // 如果库存为null或负数，设为0
        if (is_null($stock_quantity) || $stock_quantity < 0) {
            $stock_quantity = 0;
        }
        
        woo_walmart_sync_log('库存同步', '信息', [
            'product_id' => $product_id,
            'walmart_sku' => $walmart_sku,
            'stock_quantity' => $stock_quantity
        ], '准备同步单个商品库存');
        
        // 记录库存同步状态
        $this->update_inventory_sync_status($product_id, $walmart_sku, self::STATUS_PENDING, $stock_quantity);
        
        // 调用Walmart Inventory API
        $result = $this->call_walmart_inventory_api($walmart_sku, $stock_quantity);
        
        if ($result['success']) {
            $this->update_inventory_sync_status($product_id, $walmart_sku, self::STATUS_SUCCESS, $stock_quantity, $result['data']);
            woo_walmart_sync_log('库存同步', '成功', [
                'product_id' => $product_id,
                'walmart_sku' => $walmart_sku,
                'stock_quantity' => $stock_quantity
            ], '单个商品库存同步成功');
            return true;
        } else {
            $this->update_inventory_sync_status($product_id, $walmart_sku, self::STATUS_FAILED, $stock_quantity, $result['error']);
            woo_walmart_sync_log('库存同步', '错误', [
                'product_id' => $product_id,
                'walmart_sku' => $walmart_sku,
                'error' => $result['error']
            ], '单个商品库存同步失败');
            return false;
        }
    }
    
    /**
     * 同步变体商品库存
     * 
     * @param WC_Product_Variable $product 变体商品对象
     * @param string $parent_walmart_sku 父商品Walmart SKU
     * @return bool 同步是否成功
     */
    public function sync_variable_product_inventory($product, $parent_walmart_sku) {
        $variations = $product->get_children();
        $success_count = 0;
        $total_count = count($variations);
        
        woo_walmart_sync_log('库存同步', '信息', [
            'parent_product_id' => $product->get_id(),
            'variations_count' => $total_count
        ], '开始同步变体商品库存');
        
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            
            // 生成变体的Walmart SKU
            $variation_walmart_sku = $this->generate_variation_walmart_sku($parent_walmart_sku, $variation);
            
            if ($this->sync_single_product_inventory($variation, $variation_walmart_sku)) {
                $success_count++;
            }
        }
        
        $success_rate = $total_count > 0 ? ($success_count / $total_count) * 100 : 0;
        
        woo_walmart_sync_log('库存同步', '信息', [
            'parent_product_id' => $product->get_id(),
            'success_count' => $success_count,
            'total_count' => $total_count,
            'success_rate' => $success_rate
        ], '变体商品库存同步完成');
        
        return $success_count > 0;
    }
    
    /**
     * 批量库存同步
     *
     * @param array $product_data 商品数据数组
     */
    public function sync_batch_inventory($product_data) {
        woo_walmart_sync_log('库存同步', '信息', [
            'products_count' => count($product_data),
            'product_data' => $product_data
        ], '库存管理器收到批量库存同步请求');
        
        $inventory_data = array();
        
        foreach ($product_data as $data) {
            $product_id = $data['product_id'];
            $walmart_sku = $data['walmart_sku'];
            
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            
            if ($product->is_type('variable')) {
                // 处理变体商品
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $variation_walmart_sku = $this->generate_variation_walmart_sku($walmart_sku, $variation);
                        $stock_quantity = $variation->get_stock_quantity();
                        if (is_null($stock_quantity) || $stock_quantity < 0) {
                            $stock_quantity = 0;
                        }
                        
                        $inventory_data[] = array(
                            'sku' => $variation_walmart_sku,
                            'quantity' => $stock_quantity,
                            'product_id' => $variation_id
                        );
                    }
                }
            } else {
                // 处理单个商品
                $stock_quantity = $product->get_stock_quantity();
                if (is_null($stock_quantity) || $stock_quantity < 0) {
                    $stock_quantity = 0;
                }
                
                $inventory_data[] = array(
                    'sku' => $walmart_sku,
                    'quantity' => $stock_quantity,
                    'product_id' => $product_id
                );
            }
        }
        
        // 批量调用Walmart Inventory API
        if (!empty($inventory_data)) {
            $this->call_walmart_bulk_inventory_api($inventory_data);
        }
    }
    
    /**
     * 调用Walmart Inventory API
     *
     * @param string $sku Walmart SKU
     * @param int $quantity 库存数量
     * @return array API调用结果
     */
    private function call_walmart_inventory_api($sku, $quantity) {
        try {
            $inventory_data = array(
                'sku' => $sku,
                'quantity' => array(
                    'unit' => 'EACH',
                    'amount' => (int) $quantity
                )
            );

            $response = $this->api_client->update_inventory($inventory_data);

            // 检查是否是WP_Error
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => $response->get_error_message()
                );
            }

            // 检查是否包含错误信息
            if (isset($response['error']) && is_array($response['error'])) {
                $error_details = $response['error'][0] ?? $response['error'];
                $error_code = $error_details['code'] ?? 'UNKNOWN_ERROR';
                $error_description = $error_details['description'] ?? 'Unknown error occurred';

                // 特殊处理SKU不存在的情况
                if ($error_code === 'CONTENT_NOT_FOUND.GMP_INVENTORY_API') {
                    return array(
                        'success' => false,
                        'error' => "SKU '{$sku}' 在沃尔玛系统中不存在或尚未生效。请确认商品已成功发布到沃尔玛。"
                    );
                }

                return array(
                    'success' => false,
                    'error' => "API错误 [{$error_code}]: {$error_description}"
                );
            }

            // 检查正常响应
            if ($response && isset($response['sku'])) {
                return array(
                    'success' => true,
                    'data' => $response
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'API响应格式错误：未返回预期的SKU字段'
                );
            }

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 批量调用Walmart Inventory API
     * 
     * @param array $inventory_data 库存数据数组
     */
    private function call_walmart_bulk_inventory_api($inventory_data) {
        // 分批处理，每批最多50个商品
        $batch_size = 50;
        $batches = array_chunk($inventory_data, $batch_size);
        
        foreach ($batches as $batch) {
            try {
                $response = $this->api_client->bulk_update_inventory($batch);
                
                // 处理批量响应结果
                $this->process_bulk_inventory_response($batch, $response);
                
            } catch (Exception $e) {
                // 批量失败时，标记所有商品为失败状态
                foreach ($batch as $item) {
                    $this->update_inventory_sync_status(
                        $item['product_id'], 
                        $item['sku'], 
                        self::STATUS_FAILED, 
                        $item['quantity'], 
                        $e->getMessage()
                    );
                }
                
                woo_walmart_sync_log('库存同步', '错误', [
                    'batch_size' => count($batch),
                    'error' => $e->getMessage()
                ], '批量库存同步失败');
            }
        }
    }
    
    /**
     * 处理批量库存同步响应
     * 
     * @param array $batch 批量数据
     * @param array $response API响应
     */
    private function process_bulk_inventory_response($batch, $response) {
        // 根据响应结果更新每个商品的同步状态
        foreach ($batch as $item) {
            $sku = $item['sku'];
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            
            // 检查响应中是否包含该SKU的结果
            $item_result = $this->find_item_result_in_response($sku, $response);
            
            if ($item_result && $item_result['success']) {
                $this->update_inventory_sync_status($product_id, $sku, self::STATUS_SUCCESS, $quantity, $item_result['data']);
            } else {
                $error_message = $item_result ? $item_result['error'] : '未知错误';
                $this->update_inventory_sync_status($product_id, $sku, self::STATUS_FAILED, $quantity, $error_message);
            }
        }
    }
    
    /**
     * 在API响应中查找特定SKU的结果
     *
     * @param string $sku SKU
     * @param array $response API响应
     * @return array|null 结果数据
     */
    private function find_item_result_in_response($sku, $response) {
        // 这里需要根据Walmart API的实际响应格式来解析
        // 暂时返回成功状态，实际实现时需要根据API文档调整
        return array(
            'success' => true,
            'data' => $response
        );
    }

    /**
     * 更新库存同步状态
     *
     * @param int $product_id 商品ID
     * @param string $walmart_sku Walmart SKU
     * @param string $status 同步状态
     * @param int $quantity 库存数量
     * @param mixed $response_data 响应数据或错误信息
     */
    private function update_inventory_sync_status($product_id, $walmart_sku, $status, $quantity, $response_data = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'walmart_inventory_sync';

        // 确保表存在
        $this->create_inventory_sync_table();

        $data = array(
            'product_id' => $product_id,
            'walmart_sku' => $walmart_sku,
            'status' => $status,
            'quantity' => $quantity,
            'last_sync_time' => current_time('mysql'),
            'response_data' => is_array($response_data) ? wp_json_encode($response_data) : $response_data
        );

        // 检查是否已存在记录
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, retry_count FROM {$table_name} WHERE product_id = %d AND walmart_sku = %s",
            $product_id, $walmart_sku
        ));

        if ($existing) {
            // 更新现有记录
            $data['retry_count'] = $status === self::STATUS_FAILED ? $existing->retry_count + 1 : 0;
            $updated = $wpdb->update($table_name, $data, array('id' => $existing->id));

            woo_walmart_sync_log('库存同步状态更新', $updated !== false ? '成功' : '失败', [
                'product_id' => $product_id,
                'walmart_sku' => $walmart_sku,
                'status' => $status,
                'existing_id' => $existing->id,
                'updated_rows' => $updated,
                'wpdb_error' => $wpdb->last_error
            ], $updated !== false ? "库存同步状态已更新为 {$status}" : "库存同步状态更新失败");
        } else {
            // 插入新记录
            $data['retry_count'] = 0;
            $data['created_time'] = current_time('mysql');
            $inserted = $wpdb->insert($table_name, $data);

            woo_walmart_sync_log('库存同步状态插入', $inserted !== false ? '成功' : '失败', [
                'product_id' => $product_id,
                'walmart_sku' => $walmart_sku,
                'status' => $status,
                'insert_id' => $wpdb->insert_id,
                'wpdb_error' => $wpdb->last_error
            ], $inserted !== false ? "库存同步状态已插入，状态: {$status}" : "库存同步状态插入失败");
        }
    }

    /**
     * 创建库存同步状态表
     */
    private function create_inventory_sync_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'walmart_inventory_sync';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            walmart_sku varchar(191) NOT NULL,
            status varchar(20) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 0,
            retry_count int(11) NOT NULL DEFAULT 0,
            last_sync_time datetime NOT NULL,
            created_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            response_data longtext,
            PRIMARY KEY (id),
            UNIQUE KEY product_sku (product_id, walmart_sku),
            KEY status (status),
            KEY last_sync_time (last_sync_time)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 重试失败的库存同步
     */
    public function retry_failed_inventory_sync() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'walmart_inventory_sync';

        // 获取需要重试的记录（失败且重试次数未超过限制）
        $failed_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE status = %s
             AND retry_count < %d
             AND last_sync_time < %s
             ORDER BY last_sync_time ASC
             LIMIT 20",
            self::STATUS_FAILED,
            self::MAX_RETRY_ATTEMPTS,
            date('Y-m-d H:i:s', strtotime('-1 hour')) // 至少间隔1小时重试
        ));

        if (empty($failed_items)) {
            return;
        }

        woo_walmart_sync_log('库存同步', '信息', [
            'retry_count' => count($failed_items)
        ], '开始重试失败的库存同步');

        foreach ($failed_items as $item) {
            // 标记为重试状态
            $this->update_inventory_sync_status(
                $item->product_id,
                $item->walmart_sku,
                self::STATUS_RETRYING,
                $item->quantity
            );

            // 重新同步
            $product = wc_get_product($item->product_id);
            if ($product) {
                $this->sync_single_product_inventory($product, $item->walmart_sku);
            }
        }
    }

    /**
     * 生成变体商品的Walmart SKU
     *
     * @param string $parent_sku 父商品SKU
     * @param WC_Product_Variation $variation 变体商品
     * @return string 变体SKU
     */
    private function generate_variation_walmart_sku($parent_sku, $variation) {
        $variation_id = $variation->get_id();
        $variation_sku = $variation->get_sku();

        // 如果变体有自己的SKU，使用变体SKU
        if (!empty($variation_sku)) {
            return $variation_sku;
        }

        // 否则使用父SKU + 变体ID
        return $parent_sku . '-' . $variation_id;
    }

    /**
     * 获取库存同步状态
     *
     * @param int $product_id 商品ID
     * @param string $walmart_sku Walmart SKU（可选）
     * @return array 同步状态数据
     */
    public function get_inventory_sync_status($product_id, $walmart_sku = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'walmart_inventory_sync';

        if ($walmart_sku) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE product_id = %d AND walmart_sku = %s",
                $product_id, $walmart_sku
            ), ARRAY_A);
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE product_id = %d",
                $product_id
            ), ARRAY_A);
        }
    }

    /**
     * 获取失败的库存同步记录
     *
     * @param int $limit 限制数量
     * @return array 失败记录
     */
    public function get_failed_inventory_sync($limit = 100) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'walmart_inventory_sync';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.post_title as product_name
             FROM {$table_name} s
             LEFT JOIN {$wpdb->posts} p ON s.product_id = p.ID
             WHERE s.status = %s
             ORDER BY s.last_sync_time DESC
             LIMIT %d",
            self::STATUS_FAILED,
            $limit
        ), ARRAY_A);
    }

    /**
     * 公共的单个商品库存同步方法
     *
     * @param int $product_id 商品ID
     * @param string $walmart_sku Walmart SKU
     * @return bool 是否成功
     */
    public function sync_single_inventory($product_id, $walmart_sku) {
        $product = wc_get_product($product_id);
        if (!$product) {
            woo_walmart_sync_log('库存同步', '错误', ['product_id' => $product_id], '商品不存在');
            return false;
        }

        woo_walmart_sync_log('库存同步', '信息', [
            'product_id' => $product_id,
            'walmart_sku' => $walmart_sku
        ], '开始单个商品库存同步');

        // 处理变体商品
        if ($product->is_type('variable')) {
            return $this->sync_variable_product_inventory($product, $walmart_sku);
        } else {
            return $this->sync_single_product_inventory($product, $walmart_sku);
        }
    }

    /**
     * 手动重试单个商品的库存同步
     *
     * @param int $product_id 商品ID
     * @param string $walmart_sku Walmart SKU
     * @return bool 是否成功
     */
    public function manual_retry_inventory_sync($product_id, $walmart_sku) {
        woo_walmart_sync_log('库存同步', '信息', [
            'product_id' => $product_id,
            'walmart_sku' => $walmart_sku
        ], '手动重试库存同步');

        return $this->sync_single_inventory($product_id, $walmart_sku);
    }

    /**
     * 导出失败的库存同步记录为CSV
     *
     * @return string CSV文件路径
     */
    public function export_failed_inventory_sync() {
        $failed_items = $this->get_failed_inventory_sync(1000);

        if (empty($failed_items)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $file_name = 'walmart-inventory-sync-failed-' . date('Y-m-d-H-i-s') . '.csv';
        $file_path = $upload_dir['path'] . '/' . $file_name;

        $file = fopen($file_path, 'w');

        // 写入CSV头部
        fputcsv($file, array(
            'Product ID',
            'Product Name',
            'Walmart SKU',
            'Status',
            'Quantity',
            'Retry Count',
            'Last Sync Time',
            'Error Message'
        ));

        // 写入数据
        foreach ($failed_items as $item) {
            fputcsv($file, array(
                $item['product_id'],
                $item['product_name'],
                $item['walmart_sku'],
                $item['status'],
                $item['quantity'],
                $item['retry_count'],
                $item['last_sync_time'],
                $item['response_data']
            ));
        }

        fclose($file);

        return $upload_dir['url'] . '/' . $file_name;
    }
}
