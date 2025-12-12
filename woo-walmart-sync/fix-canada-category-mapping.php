<?php
/**
 * 修复加拿大市场分类映射问题
 *
 * 问题描述:
 * 在分类映射页面的AJAX函数中,feedType被硬编码为'MP_ITEM'
 * 导致加拿大市场(需要使用MP_ITEM_INTL)的分类属性获取失败
 *
 * 影响范围:
 * - 分类映射页面的"智能加载"按钮
 * - 分类映射页面的"重置属性"按钮
 * - 分类映射页面的"调试API"按钮
 *
 * 修复位置:
 * woo-walmart-sync.php 的以下函数:
 * - Line 13298: wp_ajax_get_walmart_category_attributes
 * - Line 13358: wp_ajax_debug_walmart_api_response
 */

// 不执行此文件,仅作为修复说明文档
if (!defined('ABSPATH')) {
    exit;
}

?>

==============================================
修复方案说明
==============================================

问题根源:
---------
在 woo-walmart-sync.php 的以下位置,feedType 被硬编码为 'MP_ITEM':

1. Line 13298 (获取分类属性函数):
   $body = [
       'feedType' => 'MP_ITEM',  // ❌ 硬编码,不支持多市场
       'version' => '5.0.20241118-04_39_24-api',
       'productTypes' => [$category_id]
   ];

2. Line 13358 (调试API函数):
   $body = [
       'feedType' => 'MP_ITEM',  // ❌ 硬编码,不支持多市场
       'version' => '5.0.20241118-04_39_24-api',
       'productTypes' => [$category_id]
   ];

修复方法:
---------
需要根据当前主市场动态获取正确的 feedType。

修复步骤 1: 修改 Line 13288-13301
将硬编码的 feedType 改为动态获取:

【原代码】:
    if ($attributes === false) {
        $api_auth = new Woo_Walmart_API_Key_Auth();

        // 使用V5.0沃尔玛 Get Spec API
        woo_walmart_sync_log('V5.0动态获取属性', '信息', ['category_name' => $category_name, 'category_id' => $category_id], 'V5.0使用Get Spec API动态获取属性');

        // 使用参考插件的API调用方式
        $endpoint = '/v3/items/spec';
        $body = [
            'feedType' => 'MP_ITEM',  // ❌ 问题所在
            'version' => '5.0.20241118-04_39_24-api',
            'productTypes' => [$category_id]
        ];

【修复后】:
    if ($attributes === false) {
        $api_auth = new Woo_Walmart_API_Key_Auth();

        // 🔧 根据当前主市场动态获取 feedType
        $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
        $market_code = str_replace('WALMART_', '', $business_unit);

        require_once plugin_dir_path(__FILE__) . 'includes/class-multi-market-config.php';
        $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);
        $feed_type = $market_config['feed_types']['item'] ?? 'MP_ITEM';

        woo_walmart_sync_log('V5.0动态获取属性', '信息', [
            'category_name' => $category_name,
            'category_id' => $category_id,
            'market' => $market_code,
            'feed_type' => $feed_type
        ], 'V5.0使用Get Spec API动态获取属性');

        $endpoint = '/v3/items/spec';
        $body = [
            'feedType' => $feed_type,  // ✅ 动态获取
            'version' => '5.0.20241118-04_39_24-api',
            'productTypes' => [$category_id]
        ];


修复步骤 2: 修改 Line 13353-13361
同样修改调试API函数:

【原代码】:
    $api_auth = new Woo_Walmart_API_Key_Auth();

    // V5.0 API调用
    $endpoint = '/v3/items/spec';
    $body = [
        'feedType' => 'MP_ITEM',  // ❌ 问题所在
        'version' => '5.0.20241118-04_39_24-api',
        'productTypes' => [$category_id]
    ];

【修复后】:
    $api_auth = new Woo_Walmart_API_Key_Auth();

    // 🔧 根据当前主市场动态获取 feedType
    $business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
    $market_code = str_replace('WALMART_', '', $business_unit);

    require_once plugin_dir_path(__FILE__) . 'includes/class-multi-market-config.php';
    $market_config = Woo_Walmart_Multi_Market_Config::get_market_config($market_code);
    $feed_type = $market_config['feed_types']['item'] ?? 'MP_ITEM';

    // V5.0 API调用
    $endpoint = '/v3/items/spec';
    $body = [
        'feedType' => $feed_type,  // ✅ 动态获取
        'version' => '5.0.20241118-04_39_24-api',
        'productTypes' => [$category_id]
    ];


验证修复:
---------
修复后,不同市场将使用正确的 feedType:

✅ 美国市场 (US):
   business_unit: WALMART_US
   feed_type: MP_ITEM

✅ 加拿大市场 (CA):
   business_unit: WALMART_CA
   feed_type: MP_ITEM_INTL

✅ 墨西哥市场 (MX):
   business_unit: WALMART_MX
   feed_type: MP_ITEM_INTL

✅ 智利市场 (CL):
   business_unit: WALMART_CL
   feed_type: MP_ITEM_INTL


测试步骤:
---------
1. 在API设置页面,将主市场设置为"加拿大 (CA)"
2. 保存设置
3. 进入分类映射页面
4. 点击"从沃尔玛更新分类列表"按钮
5. 选择一个分类,点击"智能加载"按钮
6. 验证是否成功获取加拿大市场的分类属性
7. 检查日志表中的API调用,确认使用的是 MP_ITEM_INTL


相关配置文件:
-------------
includes/class-multi-market-config.php 中的市场配置:

'US' => [
    'feed_types' => [
        'item' => 'MP_ITEM',
        'price' => 'price',
        'inventory' => 'inventory'
    ]
]

'CA' => [
    'feed_types' => [
        'item' => 'MP_ITEM_INTL',
        'price' => 'price',
        'inventory' => 'inventory'
    ]
]

==============================================
