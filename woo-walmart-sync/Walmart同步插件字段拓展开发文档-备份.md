# 🔧 Walmart同步插件字段拓展开发文档

## 🚨 **强制性开发规则**

### **规则1：通用属性优先原则 - 必须严格遵守**
**🔥 后续所有的字段只添加到通用属性 (`v5_common_attributes`) 中。禁止添加到其他特定属性，除非特别说明。这是强制性规则，必须严格遵守！**

#### **严格执行标准：**
- ✅ **正确做法**: 将新字段添加到 `v5_common_attributes` 数组
- ❌ **错误做法**: 将新字段添加到家具、服装、电子等特定类目配置中
- ❌ **错误做法**: 自作主张判断字段适用范围

#### **违规后果：**
- 导致字段只在特定类目中可用，限制了使用范围
- 增加维护复杂度和配置冗余
- 不符合沃尔玛API的通用性要求
- **直接违反开发规则，必须立即修正**

---

## 📋 概述

本文档详细说明如何在Walmart同步插件中添加和拓展新的产品属性字段，包括前端配置、后端实现、数据映射和同步逻辑的完整流程。

## 🎯 字段类型说明

### 1. 四种映射类型

| 映射类型 | 说明 | 使用场景 | 示例 |
|---------|------|----------|------|
| `auto_generate` | 系统自动生成 | 需要从产品数据计算或提取的字段 | 产品名称、品牌、尺寸 |
| `wc_attribute` | WooCommerce属性 | 从产品属性中获取值 | 颜色、材质、尺寸 |
| `default_value` | 固定默认值 | 使用预设的固定值 | 保修文本、组装说明 |
| `walmart_field` | Walmart枚举值 | 使用Walmart规范的枚举选项 | 条件、警告标识 |

### 2. 字段分组

- **Orderable**: 订购相关字段（价格、库存、标识符等）
- **Visible**: 展示相关字段（名称、描述、图片、规格等）

## 🚀 完整开发流程

### 步骤1: 前端JavaScript配置

#### 1.1 添加字段到预定义数组

**文件位置**: `woo-walmart-sync.php` (JavaScript部分)

**🚨 重要警告：前端配置决定字段类型显示**

前端的 `autoGenerateFields` 数组决定了字段在界面中显示为"自动生成"还是"沃尔玛字段"。如果字段不在此数组中，即使后端配置为 `auto_generate`，前端仍会显示为"沃尔玛字段"。

**⚠️ 必须同时修改两个位置的 autoGenerateFields 数组！**

```javascript
// 🔥 关键：必须在两个位置都添加字段到 autoGenerateFields 数组
// 位置1: 重置属性功能中 (约第9124行)
// 位置2: 智能加载功能中 (约第9426行)

var autoGenerateFields = [
    'productName', 'brand', 'shortDescription', 'keyFeatures',
    'mainImageUrl', 'material', 'color', 'assembledProductWeight',
    'has_storage', 'has_trundle', 'homeDecorStyle',  // 智能识别字段

    // 🆕 添加新的自动生成字段
    'productDimensions',        // 产品尺寸
    'packageWeight',           // 包装重量
    'energyEfficiencyRating'   // 能效等级
];

// Walmart字段预定义值
var walmartFields = {
    'isProp65WarningRequired': 'No',
    'condition': 'New',
    'has_written_warranty': 'Yes - Warranty Text',
    'isAssemblyRequired': 'Yes',  // 用户可选择的字段

    // 🆕 添加新的Walmart字段默认值
    'batteryRequired': 'No',
    'ageGroup': 'Adult',
    'safetyWarning': 'No Warning Applicable'

    // ❌ 不要在这里添加自动生成字段！
    // 'has_storage': 'No',     // 错误：自动生成字段不应在此
    // 'has_trundle': 'No',     // 错误：自动生成字段不应在此
};

// 默认值字段
var defaultValueFields = {
    'warrantyText': 'This warranty does not cover damages caused by misuse...',
    'assemblyInstructions': 'Assembly is effortless with our clear instructions...',
    'countPerPack': '1',

    // 🆕 添加新的默认值字段
    'careInstructions': 'Wipe clean with damp cloth. Do not use harsh chemicals.',
    'storageInstructions': 'Store in a cool, dry place away from direct sunlight.',
    'usageInstructions': 'Please read all instructions before use.'
};
```

#### 1.2 添加字段说明

```javascript
// 获取自动生成规则的函数
function getAutoGenerationRule(attributeName) {
    var rules = {
        // 现有规则
        'productName': '使用产品标题',
        'brand': '使用产品品牌，无品牌时使用"Unbranded"',
        'shortDescription': '使用产品完整描述',
        
        // 🆕 添加新字段的详细说明
        'productDimensions': '从产品属性Product Size提取完整尺寸信息，格式：长x宽x高',
        'packageWeight': '优先使用产品属性Package Weight，否则使用产品重量+包装估重',
        'energyEfficiencyRating': '从产品属性Energy Rating获取，无则默认为Not Applicable',
        'careInstructions': '从产品属性Care Instructions获取，无则使用默认护理说明',
        'batteryRequired': '根据产品分类和属性自动判断是否需要电池',
        'ageGroup': '根据产品分类自动判断适用年龄组，默认为Adult'
    };
    
    return rules[attributeName] || '自动生成';
}
```

#### 1.3 添加枚举选项配置

```javascript
// 加载沃尔玛字段选项的函数中添加新字段
function loadWalmartFieldOptions(selectElement, attributeName, currentValue, enumValues) {
    // ... 现有代码
    
    var walmartFieldOptions = {
        // 现有选项
        'isProp65WarningRequired': ['Yes', 'No'],
        'condition': ['New', 'Open Box'],
        
        // 🆕 添加新字段的枚举选项
        'batteryRequired': ['Yes', 'No'],
        'ageGroup': ['Adult', 'Teen', 'Child', 'Infant', 'Toddler'],
        'energyEfficiencyRating': ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'Not Applicable'],
        'safetyWarning': [
            'No Warning Applicable',
            'Choking Hazard - Small Parts',
            'Not suitable for children under 3 years',
            'Adult supervision required'
        ]
    };
    
    // ... 其余代码保持不变
}
```

### 步骤2: 后端PHP实现

#### 2.1 添加自动生成逻辑

**文件位置**: `includes/class-product-mapper.php`

```php
/**
 * 生成特殊属性值
 */
private function generate_special_attribute_value($attribute_name, $product, $fulfillment_lag_time) {
    switch (strtolower($attribute_name)) {
        // 现有case
        case 'productname':
            return $product->get_name();
        case 'brand':
            return $product->get_attribute('brand') ?: 'Unbranded';
            
        // 🆕 添加新字段的生成逻辑
        case 'productdimensions':
            return $this->generate_product_dimensions($product);
            
        case 'packageweight':
            return $this->generate_package_weight($product);
            
        case 'energyefficiencyrating':
            return $this->generate_energy_rating($product);
            
        case 'batteryrequired':
            return $this->determine_battery_requirement($product);
            
        case 'agegroup':
            return $this->determine_age_group($product);
            
        default:
            // 尝试从morenzhi.php获取
            if (function_exists('handle_auto_generate_field')) {
                $value = handle_auto_generate_field($product, $attribute_name);
                if ($value !== null) return $value;
            }
            return null;
    }
}

/**
 * 🆕 生成产品尺寸
 */
private function generate_product_dimensions($product) {
    // 尝试从产品属性获取
    $dimensions = $product->get_attribute('Product Size');
    if (!empty($dimensions)) {
        return $dimensions;
    }
    
    // 尝试从WooCommerce内置尺寸获取
    $length = $product->get_length();
    $width = $product->get_width();
    $height = $product->get_height();
    
    if ($length && $width && $height) {
        return "{$length} x {$width} x {$height} inches";
    }
    
    return 'Dimensions not specified';
}

/**
 * 🆕 生成包装重量
 */
private function generate_package_weight($product) {
    // 优先使用包装重量属性
    $package_weight = $product->get_attribute('Package Weight');
    if (!empty($package_weight)) {
        // 确保包含单位
        return preg_match('/\d+(\.\d+)?\s*(lb|kg|oz)/', $package_weight) ? 
               $package_weight : $package_weight . ' lb';
    }
    
    // 使用产品重量 + 估算包装重量
    $product_weight = $product->get_weight();
    if ($product_weight) {
        $estimated_package_weight = floatval($product_weight) * 1.2; // 增加20%包装重量
        return number_format($estimated_package_weight, 2) . ' lb';
    }
    
    return '1 lb'; // 默认重量
}

/**
 * 🆕 生成能效等级
 */
private function generate_energy_rating($product) {
    $energy_rating = $product->get_attribute('Energy Rating');
    if (!empty($energy_rating)) {
        return $energy_rating;
    }
    
    // 根据产品分类判断是否需要能效等级
    $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    $category_names = implode(' ', $categories);
    
    $energy_categories = ['appliance', 'electronic', 'lighting', 'hvac'];
    foreach ($energy_categories as $cat) {
        if (stripos($category_names, $cat) !== false) {
            return 'A'; // 默认A级能效
        }
    }
    
    return 'Not Applicable';
}

/**
 * 🆕 判断是否需要电池
 */
private function determine_battery_requirement($product) {
    // 检查产品属性
    $battery_attr = $product->get_attribute('Battery Required');
    if (!empty($battery_attr)) {
        return strtolower($battery_attr) === 'yes' ? 'Yes' : 'No';
    }
    
    // 根据产品名称和描述判断
    $name = strtolower($product->get_name());
    $description = strtolower($product->get_description());
    $content = $name . ' ' . $description;
    
    $battery_keywords = ['battery', 'rechargeable', 'cordless', 'wireless', 'remote', 'electronic'];
    foreach ($battery_keywords as $keyword) {
        if (strpos($content, $keyword) !== false) {
            return 'Yes';
        }
    }
    
    return 'No';
}

/**
 * 🆕 判断适用年龄组
 */
private function determine_age_group($product) {
    // 检查产品属性
    $age_group = $product->get_attribute('Age Group');
    if (!empty($age_group)) {
        return $age_group;
    }
    
    // 根据产品分类判断
    $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    $category_names = strtolower(implode(' ', $categories));
    
    if (strpos($category_names, 'baby') !== false || strpos($category_names, 'infant') !== false) {
        return 'Infant';
    } elseif (strpos($category_names, 'toddler') !== false) {
        return 'Toddler';
    } elseif (strpos($category_names, 'child') !== false || strpos($category_names, 'kid') !== false) {
        return 'Child';
    } elseif (strpos($category_names, 'teen') !== false) {
        return 'Teen';
    }
    
    return 'Adult'; // 默认成人
}
```

#### 2.2 添加数据类型转换

```php
/**
 * 转换字段数据类型
 */
private function convert_field_data_type($field_name, $value, $format_override = null) {
    switch (strtolower($field_name)) {
        // 现有转换逻辑
        case 'price':
            return (float) $value;
        case 'productidentifiers':
            return is_array($value) ? $value : [$value];
            
        // 🆕 添加新字段的数据类型转换
        case 'packageweight':
        case 'assembledproductweight':
            // 确保重量值为数字格式
            $numeric_value = preg_replace('/[^\d.]/', '', $value);
            return $numeric_value ? (float) $numeric_value : 1.0;
            
        case 'energyefficiencyrating':
            // 确保能效等级格式正确
            $valid_ratings = ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'Not Applicable'];
            return in_array($value, $valid_ratings) ? $value : 'Not Applicable';
            
        case 'batteryrequired':
        case 'agegroup':
            // 确保枚举值有效
            return is_string($value) ? $value : 'No';
            
        default:
            return $value;
    }
}
```

#### 2.3 配置字段分组

```php
/**
 * 在map方法中配置字段分组
 */
public function map($product, $walmart_category_name, $upc, $attribute_rules, $fulfillment_lag_time) {
    // ... 现有代码
    
    // 定义Orderable字段（订购相关）
    $orderable_fields = [
        'externalProductIdentifier', 'productIdentifiers', 'price', 'stateRestrictions',
        'fulfillmentLagTime', 'shippingWeight',
        
        // 🆕 添加新的Orderable字段
        'packageWeight'  // 包装重量影响运费计算
    ];
    
    // 其他字段默认归入Visible分组
    // 包括：productDimensions, energyEfficiencyRating, batteryRequired, ageGroup 等
    
    // ... 字段分组应用逻辑保持不变
}
```

### 步骤3: 测试和验证

#### 3.1 创建测试脚本

```php
<?php
/**
 * 测试新字段功能
 */
require_once '../../../wp-load.php';

// 测试产品ID
$test_product_id = 12345;
$product = wc_get_product($test_product_id);

if (!$product) {
    echo "产品不存在\n";
    exit;
}

echo "=== 测试新字段生成 ===\n";
echo "产品: {$product->get_name()}\n\n";

// 测试映射器
require_once 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 使用反射测试私有方法
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('generate_special_attribute_value');
$method->setAccessible(true);

// 测试新字段
$test_fields = [
    'productDimensions',
    'packageWeight', 
    'energyEfficiencyRating',
    'batteryRequired',
    'ageGroup'
];

foreach ($test_fields as $field) {
    try {
        $value = $method->invoke($mapper, $field, $product, 1);
        echo "✅ {$field}: {$value}\n";
    } catch (Exception $e) {
        echo "❌ {$field}: 生成失败 - {$e->getMessage()}\n";
    }
}
?>
```

## 📚 实际应用示例

### 示例1: 添加产品保修期字段

```javascript
// 前端配置
var autoGenerateFields = [..., 'warrantyPeriod'];

function getAutoGenerationRule(attributeName) {
    var rules = {
        // ...
        'warrantyPeriod': '从产品属性Warranty Period获取，默认为1年'
    };
    return rules[attributeName] || '自动生成';
}
```

```php
// 后端实现
case 'warrantyperiod':
    $warranty = $product->get_attribute('Warranty Period');
    if (!empty($warranty)) {
        return $warranty;
    }
    
    // 根据产品价格判断保修期
    $price = $product->get_price();
    if ($price > 500) {
        return '2 Years';
    } elseif ($price > 100) {
        return '1 Year';
    } else {
        return '90 Days';
    }
```

### 示例2: 添加环保认证字段

```javascript
// 前端配置
var walmartFields = {
    // ...
    'ecoFriendly': 'Yes'
};

var walmartFieldOptions = {
    // ...
    'ecoFriendly': ['Yes', 'No', 'Partially']
};
```

```php
// 后端实现
case 'ecofriendly':
    $eco_cert = $product->get_attribute('Eco Certification');
    if (!empty($eco_cert)) {
        return $eco_cert;
    }
    
    // 检查产品标签或分类
    $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']);
    $eco_tags = ['eco-friendly', 'sustainable', 'recyclable', 'organic'];
    
    foreach ($tags as $tag) {
        if (in_array(strtolower($tag), $eco_tags)) {
            return 'Yes';
        }
    }
    
    return 'No';
```

## ⚠️ 注意事项

1. **字段命名**: 使用驼峰命名法，与Walmart API规范保持一致
2. **数据验证**: 确保生成的值符合Walmart的数据格式要求
3. **性能考虑**: 避免在生成函数中进行复杂的数据库查询
4. **错误处理**: 为每个生成函数添加适当的错误处理和默认值
5. **测试覆盖**: 为新字段创建完整的测试用例

## 🔄 部署流程

1. **开发环境测试**: 在开发环境中测试所有新字段
2. **数据备份**: 部署前备份现有的分类映射配置
3. **分步部署**: 先部署后端逻辑，再部署前端配置
4. **功能验证**: 部署后验证重置属性和同步功能
5. **监控日志**: 监控同步日志确保新字段正常工作

## 🛠️ 高级功能拓展

### 条件字段生成

某些字段可能需要根据产品分类或其他条件动态生成：

```php
/**
 * 条件字段生成示例
 */
private function generate_conditional_field($product, $field_name) {
    $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    $category_names = strtolower(implode(' ', $categories));

    switch ($field_name) {
        case 'safetyWarning':
            if (strpos($category_names, 'toy') !== false) {
                return 'Not suitable for children under 3 years';
            } elseif (strpos($category_names, 'electronic') !== false) {
                return 'Adult supervision required';
            }
            return 'No Warning Applicable';

        case 'assemblyRequired':
            if (strpos($category_names, 'furniture') !== false) {
                return 'Yes';
            }
            return 'No';
    }
}
```

### 多语言支持

为字段说明添加多语言支持：

```javascript
function getAutoGenerationRule(attributeName, language = 'zh') {
    var rules = {
        'zh': {
            'productName': '使用产品标题',
            'brand': '使用产品品牌，无品牌时使用"Unbranded"'
        },
        'en': {
            'productName': 'Use product title',
            'brand': 'Use product brand, default to "Unbranded" if empty'
        }
    };

    return rules[language][attributeName] || (language === 'zh' ? '自动生成' : 'Auto Generate');
}
```

### 字段依赖关系

某些字段可能依赖于其他字段的值：

```php
/**
 * 处理字段依赖关系
 */
private function handle_field_dependencies($product, $field_name, $existing_values) {
    switch ($field_name) {
        case 'shippingWeight':
            // 依赖于packageWeight
            if (isset($existing_values['packageWeight'])) {
                $package_weight = floatval($existing_values['packageWeight']);
                return max($package_weight, 1.0); // 运输重量不能小于包装重量
            }
            break;

        case 'batteryWarning':
            // 依赖于batteryRequired
            if (isset($existing_values['batteryRequired']) && $existing_values['batteryRequired'] === 'Yes') {
                return 'Batteries not included';
            }
            return 'No battery warning applicable';
    }

    return null;
}
```

## 📊 数据验证和质量控制

### 字段值验证

```php
/**
 * 验证字段值是否符合Walmart规范
 */
private function validate_field_value($field_name, $value) {
    $validation_rules = [
        'price' => [
            'type' => 'float',
            'min' => 0.01,
            'max' => 999999.99
        ],
        'productName' => [
            'type' => 'string',
            'min_length' => 1,
            'max_length' => 200,
            'required' => true
        ],
        'energyEfficiencyRating' => [
            'type' => 'enum',
            'values' => ['A+++', 'A++', 'A+', 'A', 'B', 'C', 'D', 'Not Applicable']
        ]
    ];

    if (!isset($validation_rules[$field_name])) {
        return true; // 没有验证规则的字段默认通过
    }

    $rule = $validation_rules[$field_name];

    // 必填字段检查
    if (isset($rule['required']) && $rule['required'] && empty($value)) {
        return false;
    }

    // 类型检查
    switch ($rule['type']) {
        case 'float':
            if (!is_numeric($value)) return false;
            $value = floatval($value);
            if (isset($rule['min']) && $value < $rule['min']) return false;
            if (isset($rule['max']) && $value > $rule['max']) return false;
            break;

        case 'string':
            if (!is_string($value)) return false;
            $length = strlen($value);
            if (isset($rule['min_length']) && $length < $rule['min_length']) return false;
            if (isset($rule['max_length']) && $length > $rule['max_length']) return false;
            break;

        case 'enum':
            if (!in_array($value, $rule['values'])) return false;
            break;
    }

    return true;
}
```

### 数据质量报告

```php
/**
 * 生成数据质量报告
 */
public function generate_quality_report($product_ids) {
    $report = [
        'total_products' => count($product_ids),
        'field_coverage' => [],
        'validation_errors' => [],
        'missing_attributes' => []
    ];

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) continue;

        // 检查字段覆盖率
        $required_fields = ['productName', 'brand', 'price', 'shortDescription'];
        foreach ($required_fields as $field) {
            $value = $this->generate_special_attribute_value($field, $product, 1);
            if (empty($value)) {
                $report['missing_attributes'][] = [
                    'product_id' => $product_id,
                    'field' => $field,
                    'product_name' => $product->get_name()
                ];
            } else {
                $report['field_coverage'][$field] = ($report['field_coverage'][$field] ?? 0) + 1;
            }
        }
    }

    return $report;
}
```

## 🧪 测试文件创建指南

### 测试文件创建注意事项

#### 1. 路径配置要点
- **使用绝对路径**：避免相对路径导致的加载问题
- **Windows路径处理**：使用双反斜杠 `\\` 或正斜杠 `/`
- **路径验证**：在加载前检查文件是否存在

#### 2. 输出处理策略
- **双重输出**：同时输出到控制台和文件
- **错误报告**：启用完整的错误报告
- **结果保存**：将测试结果保存到文件便于查看

#### 3. WordPress环境加载
- **正确的加载顺序**：先加载wp-config.php，再加载wp-load.php
- **常量定义**：确保WOO_WALMART_SYNC_PATH等常量正确定义
- **类加载检查**：验证所需类是否成功加载

### 完整测试文件模板

```php
<?php
/**
 * 字段测试模板
 * 用途：测试新字段的完整功能
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 输出管理
$output_file = 'field-test-results.txt';
$output = '';

function log_output($message) {
    global $output;
    $output .= $message . "\n";
    echo $message . "\n";
}

log_output("=== 字段功能测试 ===");
log_output("PHP版本: " . phpversion());
log_output("测试时间: " . date('Y-m-d H:i:s'));

// WordPress环境加载
$wp_path = 'D:\\phpstudy_pro\\WWW\\test.localhost';
log_output("WordPress路径: {$wp_path}");

if (!file_exists($wp_path . '\\wp-config.php')) {
    log_output("❌ wp-config.php 不存在");
    file_put_contents($output_file, $output);
    exit;
}

log_output("加载WordPress...");
require_once $wp_path . '\\wp-config.php';
require_once $wp_path . '\\wp-load.php';
log_output("✅ WordPress加载成功");

// 插件环境设置
$plugin_path = 'D:\\phpstudy_pro\\WWW\\test.localhost\\wp-content\\plugins\\woo-walmart-sync';
if (!defined('WOO_WALMART_SYNC_PATH')) {
    define('WOO_WALMART_SYNC_PATH', $plugin_path . '\\');
}

$mapper_file = $plugin_path . '\\includes\\class-product-mapper.php';
if (!file_exists($mapper_file)) {
    log_output("❌ 映射器文件不存在: {$mapper_file}");
    file_put_contents($output_file, $output);
    exit;
}

require_once $mapper_file;
if (!class_exists('Woo_Walmart_Product_Mapper')) {
    log_output("❌ 映射器类不存在");
    file_put_contents($output_file, $output);
    exit;
}

log_output("✅ 映射器类加载成功");

// 1. 测试数据类型转换
log_output("\n1. 测试数据类型转换:");

$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('convert_field_data_type');
$method->setAccessible(true);

// 测试用例
$test_cases = [
    [
        'name' => '正常值测试',
        'value' => 'test_value_here'
    ],
    [
        'name' => '空值测试',
        'value' => ''
    ],
    [
        'name' => 'null值测试',
        'value' => null
    ],
    [
        'name' => '数组测试',
        'value' => ['item1', 'item2', 'item3']
    ]
];

$field_name = 'your_field_name'; // 替换为要测试的字段名

foreach ($test_cases as $i => $test_case) {
    log_output("\n测试用例 " . ($i + 1) . ": {$test_case['name']}");
    log_output("输入值: " . (is_null($test_case['value']) ? 'null' :
              (is_array($test_case['value']) ? json_encode($test_case['value']) : "'{$test_case['value']}'")));

    try {
        $result = $method->invoke($mapper, $field_name, $test_case['value']);
        log_output("输出值: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        log_output("输出类型: " . gettype($result));

        // 验证结果
        if (is_array($result)) {
            log_output("✅ 转换为数组格式");
            log_output("数组元素数量: " . count($result));
        } elseif (is_string($result)) {
            log_output("✅ 转换为字符串格式");
            log_output("字符串长度: " . strlen($result));
        } else {
            log_output("✅ 保持原始类型");
        }
    } catch (Exception $e) {
        log_output("❌ 转换失败: " . $e->getMessage());
    }
}

// 2. 测试分类映射配置
log_output("\n2. 测试分类映射配置:");

global $wpdb;
$map_table = $wpdb->prefix . 'walmart_category_map';

$mappings = $wpdb->get_results("SELECT * FROM $map_table WHERE walmart_attributes LIKE '%{$field_name}%' LIMIT 3");

if (empty($mappings)) {
    log_output("⚠️ 没有找到包含{$field_name}字段的分类映射");
} else {
    log_output("✅ 找到 " . count($mappings) . " 个包含{$field_name}字段的分类映射");

    foreach ($mappings as $i => $mapping) {
        log_output("\n映射 " . ($i + 1) . ": {$mapping->walmart_category_path}");

        $attribute_rules = json_decode($mapping->walmart_attributes, true);

        if (is_array($attribute_rules) && isset($attribute_rules['name'])) {
            $field_index = array_search($field_name, $attribute_rules['name']);

            if ($field_index !== false) {
                $mapping_type = $attribute_rules['type'][$field_index] ?? 'N/A';
                $source_value = $attribute_rules['source'][$field_index] ?? 'N/A';

                log_output("  映射类型: {$mapping_type}");
                log_output("  来源值: " . substr($source_value, 0, 100) . (strlen($source_value) > 100 ? '...' : ''));

                // 验证配置
                $expected_type = 'default_value'; // 根据实际需求修改
                if ($mapping_type === $expected_type) {
                    log_output("  ✅ 映射类型正确");
                } else {
                    log_output("  ⚠️ 映射类型为 {$mapping_type}，期望 {$expected_type}");
                }
            }
        }
    }
}

// 3. 测试产品映射流程
log_output("\n3. 测试产品映射流程:");

$test_products = get_posts([
    'post_type' => 'product',
    'post_status' => 'publish',
    'numberposts' => 1
]);

if (!empty($test_products) && !empty($mappings)) {
    $test_product = wc_get_product($test_products[0]->ID);
    $test_mapping = $mappings[0];
    $attribute_rules = json_decode($test_mapping->walmart_attributes, true);

    log_output("测试产品: {$test_product->get_name()}");
    log_output("使用映射: {$test_mapping->walmart_category_path}");

    try {
        $walmart_data = $mapper->map(
            $test_product,
            $test_mapping->walmart_category_path,
            '123456789012',
            $attribute_rules,
            1
        );

        log_output("✅ 产品映射成功");

        // 检查字段是否存在于API数据中
        $visible = $walmart_data['MPItem'][0]['Visible'][$test_mapping->walmart_category_path] ?? [];
        $orderable = $walmart_data['MPItem'][0]['Orderable'] ?? [];

        if (isset($visible[$field_name])) {
            log_output("✅ 在Visible中找到{$field_name}字段");
            log_output("字段值: " . json_encode($visible[$field_name], JSON_UNESCAPED_UNICODE));
        } elseif (isset($orderable[$field_name])) {
            log_output("✅ 在Orderable中找到{$field_name}字段");
            log_output("字段值: " . json_encode($orderable[$field_name], JSON_UNESCAPED_UNICODE));
        } else {
            log_output("⚠️ 在API数据中未找到{$field_name}字段");
        }

    } catch (Exception $e) {
        log_output("❌ 产品映射失败: " . $e->getMessage());
    }
}

// 保存结果
log_output("\n=== 测试完成 ===");
file_put_contents($output_file, $output);
log_output("测试结果已保存到: {$output_file}");
?>
```

### 快速测试脚本模板

```php
<?php
/**
 * 快速字段测试脚本
 */

// 基础设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// WordPress加载
require_once 'D:\\phpstudy_pro\\WWW\\test.localhost\\wp-config.php';
require_once 'D:\\phpstudy_pro\\WWW\\test.localhost\\wp-load.php';

// 插件加载
define('WOO_WALMART_SYNC_PATH', 'D:\\phpstudy_pro\\WWW\\test.localhost\\wp-content\\plugins\\woo-walmart-sync\\');
require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';

echo "=== 快速字段测试 ===\n";

// 测试字段转换
$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('convert_field_data_type');
$method->setAccessible(true);

$field_name = 'your_field_name'; // 替换为实际字段名
$test_value = 'your_test_value'; // 替换为测试值

try {
    $result = $method->invoke($mapper, $field_name, $test_value);
    echo "字段: {$field_name}\n";
    echo "输入: {$test_value}\n";
    echo "输出: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    echo "类型: " . gettype($result) . "\n";
    echo "✅ 测试成功\n";
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
}
?>
```

### 测试最佳实践

#### 1. 测试覆盖范围
- **边界值测试**：空值、null、极长字符串
- **类型转换测试**：字符串、数组、数字等不同类型
- **分隔符测试**：不同分隔符的处理
- **编码测试**：中文字符、特殊字符的处理

#### 2. 错误处理验证
- **异常捕获**：确保所有异常都被正确处理
- **错误日志**：记录详细的错误信息和堆栈跟踪
- **回退机制**：验证默认值和回退逻辑

#### 3. 性能测试
- **批量测试**：测试大量数据的处理性能
- **内存使用**：监控内存使用情况
- **执行时间**：测量关键操作的执行时间

#### 4. 集成测试
- **完整流程**：从前端配置到API数据生成的完整流程
- **多产品测试**：不同类型产品的兼容性
- **多分类测试**：不同Walmart分类的适配性

## 🔧 调试和故障排除

### 调试工具

```php
/**
 * 字段生成调试工具
 */
public function debug_field_generation($product_id, $field_name) {
    $product = wc_get_product($product_id);
    if (!$product) {
        return ['error' => 'Product not found'];
    }

    $debug_info = [
        'product_id' => $product_id,
        'product_name' => $product->get_name(),
        'field_name' => $field_name,
        'generation_method' => null,
        'raw_value' => null,
        'processed_value' => null,
        'validation_result' => null,
        'attributes_checked' => [],
        'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names'])
    ];

    try {
        // 记录生成过程
        $raw_value = $this->generate_special_attribute_value($field_name, $product, 1);
        $debug_info['raw_value'] = $raw_value;

        $processed_value = $this->convert_field_data_type($field_name, $raw_value);
        $debug_info['processed_value'] = $processed_value;

        $validation_result = $this->validate_field_value($field_name, $processed_value);
        $debug_info['validation_result'] = $validation_result;

        // 记录检查的属性
        $all_attributes = $product->get_attributes();
        foreach ($all_attributes as $attr_name => $attr) {
            $debug_info['attributes_checked'][$attr_name] = $product->get_attribute($attr_name);
        }

    } catch (Exception $e) {
        $debug_info['error'] = $e->getMessage();
        $debug_info['trace'] = $e->getTraceAsString();
    }

    return $debug_info;
}
```

### 常见问题解决

| 问题 | 原因 | 解决方案 |
|------|------|----------|
| 字段值为空 | 产品缺少必要属性 | 添加默认值逻辑或提示用户补充属性 |
| 数据类型错误 | 类型转换失败 | 检查convert_field_data_type方法 |
| 枚举值无效 | 值不在允许列表中 | 更新枚举值列表或添加映射逻辑 |
| 性能问题 | 生成逻辑过于复杂 | 优化算法或添加缓存机制 |

## 📈 性能优化建议

### 缓存机制

```php
/**
 * 字段值缓存
 */
private function get_cached_field_value($product_id, $field_name) {
    $cache_key = "walmart_field_{$product_id}_{$field_name}";
    $cached_value = wp_cache_get($cache_key, 'walmart_sync');

    if ($cached_value !== false) {
        return $cached_value;
    }

    // 生成新值
    $product = wc_get_product($product_id);
    $value = $this->generate_special_attribute_value($field_name, $product, 1);

    // 缓存1小时
    wp_cache_set($cache_key, $value, 'walmart_sync', 3600);

    return $value;
}
```

### 批量处理优化

```php
/**
 * 批量字段生成优化
 */
public function batch_generate_fields($product_ids, $field_names) {
    // 预加载所有产品数据
    $products = [];
    foreach ($product_ids as $product_id) {
        $products[$product_id] = wc_get_product($product_id);
    }

    // 批量生成字段值
    $results = [];
    foreach ($products as $product_id => $product) {
        if (!$product) continue;

        $results[$product_id] = [];
        foreach ($field_names as $field_name) {
            $results[$product_id][$field_name] = $this->generate_special_attribute_value(
                $field_name, $product, 1
            );
        }
    }

    return $results;
}
```

## 📋 检查清单

在添加新字段时，请确保完成以下检查：

### 🔧 开发阶段
- [ ] 前端JavaScript配置已更新
- [ ] 字段说明已添加到getAutoGenerationRule函数
- [ ] 后端生成逻辑已实现
- [ ] 数据类型转换已配置
- [ ] 字段分组已正确设置
- [ ] 验证规则已定义
- [ ] 错误处理已完善

### 🧪 测试阶段
- [ ] 创建了完整的测试文件
- [ ] 测试了数据类型转换功能
- [ ] 测试了边界值和异常情况
- [ ] 验证了分类映射配置
- [ ] 测试了完整的产品映射流程
- [ ] 验证了API数据格式正确性
- [ ] 测试了多种输入格式的兼容性
- [ ] 检查了字段在API数据中的位置（Visible/Orderable）

### 📊 验证阶段
- [ ] 在开发环境中测试通过
- [ ] 使用真实产品数据测试
- [ ] 验证生成的API数据符合Walmart规范
- [ ] 检查字段值的商业合理性
- [ ] 确认字段不会导致API提交失败

### 📚 文档阶段
- [ ] 更新了字段拓展开发文档
- [ ] 添加了字段说明和使用示例
- [ ] 记录了测试用例和预期结果
- [ ] 更新了快速参考指南
- [ ] 文档化了任何特殊配置要求

### 🚀 部署阶段
- [ ] 代码审查已完成
- [ ] 备份了现有配置
- [ ] 在测试环境中验证
- [ ] 性能影响已评估
- [ ] 部署计划已制定
- [ ] 回滚方案已准备

## 📝 实际测试案例：occasion字段优化

### 案例背景
将`occasion`字段从`auto_generate`类型改为`default_value`类型，使用预设的美国节日列表作为默认值。

### 实施步骤

#### 1. 前端配置修改
```javascript
// 从autoGenerateFields中移除occasion
var autoGenerateFields = [
    'productName', 'brand', 'shortDescription', 'keyFeatures',
    // 'occasion', // 移除这一行
    'productLine', 'swatchImages'
];

// 添加到defaultValueFields
var defaultValueFields = {
    'warrantyText': 'This warranty does not cover damages...',
    // 添加occasion字段
    'occasion': 'Labor Day;Memorial Day;Independence Day;Black Friday;Cyber Monday;Christmas;New Year;Presidents\' Day;Thanksgiving'
};

// 更新字段说明
function getAutoGenerationRule(attributeName) {
    var rules = {
        // 更新说明
        'occasion': '使用预设的节日场合列表：Labor Day;Memorial Day;Independence Day;Black Friday;Cyber Monday;Christmas;New Year;Presidents\' Day;Thanksgiving'
    };
    return rules[attributeName] || '自动生成';
}
```

#### 2. 后端数据处理
```php
// 在convert_field_data_type方法中添加特殊处理
switch (strtolower($field_name)) {
    case 'occasion':
        // 将分号分隔的字符串转换为数组
        if (is_string($value) && !empty($value)) {
            $occasion_array = preg_split('/[;,|]/', $value);
            return array_map('trim', array_filter($occasion_array));
        } elseif (is_array($value)) {
            return array_filter($value);
        }
        return [];
}

// 注释掉generate_special_attribute_value中的旧逻辑
// case 'occasion':
//     // 旧的自动生成逻辑已不再需要
```

#### 3. 测试验证
```php
// 测试数据转换
$test_value = 'Labor Day;Memorial Day;Independence Day;Black Friday;Cyber Monday;Christmas;New Year;Presidents\' Day;Thanksgiving';
$result = $mapper->convert_field_data_type('occasion', $test_value);

// 预期结果：
// ["Labor Day","Memorial Day","Independence Day","Black Friday","Cyber Monday","Christmas","New Year","Presidents' Day","Thanksgiving"]

// 验证结果
assert(is_array($result));
assert(count($result) === 9);
assert(in_array('Christmas', $result));
assert(in_array('Black Friday', $result));
```

#### 4. 测试结果
- ✅ **数据转换正常**：分号分隔字符串正确转换为9个元素的数组
- ✅ **多分隔符支持**：支持分号、逗号、竖线等分隔符
- ✅ **API格式符合**：输出格式完全符合Walmart API要求
- ✅ **配置应用成功**：在分类映射页面重置属性后正确应用新配置

---

## 🚨 **常见错误与避免方法**

### **错误1：前端字段类型配置不一致**

#### **错误现象**
- 后端配置为 `auto_generate`，但前端显示为"沃尔玛字段"
- 用户可以手动选择值，与智能识别功能冲突
- 重置属性后字段类型显示错误

#### **错误原因**
前端的 `autoGenerateFields` 数组决定字段类型显示，如果字段不在此数组中，即使后端配置为 `auto_generate`，前端仍显示为"沃尔玛字段"。

#### **错误示例**
```javascript
// ❌ 错误：只修改了后端配置
// 后端配置
[
    'attributeName' => 'has_storage',
    'defaultType' => 'auto_generate',  // 配置为自动生成
    // ...
]

// 前端配置（错误：未添加到autoGenerateFields）
var autoGenerateFields = [
    'productName', 'brand', 'shortDescription'
    // 缺少 'has_storage'  ❌
];

var walmartFields = {
    'has_storage': 'No'  // ❌ 错误：自动生成字段不应在此
};
```

#### **正确做法**
```javascript
// ✅ 正确：前后端配置一致
// 1. 添加到autoGenerateFields数组（两个位置都要添加！）
var autoGenerateFields = [
    'productName', 'brand', 'shortDescription',
    'has_storage', 'has_trundle', 'homeDecorStyle'  // ✅ 添加自动生成字段
];

// 2. 从walmartFields中移除
var walmartFields = {
    'isProp65WarningRequired': 'No',
    'condition': 'New'
    // ✅ 不包含自动生成字段
};

// 3. 后端配置保持auto_generate
[
    'attributeName' => 'has_storage',
    'defaultType' => 'auto_generate',  // ✅ 一致
    // ...
]
```

#### **检查清单**
- [ ] 字段已添加到两个 `autoGenerateFields` 数组中
- [ ] 字段已从 `walmartFields` 对象中移除
- [ ] 后端配置为 `auto_generate`
- [ ] 已实现对应的智能识别函数

---

## 🚫 **严禁的错误做法**

### **错误示例1：添加到特定类目**
```php
// ❌ 绝对禁止这样做
if (strpos($category_lower, 'furniture') !== false) {
    $furniture_attrs = [
        ['attributeName' => '新字段', ...]  // 错误！
    ];
}

if (strpos($category_lower, 'electronics') !== false) {
    $electronics_attrs = [
        ['attributeName' => '新字段', ...]  // 错误！
    ];
}
```

### **错误示例2：自作主张判断适用性**
```php
// ❌ 不要自己判断字段适用范围
// "这个字段看起来只适用于家具类目" - 错误思维
// "电子产品才需要这个字段" - 错误思维
```

### **正确示例：添加到通用属性**
```php
// ✅ 正确做法 - 添加到通用属性
$v5_common_attributes = [
    // ... 其他字段
    [
        'attributeName' => '新字段',
        'isrequired' => false,
        'description' => '字段描述',
        'defaultType' => 'walmart_field',
        'enumValues' => ['Yes', 'No'],
        'requiredLevel' => 'required'
    ]
];
```

---

## 📚 **历史教训记录**

### **错误案例：has_storage 和 has_trundle**
- **错误**: 最初添加到家具特定配置中
- **问题**: 限制了字段的使用范围，其他类目无法使用
- **修正**: 移动到通用属性配置中
- **教训**: 所有字段都应该是通用的，除非明确指定

### **正确案例：gender 字段**
- **正确**: 添加到通用属性中
- **效果**: 所有类目都可以使用性别分类
- **优势**: 提高了字段的通用性和实用性

---

## 🔧 **开发检查清单**

### **添加新字段前必须确认：**
- [ ] 字段已添加到 `v5_common_attributes` 数组
- [ ] 字段未添加到任何特定类目配置中
- [ ] **前端配置已正确设置**：
  - [ ] 自动生成字段已添加到两个 `autoGenerateFields` 数组中
  - [ ] 自动生成字段已从 `walmartFields` 对象中移除
  - [ ] 沃尔玛字段已添加到 `walmartFields` 对象中
  - [ ] 沃尔玛字段已添加到 `walmartFieldOptions` 数组中
- [ ] 字段说明已添加到所有相关位置
- [ ] 后端处理逻辑已实现（如需要）
- [ ] 测试脚本已创建并通过

### **代码审查要点：**
- [ ] 确认没有使用 `if (strpos($category_lower, '类目名'))` 来限制字段
- [ ] 确认字段在所有类目中都可用
- [ ] **确认前后端配置一致性**：
  - [ ] 自动生成字段：后端 `auto_generate` + 前端 `autoGenerateFields` 包含
  - [ ] 沃尔玛字段：后端 `walmart_field` + 前端 `walmartFields` 包含
  - [ ] 默认值字段：后端 `default_value` + 前端 `defaultValueFields` 包含
- [ ] 确认字段类型在界面中显示正确

---

### 经验总结

#### 成功要点
1. **路径配置正确**：使用绝对路径避免加载问题
2. **双重输出策略**：同时输出到控制台和文件
3. **完整测试覆盖**：从数据转换到API生成的全流程测试
4. **边界情况处理**：测试空值、null值等特殊情况

#### 常见问题
1. **终端输出问题**：Windows环境下PHP命令行输出可能不显示，使用文件输出解决
2. **路径分隔符**：Windows下需要使用双反斜杠或正斜杠
3. **WordPress加载**：确保正确的加载顺序和路径

#### 最佳实践
1. **先简单后复杂**：从基础功能测试开始，逐步增加复杂度
2. **保存测试结果**：将测试输出保存到文件便于分析
3. **完整性验证**：不仅测试成功情况，也要测试失败情况
4. **实际数据测试**：使用真实的产品和分类数据进行测试

通过遵循这个完整的文档和实际案例，您可以系统性地添加和拓展新的产品属性字段，确保整个流程的完整性、一致性和可维护性。

---

## 🚨 **最终重要提醒**

### **记住核心原则：**
**🔥 所有字段都是通用的，适用于所有类目，除非明确指定！**

### **强制性规则再次确认：**
1. **所有新字段只添加到 `v5_common_attributes`**
2. **禁止添加到特定类目配置中**
3. **不要自作主张判断字段适用范围**
4. **违反规则必须立即修正**

### **开发者承诺：**
- [ ] 我已阅读并理解所有强制性规则
- [ ] 我承诺严格遵守通用属性优先原则
- [ ] 我不会将新字段添加到特定类目配置中
- [ ] 我会确保前后端配置一致性
- [ ] 我会在每次开发前检查此文档

---

## 🚑 **快速故障排除指南**

### **问题：重置属性后字段类型显示错误**

#### **症状**
- 后端配置为 `auto_generate`，但前端显示为"沃尔玛字段"
- 用户可以手动选择值，与智能识别功能冲突

#### **诊断步骤**
1. 检查前端 `autoGenerateFields` 数组是否包含该字段
2. 检查前端 `walmartFields` 对象是否包含该字段（不应该包含）
3. 确认是否修改了两个位置的 `autoGenerateFields` 数组

#### **解决方案**
```javascript
// 1. 添加到autoGenerateFields数组（两个位置）
var autoGenerateFields = [
    // ... 其他字段
    'your_field_name'  // ✅ 添加字段
];

// 2. 从walmartFields中移除
var walmartFields = {
    // ... 其他字段
    // 'your_field_name': 'default_value'  // ❌ 移除此行
};
```

### **问题：字段只在特定类目中显示**

#### **症状**
- 字段只在某些产品类目中可用
- 其他类目重置属性后没有该字段

#### **解决方案**
- 检查字段是否添加到了 `v5_common_attributes` 而不是特定类目配置
- 将字段从特定类目配置移动到通用属性配置

### **问题：智能识别不工作**

#### **症状**
- 字段显示为"自动生成"但值不正确
- 智能识别函数没有被调用

#### **解决方案**
- 检查后端处理逻辑中是否添加了对应的 case
- 确认智能识别函数名称正确
- 验证函数逻辑是否正确实现

---

*最后更新时间: 2025-09-02*
*版本: v2.1*
*重要性: 🚨 强制性规则，必须严格遵守*
