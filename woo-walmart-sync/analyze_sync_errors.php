<?php
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/test.localhost/wp-load.php';

echo "=== 分析同步失败原因 ===\n\n";

// 根据错误信息分析问题
$errors = [
    [
        'field' => 'sportsLeague',
        'code' => 'IB_DATA_TYPE',
        'description' => "The 'sportsLeague' value is invalid. Enter a 'JSONArray' data type.",
        'current_type' => 'string',
        'required_type' => 'JSONArray'
    ],
    [
        'field' => 'suggested_number_of_people_for_assembly',
        'code' => 'IB_DATA_TYPE', 
        'description' => "The 'suggested_number_of_people_for_assembly' value is invalid. Enter a 'Number' data type.",
        'current_type' => 'string',
        'required_type' => 'Number'
    ],
    [
        'field' => 'businessUnit',
        'code' => 'IB_MISSING_ATTRIBUTE',
        'description' => "`businessUnit` is a required attribute. Enter value for the attribute `businessUnit`.",
        'current_type' => 'missing',
        'required_type' => 'string'
    ],
    [
        'field' => 'fulfillmentCenterID',
        'code' => 'IB_ATTRIBUTE_MINLENGTH',
        'description' => "'fulfillmentCenterID' is a required field with a minimum length of '1' characters. Enter a 'fulfillmentCenterID.'",
        'current_type' => 'empty_string',
        'required_type' => 'non_empty_string'
    ],
    [
        'field' => 'externalProductIdentifier',
        'code' => 'IB_ARRAY_MINITEMS',
        'description' => "'externalProductIdentifier' requires '1' entries. Enter the minimum number of fields.",
        'current_type' => 'empty_array',
        'required_type' => 'array_with_items'
    ],
    [
        'field' => 'inventoryAvailabilityDate',
        'code' => 'IB_DATE',
        'description' => "'' is not a valid format for the `inventoryAvailabilityDate' field. Enter a valid value in the format YYYY-MM-DD.",
        'current_type' => 'empty_string',
        'required_type' => 'date_YYYY-MM-DD'
    ],
    [
        'field' => 'stateRestrictions',
        'code' => 'IB_ARRAY_MINITEMS',
        'description' => "'stateRestrictions' requires '1' entries. Enter the minimum number of fields.",
        'current_type' => 'empty_array',
        'required_type' => 'array_with_items'
    ],
    [
        'field' => 'manufacturer',
        'code' => 'IB_ATTRIBUTE_MINLENGTH',
        'description' => "'manufacturer' is a required field with a minimum length of '1' characters. Enter a 'manufacturer.'",
        'current_type' => 'empty_string',
        'required_type' => 'non_empty_string'
    ],
    [
        'field' => 'productLine',
        'code' => 'IB_DATA_TYPE',
        'description' => "The 'productLine' value is invalid. Enter a 'JSONArray' data type.",
        'current_type' => 'string',
        'required_type' => 'JSONArray'
    ],
    [
        'field' => 'pieceCount',
        'code' => 'IB_DATA_TYPE',
        'description' => "The 'pieceCount' value is invalid. Enter a 'Number' data type.",
        'current_type' => 'string',
        'required_type' => 'Number'
    ]
];

echo "发现的错误字段数量: " . count($errors) . "\n\n";

// 按错误类型分组
$error_types = [];
foreach ($errors as $error) {
    $error_types[$error['code']][] = $error;
}

echo "=== 按错误类型分组分析 ===\n\n";

foreach ($error_types as $code => $fields) {
    echo "【{$code}】 - " . count($fields) . " 个字段\n";
    
    switch ($code) {
        case 'IB_DATA_TYPE':
            echo "问题：数据类型错误\n";
            echo "解决方案：修改字段的数据类型\n";
            break;
        case 'IB_MISSING_ATTRIBUTE':
            echo "问题：缺少必需属性\n";
            echo "解决方案：添加缺失的字段\n";
            break;
        case 'IB_ATTRIBUTE_MINLENGTH':
            echo "问题：字段长度不足\n";
            echo "解决方案：提供非空值\n";
            break;
        case 'IB_ARRAY_MINITEMS':
            echo "问题：数组项目数量不足\n";
            echo "解决方案：提供至少一个数组项\n";
            break;
        case 'IB_DATE':
            echo "问题：日期格式错误\n";
            echo "解决方案：使用YYYY-MM-DD格式\n";
            break;
    }
    
    foreach ($fields as $field) {
        echo "  - {$field['field']}: {$field['current_type']} → {$field['required_type']}\n";
    }
    echo "\n";
}

echo "=== 具体修复建议 ===\n\n";

echo "1. 【数据类型修复】\n";
echo "   - sportsLeague: \"\" → [] (空数组)\n";
echo "   - productLine: \"Bed frame series\" → [\"Bed frame series\"] (字符串数组)\n";
echo "   - suggested_number_of_people_for_assembly: \"2\" → 2 (整数)\n";
echo "   - pieceCount: \"1\" → 1 (整数)\n\n";

echo "2. 【缺失字段修复】\n";
echo "   - businessUnit: 添加到MPItemFeedHeader中\n";
echo "   - manufacturer: 提供制造商名称\n\n";

echo "3. 【空值修复】\n";
echo "   - fulfillmentCenterID: \"\" → \"DEFAULT\" 或具体的履行中心ID\n";
echo "   - inventoryAvailabilityDate: \"\" → \"2025-08-03\" 或删除该字段\n\n";

echo "4. 【数组修复】\n";
echo "   - externalProductIdentifier: [] → [{\"productIdType\":\"GTIN\",\"productId\":\"123456789012\"}]\n";
echo "   - stateRestrictions: [] → [\"None\"] 或删除该字段\n\n";

// 检查当前的产品映射器设置
echo "=== 检查当前设置 ===\n\n";

$business_unit = get_option('woo_walmart_business_unit', '');
echo "当前businessUnit设置: " . ($business_unit ?: '未设置') . "\n";

if (empty($business_unit)) {
    echo "❌ businessUnit未设置，这是导致错误的主要原因之一\n";
    echo "建议设置为: WALMART_US\n\n";
} else {
    echo "✅ businessUnit已设置\n\n";
}

// 查看最近的映射数据
global $wpdb;
$logs_table = $wpdb->prefix . 'woo_walmart_sync_logs';

$recent_mapping = $wpdb->get_row("
    SELECT request FROM $logs_table 
    WHERE action = '产品映射-最终数据结构'
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($recent_mapping) {
    $mapping_data = json_decode($recent_mapping->request, true);
    
    echo "=== 最近映射数据检查 ===\n\n";
    
    // 检查MPItemFeedHeader
    if (isset($mapping_data['MPItemFeedHeader'])) {
        $header = $mapping_data['MPItemFeedHeader'];
        echo "【MPItemFeedHeader】\n";
        echo "  - businessUnit: " . (isset($header['businessUnit']) ? $header['businessUnit'] : '缺失') . "\n";
        echo "  - locale: " . (isset($header['locale']) ? $header['locale'] : '缺失') . "\n";
        echo "  - version: " . (isset($header['version']) ? $header['version'] : '缺失') . "\n\n";
    }
    
    // 检查第一个商品的问题字段
    if (isset($mapping_data['MPItem'][0])) {
        $item = $mapping_data['MPItem'][0];
        
        echo "【Orderable部分问题字段】\n";
        $orderable = $item['Orderable'] ?? [];
        
        echo "  - fulfillmentCenterID: " . (isset($orderable['fulfillmentCenterID']) ? "'{$orderable['fulfillmentCenterID']}'" : '缺失') . "\n";
        echo "  - externalProductIdentifier: " . (isset($orderable['externalProductIdentifier']) ? json_encode($orderable['externalProductIdentifier']) : '缺失') . "\n";
        echo "  - inventoryAvailabilityDate: " . (isset($orderable['inventoryAvailabilityDate']) ? "'{$orderable['inventoryAvailabilityDate']}'" : '缺失') . "\n";
        echo "  - stateRestrictions: " . (isset($orderable['stateRestrictions']) ? json_encode($orderable['stateRestrictions']) : '缺失') . "\n\n";
        
        echo "【Visible部分问题字段】\n";
        $visible = $item['Visible'] ?? [];
        $category_data = reset($visible); // 获取第一个分类的数据
        
        echo "  - manufacturer: " . (isset($category_data['manufacturer']) ? "'{$category_data['manufacturer']}'" : '缺失') . "\n";
        echo "  - sportsLeague: " . (isset($category_data['sportsLeague']) ? json_encode($category_data['sportsLeague']) : '缺失') . "\n";
        echo "  - productLine: " . (isset($category_data['productLine']) ? json_encode($category_data['productLine']) : '缺失') . "\n";
        echo "  - pieceCount: " . (isset($category_data['pieceCount']) ? "'{$category_data['pieceCount']}' (类型: " . gettype($category_data['pieceCount']) . ")" : '缺失') . "\n";
        echo "  - suggested_number_of_people_for_assembly: " . (isset($category_data['suggested_number_of_people_for_assembly']) ? "'{$category_data['suggested_number_of_people_for_assembly']}' (类型: " . gettype($category_data['suggested_number_of_people_for_assembly']) . ")" : '缺失') . "\n";
    }
}

echo "\n=== 总结 ===\n";
echo "主要问题：\n";
echo "1. ❌ businessUnit字段缺失（在MPItemFeedHeader中）\n";
echo "2. ❌ 多个字段数据类型错误（字符串应为数字或数组）\n";
echo "3. ❌ 必需字段为空值\n";
echo "4. ❌ 数组字段为空但要求至少一个项目\n\n";

echo "修复优先级：\n";
echo "1. 🔥 立即修复businessUnit缺失问题\n";
echo "2. 🔥 修复数据类型错误\n";
echo "3. 🔧 处理空值字段\n";
echo "4. 🔧 优化数组字段\n";
?>
