<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 美国 vs 加拿大市场分类和属性对比测试 ===\n\n";

// 检查是否有加拿大市场的配置
$ca_client_id = get_option('woo_walmart_CA_client_id', '');
$ca_client_secret = get_option('woo_walmart_CA_client_secret', '');

if (empty($ca_client_id) || empty($ca_client_secret)) {
    echo "❌ 加拿大市场未配置API认证信息\n";
    echo "请先在设置页面配置加拿大市场的Client ID和Client Secret\n";
    echo "然后重新运行此测试\n\n";
    
    echo "当前配置状态:\n";
    echo "- 加拿大Client ID: " . (empty($ca_client_id) ? '❌ 未配置' : '✅ 已配置') . "\n";
    echo "- 加拿大Client Secret: " . (empty($ca_client_secret) ? '❌ 未配置' : '✅ 已配置') . "\n";
    exit;
}

echo "✅ 加拿大市场配置检查通过\n\n";

// 保存原始配置
$original_client_id = get_option('woo_walmart_client_id', '');
$original_client_secret = get_option('woo_walmart_client_secret', '');
$original_business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');

echo "=== 1. 测试美国市场分类获取 ===\n";

// 设置美国市场配置
$us_client_id = get_option('woo_walmart_US_client_id', get_option('woo_walmart_client_id', ''));
$us_client_secret = get_option('woo_walmart_US_client_secret', get_option('woo_walmart_client_secret', ''));

update_option('woo_walmart_client_id', $us_client_id);
update_option('woo_walmart_client_secret', $us_client_secret);
update_option('woo_walmart_business_unit', 'WALMART_US');

$api_auth = new Woo_Walmart_API_Key_Auth();

// 获取美国市场分类
echo "正在获取美国市场分类...\n";
$us_categories_response = $api_auth->make_request('/v3/items/taxonomy?version=5.0', 'GET');

if ($us_categories_response && isset($us_categories_response['payload'])) {
    $us_categories = $us_categories_response['payload'];
    echo "✅ 美国市场分类获取成功，共 " . count($us_categories) . " 个分类\n";
    
    // 显示前5个分类作为示例
    echo "美国市场分类示例:\n";
    $count = 0;
    foreach ($us_categories as $category) {
        if ($count >= 5) break;
        echo "  - " . $category['categoryId'] . ": " . $category['categoryName'] . "\n";
        $count++;
    }
} else {
    echo "❌ 美国市场分类获取失败\n";
    if ($us_categories_response) {
        echo "错误信息: " . json_encode($us_categories_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== 2. 测试加拿大市场分类获取 ===\n";

// 设置加拿大市场配置
update_option('woo_walmart_client_id', $ca_client_id);
update_option('woo_walmart_client_secret', $ca_client_secret);
update_option('woo_walmart_business_unit', 'WALMART_CA');

// 重新创建API认证实例以使用新配置
$api_auth_ca = new Woo_Walmart_API_Key_Auth();

// 获取加拿大市场分类
echo "正在获取加拿大市场分类...\n";
$ca_categories_response = $api_auth_ca->make_request('/v3/items/taxonomy?version=5.0', 'GET');

if ($ca_categories_response && isset($ca_categories_response['payload'])) {
    $ca_categories = $ca_categories_response['payload'];
    echo "✅ 加拿大市场分类获取成功，共 " . count($ca_categories) . " 个分类\n";
    
    // 显示前5个分类作为示例
    echo "加拿大市场分类示例:\n";
    $count = 0;
    foreach ($ca_categories as $category) {
        if ($count >= 5) break;
        echo "  - " . $category['categoryId'] . ": " . $category['categoryName'] . "\n";
        $count++;
    }
} else {
    echo "❌ 加拿大市场分类获取失败\n";
    if ($ca_categories_response) {
        echo "错误信息: " . json_encode($ca_categories_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== 3. 分类对比分析 ===\n";

if (isset($us_categories) && isset($ca_categories)) {
    // 创建分类ID映射
    $us_category_ids = array_column($us_categories, 'categoryId');
    $ca_category_ids = array_column($ca_categories, 'categoryId');
    
    $common_categories = array_intersect($us_category_ids, $ca_category_ids);
    $us_only_categories = array_diff($us_category_ids, $ca_category_ids);
    $ca_only_categories = array_diff($ca_category_ids, $us_category_ids);
    
    echo "分类对比结果:\n";
    echo "- 共同分类: " . count($common_categories) . " 个\n";
    echo "- 仅美国有: " . count($us_only_categories) . " 个\n";
    echo "- 仅加拿大有: " . count($ca_only_categories) . " 个\n";
    
    if (count($common_categories) > 0) {
        echo "\n相同分类比例: " . round((count($common_categories) / max(count($us_categories), count($ca_categories))) * 100, 2) . "%\n";
    }
    
    // 显示一些仅美国有的分类
    if (count($us_only_categories) > 0) {
        echo "\n仅美国市场的分类示例 (前3个):\n";
        $count = 0;
        foreach ($us_categories as $category) {
            if ($count >= 3) break;
            if (in_array($category['categoryId'], $us_only_categories)) {
                echo "  - " . $category['categoryId'] . ": " . $category['categoryName'] . "\n";
                $count++;
            }
        }
    }
    
    // 显示一些仅加拿大有的分类
    if (count($ca_only_categories) > 0) {
        echo "\n仅加拿大市场的分类示例 (前3个):\n";
        $count = 0;
        foreach ($ca_categories as $category) {
            if ($count >= 3) break;
            if (in_array($category['categoryId'], $ca_only_categories)) {
                echo "  - " . $category['categoryId'] . ": " . $category['categoryName'] . "\n";
                $count++;
            }
        }
    }
}

// 恢复原始配置
update_option('woo_walmart_client_id', $original_client_id);
update_option('woo_walmart_client_secret', $original_client_secret);
update_option('woo_walmart_business_unit', $original_business_unit);

echo "\n=== 测试完成 ===\n";
echo "原始配置已恢复\n";
?>
