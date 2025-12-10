<?php
/**
 * Walmart多市场API路由管理器
 * 
 * @package WooWalmartSync
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-multi-market-config.php';

class Woo_Walmart_Multi_Market_API_Router {
    
    /**
     * 当前市场
     * @var string
     */
    private $current_market;
    
    /**
     * 市场配置
     * @var array
     */
    private $market_config;
    
    /**
     * API认证令牌缓存
     * @var array
     */
    private static $token_cache = [];
    
    /**
     * 构造函数
     * 
     * @param string $market_code 市场代码
     */
    public function __construct($market_code = null) {
        $this->current_market = $market_code ?: Woo_Walmart_Multi_Market_Config::get_default_market();
        $this->market_config = Woo_Walmart_Multi_Market_Config::get_market_config($this->current_market);
        
        if (!$this->market_config) {
            throw new Exception("无效的市场代码：{$this->current_market}");
        }
    }
    
    /**
     * 获取访问令牌
     * 
     * @return string|false
     */
    public function get_access_token() {
        $cache_key = "walmart_token_{$this->current_market}";
        
        // 检查缓存
        if (isset(self::$token_cache[$cache_key])) {
            $cached_token = self::$token_cache[$cache_key];
            if ($cached_token['expires'] > time()) {
                return $cached_token['token'];
            }
        }
        
        // 获取认证配置
        $auth_config = Woo_Walmart_Multi_Market_Config::get_market_auth_config($this->current_market);
        
        if (empty($auth_config['client_id']) || empty($auth_config['client_secret'])) {
            error_log("Walmart API: {$this->current_market}市场缺少认证配置");
            return false;
        }
        
        // 请求新令牌
        $token_endpoint = $this->market_config['api_base_url'] . 'v3/token';

        // 使用Basic认证（正确的方式）
        $auth_string = base64_encode($auth_config['client_id'] . ':' . $auth_config['client_secret']);

        $response = wp_remote_post($token_endpoint, [
            'headers' => [
                'Authorization' => 'Basic ' . $auth_string,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID' => $this->generate_correlation_id()
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log("Walmart API Token Error ({$this->current_market}): " . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            error_log("Walmart API Token Error ({$this->current_market}): 无效的响应格式");
            return false;
        }
        
        // 缓存令牌
        $expires_in = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        self::$token_cache[$cache_key] = [
            'token' => $data['access_token'],
            'expires' => time() + $expires_in - 300 // 提前5分钟过期
        ];
        
        return $data['access_token'];
    }
    
    /**
     * 执行API请求
     * 
     * @param string $endpoint API端点
     * @param string $method HTTP方法
     * @param array $body 请求体
     * @param array $additional_headers 额外请求头
     * @return array|WP_Error
     */
    public function make_request($endpoint, $method = 'GET', $body = [], $additional_headers = []) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return new WP_Error('auth_failed', "无法获取{$this->current_market}市场的访问令牌");
        }
        
        // 构建完整URL
        $url = Woo_Walmart_Multi_Market_Config::get_market_api_endpoint($this->current_market, $endpoint);
        
        // 构建请求头
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $access_token,
            'WM_SEC.ACCESS_TOKEN' => $access_token,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => $this->generate_correlation_id(),
            'WM_CONSUMER.CHANNEL.TYPE' => $this->market_config['business_unit'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ], $additional_headers);
        
        // 构建请求参数
        $args = [
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => 60
        ];
        
        if (!empty($body) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = is_array($body) ? json_encode($body) : $body;
        }
        
        // 记录请求日志
        $this->log_request($url, $method, $args);
        
        // 执行请求
        $response = wp_remote_request($url, $args);
        
        // 记录响应日志
        $this->log_response($response);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // 解析响应
        $data = json_decode($response_body, true);
        
        if ($response_code >= 400) {
            $error_message = $this->parse_error_response($data, $response_code);
            return new WP_Error('api_error', $error_message, [
                'response_code' => $response_code,
                'response_body' => $response_body
            ]);
        }
        
        return [
            'success' => true,
            'data' => $data,
            'response_code' => $response_code,
            'market' => $this->current_market
        ];
    }
    
    /**
     * 切换市场
     * 
     * @param string $market_code 市场代码
     * @return bool
     */
    public function switch_market($market_code) {
        $config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);
        if (!$config) {
            return false;
        }
        
        $this->current_market = $market_code;
        $this->market_config = $config;
        
        return true;
    }
    
    /**
     * 获取当前市场
     * 
     * @return string
     */
    public function get_current_market() {
        return $this->current_market;
    }
    
    /**
     * 检查市场是否支持特定端点
     * 
     * @param string $endpoint 端点路径
     * @return bool
     */
    public function market_supports_endpoint($endpoint) {
        // 根据市场支持的模块判断端点是否可用
        $supported_modules = $this->market_config['supported_modules'];
        
        // 端点到模块的映射
        $endpoint_module_map = [
            '/v3/feeds' => 'feed_management',
            '/v3/inventory' => 'inventory_management',
            '/v3/items' => 'item_management',
            '/v3/orders' => 'order_management',
            '/v3/prices' => 'price_management',
            '/v3/promotions' => 'promotion_management',
            '/v3/reports' => 'reports',
            '/v3/advertising' => 'advertising',
            '/v3/catalog' => 'catalog',
            '/v3/disputes' => 'disputes',
            '/v3/payments' => 'payments',
            '/v3/returns' => 'returns'
        ];
        
        foreach ($endpoint_module_map as $pattern => $module) {
            if (strpos($endpoint, $pattern) === 0) {
                return in_array($module, $supported_modules);
            }
        }
        
        // 默认支持基础端点
        return true;
    }
    
    /**
     * 生成关联ID
     * 
     * @return string
     */
    private function generate_correlation_id() {
        return uniqid('woo_walmart_' . $this->current_market . '_', true);
    }
    
    /**
     * 记录请求日志
     * 
     * @param string $url 请求URL
     * @param string $method HTTP方法
     * @param array $args 请求参数
     */
    private function log_request($url, $method, $args) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Walmart API Request ({$this->current_market}): {$method} {$url}");
        }
    }
    
    /**
     * 记录响应日志
     * 
     * @param mixed $response 响应数据
     */
    private function log_response($response) {
        if (defined('WP_DEBUG') && WP_DEBUG && !is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            error_log("Walmart API Response ({$this->current_market}): HTTP {$response_code}");
        }
    }
    
    /**
     * 解析错误响应
     * 
     * @param array $data 响应数据
     * @param int $response_code HTTP状态码
     * @return string
     */
    private function parse_error_response($data, $response_code) {
        if (isset($data['error']['description'])) {
            return $data['error']['description'];
        }
        
        if (isset($data['errors']) && is_array($data['errors'])) {
            $errors = [];
            foreach ($data['errors'] as $error) {
                if (isset($error['description'])) {
                    $errors[] = $error['description'];
                }
            }
            return implode('; ', $errors);
        }
        
        return "API请求失败 (HTTP {$response_code})";
    }
}
