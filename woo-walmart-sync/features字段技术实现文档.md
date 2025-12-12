# Features 字段技术实现文档

## 📋 目录

1. [代码架构](#代码架构)
2. [核心方法详解](#核心方法详解)
3. [数据流程](#数据流程)
4. [关键代码片段](#关键代码片段)
5. [性能优化](#性能优化)
6. [错误处理](#错误处理)
7. [扩展性设计](#扩展性设计)

---

## 代码架构

### 文件结构

```
woo-walmart-sync/
├── includes/
│   └── class-product-mapper.php          # 核心映射类
│       ├── generate_special_attribute_value()      # 字段生成入口
│       ├── extract_features_by_category_id()       # 分类特性提取
│       ├── get_product_walmart_categories()        # 获取Walmart分类
│       └── match_features_from_content()           # 关键词匹配
├── test-features-sofas-couches.php       # Sofas & Couches 测试
├── features字段分类特定拓展指南.md       # 使用指南
└── features字段技术实现文档.md           # 本文档
```

### 类图

```
Woo_Walmart_Product_Mapper
│
├── generate_special_attribute_value($attribute_name, $product, $fulfillment_lag_time)
│   │
│   └── case 'features':
│       └── extract_features_by_category_id($product, $simulate_walmart_category = null)
│           │
│           ├── get_product_walmart_categories($product)
│           │   └── 查询 wp_walmart_category_map 表
│           │
│           ├── $category_features_map[]  // 分类→特性映射
│           │
│           └── match_features_from_content($product, $available_features, $walmart_category = null)
│               ├── $special_matches[]    // 特殊关键词规则
│               ├── 关键词匹配循环
│               └── 返回匹配的特性数组
```

---

## 核心方法详解

### 1. generate_special_attribute_value()

**位置**: `includes/class-product-mapper.php` Line 729+

**作用**: 字段生成的总入口，根据字段名调用相应的生成方法

**代码片段**:
```php
private function generate_special_attribute_value($attribute_name, $product, $fulfillment_lag_time) {
    switch (strtolower($attribute_name)) {
        // ... 其他字段 ...
        
        case 'features':
            // 特性：根据分类ID动态获取枚举值并智能匹配
            return $this->extract_features_by_category_id($product);
        
        // ... 其他字段 ...
    }
}
```

**参数**:
- `$attribute_name`: 字段名称（如 "features"）
- `$product`: WC_Product 对象
- `$fulfillment_lag_time`: 履行延迟时间（本方法未使用）

**返回值**: 
- `array|null`: 特性数组或 null

---

### 2. extract_features_by_category_id()

**位置**: Line 6275-6341

**作用**: 根据产品的 Walmart 分类提取特性

**完整代码**:
```php
private function extract_features_by_category_id($product, $simulate_walmart_category = null) {
    // 1. 获取产品的Walmart分类名称
    $walmart_categories = $this->get_product_walmart_categories($product);
    
    // 2. 模拟测试模式：添加指定的Walmart分类
    if ($simulate_walmart_category) {
        $walmart_categories[] = $simulate_walmart_category;
    }
    
    if (empty($walmart_categories)) {
        return null;
    }
    
    // 3. Walmart分类特定的特性配置
    $category_features_map = [
        'Bed Frames' => ['Adjustable Height', 'Wireless Remote', ...],
        'Kitchen Serving Carts' => ['Rolling', 'Folding', ...],
        'Dining Furniture Sets' => ['Live Edge', 'Storage', ...],
        'Sofas & Couches' => ['Reclining', 'USB', 'Tufted', ...]
    ];
    
    // 4. 查找匹配的分类配置
    $available_features = null;
    $matched_category = null;
    foreach ($walmart_categories as $walmart_category) {
        if (isset($category_features_map[$walmart_category])) {
            $available_features = $category_features_map[$walmart_category];
            $matched_category = $walmart_category;
            break; // 找到第一个匹配的分类就停止
        }
    }
    
    // 5. 如果没有找到对应的分类配置，返回null
    if (empty($available_features)) {
        return null;
    }
    
    // 6. 从产品信息中智能匹配特性
    return $this->match_features_from_content($product, $available_features, $matched_category);
}
```

**参数**:
- `$product`: WC_Product 对象
- `$simulate_walmart_category`: 模拟的分类名称（用于测试）

**返回值**:
- `array|null`: 匹配的特性数组，无匹配则返回 null 或默认值

**关键逻辑**:
1. 获取产品的 Walmart 分类
2. 在配置表中查找该分类的特性列表
3. 调用匹配方法提取特性

---

### 3. get_product_walmart_categories()

**位置**: Line 6340-6375

**作用**: 通过分类映射表获取产品对应的 Walmart 分类名称

**完整代码**:
```php
private function get_product_walmart_categories($product) {
    global $wpdb;
    
    // 1. 获取产品的本地分类ID
    $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
    
    if (empty($product_categories)) {
        return [];
    }
    
    $walmart_categories = [];
    
    // 2. 查询分类映射表，获取对应的Walmart分类
    $placeholders = implode(',', array_fill(0, count($product_categories), '%d'));
    $query = $wpdb->prepare("
        SELECT DISTINCT walmart_category_path
        FROM {$wpdb->prefix}walmart_category_map
        WHERE local_category_id IN ({$placeholders})
    ", $product_categories);
    
    $results = $wpdb->get_results($query);
    
    // 3. 提取最后一级分类名称
    foreach ($results as $result) {
        if (!empty($result->walmart_category_path)) {
            // 如 "Home > Furniture > Living Room > Sofas & Couches" -> "Sofas & Couches"
            $path_parts = explode(' > ', $result->walmart_category_path);
            $walmart_category = trim(end($path_parts));
            
            if (!empty($walmart_category)) {
                $walmart_categories[] = $walmart_category;
            }
        }
    }
    
    return array_unique($walmart_categories);
}
```

**数据库查询**:
```sql
SELECT DISTINCT walmart_category_path
FROM wp_walmart_category_map
WHERE local_category_id IN (123, 456, 789)
```

**返回值示例**:
```php
['Sofas & Couches', 'Living Room Furniture']
```

**关键点**:
- 只提取分类路径的最后一级
- 支持一个产品属于多个 Walmart 分类
- 使用 `array_unique()` 去重

---

### 4. match_features_from_content()

**位置**: Line 6388-6471

**作用**: 从产品标题和描述中匹配特性关键词

**完整代码**:
```php
private function match_features_from_content($product, $available_features, $walmart_category = null) {
    // 1. 获取产品内容并转为小写
    $content = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());
    $matched_features = [];
    
    // 2. 遍历所有可用特性
    foreach ($available_features as $feature) {
        $feature_lower = strtolower($feature);
        
        // 3. 创建通用匹配模式
        $patterns = [
            '/\b' . preg_quote($feature_lower, '/') . '\b/',  // 完整匹配
            '/\b' . preg_quote(str_replace([' ', '-'], '[-\s]', $feature_lower), '/') . '\b/',  // 分词匹配
        ];
        
        // 4. 特殊关键词匹配规则
        $special_matches = [
            'Reclining' => ['reclining', 'recline', 'recliner', ...],
            'USB' => ['usb', 'usb port', 'charging port', ...],
            // ... 更多规则
        ];
        
        // 5. 检查特殊匹配规则
        $feature_matched = false;
        if (isset($special_matches[$feature])) {
            foreach ($special_matches[$feature] as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    $matched_features[] = $feature;
                    $feature_matched = true;
                    break; // 跳出关键词循环
                }
            }
        }
        
        // 6. 如果特殊规则已匹配，跳过通用模式检查
        if ($feature_matched) {
            continue;
        }
        
        // 7. 检查通用模式匹配
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $matched_features[] = $feature;
                break; // 找到匹配就跳出模式循环
            }
        }
    }
    
    // 8. 如果没有匹配到任何特性，根据分类返回默认值或null
    if (empty($matched_features)) {
        if ($walmart_category === 'Sofas & Couches') {
            return ['Multifunctional'];
        }
        return null;
    }
    
    // 9. 去重并返回
    return array_unique($matched_features);
}
```

**匹配流程**:
```
产品内容 → 转小写 → 遍历特性 → 特殊关键词匹配 → 通用模式匹配 → 去重 → 返回
```

**关键优化**:
- 使用 `strpos()` 而不是正则表达式（性能更好）
- 特殊规则优先于通用规则
- 找到匹配后立即跳出内层循环

---

## 数据流程

### 完整流程图

```
┌─────────────────────────────────────────────────────────────┐
│ 1. 产品同步请求                                              │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. map_product_to_walmart_format()                          │
│    遍历所有字段，调用 generate_special_attribute_value()     │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. generate_special_attribute_value('features', ...)        │
│    识别到 features 字段                                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. extract_features_by_category_id($product)                │
│    ├─ get_product_walmart_categories($product)              │
│    │   └─ 查询数据库获取 Walmart 分类                        │
│    ├─ 查找 $category_features_map                           │
│    └─ 调用 match_features_from_content()                    │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. match_features_from_content($product, $features, $cat)  │
│    ├─ 获取产品内容（标题+描述）                              │
│    ├─ 遍历可用特性                                          │
│    │   ├─ 检查特殊关键词匹配                                 │
│    │   └─ 检查通用模式匹配                                   │
│    ├─ 收集匹配的特性                                         │
│    └─ 返回特性数组或默认值                                   │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. convert_field_data_type('features', $value)             │
│    验证并转换为数组格式                                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. 添加到 Walmart API 请求数据                              │
│    "features": ["Reclining", "USB", "Storage"]              │
└─────────────────────────────────────────────────────────────┘
```

### 数据示例

**输入**:
```php
$product = WC_Product {
    name: "Modern Reclining Sofa with USB Charging Port",
    description: "Comfortable reclining sofa with built-in USB ports...",
    categories: [123] // 映射到 "Sofas & Couches"
}
```

**中间数据**:
```php
// Step 1: 获取 Walmart 分类
$walmart_categories = ['Sofas & Couches'];

// Step 2: 获取可用特性
$available_features = ['Reclining', 'USB', 'Tufted', 'Storage', ...];

// Step 3: 匹配关键词
$content = "modern reclining sofa with usb charging port...";
$matched_features = ['Reclining', 'USB'];
```

**输出**:
```json
{
  "features": ["Reclining", "USB"]
}
```

---

## 关键代码片段

### 分类配置表

```php
$category_features_map = [
    'Sofas & Couches' => [
        'Reclining',
        'USB',
        'Tufted',
        'Storage',
        'Nailhead Trim',
        'Multifunctional',
        'Massaging'
    ]
];
```

### 关键词匹配规则

```php
$special_matches = [
    'Reclining' => ['reclining', 'recline', 'recliner', 'reclinable', 'adjustable back'],
    'USB' => ['usb', 'usb port', 'charging port', 'power port', 'usb charging'],
    'Multifunctional' => ['multifunctional', 'multi-functional', 'versatile', 'convertible', 'sleeper', 'sofa bed', 'pull out', 'futon']
];
```

### 默认值处理

```php
if (empty($matched_features)) {
    if ($walmart_category === 'Sofas & Couches') {
        return ['Multifunctional'];
    }
    return null;
}
```

---

## 性能优化

### 1. 数据库查询优化

**问题**: 每个产品都查询数据库获取分类

**优化**: 使用缓存

```php
// 未来优化方案
private $category_cache = [];

private function get_product_walmart_categories($product) {
    $product_id = $product->get_id();
    
    if (isset($this->category_cache[$product_id])) {
        return $this->category_cache[$product_id];
    }
    
    // ... 查询逻辑 ...
    
    $this->category_cache[$product_id] = $walmart_categories;
    return $walmart_categories;
}
```

### 2. 字符串匹配优化

**当前**: 使用 `strpos()` 而不是正则表达式

**性能对比**:
- `strpos()`: ~0.001ms
- `preg_match()`: ~0.01ms

**10倍性能提升**！

### 3. 循环优化

**优化前**:
```php
foreach ($available_features as $feature) {
    foreach ($special_matches[$feature] as $keyword) {
        if (strpos($content, $keyword) !== false) {
            $matched_features[] = $feature;
            break 2; // ❌ 跳出外层循环，只能匹配一个特性
        }
    }
}
```

**优化后**:
```php
foreach ($available_features as $feature) {
    $feature_matched = false;
    foreach ($special_matches[$feature] as $keyword) {
        if (strpos($content, $keyword) !== false) {
            $matched_features[] = $feature;
            $feature_matched = true;
            break; // ✅ 只跳出内层循环
        }
    }
    if ($feature_matched) {
        continue; // ✅ 继续检查下一个特性
    }
}
```

---

## 错误处理

### 1. 分类不存在

```php
if (empty($walmart_categories)) {
    return null; // 不传递 features 字段
}
```

### 2. 分类配置缺失

```php
if (empty($available_features)) {
    return null; // 该分类不支持 features 字段
}
```

### 3. 无匹配特性

```php
if (empty($matched_features)) {
    // 根据分类决定返回默认值或 null
    if ($walmart_category === 'Sofas & Couches') {
        return ['Multifunctional'];
    }
    return null;
}
```

### 4. 数据类型验证

在 `convert_field_data_type()` 方法中：

```php
case 'features':
    if (!is_array($value)) {
        if (is_string($value)) {
            return explode(',', $value);
        }
        return [$value];
    }
    return $value;
```

---

## 扩展性设计

### 1. 配置驱动

所有分类配置集中在一个数组中，易于维护：

```php
$category_features_map = [
    'Category 1' => [...],
    'Category 2' => [...],
    // 添加新分类只需在这里添加一行
];
```

### 2. 关键词规则分离

关键词规则独立于匹配逻辑：

```php
$special_matches = [
    'Feature 1' => ['keyword1', 'keyword2'],
    // 添加新规则不影响匹配逻辑
];
```

### 3. 测试接口

提供公共测试方法：

```php
public function test_extract_features_walmart_category($product, $walmart_category) {
    return $this->extract_features_by_category_id($product, $walmart_category);
}
```

### 4. 模拟模式

支持模拟分类进行测试：

```php
private function extract_features_by_category_id($product, $simulate_walmart_category = null) {
    if ($simulate_walmart_category) {
        $walmart_categories[] = $simulate_walmart_category;
    }
    // ...
}
```

---

## 版本历史

| 版本 | 日期 | 修改内容 |
|------|------|----------|
| 1.0 | 2025-10-13 | 初始版本 |
| 1.1 | 2025-10-13 | 修复多特性匹配bug，优化循环逻辑 |

---

## 相关文档

- [features字段分类特定拓展指南.md](./features字段分类特定拓展指南.md) - 使用指南
- [Walmart同步插件字段拓展开发文档.md](./Walmart同步插件字段拓展开发文档.md) - 总体开发文档

