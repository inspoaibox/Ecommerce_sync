# Features 字段分类特定拓展指南

## 📋 目录

1. [概述](#概述)
2. [实现原理](#实现原理)
3. [添加新分类步骤](#添加新分类步骤)
4. [关键词匹配规则](#关键词匹配规则)
5. [测试流程](#测试流程)
6. [已支持的分类](#已支持的分类)
7. [注意事项](#注意事项)
8. [常见问题](#常见问题)

---

## 概述

### 什么是 Features 字段？

`features` 字段用于描述产品的附加特性，是 Walmart API 中的一个 **multiselect** 类型字段。不同的 Walmart 分类有不同的可选特性枚举值。

### 为什么需要分类特定拓展？

- ✅ **不同分类有不同的特性选项** - 床架的特性（如 "Adjustable Height"）与沙发的特性（如 "Reclining"）完全不同
- ✅ **智能匹配提高准确性** - 根据产品标题和描述自动提取相关特性
- ✅ **避免无效值** - 只返回该分类允许的枚举值
- ✅ **提供默认值保护** - 某些分类在无匹配时需要返回默认值

---

## 实现原理

### 核心流程

```
产品 → 获取Walmart分类 → 查找分类配置 → 关键词匹配 → 返回特性数组
```

### 代码架构

```
includes/class-product-mapper.php
├── generate_special_attribute_value()
│   └── case 'features': 
│       └── extract_features_by_category_id()
│           ├── get_product_walmart_categories()  // 获取产品的Walmart分类
│           ├── $category_features_map[]          // 分类→特性映射表
│           └── match_features_from_content()     // 关键词匹配逻辑
│               ├── $special_matches[]            // 特殊关键词规则
│               └── 通用模式匹配
```

### 关键方法说明

| 方法名 | 作用 | 位置 |
|--------|------|------|
| `extract_features_by_category_id()` | 根据Walmart分类提取特性 | Line 6275 |
| `get_product_walmart_categories()` | 获取产品的Walmart分类名称 | Line 6340 |
| `match_features_from_content()` | 从产品内容中匹配特性 | Line 6388 |

---

## 添加新分类步骤

### 步骤1: 获取分类的枚举值

从 Walmart API 规范或 `newleimu.json` 中获取该分类的 `features` 字段枚举值。

**示例**（Sofas & Couches）:
```json
{
  "attributeName": "features",
  "defaultType": "multiselect",
  "allowed_values": [
    "Reclining",
    "USB",
    "Tufted",
    "Storage",
    "Nailhead Trim",
    "Multifunctional",
    "Massaging"
  ]
}
```

### 步骤2: 添加分类配置

**文件**: `includes/class-product-mapper.php`  
**方法**: `extract_features_by_category_id()`  
**位置**: Line 6288-6322

在 `$category_features_map` 数组中添加新分类：

```php
$category_features_map = [
    // ... 现有分类 ...
    
    'Your Walmart Category Name' => [  // ← 使用完整的Walmart分类名称
        'Feature 1',
        'Feature 2',
        'Feature 3',
        // ... 所有枚举值
    ]
];
```

**⚠️ 重要**:
- 使用 **Walmart 分类的最后一级名称**（如 "Sofas & Couches"，不是完整路径）
- 枚举值必须 **完全匹配** Walmart API 规范中的值（大小写敏感）
- 按照 Walmart API 规范的顺序排列

### 步骤3: 添加关键词匹配规则

**位置**: Line 6409-6436

在 `$special_matches` 数组中添加关键词规则：

```php
$special_matches = [
    // ... 现有规则 ...
    
    // Your Category 分类特性
    'Feature 1' => ['keyword1', 'keyword2', 'keyword3'],
    'Feature 2' => ['keyword4', 'keyword5'],
    // ... 为每个特性定义关键词
];
```

**关键词设计原则**:
1. **小写** - 所有关键词使用小写
2. **多样性** - 包含同义词、变体、常见拼写
3. **精确性** - 避免过于宽泛的关键词导致误匹配
4. **完整性** - 包含单词、短语、连字符变体

**示例**:
```php
'Reclining' => ['reclining', 'recline', 'recliner', 'reclinable', 'adjustable back'],
'USB' => ['usb', 'usb port', 'charging port', 'power port', 'usb charging'],
```

### 步骤4: 配置默认值（可选）

如果该分类在无匹配时需要返回默认值，修改 `match_features_from_content()` 方法：

**位置**: Line 6460-6471

```php
// 如果没有匹配到任何特性，根据分类返回默认值或null
if (empty($matched_features)) {
    // Your Category 分类：返回默认值
    if ($walmart_category === 'Your Walmart Category Name') {
        return ['Default Feature'];
    }
    // Sofas & Couches 分类：返回默认值 Multifunctional
    if ($walmart_category === 'Sofas & Couches') {
        return ['Multifunctional'];
    }
    // 其他分类：返回null（不传递此字段）
    return null;
}
```

**⚠️ 注意**:
- 只有在 Walmart API 要求必须有值时才设置默认值
- 默认值必须是该分类允许的枚举值之一

### 步骤5: 创建测试脚本

复制并修改 `test-features-sofas-couches.php`：

```php
<?php
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-config.php';
require_once 'D:/phpstudy_pro/WWW/canda.localhost/wp-load.php';

echo "=== 测试 Your Category 分类的 features 字段 ===\n\n";

require_once WOO_WALMART_SYNC_PATH . 'includes/class-product-mapper.php';
$mapper = new Woo_Walmart_Product_Mapper();

// 测试用例
$test_cases = [
    [
        'name' => '测试1: 包含 Feature1 关键词',
        'product_name' => 'Product with Feature1',
        'description' => 'Description containing keyword1...',
        'expected' => ['Feature 1']
    ],
    // ... 更多测试用例
];

// 测试逻辑
foreach ($test_cases as $test) {
    $product = new WC_Product_Simple();
    $product->set_name($test['product_name']);
    $product->set_description($test['description']);
    
    $result = $mapper->test_extract_features_walmart_category($product, 'Your Walmart Category Name');
    
    // 验证结果...
}
?>
```

### 步骤6: 运行测试

```bash
php test-features-your-category.php
```

**期望结果**: 100% 通过率

---

## 关键词匹配规则

### 匹配优先级

1. **特殊关键词匹配** (`$special_matches`) - 优先级最高
2. **通用模式匹配** (完整词匹配) - 备用方案

### 特殊关键词匹配

**适用场景**:
- 需要匹配多个同义词
- 需要匹配短语（如 "pull out bed"）
- 需要匹配变体（如 "multi-functional", "multifunctional"）

**示例**:
```php
'Multifunctional' => [
    'multifunctional',
    'multi-functional',
    'multi function',
    'versatile',
    'convertible',
    'sleeper',
    'sofa bed',
    'pull out',
    'futon'
]
```

### 通用模式匹配

**适用场景**:
- 特性名称本身就是常见词汇
- 不需要额外的同义词

**工作原理**:
```php
// 自动生成匹配模式
$feature = "Tufted";
$pattern = '/\btufted\b/';  // 完整词边界匹配
```

### 关键词设计最佳实践

#### ✅ 好的关键词设计

```php
'Storage' => [
    'storage',        // 基础词
    'drawer',         // 同义词
    'shelf',          // 相关词
    'shelves',        // 复数形式
    'cabinet',        // 相关词
    'compartment'     // 相关词
]
```

#### ❌ 不好的关键词设计

```php
'Storage' => [
    'stor',           // ❌ 太短，会误匹配 "store", "story"
    'with storage',   // ❌ 太具体，可能漏掉 "has storage"
    'storage space'   // ❌ 太具体，可能漏掉 "storage drawer"
]
```

### 避免关键词冲突

**问题**: 多个特性使用相同的关键词

**解决方案**: 使用更具体的关键词或短语

```php
// ❌ 错误：两个特性都匹配 "usb"
'USB Port' => ['usb', 'port'],
'USB' => ['usb'],

// ✅ 正确：使用不同的关键词组合
'USB Port' => ['usb port', 'usb outlet', 'usb socket'],
'USB' => ['usb', 'usb charging', 'charging port'],
```

---

## 测试流程

### 测试用例设计

每个新分类至少需要以下测试用例：

1. **单个特性匹配** - 测试每个枚举值的关键词
2. **多个特性匹配** - 测试同时匹配多个特性
3. **关键词变体** - 测试同义词、复数、连字符等
4. **无匹配情况** - 测试默认值逻辑
5. **边界情况** - 测试特殊字符、大小写等

### 测试模板

```php
$test_cases = [
    // 1. 单个特性测试
    [
        'name' => '测试: Feature1 关键词',
        'description' => 'Product with keyword1',
        'expected' => ['Feature 1']
    ],
    
    // 2. 多特性测试
    [
        'name' => '测试: 多个特性',
        'description' => 'Product with keyword1 and keyword4',
        'expected' => ['Feature 1', 'Feature 2']
    ],
    
    // 3. 关键词变体测试
    [
        'name' => '测试: 同义词',
        'description' => 'Product with synonym1',
        'expected' => ['Feature 1']
    ],
    
    // 4. 默认值测试
    [
        'name' => '测试: 无匹配（默认值）',
        'description' => 'Simple product',
        'expected' => ['Default Feature']  // 或 null
    ],
    
    // 5. 边界情况测试
    [
        'name' => '测试: 大小写不敏感',
        'description' => 'Product with KEYWORD1',
        'expected' => ['Feature 1']
    ]
];
```

### 验证标准

✅ **测试通过条件**:
- 所有测试用例 100% 通过
- 匹配的特性完全符合预期
- 无误匹配（不应该匹配的特性没有被匹配）
- 默认值逻辑正确

❌ **常见失败原因**:
- 关键词拼写错误
- 枚举值大小写不匹配
- 关键词过于宽泛导致误匹配
- 忘记添加关键词变体

---

## 已支持的分类

### 1. Bed Frames（床架）

**枚举值**:
- Adjustable Height
- Wireless Remote
- Heavy Duty
- Center Supports
- USB Port
- Headboard Compatible
- Massaging

**默认值**: null（无匹配时不传递字段）

**测试文件**: `test-features-bed-frames.php`（需创建）

---

### 2. Kitchen Serving Carts（厨房推车）

**枚举值**:
- Rolling
- Folding
- Portable
- Removable

**默认值**: null

**测试文件**: `test-features-kitchen-carts.php`（需创建）

---

### 3. Dining Furniture Sets（餐厅家具套装）

**枚举值**:
- Live Edge
- Storage
- Nailhead Trim
- Folding
- Tufted

**默认值**: null

**测试文件**: `test-features-dining-sets.php`（需创建）

---

### 4. Sofas & Couches（沙发）

**枚举值**:
- Reclining
- USB
- Tufted
- Storage
- Nailhead Trim
- Multifunctional
- Massaging

**默认值**: Multifunctional

**测试文件**: `test-features-sofas-couches.php` ✅

**关键词示例**:
```php
'Reclining' => ['reclining', 'recline', 'recliner', 'reclinable', 'adjustable back'],
'USB' => ['usb', 'usb port', 'charging port', 'power port', 'usb charging'],
'Multifunctional' => ['multifunctional', 'multi-functional', 'versatile', 'convertible', 'sleeper', 'sofa bed', 'pull out', 'futon']
```

---

## 注意事项

### ⚠️ 关键注意事项

1. **Walmart分类名称必须精确**
   - 使用分类路径的最后一级（如 "Sofas & Couches"）
   - 大小写、空格、符号必须完全匹配
   - 可以通过 `wp_walmart_category_map` 表查询

2. **枚举值必须完全匹配API规范**
   - 大小写敏感（"USB" ≠ "usb"）
   - 空格和标点符号必须一致
   - 不能自创枚举值

3. **关键词匹配是小写的**
   - 所有关键词使用小写
   - 产品内容会被转换为小写后匹配
   - 不需要考虑大小写变体

4. **避免过度匹配**
   - 关键词不要太短或太宽泛
   - 使用完整词边界匹配
   - 测试时注意误匹配情况

5. **默认值谨慎使用**
   - 只在API要求必须有值时使用
   - 默认值必须是有效的枚举值
   - 记录在文档中

### 🔧 调试技巧

**查看产品的Walmart分类**:
```php
$mapper = new Woo_Walmart_Product_Mapper();
$reflection = new ReflectionClass($mapper);
$method = $reflection->getMethod('get_product_walmart_categories');
$method->setAccessible(true);
$categories = $method->invoke($mapper, $product);
print_r($categories);
```

**测试特定分类的特性提取**:
```php
$result = $mapper->test_extract_features_walmart_category($product, 'Sofas & Couches');
print_r($result);
```

**查看匹配的关键词**:
在 `match_features_from_content()` 方法中添加调试输出：
```php
if (strpos($content, $keyword) !== false) {
    error_log("Matched: $feature with keyword: $keyword");
    $matched_features[] = $feature;
}
```

---

## 常见问题

### Q1: 为什么我的特性没有被匹配？

**可能原因**:
1. 关键词拼写错误
2. 产品描述中没有相关关键词
3. 关键词被其他词包含（如 "storage" 在 "restorage" 中）

**解决方案**:
- 检查关键词拼写
- 添加更多同义词
- 使用完整词边界匹配

### Q2: 为什么匹配了不应该匹配的特性？

**可能原因**:
- 关键词太宽泛
- 多个特性使用了相同的关键词

**解决方案**:
- 使用更具体的关键词或短语
- 检查关键词冲突

### Q3: 如何处理多语言产品？

**当前实现**: 只支持英文关键词匹配

**扩展方案**:
```php
$special_matches = [
    'Storage' => [
        // 英文
        'storage', 'drawer', 'shelf',
        // 中文（需要产品描述包含中文）
        '储物', '抽屉', '架子'
    ]
];
```

### Q4: 如何复用已有的关键词规则？

某些特性（如 "Storage", "Tufted"）在多个分类中都有，它们会自动复用已定义的关键词规则。

**示例**:
```php
// Dining Furniture Sets 定义了 Storage
'Storage' => ['storage', 'drawer', 'shelf', ...],

// Sofas & Couches 也有 Storage，会自动使用相同的关键词
'Sofas & Couches' => [
    'Storage',  // ← 自动复用上面的关键词
    'Reclining',
    // ...
]
```

### Q5: 测试通过了，但实际同步时没有传递 features 字段？

**可能原因**:
1. 产品的本地分类没有映射到该Walmart分类
2. 分类映射表中没有配置 features 字段

**解决方案**:
1. 检查 `wp_walmart_category_map` 表
2. 确认产品的本地分类已映射
3. 在分类映射页面重置属性，确保 features 字段被添加

---

## 版本历史

| 版本 | 日期 | 修改内容 |
|------|------|----------|
| 1.0 | 2025-10-13 | 初始版本，支持4个分类 |
| 1.1 | 2025-10-13 | 修复多特性匹配bug |

---

## 相关文档

- [Walmart同步插件字段拓展开发文档.md](./Walmart同步插件字段拓展开发文档.md)
- [字段拓展开发文档 - 备份.md](./字段拓展开发文档%20-%20备份.md)

---

**文档维护**: 每次添加新分类后，请更新本文档的"已支持的分类"部分。

