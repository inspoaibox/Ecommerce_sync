<?php
// 直接连接数据库进行分析
$host = 'localhost';
$dbname = '11_1_aboen_com';  // 从你之前的信息中获取
$username = 'root';  // 根据你的环境调整
$password = '';      // 根据你的环境调整

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== SKU对比分析 ===\n\n";
    
    $success_skus = ['LT000682AAK', 'B2741S00491', 'N7090004012A'];
    $failed_skus = ['B2726S00512', 'B2741S00266'];
    
    function analyze_sku($pdo, $sku, $status) {
        echo "📦 SKU: $sku ($status)\n";
        
        // 获取产品基本信息
        $stmt = $pdo->prepare("
            SELECT p.ID, p.post_title, p.post_status, p.post_type
            FROM wp_posts p
            JOIN wp_postmeta pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_sku' AND pm.meta_value = ?
        ");
        $stmt->execute([$sku]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo "❌ 产品未找到\n\n";
            return;
        }
        
        echo "产品ID: {$product['ID']}\n";
        echo "产品名称: {$product['post_title']}\n";
        echo "产品状态: {$product['post_status']}\n";
        
        // 获取产品分类
        $stmt = $pdo->prepare("
            SELECT t.name
            FROM wp_terms t
            JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
            JOIN wp_term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tr.object_id = ? AND tt.taxonomy = 'product_cat'
        ");
        $stmt->execute([$product['ID']]);
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "WC分类: " . implode(', ', $categories) . "\n";
        
        // 获取沃尔玛分类映射
        if (!empty($categories)) {
            $stmt = $pdo->prepare("
                SELECT wcm.walmart_category_path
                FROM wp_walmart_category_map wcm
                JOIN wp_term_taxonomy tt ON wcm.wc_category_id = tt.term_taxonomy_id
                JOIN wp_terms t ON tt.term_id = t.term_id
                WHERE t.name = ?
                LIMIT 1
            ");
            $stmt->execute([$categories[0]]);
            $walmart_category = $stmt->fetchColumn();
            echo "沃尔玛分类: " . ($walmart_category ?: '未映射') . "\n";
        }
        
        // 获取产品元数据
        $stmt = $pdo->prepare("
            SELECT meta_key, meta_value
            FROM wp_postmeta
            WHERE post_id = ? AND meta_key IN (
                '_weight', '_length', '_width', '_height',
                '_manage_stock', '_stock_status', '_stock',
                'electronicsIndicator', 'batteryTechnologyType',
                'chemicalAerosolPesticide', 'MustShipAlone', 'ShippingWeight'
            )
        ");
        $stmt->execute([$product['ID']]);
        $meta_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        echo "重量: " . ($meta_data['_weight'] ?? '未设置') . "\n";
        echo "尺寸: " . ($meta_data['_length'] ?? '0') . " x " . ($meta_data['_width'] ?? '0') . " x " . ($meta_data['_height'] ?? '0') . "\n";
        echo "库存管理: " . ($meta_data['_manage_stock'] ?? 'no') . "\n";
        echo "库存状态: " . ($meta_data['_stock_status'] ?? '未设置') . "\n";
        echo "库存数量: " . ($meta_data['_stock'] ?? '未设置') . "\n";
        
        echo "关键属性:\n";
        $key_attrs = ['electronicsIndicator', 'batteryTechnologyType', 'chemicalAerosolPesticide', 'MustShipAlone', 'ShippingWeight'];
        foreach ($key_attrs as $attr) {
            if (isset($meta_data[$attr])) {
                echo "  $attr: {$meta_data[$attr]}\n";
            }
        }
        
        echo "\n" . str_repeat('-', 80) . "\n\n";
    }
    
    echo "🟢 成功的产品:\n";
    foreach ($success_skus as $sku) {
        analyze_sku($pdo, $sku, '成功');
    }
    
    echo "🔴 失败的产品:\n";
    foreach ($failed_skus as $sku) {
        analyze_sku($pdo, $sku, '失败');
    }
    
    // 检查系统配置
    echo "=== 系统配置检查 ===\n";
    $stmt = $pdo->prepare("SELECT option_name, option_value FROM wp_options WHERE option_name LIKE '%fulfillment%'");
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($options as $name => $value) {
        echo "$name: " . ($value ?: '未设置') . "\n";
    }
    
} catch (PDOException $e) {
    echo "数据库连接错误: " . $e->getMessage() . "\n";
    echo "请检查数据库连接参数\n";
}
?>
