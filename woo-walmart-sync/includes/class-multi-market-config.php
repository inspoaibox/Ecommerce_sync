<?php
/**
 * Walmart多市场配置管理类
 * 
 * @package WooWalmartSync
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Walmart_Multi_Market_Config {
    
    /**
     * 市场配置数据
     * @var array
     */
    private static $market_configs = null;
    
    /**
     * 获取所有市场配置
     * 
     * @return array
     */
    public static function get_all_markets() {
        if (self::$market_configs === null) {
            self::$market_configs = [
                'US' => [
                    'business_unit' => 'WALMART_US',
                    'api_base_url' => 'https://marketplace.walmartapis.com/',
                    'currency' => 'USD',
                    'locale' => 'en',
                    'country_code' => 'US',
                    'country_name' => 'United States',
                    'flag' => '🇺🇸',
                    'timezone' => 'America/New_York',
                    'tax_required' => false,
                    'tax_rates' => [],
                    'supported_modules' => [
                        'advertising', 'catalog', 'disputes', 'multichannel',
                        'payments', 'reviews', 'full_fulfillment', 'full_insights',
                        'notifications', 'utilities', 'simplified_shipping'
                    ],
                    'fulfillment_centers' => ['WFS', 'SELLER_FULFILLED'], // 支持任何用户设置的履行中心ID
                    'api_version' => '5.0.20241118-04_39_24-api',
                    // 🆕 Feed类型配置
                    'feed_types' => [
                        'item' => 'MP_ITEM',           // 美国市场使用标准MP_ITEM
                        'price' => 'price',
                        'inventory' => 'inventory'
                    ],
                    'priority' => 1,
                    'is_enabled' => true
                ],
                'CA' => [
                    'business_unit' => 'WALMART_CA',
                    'api_base_url' => 'https://marketplace.walmartapis.com/',
                    'currency' => 'CAD',
                    'locale' => 'en',
                    'country_code' => 'CA',
                    'country_name' => 'Canada',
                    'flag' => '🇨🇦',
                    'timezone' => 'America/Toronto',
                    'tax_required' => true,
                    'tax_rates' => [
                        'GST' => 0.05,  // 联邦商品服务税
                        'PST' => 0.07,  // 省销售税(各省不同)
                        'HST' => 0.13   // 统一销售税(部分省份)
                    ],
                    'supported_modules' => [
                        'standard_fulfillment', 'basic_insights', 'international_shipping',
                        'assortment_recommendations'
                    ],
                    'fulfillment_centers' => ['WFS_CA', 'SELLER_FULFILLED'], // 支持任何用户设置的履行中心ID
                    'api_version' => '5.0.20241118-04_39_24-api',
                    // 🔧 加拿大市场正确配置
                    'feed_types' => [
                        'item' => 'MP_ITEM_INTL',      // 加拿大自发货模式使用MP_ITEM_INTL
                        'price' => 'price',
                        'inventory' => 'inventory'
                    ],
                    // 🔧 根据官方回复：所有市场使用OAuth 2.0认证 + WM_MARKET头
                    'auth_method' => 'oauth',          // 使用OAuth 2.0认证
                    'auth_config' => [
                        'client_id_option' => 'woo_walmart_CA_client_id',      // 修正：与API设置页面字段名一致
                        'client_secret_option' => 'woo_walmart_CA_client_secret', // 需要从开发者门户获取Client Secret
                        'token_url' => '/v3/token',
                        'market_header' => 'CA'  // WM_MARKET头的值
                    ],
                    'language_requirements' => [
                        'bilingual_labels' => true,
                        'french_support' => 'required_in_quebec'
                    ],
                    'priority' => 2,
                    'is_enabled' => false
                ],
                'MX' => [
                    'business_unit' => 'WALMART_MX',
                    'api_base_url' => 'https://marketplace.walmartapis.com/',
                    'currency' => 'MXN',
                    'locale' => 'es',
                    'country_code' => 'MX',
                    'country_name' => 'Mexico',
                    'flag' => '🇲🇽',
                    'timezone' => 'America/Mexico_City',
                    'tax_required' => true,
                    'tax_rates' => [
                        'IVA' => 0.16  // 增值税16%
                    ],
                    'supported_modules' => [
                        'mx_reports', 'returns', 'standard_fulfillment', 'basic_insights',
                        'international_shipping'
                    ],
                    'fulfillment_centers' => ['WFS_MX', 'SELLER_FULFILLED'], // 支持任何用户设置的履行中心ID
                    'api_version' => '5.0.20241118-04_39_24-api',
                    // 🆕 Feed类型配置 - 墨西哥市场使用国际版本
                    'feed_types' => [
                        'item' => 'MP_ITEM_INTL',      // 墨西哥市场使用国际版本
                        'price' => 'price',
                        'inventory' => 'inventory'
                    ],
                    'required_fields' => [
                        'brand', 'model', 'origin_country', 'mexican_tax_id'
                    ],
                    'restricted_categories' => [
                        'alcohol', 'tobacco', 'pharmaceuticals'
                    ],
                    'priority' => 3,
                    'is_enabled' => false
                ],
                'CL' => [
                    'business_unit' => 'WALMART_CL',
                    'api_base_url' => 'https://marketplace.walmartapis.com/',
                    'currency' => 'CLP',
                    'locale' => 'es',
                    'country_code' => 'CL',
                    'country_name' => 'Chile',
                    'flag' => '🇨🇱',
                    'timezone' => 'America/Santiago',
                    'tax_required' => true,
                    'tax_rates' => [
                        'IVA' => 0.19  // 增值税19%
                    ],
                    'supported_modules' => [
                        'basic_core_only', 'lead_time_management'
                    ],
                    'fulfillment_centers' => ['WFS_CL', 'SELLER_FULFILLED'], // 支持任何用户设置的履行中心ID
                    'api_version' => '5.0.20241118-04_39_24-api',
                    // 🆕 Feed类型配置 - 智利市场使用国际版本
                    'feed_types' => [
                        'item' => 'MP_ITEM_INTL',      // 智利市场使用国际版本
                        'price' => 'price',
                        'inventory' => 'inventory'
                    ],
                    'required_fields' => [
                        'brand', 'model', 'warranty', 'chilean_tax_id'
                    ],
                    'restricted_categories' => [
                        'pharmaceuticals', 'medical_devices'
                    ],
                    'currency_special' => [
                        'no_decimals' => true  // CLP通常不使用小数点
                    ],
                    'priority' => 4,
                    'is_enabled' => false
                ]
            ];
        }
        
        return self::$market_configs;
    }
    
    /**
     * 获取特定市场配置
     * 
     * @param string $market_code 市场代码
     * @return array|null
     */
    public static function get_market_config($market_code) {
        $markets = self::get_all_markets();
        return isset($markets[$market_code]) ? $markets[$market_code] : null;
    }
    
    /**
     * 获取启用的市场
     * 
     * @return array
     */
    public static function get_enabled_markets() {
        $markets = self::get_all_markets();
        $enabled_markets = [];
        
        foreach ($markets as $code => $config) {
            if ($config['is_enabled']) {
                $enabled_markets[$code] = $config;
            }
        }
        
        return $enabled_markets;
    }
    
    /**
     * 获取默认市场
     * 
     * @return string
     */
    public static function get_default_market() {
        return get_option('woo_walmart_default_market', 'US');
    }
    
    /**
     * 检查市场是否支持特定功能模块
     * 
     * @param string $market_code 市场代码
     * @param string $module 功能模块
     * @return bool
     */
    public static function market_supports_module($market_code, $module) {
        $config = self::get_market_config($market_code);
        if (!$config) {
            return false;
        }
        
        return in_array($module, $config['supported_modules']);
    }
    
    /**
     * 获取市场特定的API端点
     *
     * @param string $market_code 市场代码
     * @param string $endpoint 端点路径
     * @return string
     */
    public static function get_market_api_endpoint($market_code, $endpoint) {
        $config = self::get_market_config($market_code);
        if (!$config) {
            return $endpoint;
        }

        $base_url = $config['api_base_url'];
        $clean_endpoint = ltrim($endpoint, '/');

        // 加拿大市场使用正确的 /v3/ca/ 端点，不需要覆盖

        // 🔧 原有逻辑：不同市场需要不同的API端点路径（仅在没有覆盖时使用）
        if (!isset($config['endpoint_overrides']) || !self::has_endpoint_override($config, $clean_endpoint)) {
            if (self::endpoint_requires_market_path($clean_endpoint)) {
                $market_path = self::get_market_path($market_code);
                if ($market_path) {
                    // 插入市场路径：v3/feeds -> v3/ca/feeds
                    $clean_endpoint = str_replace('v3/', "v3/{$market_path}/", $clean_endpoint);
                }
            }
        }

        return $base_url . $clean_endpoint;
    }

    /**
     * 检查是否有端点覆盖
     */
    private static function has_endpoint_override($config, $endpoint) {
        if (!isset($config['endpoint_overrides'])) {
            return false;
        }

        foreach ($config['endpoint_overrides'] as $pattern => $override) {
            if (strpos($endpoint, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查端点是否需要市场特定路径
     *
     * @param string $endpoint 端点路径
     * @return bool
     */
    private static function endpoint_requires_market_path($endpoint) {
        // 需要市场特定路径的端点列表
        $market_specific_endpoints = [
            'v3/feeds',           // Feed管理
            'v3/items',           // 商品管理
            'v3/inventory',       // 库存管理
            'v3/prices',          // 价格管理
            'v3/orders',          // 订单管理
            'v3/reports',         // 报告
            'v3/returns',         // 退货
            'v3/promotions',      // 促销
        ];

        // 检查端点是否匹配需要市场路径的模式
        foreach ($market_specific_endpoints as $pattern) {
            if (strpos($endpoint, $pattern) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取市场的认证配置
     * 
     * @param string $market_code 市场代码
     * @return array
     */
    public static function get_market_auth_config($market_code) {
        $config = self::get_market_config($market_code);
        if (!$config) {
            return [];
        }
        
        return [
            'client_id' => get_option("woo_walmart_{$market_code}_client_id", ''),
            'client_secret' => get_option("woo_walmart_{$market_code}_client_secret", ''),
            'business_unit' => $config['business_unit'],
            'locale' => $config['locale'],
            'api_version' => $config['api_version']
        ];
    }
    
    /**
     * 验证市场配置
     * 
     * @param string $market_code 市场代码
     * @return array 验证结果
     */
    public static function validate_market_config($market_code) {
        $config = self::get_market_config($market_code);
        $errors = [];
        
        if (!$config) {
            $errors[] = "无效的市场代码：{$market_code}";
            return ['valid' => false, 'errors' => $errors];
        }
        
        // 检查认证配置
        $auth_config = self::get_market_auth_config($market_code);
        if (empty($auth_config['client_id'])) {
            $errors[] = "缺少{$market_code}市场的Client ID";
        }
        
        if (empty($auth_config['client_secret'])) {
            $errors[] = "缺少{$market_code}市场的Client Secret";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'config' => $config
        ];
    }

    /**
     * 获取市场特定的API路径前缀
     *
     * @param string $market_code 市场代码
     * @return string|null 市场路径前缀
     */
    private static function get_market_path($market_code) {
        // 根据沃尔玛官方文档的市场路径映射
        $market_paths = [
            'US' => null,        // 美国市场不需要路径前缀
            'CA' => 'ca',        // 加拿大市场使用 /ca/ 前缀
            'MX' => 'mx',        // 墨西哥市场使用 /mx/ 前缀
            'CL' => 'cl',        // 智利市场使用 /cl/ 前缀
        ];

        return $market_paths[$market_code] ?? null;
    }

    /**
     * 获取市场特定的Feed类型
     *
     * @param string $market_code 市场代码
     * @param string $feed_category Feed分类 (item, price, inventory)
     * @return string Feed类型
     */
    public static function get_market_feed_type($market_code, $feed_category = 'item') {
        $config = self::get_market_config($market_code);
        if (!$config || !isset($config['feed_types'])) {
            // 默认使用美国市场的Feed类型
            return $feed_category === 'item' ? 'MP_ITEM' : $feed_category;
        }

        return $config['feed_types'][$feed_category] ?? ($feed_category === 'item' ? 'MP_ITEM' : $feed_category);
    }
}
