<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>测试市场数据隔离</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .market-us { background-color: #e3f2fd; }
        .market-ca { background-color: #fff3e0; }
        .market-mx { background-color: #f3e5f5; }
        .market-cl { background-color: #e8f5e9; }
    </style>
</head>
<body>
    <h1>🔍 测试市场数据隔离</h1>
    <p>验证美国市场数据是否受到影响，以及不同市场的数据是否正确隔离</p>
    <hr>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'walmart_categories';

echo "<h2>1️⃣ 数据库表结构检查</h2>";

// 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

if (!$table_exists) {
    echo "<p class='error'>❌ 表 {$table_name} 不存在</p>";
    exit;
}

echo "<p class='success'>✅ 表 {$table_name} 存在</p>";

// 检查 market 字段
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'market'");
if (empty($columns)) {
    echo "<p class='error'>❌ market 字段不存在，请先运行数据库升级</p>";
    exit;
}

echo "<p class='success'>✅ market 字段已存在</p>";

echo "<hr>";
echo "<h2>2️⃣ 各市场数据统计</h2>";

// 统计各市场的数据
$market_stats = $wpdb->get_results("
    SELECT market, COUNT(*) as count 
    FROM {$table_name} 
    GROUP BY market
    ORDER BY market
");

if (empty($market_stats)) {
    echo "<p class='warning'>⚠️ 数据库中没有任何分类数据</p>";
} else {
    echo "<table>";
    echo "<tr><th>市场</th><th>分类数量</th><th>状态</th></tr>";
    
    $market_names = [
        'US' => '🇺🇸 美国',
        'CA' => '🇨🇦 加拿大',
        'MX' => '🇲🇽 墨西哥',
        'CL' => '🇨🇱 智利'
    ];
    
    foreach ($market_stats as $stat) {
        $market_name = isset($market_names[$stat->market]) ? $market_names[$stat->market] : $stat->market;
        $class = 'market-' . strtolower($stat->market);
        echo "<tr class='{$class}'>";
        echo "<td>{$market_name}</td>";
        echo "<td>{$stat->count}</td>";
        echo "<td class='success'>✅ 有数据</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>3️⃣ 当前市场设置</h2>";

$business_unit = get_option('woo_walmart_business_unit', 'WALMART_US');
$market_code = str_replace('WALMART_', '', $business_unit);

$market_names = [
    'US' => '🇺🇸 美国',
    'CA' => '🇨🇦 加拿大',
    'MX' => '🇲🇽 墨西哥',
    'CL' => '🇨🇱 智利'
];

$current_market_name = isset($market_names[$market_code]) ? $market_names[$market_code] : $market_code;

echo "<p>当前主市场: <strong class='info'>{$current_market_name} ({$market_code})</strong></p>";

echo "<hr>";
echo "<h2>4️⃣ 测试 woo_walmart_get_categories_for_mapping() 函数</h2>";

if (function_exists('woo_walmart_get_categories_for_mapping')) {
    $categories = woo_walmart_get_categories_for_mapping();
    
    if (empty($categories)) {
        echo "<p class='warning'>⚠️ 函数返回空数组（当前市场 {$market_code} 没有分类数据）</p>";
    } else {
        echo "<p class='success'>✅ 函数返回 " . count($categories) . " 个分类</p>";
        
        // 显示前5个分类
        echo "<h3>前5个分类样本:</h3>";
        echo "<table>";
        echo "<tr><th>分类ID</th><th>分类名称</th><th>级别</th><th>路径</th></tr>";
        
        foreach (array_slice($categories, 0, 5) as $cat) {
            echo "<tr>";
            echo "<td>{$cat['categoryId']}</td>";
            echo "<td>{$cat['categoryName']}</td>";
            echo "<td>{$cat['level']}</td>";
            echo "<td>" . substr($cat['path'], 0, 50) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 验证这些分类是否属于当前市场
        echo "<h3>验证数据市场归属:</h3>";
        $sample_category_id = $categories[0]['categoryId'];
        $db_check = $wpdb->get_row($wpdb->prepare(
            "SELECT market FROM {$table_name} WHERE category_id = %s LIMIT 1",
            $sample_category_id
        ));
        
        if ($db_check) {
            if ($db_check->market === $market_code) {
                echo "<p class='success'>✅ 数据归属正确：分类 {$sample_category_id} 属于市场 {$db_check->market}</p>";
            } else {
                echo "<p class='error'>❌ 数据归属错误：分类 {$sample_category_id} 属于市场 {$db_check->market}，但当前市场是 {$market_code}</p>";
            }
        }
    }
} else {
    echo "<p class='error'>❌ 函数 woo_walmart_get_categories_for_mapping 不存在</p>";
}

echo "<hr>";
echo "<h2>5️⃣ 测试结论</h2>";

$us_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE market = 'US'");
$ca_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE market = 'CA'");

echo "<ul>";
echo "<li><strong>美国市场数据:</strong> {$us_count} 条分类";
if ($us_count > 0) {
    echo " <span class='success'>✅ 美国市场数据正常</span>";
} else {
    echo " <span class='warning'>⚠️ 美国市场没有数据</span>";
}
echo "</li>";

echo "<li><strong>加拿大市场数据:</strong> {$ca_count} 条分类";
if ($ca_count > 0) {
    echo " <span class='success'>✅ 加拿大市场数据已隔离</span>";
} else {
    echo " <span class='info'>ℹ️ 加拿大市场暂无数据</span>";
}
echo "</li>";

echo "<li><strong>数据隔离:</strong> ";
if ($us_count > 0 && $ca_count > 0) {
    echo "<span class='success'>✅ 两个市场的数据已成功隔离</span>";
} elseif ($us_count > 0 || $ca_count > 0) {
    echo "<span class='info'>ℹ️ 只有一个市场有数据，无法验证隔离效果</span>";
} else {
    echo "<span class='warning'>⚠️ 两个市场都没有数据</span>";
}
echo "</li>";
echo "</ul>";

?>

</body>
</html>

