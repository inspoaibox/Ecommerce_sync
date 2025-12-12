<?php
/**
 * Walmart本地数据管理类
 * 处理本地WooCommerce商品数据的缓存和同步
 */

if (!defined('ABSPATH')) {
    exit;
}

class Walmart_Local_Data_Manager {
    
    private $cache_table;
    private $cache_duration = 24 * 60 * 60; // 24小时缓存
    
    public function __construct() {
        global $wpdb;
        $this->cache_table = $wpdb->prefix . 'walmart_local_cache';
    }
    
    /**
     * 同步本地商品数据到缓存
     * @param bool $force_refresh 是否强制刷新
     * @return array
     */
    public function sync_local_data($force_refresh = false) {
        global $wpdb;
        
        // 检查是否需要刷新缓存
        if (!$force_refresh && !$this->should_refresh_cache()) {
            return [
                'success' => true,
                'message' => '缓存仍然有效，无需刷新',
                'cached_count' => $this->get_cached_count()
            ];
        }
        
        woo_walmart_sync_log('本地数据同步', '开始', [
            'force_refresh' => $force_refresh
        ], '开始同步本地商品数据到缓存');
        
        try {
            // 获取所有有SKU的WooCommerce商品
            $products = $this->get_woocommerce_products_with_sku();
            
            if (empty($products)) {
                return [
                    'success' => false,
                    'message' => '没有找到有SKU的WooCommerce商品'
                ];
            }
            
            // 批量更新缓存
            $updated_count = $this->batch_update_cache($products);
            
            // 清理过期数据
            $this->cleanup_old_cache();
            
            woo_walmart_sync_log('本地数据同步', '成功', [
                'total_products' => count($products),
                'updated_count' => $updated_count
            ], "成功同步 {$updated_count} 个本地商品数据");
            
            return [
                'success' => true,
                'message' => "成功同步 {$updated_count} 个本地商品数据",
                'updated_count' => $updated_count
            ];
            
        } catch (Exception $e) {
            woo_walmart_sync_log('本地数据同步', '错误', [
                'error' => $e->getMessage()
            ], '本地数据同步失败');
            
            return [
                'success' => false,
                'message' => '同步失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取所有有SKU的WooCommerce商品
     * @return array
     */
    private function get_woocommerce_products_with_sku() {
        global $wpdb;
        
        $query = "
            SELECT 
                p.ID as product_id,
                p.post_title as product_name,
                p.post_status as product_status,
                pm_sku.meta_value as sku,
                pm_price.meta_value as price,
                pm_stock.meta_value as stock_quantity,
                pm_manage_stock.meta_value as manage_stock,
                pm_stock_status.meta_value as stock_status
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$wpdb->postmeta} pm_manage_stock ON p.ID = pm_manage_stock.post_id AND pm_manage_stock.meta_key = '_manage_stock'
            LEFT JOIN {$wpdb->postmeta} pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'private', 'draft')
            AND pm_sku.meta_value != ''
            AND pm_sku.meta_value IS NOT NULL
        ";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * 批量更新缓存数据
     * @param array $products
     * @return int
     */
    private function batch_update_cache($products) {
        global $wpdb;
        
        $updated_count = 0;
        $current_time = current_time('mysql');
        
        foreach ($products as $product) {
            $data = [
                'sku' => $product->sku,
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'price' => floatval($product->price ?: 0),
                'stock_quantity' => intval($product->stock_quantity ?: 0),
                'manage_stock' => intval($product->manage_stock ?: 0),
                'stock_status' => $product->stock_status ?: 'instock',
                'product_status' => $product->product_status,
                'last_sync_time' => $current_time,
                'updated_at' => $current_time
            ];
            
            // 检查是否已存在
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->cache_table} WHERE sku = %s",
                $product->sku
            ));
            
            if ($existing) {
                // 更新现有记录
                $result = $wpdb->update(
                    $this->cache_table,
                    $data,
                    ['sku' => $product->sku],
                    ['%s', '%d', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s'],
                    ['%s']
                );
            } else {
                // 插入新记录
                $data['created_at'] = $current_time;
                $result = $wpdb->insert(
                    $this->cache_table,
                    $data,
                    ['%s', '%d', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
                );
            }
            
            if ($result !== false) {
                $updated_count++;
            }
        }
        
        return $updated_count;
    }
    
    /**
     * 检查是否需要刷新缓存
     * @return bool
     */
    private function should_refresh_cache() {
        global $wpdb;
        
        $last_sync = $wpdb->get_var(
            "SELECT MAX(last_sync_time) FROM {$this->cache_table}"
        );
        
        if (!$last_sync) {
            return true; // 没有缓存数据，需要刷新
        }
        
        $cache_age = time() - strtotime($last_sync);
        return $cache_age > $this->cache_duration;
    }
    
    /**
     * 获取缓存数据数量
     * @return int
     */
    private function get_cached_count() {
        global $wpdb;
        return intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->cache_table}"));
    }
    
    /**
     * 清理过期缓存数据
     */
    private function cleanup_old_cache() {
        global $wpdb;
        
        // 删除超过7天没有更新的记录
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->cache_table} WHERE updated_at < %s",
            date('Y-m-d H:i:s', time() - (7 * 24 * 60 * 60))
        ));
    }
    
    /**
     * 根据SKU获取本地商品数据
     * @param string $sku
     * @return object|null
     */
    public function get_local_data_by_sku($sku) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->cache_table} WHERE sku = %s",
            $sku
        ));
    }
    
    /**
     * 批量获取本地商品数据
     * @param array $skus
     * @return array
     */
    public function get_local_data_by_skus($skus) {
        global $wpdb;
        
        if (empty($skus)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($skus), '%s'));
        $query = "SELECT * FROM {$this->cache_table} WHERE sku IN ($placeholders)";
        
        return $wpdb->get_results($wpdb->prepare($query, $skus));
    }
    
    /**
     * 获取有差异的商品统计
     * @param array $walmart_products
     * @return array
     */
    public function get_difference_stats($walmart_products) {
        $price_diff_count = 0;
        $inventory_diff_count = 0;
        $total_compared = 0;

        foreach ($walmart_products as $walmart_product) {
            $local_data = $this->get_local_data_by_sku($walmart_product->sku);

            if ($local_data) {
                $total_compared++;

                // 价格差异检查（允许0.01的误差）
                $price_diff = abs(floatval($walmart_product->price) - floatval($local_data->price));
                if ($price_diff > 0.01) {
                    $price_diff_count++;
                }

                // 库存差异检查
                if (intval($walmart_product->inventory_count) !== intval($local_data->stock_quantity)) {
                    $inventory_diff_count++;
                }
            }
        }

        return [
            'price_diff_count' => $price_diff_count,
            'inventory_diff_count' => $inventory_diff_count,
            'total_compared' => $total_compared,
            'total_walmart' => count($walmart_products)
        ];
    }

    /**
     * 获取全局差异统计（所有商品）
     * @return array
     */
    public function get_global_difference_stats() {
        global $wpdb;

        // 检查是否有缓存的统计数据
        $stats_cache = get_option('walmart_global_diff_stats', null);
        $last_update = get_option('walmart_global_diff_stats_time', null);

        // 如果缓存存在且未过期（24小时），直接返回
        if ($stats_cache && $last_update && (time() - strtotime($last_update)) < 24 * 60 * 60) {
            $stats_cache['last_update'] = $last_update;
            return $stats_cache;
        }

        // 重新计算统计数据
        $walmart_cache_table = $wpdb->prefix . 'walmart_products_cache';

        // 获取所有Walmart商品
        $walmart_products = $wpdb->get_results("SELECT * FROM {$walmart_cache_table}");

        if (empty($walmart_products)) {
            $stats = [
                'price_diff_count' => 0,
                'inventory_diff_count' => 0,
                'total_compared' => 0,
                'total_walmart' => 0,
                'last_update' => null
            ];

            update_option('walmart_global_diff_stats', $stats);
            update_option('walmart_global_diff_stats_time', current_time('mysql'));

            return $stats;
        }

        // 获取所有SKU的本地数据
        $skus = array_column($walmart_products, 'sku');
        $local_data_list = $this->get_local_data_by_skus($skus);

        // 创建SKU到本地数据的映射
        $local_data_map = [];
        foreach ($local_data_list as $local_item) {
            $local_data_map[$local_item->sku] = $local_item;
        }

        // 计算差异
        $price_diff_count = 0;
        $inventory_diff_count = 0;
        $total_compared = 0;

        foreach ($walmart_products as $walmart_product) {
            $local_data = isset($local_data_map[$walmart_product->sku]) ? $local_data_map[$walmart_product->sku] : null;

            if ($local_data) {
                $total_compared++;

                // 价格差异检查（允许0.01的误差）
                $price_diff = abs(floatval($walmart_product->price) - floatval($local_data->price));
                if ($price_diff > 0.01) {
                    $price_diff_count++;
                }

                // 库存差异检查
                if (intval($walmart_product->inventory_count) !== intval($local_data->stock_quantity)) {
                    $inventory_diff_count++;
                }
            }
        }

        $stats = [
            'price_diff_count' => $price_diff_count,
            'inventory_diff_count' => $inventory_diff_count,
            'total_compared' => $total_compared,
            'total_walmart' => count($walmart_products),
            'last_update' => current_time('mysql')
        ];

        // 缓存统计结果
        update_option('walmart_global_diff_stats', $stats);
        update_option('walmart_global_diff_stats_time', current_time('mysql'));

        woo_walmart_sync_log('全局差异统计', '信息', $stats, '完成全局差异统计计算');

        return $stats;
    }

    /**
     * 获取有差异的商品详细列表
     * @param string $type 差异类型：'price', 'inventory', 'all'
     * @param int $limit 限制数量
     * @return array
     */
    public function get_difference_products($type = 'all', $limit = 50) {
        global $wpdb;

        $walmart_cache_table = $wpdb->prefix . 'walmart_products_cache';

        // 获取所有Walmart商品
        $walmart_products = $wpdb->get_results("SELECT * FROM {$walmart_cache_table} ORDER BY updated_at DESC");

        if (empty($walmart_products)) {
            return [];
        }

        // 获取所有SKU的本地数据
        $skus = array_column($walmart_products, 'sku');
        $local_data_list = $this->get_local_data_by_skus($skus);

        // 创建SKU到本地数据的映射
        $local_data_map = [];
        foreach ($local_data_list as $local_item) {
            $local_data_map[$local_item->sku] = $local_item;
        }

        $diff_products = [];

        foreach ($walmart_products as $walmart_product) {
            $local_data = isset($local_data_map[$walmart_product->sku]) ? $local_data_map[$walmart_product->sku] : null;

            if (!$local_data) {
                continue; // 跳过没有本地数据的商品
            }

            $has_price_diff = false;
            $has_inventory_diff = false;

            // 检查价格差异
            $price_diff = abs(floatval($walmart_product->price) - floatval($local_data->price));
            if ($price_diff > 0.01) {
                $has_price_diff = true;
            }

            // 检查库存差异
            if (intval($walmart_product->inventory_count) !== intval($local_data->stock_quantity)) {
                $has_inventory_diff = true;
            }

            // 根据类型筛选
            $should_include = false;
            switch ($type) {
                case 'price':
                    $should_include = $has_price_diff;
                    break;
                case 'inventory':
                    $should_include = $has_inventory_diff;
                    break;
                case 'all':
                default:
                    $should_include = $has_price_diff || $has_inventory_diff;
                    break;
            }

            if ($should_include) {
                $diff_products[] = [
                    'walmart_product' => $walmart_product,
                    'local_data' => $local_data,
                    'price_diff' => floatval($local_data->price) - floatval($walmart_product->price),
                    'inventory_diff' => intval($local_data->stock_quantity) - intval($walmart_product->inventory_count),
                    'has_price_diff' => $has_price_diff,
                    'has_inventory_diff' => $has_inventory_diff
                ];

                // 限制数量
                if (count($diff_products) >= $limit) {
                    break;
                }
            }
        }

        return $diff_products;
    }
}
