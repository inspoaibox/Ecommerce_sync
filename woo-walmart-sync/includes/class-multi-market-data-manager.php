<?php
/**
 * Walmart多市场数据管理器
 * 
 * @package WooWalmartSync
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-multi-market-config.php';

class Woo_Walmart_Multi_Market_Data_Manager {
    
    /**
     * 数据库表前缀
     * @var string
     */
    private $table_prefix;
    
    /**
     * WordPress数据库对象
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix;
    }
    
    /**
     * 创建多市场数据库表
     */
    public function create_multi_market_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // 1. 市场配置表
        $market_configs_table = $this->table_prefix . 'walmart_market_configs';
        $sql1 = "CREATE TABLE IF NOT EXISTS {$market_configs_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            market varchar(10) NOT NULL,
            business_unit varchar(50) NOT NULL,
            api_base_url varchar(255) NOT NULL,
            currency varchar(10) NOT NULL,
            locale varchar(10) NOT NULL,
            country_code varchar(10) NOT NULL,
            tax_required tinyint(1) DEFAULT 0,
            is_enabled tinyint(1) DEFAULT 1,
            priority int(11) DEFAULT 1,
            config_data longtext,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY market (market),
            KEY is_enabled (is_enabled),
            KEY priority (priority)
        ) {$charset_collate};";
        
        // 2. 升级分类映射表
        $category_map_table = $this->table_prefix . 'walmart_category_map';
        $sql2 = "ALTER TABLE {$category_map_table} 
                ADD COLUMN IF NOT EXISTS market varchar(10) DEFAULT 'US' AFTER id,
                ADD INDEX IF NOT EXISTS idx_market_category (market, wc_category_id);";
        
        // 3. 升级同步日志表
        $sync_logs_table = $this->table_prefix . 'woo_walmart_sync_logs';
        $sql3 = "ALTER TABLE {$sync_logs_table} 
                ADD COLUMN IF NOT EXISTS market varchar(10) DEFAULT 'US' AFTER id,
                ADD INDEX IF NOT EXISTS idx_market_sync (market, sync_date);";
        
        // 4. 升级库存同步表
        $inventory_sync_table = $this->table_prefix . 'walmart_inventory_sync';
        $sql4 = "ALTER TABLE {$inventory_sync_table} 
                ADD COLUMN IF NOT EXISTS market varchar(10) DEFAULT 'US' AFTER id,
                ADD INDEX IF NOT EXISTS idx_market_inventory (market, product_id);";
        
        // 执行SQL
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        
        // 执行ALTER语句
        $this->wpdb->query($sql2);
        $this->wpdb->query($sql3);
        $this->wpdb->query($sql4);
        
        // 插入默认市场配置
        $this->insert_default_market_configs();
    }
    
    /**
     * 插入默认市场配置
     */
    private function insert_default_market_configs() {
        $markets = Woo_Walmart_Multi_Market_Config::get_all_markets();
        $table = $this->table_prefix . 'walmart_market_configs';
        
        foreach ($markets as $code => $config) {
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$table} WHERE market = %s",
                $code
            ));
            
            if (!$existing) {
                $this->wpdb->insert($table, [
                    'market' => $code,
                    'business_unit' => $config['business_unit'],
                    'api_base_url' => $config['api_base_url'],
                    'currency' => $config['currency'],
                    'locale' => $config['locale'],
                    'country_code' => $config['country_code'],
                    'tax_required' => $config['tax_required'] ? 1 : 0,
                    'is_enabled' => $config['is_enabled'] ? 1 : 0,
                    'priority' => $config['priority'],
                    'config_data' => json_encode($config)
                ]);
            }
        }
    }
    
    /**
     * 获取市场特定的分类映射
     * 
     * @param int $wc_category_id WooCommerce分类ID
     * @param string $market 市场代码
     * @return object|null
     */
    public function get_category_mapping($wc_category_id, $market = 'US') {
        $table = $this->table_prefix . 'walmart_category_map';
        
        // 首先查找市场特定的映射
        $mapping = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE wc_category_id = %d AND market = %s",
            $wc_category_id, $market
        ));
        
        // 如果没有找到，查找默认映射（US市场）
        if (!$mapping && $market !== 'US') {
            $mapping = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE wc_category_id = %d AND market = 'US'",
                $wc_category_id
            ));
        }
        
        return $mapping;
    }
    
    /**
     * 保存市场特定的分类映射
     * 
     * @param int $wc_category_id WooCommerce分类ID
     * @param string $walmart_category_path Walmart分类路径
     * @param array $attributes 属性配置
     * @param string $market 市场代码
     * @return bool
     */
    public function save_category_mapping($wc_category_id, $walmart_category_path, $attributes, $market = 'US') {
        $table = $this->table_prefix . 'walmart_category_map';
        
        $data = [
            'wc_category_id' => $wc_category_id,
            'walmart_category_path' => $walmart_category_path,
            'walmart_attributes' => json_encode($attributes),
            'market' => $market,
            'updated_at' => current_time('mysql')
        ];
        
        // 检查是否已存在
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE wc_category_id = %d AND market = %s",
            $wc_category_id, $market
        ));
        
        if ($existing) {
            return $this->wpdb->update($table, $data, [
                'wc_category_id' => $wc_category_id,
                'market' => $market
            ]);
        } else {
            $data['created_at'] = current_time('mysql');
            return $this->wpdb->insert($table, $data);
        }
    }
    
    /**
     * 记录市场特定的同步日志
     * 
     * @param string $action 操作类型
     * @param string $status 状态
     * @param mixed $request 请求数据
     * @param mixed $response 响应数据
     * @param string $market 市场代码
     * @return bool
     */
    public function log_sync_operation($action, $status, $request = null, $response = null, $market = 'US') {
        $table = $this->table_prefix . 'woo_walmart_sync_logs';
        
        $data = [
            'market' => $market,
            'action' => $action,
            'status' => $status,
            'request' => is_array($request) ? json_encode($request) : $request,
            'response' => is_array($response) ? json_encode($response) : $response,
            'sync_date' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ];
        
        return $this->wpdb->insert($table, $data);
    }
    
    /**
     * 获取市场特定的同步日志
     * 
     * @param string $market 市场代码
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public function get_sync_logs($market = null, $limit = 50, $offset = 0) {
        $table = $this->table_prefix . 'woo_walmart_sync_logs';
        
        $where = '';
        $params = [];
        
        if ($market) {
            $where = 'WHERE market = %s';
            $params[] = $market;
        }
        
        $sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * 更新库存同步记录
     * 
     * @param int $product_id 产品ID
     * @param int $quantity 库存数量
     * @param string $market 市场代码
     * @return bool
     */
    public function update_inventory_sync($product_id, $quantity, $market = 'US') {
        $table = $this->table_prefix . 'walmart_inventory_sync';
        
        $data = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'market' => $market,
            'last_sync' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        // 检查是否已存在
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE product_id = %d AND market = %s",
            $product_id, $market
        ));
        
        if ($existing) {
            return $this->wpdb->update($table, $data, [
                'product_id' => $product_id,
                'market' => $market
            ]);
        } else {
            $data['created_at'] = current_time('mysql');
            return $this->wpdb->insert($table, $data);
        }
    }
    
    /**
     * 获取产品的市场同步状态
     * 
     * @param int $product_id 产品ID
     * @return array
     */
    public function get_product_market_status($product_id) {
        $table = $this->table_prefix . 'walmart_inventory_sync';
        
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT market, quantity, last_sync FROM {$table} WHERE product_id = %d",
            $product_id
        ));
        
        $status = [];
        foreach ($results as $result) {
            $status[$result->market] = [
                'quantity' => $result->quantity,
                'last_sync' => $result->last_sync
            ];
        }
        
        return $status;
    }
    
    /**
     * 数据迁移：将现有数据标记为美国市场
     */
    public function migrate_existing_data_to_us_market() {
        $tables = [
            $this->table_prefix . 'walmart_category_map',
            $this->table_prefix . 'woo_walmart_sync_logs',
            $this->table_prefix . 'walmart_inventory_sync'
        ];
        
        foreach ($tables as $table) {
            // 检查表是否存在market字段
            $columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'market'");
            
            if (!empty($columns)) {
                // 将所有没有market值的记录设置为US
                $this->wpdb->query("UPDATE {$table} SET market = 'US' WHERE market IS NULL OR market = ''");
            }
        }
        
        return true;
    }
    
    /**
     * 清理特定市场的数据
     * 
     * @param string $market 市场代码
     * @return bool
     */
    public function cleanup_market_data($market) {
        if ($market === 'US') {
            return false; // 不允许清理美国市场数据
        }
        
        $tables = [
            $this->table_prefix . 'walmart_category_map',
            $this->table_prefix . 'woo_walmart_sync_logs',
            $this->table_prefix . 'walmart_inventory_sync'
        ];
        
        foreach ($tables as $table) {
            $this->wpdb->delete($table, ['market' => $market]);
        }
        
        return true;
    }
}
