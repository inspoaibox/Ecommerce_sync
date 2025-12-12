<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_Walmart_API_Key_Auth {
    private $client_id;
    private $client_secret;
    private $consumer_id;      // 🆕 旧版认证
    private $private_key;      // 🆕 旧版认证
    private $auth_method;      // 🆕 认证方式
    private $market_code;      // 🆕 当前市场代码
    private $option_key = 'woo_walmart_tokens';

    public function __construct() {
        // 🔧 根据当前主市场读取对应的凭证
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $this->market_code = str_replace('WALMART_', '', $business_unit);

        // 获取认证方式（默认 OAuth 2.0）
        $this->auth_method = get_option("woo_walmart_{$this->market_code}_auth_method", 'oauth');

        // 加载市场配置
        require_once plugin_dir_path(__FILE__) . 'class-multi-market-config.php';
        $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($this->market_code);

        if ($this->auth_method === 'signature') {
            // 🆕 旧版 Digital Signature 认证
            $this->consumer_id = get_option("woo_walmart_{$this->market_code}_consumer_id", '');
            $this->private_key = get_option("woo_walmart_{$this->market_code}_private_key", '');
            $this->client_id = $this->consumer_id; // 用于兼容性
        } else {
            // OAuth 2.0 认证
            if ($market_config && isset($market_config['auth_config'])) {
                $auth_config = $market_config['auth_config'];
                $this->client_id = get_option($auth_config['client_id_option'], '');
                $this->client_secret = get_option($auth_config['client_secret_option'], '');
            } else {
                // 降级到旧字段（美国市场）
                $this->client_id = get_option('woo_walmart_client_id', '');
                $this->client_secret = get_option('woo_walmart_client_secret', '');
            }
        }
    }

    /**
     * 🆕 生成 Digital Signature（旧版认证）
     *
     * @param string $url 完整的请求 URL
     * @param string $method 请求方法（GET, POST, PUT, DELETE 等）
     * @return array|false 返回包含签名相关信息的数组，失败返回 false
     *                     ['signature' => '签名字符串', 'timestamp' => '时间戳(毫秒)']
     */
    private function generate_signature($url, $method = 'POST') {
        if (empty($this->consumer_id) || empty($this->private_key)) {
            woo_walmart_sync_log('生成签名', '失败', [], 'Consumer ID 或 Private Key 为空');
            return false;
        }

        // 时间戳（毫秒）
        $timestamp = (string) round(microtime(true) * 1000);

        // 🔧 根据官方文档构建签名字符串：
        // Consumer ID + "\n" + URL + "\n" + Request Method + "\n" + Timestamp + "\n"
        $sign_string = $this->consumer_id . "\n" . $url . "\n" . strtoupper($method) . "\n" . $timestamp . "\n";

        // 🔧 格式化 Private Key：确保有正确的 PEM 格式头尾
        $private_key_formatted = $this->format_private_key($this->private_key);

        // 使用 SHA256 with RSA 进行签名
        $private_key_resource = openssl_pkey_get_private($private_key_formatted);

        if (!$private_key_resource) {
            $openssl_error = openssl_error_string();
            woo_walmart_sync_log('生成签名', '失败', ['private_key_preview' => substr($this->private_key, 0, 100)], 'Private Key 格式错误: ' . $openssl_error);
            return false;
        }

        $signature_binary = '';
        $sign_result = openssl_sign($sign_string, $signature_binary, $private_key_resource, OPENSSL_ALGO_SHA256);

        openssl_free_key($private_key_resource);

        if (!$sign_result) {
            woo_walmart_sync_log('生成签名', '失败', ['sign_string' => $sign_string], 'openssl_sign 失败');
            return false;
        }

        // Base64 编码签名
        $signature = base64_encode($signature_binary);

        woo_walmart_sync_log('生成签名', '成功', [
            'consumer_id' => $this->consumer_id,
            'url' => $url,
            'method' => strtoupper($method),
            'timestamp' => $timestamp,
            'signature_preview' => substr($signature, 0, 50) . '...'
        ], '签名生成成功');

        return [
            'signature' => $signature,
            'timestamp' => $timestamp
        ];
    }

    /**
     * 🔧 格式化 Private Key，确保有正确的 PEM 格式
     *
     * @param string $private_key 原始私钥（可能有或没有 PEM 头尾）
     * @return string 格式化后的 PEM 格式私钥
     */
    private function format_private_key($private_key) {
        // 去除首尾空白
        $private_key = trim($private_key);

        // 如果已经有 PEM 格式头尾，直接返回
        if (strpos($private_key, '-----BEGIN') !== false) {
            return $private_key;
        }

        // 否则，假设是纯 Base64 编码的密钥内容，添加 PEM 头尾
        // 标准格式：每64字符换行
        $key_content = chunk_split($private_key, 64, "\n");

        // 构建完整的 PEM 格式
        return "-----BEGIN PRIVATE KEY-----\n" . $key_content . "-----END PRIVATE KEY-----";
    }

    // 获取 access_token
    public function get_access_token($force_new = false) {
        $tokens = get_option($this->option_key);
        if ( ! $force_new && $tokens && ! empty($tokens['access_token']) && $tokens['expires_in'] > time() ) {
            return $tokens['access_token'];
        }

        // 🔧 所有市场都使用同一个 token 端点
        $headers = [
            'Authorization'         => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
            'Content-Type'          => 'application/x-www-form-urlencoded',
            'WM_SVC.NAME'           => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => wp_generate_uuid4(),
        ];

        $body = [
            'grant_type' => 'client_credentials',
        ];

        $request_args = [
            'headers' => $headers,
            'body'    => $body,
        ];

        // 🔧 根据官方回复：所有市场都使用同一个 token 端点
        $response = wp_remote_post('https://marketplace.walmartapis.com/v3/token', $request_args);
        
        if (is_wp_error($response)) {
            woo_walmart_sync_log('获取Token', '失败 (WP_Error)', $request_args, $response->get_error_messages());
            return false;
        }

        // 捕获完整的响应信息用于日志记录
        $full_response_for_log = [
            'code'    => wp_remote_retrieve_response_code($response),
            'message' => wp_remote_retrieve_response_message($response),
            'headers' => wp_remote_retrieve_headers($response)->getAll(),
            'body'    => wp_remote_retrieve_body($response),
        ];

        // 沃尔玛返回的是XML，所以需要用XML解析器
        $access_token = null;
        try {
            // 禁止在无效XML时输出错误，我们手动处理
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($full_response_for_log['body']);
            if ($xml !== false && isset($xml->accessToken)) {
                $access_token = (string) $xml->accessToken;
                $expires_in = (int) $xml->expiresIn;
            }
        } catch (Exception $e) {
            // 捕获异常，防止插件崩溃
        }

        if ($access_token) {
            woo_walmart_sync_log('获取Token', '成功', $request_args, $full_response_for_log);
            update_option($this->option_key, [
                'access_token' => $access_token,
                'expires_in'   => time() + $expires_in,
            ]);
            return $access_token;
        }
        
        // 记录失败日志，包含完整响应
        woo_walmart_sync_log('获取Token', '失败', $request_args, $full_response_for_log);
        return false;
    }

    // 通用API请求方法
    public function make_request($endpoint, $method = 'GET', $body = [], $extra_headers = []) {
        // 🔧 修复：根据当前市场动态构建API端点
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code = str_replace('WALMART_', '', $business_unit); // WALMART_CA -> CA

        // 使用多市场配置获取正确的API端点
        require_once plugin_dir_path(__FILE__) . 'class-multi-market-config.php';
        $url = Woo_Walmart_Multi_Market_Config::get_market_api_endpoint($market_code, $endpoint);

        $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);

        // 🔧 根据认证方式构建不同的请求头
        if ($this->auth_method === 'signature') {
            // 🆕 旧版 Digital Signature 认证
            $signature_data = $this->generate_signature($url, $method);
            if (!$signature_data) {
                return new WP_Error('signature_error', '无法生成 Digital Signature');
            }

            $headers = [
                'WM_CONSUMER.ID'           => $this->consumer_id,
                'WM_SEC.TIMESTAMP'         => $signature_data['timestamp'],
                'WM_SEC.AUTH_SIGNATURE'    => $signature_data['signature'],
                'WM_SVC.NAME'              => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID'    => wp_generate_uuid4(),
                'WM_CONSUMER.CHANNEL.TYPE' => $this->get_market_channel_type($market_code, $business_unit),
                'Content-Type'             => 'application/json',
                'Accept'                   => 'application/json',
            ];
        } else {
            // OAuth 2.0 认证
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return new WP_Error('token_error', '无法获取 Access Token');
            }

            $headers = [
                'WM_SEC.ACCESS_TOKEN'      => $access_token,
                'WM_SVC.NAME'              => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID'    => wp_generate_uuid4(),
                'WM_CONSUMER.CHANNEL.TYPE' => $this->get_market_channel_type($market_code, $business_unit),
                'Content-Type'             => 'application/json',
                'Accept'                   => 'application/json',
            ];
        }

        // 🔧 根据官方回复：通过WM_MARKET头区分市场
        if ($market_code !== 'US' && isset($market_config['auth_config']['market_header'])) {
            $headers['WM_MARKET'] = $market_config['auth_config']['market_header'];
        }

        // 合并额外的请求头
        $headers = array_merge($headers, $extra_headers);

        // 根据请求类型设置不同的超时时间
        $timeout = 60; // 默认60秒
        if (strpos($endpoint, '/feeds') !== false) {
            $timeout = 300; // Feed提交请求使用5分钟超时
        } elseif (strpos($endpoint, '/items') !== false && $method === 'POST') {
            $timeout = 180; // 商品创建/更新请求使用3分钟超时
        }

        $args = [
            'method'    => $method,
            'headers'   => $headers,
            'timeout'   => $timeout,
        ];

        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            woo_walmart_sync_log('API请求失败 (WP_Error)', $response->get_error_message(), $args, '');
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = !empty($response_body) ? json_decode($response_body, true) : null;

        // 记录每次API调用
        woo_walmart_sync_log('API请求', wp_remote_retrieve_response_message($response), $args, $response_body);

        return $decoded_body;
    }

    /**
     * 获取市场特定的Channel Type
     *
     * @param string $market_code 市场代码 (US, CA, MX, CL)
     * @param string $fallback_business_unit 备用业务单元名称
     * @return string Channel Type值（OAuth 2.0 使用 Client ID，旧版使用专用 UUID）
     */
    private function get_market_channel_type($market_code, $fallback_business_unit) {
        // 🔧 根据认证方式返回不同的 Channel Type

        if ($this->auth_method === 'signature') {
            // 旧版 Digital Signature 模式：使用专门的 Channel Type UUID
            $legacy_channel_type = get_option("woo_walmart_{$market_code}_legacy_channel_type", '');
            if (!empty($legacy_channel_type)) {
                return $legacy_channel_type;
            }

            // 降级：使用旧的 channel_type 配置
            $channel_type = get_option("woo_walmart_{$market_code}_channel_type", '');
            if (!empty($channel_type)) {
                return $channel_type;
            }
        } else {
            // OAuth 2.0 模式：使用 Client ID 作为 Channel Type
            if (!empty($this->client_id)) {
                return $this->client_id;
            }
        }

        // 最后降级：使用业务单元名称
        return $fallback_business_unit;
    }

    /**
     * 更新单个商品库存
     *
     * @param array $inventory_data 库存数据
     * @return array API响应
     */
    public function update_inventory($inventory_data) {
        $sku = $inventory_data['sku'];
        $endpoint = '/v3/inventory?sku=' . urlencode($sku);

        $data = array(
            'sku' => $sku,
            'quantity' => $inventory_data['quantity']
        );

        return $this->make_request($endpoint, 'PUT', $data);
    }

    /**
     * 批量更新商品库存
     *
     * @param array $inventory_items 库存数据数组
     * @return array API响应
     */
    public function bulk_update_inventory($inventory_items) {
        $endpoint = '/v3/feeds?feedType=inventory';

        // 构建库存Feed数据结构
        $feed_data = array(
            'InventoryHeader' => array(
                'version' => '1.4'
            ),
            'Inventory' => array()
        );

        foreach ($inventory_items as $item) {
            $feed_data['Inventory'][] = array(
                'sku' => $item['sku'],
                'quantity' => array(
                    'unit' => 'EACH',
                    'amount' => (int) $item['quantity']
                )
            );
        }

        return $this->make_file_upload_request($endpoint, $feed_data, 'inventory_feed.json');
    }

    /**
     * 批量更新商品价格
     *
     * @param array $price_items 价格数据数组
     * @return array API响应
     */
    public function bulk_update_price($price_items) {
        $endpoint = '/v3/feeds?feedType=price';

        // 构建价格Feed数据结构
        $feed_data = array(
            'PriceHeader' => array(
                'version' => '1.7'
            ),
            'Price' => array()
        );

        foreach ($price_items as $item) {
            $feed_data['Price'][] = array(
                'itemIdentifier' => array(
                    'sku' => $item['sku']
                ),
                'pricingList' => array(
                    'pricing' => array(
                        array(
                            'currentPrice' => array(
                                'value' => array(
                                    'currency' => 'USD',
                                    'amount' => round(floatval($item['price']), 2)
                                )
                            ),
                            'currentPriceType' => 'BASE'
                        )
                    )
                )
            );
        }

        return $this->make_file_upload_request($endpoint, $feed_data, 'price_feed.json');
    }

    /**
     * 批量更新商品信息（包括产品名称）
     *
     * @param array $product_items 产品信息数据数组
     * @return array API响应
     */
    public function bulk_update_product_info($product_items) {
        // 🔧 修复：根据当前市场动态选择feedType
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code = str_replace('WALMART_', '', $business_unit); // WALMART_CA -> CA

        // 使用多市场配置获取正确的feedType
        require_once plugin_dir_path(__FILE__) . 'class-multi-market-config.php';
        $feed_type = Woo_Walmart_Multi_Market_Config::get_market_feed_type($market_code, 'item');

        $endpoint = "/v3/feeds?feedType={$feed_type}";

        // 🔧 根据市场动态构建 Feed 数据结构
        if ($market_code === 'CA') {
            // 🇨🇦 加拿大市场：使用 CA_MP_ITEM_INTL_SPEC.json 规范 (版本 3.16)
            $feed_data = array(
                'MPItemFeedHeader' => array(
                    'version' => '3.16',
                    'mart' => 'WALMART_CA',
                    'sellingChannel' => 'marketplace',
                    'processMode' => 'REPLACE',
                    'subset' => 'EXTERNAL'
                ),
                'MPItem' => array()
            );
        } else {
            // 🇺🇸 美国市场：保持原有 V5.0 格式
            $feed_data = array(
                'MPItemFeedHeader' => array(
                    'businessUnit' => $business_unit,
                    'locale' => 'en',
                    'version' => '5.0.20241118-04_39_24-api'
                ),
                'MPItem' => array()
            );
        }

        foreach ($product_items as $item) {
            if ($market_code === 'CA') {
                // 🇨🇦 加拿大市场：使用 Orderable 结构和多语言 productName
                $product_data = array(
                    'Orderable' => array(
                        'sku' => $item['sku'],
                        'productName' => array(
                            'en' => $item['product_name']
                        )
                    )
                );

                // 添加简短描述（多语言）
                if (isset($item['short_description'])) {
                    $product_data['Orderable']['shortDescription'] = array(
                        'en' => $item['short_description']
                    );
                }

                // 添加主图
                if (isset($item['main_image_url'])) {
                    $product_data['Orderable']['mainImageUrl'] = $item['main_image_url'];
                }
            } else {
                // 🇺🇸 美国市场：保持原有格式
                $product_data = array(
                    'sku' => $item['sku'],
                    'productName' => $item['product_name']
                );

                if (isset($item['short_description'])) {
                    $product_data['shortDescription'] = $item['short_description'];
                }

                if (isset($item['main_image_url'])) {
                    $product_data['mainImageUrl'] = $item['main_image_url'];
                }
            }

            $feed_data['MPItem'][] = $product_data;
        }

        return $this->make_file_upload_request($endpoint, $feed_data, 'product_info_feed.json');
    }

    /**
     * 获取单个商品库存
     *
     * @param string $sku 商品SKU
     * @return array API响应
     */
    public function get_inventory($sku) {
        $endpoint = '/v3/inventory?sku=' . urlencode($sku);
        return $this->make_request($endpoint, 'GET');
    }

    /**
     * 批量获取商品库存
     *
     * @param int $limit 每页数量
     * @param string $cursor 游标（用于分页）
     * @return array API响应
     */
    public function get_inventories($limit = 50, $cursor = null) {
        $endpoint = "/v3/inventories?limit={$limit}";
        if ($cursor) {
            $endpoint .= "&nextCursor=" . urlencode($cursor);
        }
        return $this->make_request($endpoint, 'GET');
    }

    // 专门用于文件上传的API请求方法（用于 Bulk Item Setup）
    public function make_file_upload_request($endpoint, $json_data, $filename = 'feed.json') {
        // 添加调试日志确认方法被调用
        woo_walmart_sync_log('文件上传方法-开始', '调试', [
            'endpoint' => $endpoint,
            'filename' => $filename,
            'data_size' => strlen(wp_json_encode($json_data))
        ], '文件上传方法被调用');

        // 🔧 修复：根据当前市场动态构建API端点
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code = str_replace('WALMART_', '', $business_unit); // WALMART_CA -> CA

        // 使用多市场配置获取正确的API端点
        require_once plugin_dir_path(__FILE__) . 'class-multi-market-config.php';
        $url = Woo_Walmart_Multi_Market_Config::get_market_api_endpoint($market_code, $endpoint);

        $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);

        // 创建临时文件
        $temp_file = tempnam(sys_get_temp_dir(), 'walmart_feed_');
        if (!$temp_file) {
            return new WP_Error('temp_file_error', '无法创建临时文件');
        }

        $json_content = wp_json_encode($json_data, JSON_UNESCAPED_UNICODE);

        // 直接保存实际发送的JSON到文件
        file_put_contents(WOO_WALMART_SYNC_PATH . 'actual_sent_data.json', $json_content);

        // 直接保存实际发送的JSON到文件进行调试
        $debug_file = WOO_WALMART_SYNC_PATH . 'debug_sent_to_walmart.json';
        file_put_contents($debug_file, $json_content);

        // 检查尺寸字段的单位信息
        $dimension_check = [
            'assembledProductHeight' => strpos($json_content, '"assembledProductHeight"') !== false,
            'assembledProductWeight' => strpos($json_content, '"assembledProductWeight"') !== false,
            'assembledProductWidth' => strpos($json_content, '"assembledProductWidth"') !== false,
            'measure_unit_count' => preg_match_all('/"measure":\s*[\d.]+,\s*"unit":\s*"[^"]*"/', $json_content)
        ];

        $debug_info = "实际发送给沃尔玛的数据:\n";
        $debug_info .= "JSON大小: " . strlen($json_content) . " 字节\n";
        $debug_info .= "尺寸字段检查: " . json_encode($dimension_check, JSON_UNESCAPED_UNICODE) . "\n";
        $debug_info .= "已保存到: {$debug_file}\n";

        error_log($debug_info);

        if (file_put_contents($temp_file, $json_content) === false) {
            return new WP_Error('file_write_error', '无法写入临时文件');
        }

        // 构建 multipart/form-data 请求
        $boundary = wp_generate_uuid4();

        // 🔧 根据认证方式构建不同的请求头
        if ($this->auth_method === 'signature') {
            // 🆕 旧版 Digital Signature 认证
            $signature_data = $this->generate_signature($url, 'POST');
            if (!$signature_data) {
                return new WP_Error('signature_error', '无法生成 Digital Signature');
            }

            $headers = [
                'WM_CONSUMER.ID'           => $this->consumer_id,
                'WM_SEC.TIMESTAMP'         => $signature_data['timestamp'],
                'WM_SEC.AUTH_SIGNATURE'    => $signature_data['signature'],
                'WM_SVC.NAME'              => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID'    => wp_generate_uuid4(),
                'WM_CONSUMER.CHANNEL.TYPE' => $this->get_market_channel_type($market_code, $business_unit),
                'Content-Type'             => 'multipart/form-data; boundary=' . $boundary,
                'Accept'                   => 'application/json',
            ];
        } else {
            // OAuth 2.0 认证
            $access_token = $this->get_access_token();
            if (!$access_token) {
                return new WP_Error('token_error', '无法获取 Access Token');
            }

            $headers = [
                'WM_SEC.ACCESS_TOKEN'      => $access_token,
                'WM_SVC.NAME'              => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID'    => wp_generate_uuid4(),
                'WM_CONSUMER.CHANNEL.TYPE' => $this->get_market_channel_type($market_code, $business_unit),
                'Content-Type'             => 'multipart/form-data; boundary=' . $boundary,
                'Accept'                   => 'application/json',
            ];
        }

        // 🔧 根据官方回复：通过WM_MARKET头区分市场
        if ($market_code !== 'US' && isset($market_config['auth_config']['market_header'])) {
            $headers['WM_MARKET'] = $market_config['auth_config']['market_header'];
        }

        // 构建 multipart body
        $file_content = file_get_contents($temp_file);
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/json\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $args = [
            'method'    => 'POST',
            'headers'   => $headers,
            'body'      => $body,
            'timeout'   => 300, // 文件上传使用5分钟超时
        ];

        // 🔧 调试：记录完整的请求信息
        woo_walmart_sync_log('批量Feed上传-请求头检查', '调试', [
            'url' => $url,
            'business_unit' => $business_unit,
            'market_code' => $market_code,
            'headers' => $headers, // 记录实际的请求头
            'file_size' => strlen($file_content),
            'filename' => $filename
        ], '检查请求头是否包含 WM_CONSUMER.CHANNEL.TYPE');

        // 记录请求日志
        woo_walmart_sync_log('API请求-文件上传', 'OK', [
            'method' => 'POST',
            'headers' => (object) $headers,
            'timeout' => 60,
            'file_size' => strlen($file_content),
            'filename' => $filename
        ], '文件上传请求');

        $response = wp_remote_request($url, $args);

        // 清理临时文件
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded = !empty($response_body) ? json_decode($response_body, true) : null;

        // 记录响应日志
        woo_walmart_sync_log('API响应-文件上传', wp_remote_retrieve_response_message($response), $args, $response_body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'API响应不是有效的JSON: ' . $response_body);
        }

        return $decoded;
    }
}