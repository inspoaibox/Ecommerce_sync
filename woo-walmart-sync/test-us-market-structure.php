<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 美国市场分类和属性结构分析 ===\n\n";

$api_auth = new Woo_Walmart_API_Key_Auth();

echo "=== 1. 获取美国市场分类列表 ===\n";
$categories_response = $api_auth->make_request('/v3/items/taxonomy?version=5.0', 'GET');

if ($categories_response && isset($categories_response['payload'])) {
    $categories = $categories_response['payload'];
    echo "✅ 分类获取成功，共 " . count($categories) . " 个分类\n\n";
    
    // 分析分类结构
    $category_levels = [];
    $category_names = [];
    
    foreach ($categories as $category) {
        $level = isset($category['level']) ? $category['level'] : 0;
        if (!isset($category_levels[$level])) {
            $category_levels[$level] = 0;
        }
        $category_levels[$level]++;
        
        $category_names[] = [
            'id' => $category['categoryId'],
            'name' => $category['categoryName'],
            'level' => $level,
            'parent' => isset($category['parentId']) ? $category['parentId'] : null
        ];
    }
    
    echo "分类层级分布:\n";
    ksort($category_levels);
    foreach ($category_levels as $level => $count) {
        echo "  层级 {$level}: {$count} 个分类\n";
    }
    
    echo "\n顶级分类示例 (前10个):\n";
    $top_level_count = 0;
    foreach ($category_names as $cat) {
        if ($cat['level'] == 0 && $top_level_count < 10) {
            echo "  - {$cat['id']}: {$cat['name']}\n";
            $top_level_count++;
        }
    }
    
    // 选择一个常见的分类来测试属性
    $test_category_id = null;
    $test_category_name = '';
    
    // 寻找一个常见的分类，比如服装相关的
    foreach ($category_names as $cat) {
        if (stripos($cat['name'], 'clothing') !== false || 
            stripos($cat['name'], 'apparel') !== false ||
            stripos($cat['name'], 'shirt') !== false) {
            $test_category_id = $cat['id'];
            $test_category_name = $cat['name'];
            break;
        }
    }
    
    // 如果没找到服装类，就用第一个有子分类的
    if (!$test_category_id) {
        foreach ($category_names as $cat) {
            if ($cat['level'] > 0) {
                $test_category_id = $cat['id'];
                $test_category_name = $cat['name'];
                break;
            }
        }
    }
    
    if ($test_category_id) {
        echo "\n=== 2. 测试分类属性获取 ===\n";
        echo "测试分类: {$test_category_id} - {$test_category_name}\n";
        
        // 获取分类属性
        $attributes_response = $api_auth->make_request("/v3/items/taxonomy/{$test_category_id}?version=5.0", 'GET');
        
        if ($attributes_response && isset($attributes_response['payload'])) {
            $attributes = $attributes_response['payload'];
            echo "✅ 属性获取成功\n";
            
            if (isset($attributes['attributes'])) {
                echo "属性数量: " . count($attributes['attributes']) . "\n\n";
                
                echo "属性详情 (前5个):\n";
                $attr_count = 0;
                foreach ($attributes['attributes'] as $attr) {
                    if ($attr_count >= 5) break;
                    
                    echo "  属性名: " . $attr['name'] . "\n";
                    echo "    类型: " . (isset($attr['type']) ? $attr['type'] : 'N/A') . "\n";
                    echo "    必需: " . (isset($attr['required']) ? ($attr['required'] ? '是' : '否') : 'N/A') . "\n";
                    
                    if (isset($attr['allowedValues']) && is_array($attr['allowedValues']) && count($attr['allowedValues']) > 0) {
                        $values_preview = array_slice($attr['allowedValues'], 0, 3);
                        echo "    枚举值: " . implode(', ', $values_preview);
                        if (count($attr['allowedValues']) > 3) {
                            echo " (共" . count($attr['allowedValues']) . "个)";
                        }
                        echo "\n";
                    }
                    echo "\n";
                    $attr_count++;
                }
                
                // 分析属性类型分布
                $attr_types = [];
                $required_count = 0;
                $enum_count = 0;
                
                foreach ($attributes['attributes'] as $attr) {
                    $type = isset($attr['type']) ? $attr['type'] : 'unknown';
                    if (!isset($attr_types[$type])) {
                        $attr_types[$type] = 0;
                    }
                    $attr_types[$type]++;
                    
                    if (isset($attr['required']) && $attr['required']) {
                        $required_count++;
                    }
                    
                    if (isset($attr['allowedValues']) && is_array($attr['allowedValues']) && count($attr['allowedValues']) > 0) {
                        $enum_count++;
                    }
                }
                
                echo "属性统计:\n";
                echo "  必需属性: {$required_count} 个\n";
                echo "  有枚举值的属性: {$enum_count} 个\n";
                echo "  属性类型分布:\n";
                foreach ($attr_types as $type => $count) {
                    echo "    {$type}: {$count} 个\n";
                }
            }
        } else {
            echo "❌ 属性获取失败\n";
            if ($attributes_response) {
                echo "错误信息: " . json_encode($attributes_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
    
} else {
    echo "❌ 分类获取失败\n";
    if ($categories_response) {
        echo "错误信息: " . json_encode($categories_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\n=== 3. API端点测试 ===\n";

// 测试不同的API端点
$endpoints_to_test = [
    '/v3/items/taxonomy?version=5.0' => '分类列表',
    '/v3/feeds?feedType=MP_ITEM' => 'Feed端点',
    '/v3/inventory' => '库存端点'
];

foreach ($endpoints_to_test as $endpoint => $description) {
    echo "测试 {$description} ({$endpoint}):\n";
    
    if (strpos($endpoint, 'inventory') !== false) {
        // 库存端点需要GET请求
        $response = $api_auth->make_request($endpoint, 'GET');
    } elseif (strpos($endpoint, 'feeds') !== false) {
        // Feed端点测试连通性，不发送实际数据
        echo "  (跳过Feed端点测试，避免发送空数据)\n";
        continue;
    } else {
        $response = $api_auth->make_request($endpoint, 'GET');
    }
    
    if ($response) {
        if (isset($response['error'])) {
            echo "  ❌ 错误: " . $response['error'] . "\n";
        } else {
            echo "  ✅ 连接成功\n";
        }
    } else {
        echo "  ❌ 连接失败\n";
    }
}

echo "\n=== 测试完成 ===\n";
?>
