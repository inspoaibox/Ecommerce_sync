<?php
/**
 * 创建一个简单的测试脚本来验证加拿大Feed格式
 */

require_once(__DIR__ . '/../../../wp-load.php');

// 测试产品
$product_id = 47;
$product = wc_get_product($product_id);

echo "<h1>加拿大Feed格式测试</h1>";
echo "<pre>";

// 方式1: 直接在Visible下放字段（无分类名称）
$feed_no_category = [
    'MPItemFeedHeader' => [
        'version' => '3.16',
        'mart' => 'WALMART_CA',
        'sellingChannel' => 'marketplace',
        'processMode' => 'REPLACE',
        'subset' => 'EXTERNAL'
    ],
    'MPItem' => [[
        'Orderable' => [
            'sku' => $product->get_sku(),
            'productIdentifiers' => [
                'productIdType' => 'UPC',
                'productId' => '123456789012'
            ],
            'price' => $product->get_price()
        ],
        'Visible' => [
            'productName' => $product->get_name(),
            'mainImageUrl' => wp_get_attachment_url($product->get_image_id()),
            'shortDescription' => [
                'en' => $product->get_short_description() ?: $product->get_name(),
                'fr' => $product->get_short_description() ?: $product->get_name()
            ]
        ]
    ]]
];

echo "=== 方式1: Visible 直接包含字段（无分类层级）===\n";
echo json_encode($feed_no_category, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

echo "\n\n=== 方式2: Visible 有分类层级 ===\n";

$feed_with_category = [
    'MPItemFeedHeader' => [
        'version' => '3.16',
        'mart' => 'WALMART_CA',
        'sellingChannel' => 'marketplace',
        'processMode' => 'REPLACE',
        'subset' => 'EXTERNAL'
    ],
    'MPItem' => [[
        'Orderable' => [
            'sku' => $product->get_sku(),
            'productIdentifiers' => [
                'productIdType' => 'UPC',
                'productId' => '123456789012'
            ],
            'price' => $product->get_price()
        ],
        'Visible' => [
            'Furniture' => [
                'productName' => $product->get_name(),
                'mainImageUrl' => wp_get_attachment_url($product->get_image_id()),
                'shortDescription' => [
                    'en' => $product->get_short_description() ?: $product->get_name(),
                    'fr' => $product->get_short_description() ?: $product->get_name()
                ]
            ]
        ]
    ]]
];

echo json_encode($feed_with_category, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

echo "\n\n</pre>";

echo "<p>请将这两种格式中的一种上传到 Walmart API 测试哪种有效</p>";
