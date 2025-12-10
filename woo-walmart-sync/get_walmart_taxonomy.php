<?php
// 临时脚本：获取沃尔玛分类法
require_once '../../../wp-config.php';
require_once '../../../wp-load.php';
require_once 'includes/class-api-key-auth.php';

// 创建API认证实例
$api_auth = new Woo_Walmart_API_Key_Auth();

// 调用分类法API
echo "正在获取沃尔玛分类法...\n";

// 获取新版本的产品类型分类法 (使用官方支持的4.0版本)
$result = $api_auth->make_request('/v3/items/taxonomy?version=4.0');

if (is_wp_error($result)) {
    echo "API错误: " . $result->get_error_message() . "\n";
    exit;
}

if (empty($result)) {
    echo "API返回空结果\n";
    exit;
}

echo "API调用成功！\n";
echo "结果结构:\n";
print_r(array_keys($result));

// 详细查看payload结构
if (isset($result['payload'])) {
    echo "\nPayload类型: " . gettype($result['payload']) . "\n";
    if (is_array($result['payload'])) {
        echo "Payload数组长度: " . count($result['payload']) . "\n";
        if (count($result['payload']) > 0) {
            echo "第一个元素结构:\n";
            print_r(array_keys($result['payload'][0]));
            echo "第一个元素内容:\n";
            print_r($result['payload'][0]);
        }
    } else {
        echo "Payload内容:\n";
        print_r($result['payload']);
    }
}

// 保存分类数据到数据库
if (isset($result['payload']) && is_array($result['payload'])) {
    echo "\n保存分类数据到数据库...\n";

    global $wpdb;
    $table_name = $wpdb->prefix . 'woo_walmart_categories';

    // 清空旧数据
    $wpdb->query("TRUNCATE TABLE $table_name");

    $saved_count = 0;

    foreach ($result['payload'] as $category) {
        if (isset($category['categoryName'])) {
            // 构建分类路径
            $category_path = $category['categoryName'];

            // 保存主分类
            $wpdb->insert(
                $table_name,
                array(
                    'category_id' => $category['categoryId'] ?? '',
                    'category_name' => $category['categoryName'],
                    'category_path' => $category_path,
                    'parent_id' => null,
                    'level' => 1,
                    'updated_at' => current_time('mysql')
                )
            );
            $saved_count++;

            // 版本4.0的API响应只包含基本分类信息，没有嵌套的产品类型组
            // 这是简化的分类结构
        }
    }

    echo "成功保存 $saved_count 个分类到数据库\n";

    // 查找Sofas & Couches分类
    echo "\n查找Sofas & Couches分类...\n";
    $sofa_category = $wpdb->get_row("
        SELECT * FROM $table_name
        WHERE category_name = 'Sofas & Couches'
    ", ARRAY_A);

    if ($sofa_category) {
        echo "✅ 找到 Sofas & Couches 分类:\n";
        echo "  - ID: {$sofa_category['category_id']}\n";
        echo "  - 路径: {$sofa_category['category_path']}\n";
        echo "  - 级别: {$sofa_category['level']}\n";
    } else {
        echo "❌ 未找到 Sofas & Couches 分类\n";
    }
} else {
    echo "未找到payload数据\n";
    echo "完整结果:\n";
    print_r($result);
}

echo "\n完成！\n";
?>
