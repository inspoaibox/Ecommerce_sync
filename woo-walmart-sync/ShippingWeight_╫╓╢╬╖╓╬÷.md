# ShippingWeight 字段完整分析

## 📋 字段基本信息

### 字段定义
- **字段名**: `ShippingWeight`
- **数据类型**: `number` (数字)
- **是否必需**: ✅ **必需字段**
- **所属部分**: `Orderable` 部分
- **单位**: 磅 (lbs)
- **取值范围**: 0 - 10000000000000000
- **精度**: 最多3位小数 (0.001)

### API规范
```json
{
  "ShippingWeight": {
    "type": "number",
    "title": "Shipping Weight (lbs)",
    "minimum": 0,
    "maximum": 10000000000000000,
    "multipleOf": 0.001,
    "description": "包括所有包装材料的重量",
    "examples": ["5"],
    "comments": "@group=Required to sell on Walmart website"
  }
}
```

## 🔄 字段映射逻辑

### 1. 数据获取优先级

#### 第一优先级：标准包装重量字段
系统会按顺序查找以下产品属性：
```php
$standard_weight_fields = [
    'Package Weight',      // 标准包装重量
    'package_weight',      // 小写变体
    'PackageWeight',       // 驼峰命名
    'package-weight'       // 连字符变体
];
```

#### 第二优先级：多包裹重量计算
如果没有找到标准字段，系统会自动计算多包裹重量：
- 查找所有匹配模式的属性：`Package 1 Weight`, `Package-2-Weight`, `package_3_weight` 等
- 支持的命名模式：
  - `Package 1 Weight`
  - `Package-1-Weight`  
  - `package_1_weight`
  - `package1weight`
- 将所有找到的包裹重量相加

#### 第三优先级：产品重量字段
如果前两步都没有找到，使用产品重量作为备选：
```php
$product_weight_fields = [
    'Product Weight',      // 标准产品重量
    'product_weight',      // 小写变体
    'product-weight'       // 连字符变体
];
```

#### 第四优先级：默认值
如果所有方法都没有找到重量，返回默认值 `"1"`

### 2. 数据处理流程

```mermaid
flowchart TD
    A[开始处理ShippingWeight] --> B[查找标准包装重量字段]
    B --> C{找到Package Weight?}
    C -->|是| D[提取数字值]
    C -->|否| E[计算多包裹重量]
    E --> F{找到多个包裹?}
    F -->|是| G[累加所有包裹重量]
    F -->|否| H[查找产品重量字段]
    H --> I{找到Product Weight?}
    I -->|是| D
    I -->|否| J[返回默认值 "1"]
    D --> K[验证数字 > 0]
    G --> K
    K -->|是| L[返回字符串格式数字]
    K -->|否| J
    L --> M[结束]
    J --> M
```

### 3. 数字提取逻辑

系统使用 `extract_numeric_weight()` 方法处理各种格式的重量字符串：

#### 支持的格式
- `"26.4"` → `26.4`
- `"26.4 lb"` → `26.4`
- `"26.4 lbs"` → `26.4`
- `"26.4 pounds"` → `26.4`
- `"26"` → `26.0`

#### 处理逻辑
```php
private function extract_numeric_weight($weight_string) {
    // 1. 检查输入有效性
    if (empty($weight_string) || !is_string($weight_string)) {
        return 0;
    }
    
    // 2. 移除空白字符
    $weight_string = trim($weight_string);
    
    // 3. 使用正则表达式提取数字
    if (preg_match('/^(\d+(?:\.\d+)?)/', $weight_string, $matches)) {
        $numeric_value = (float) $matches[1];
        return $numeric_value > 0 ? $numeric_value : 0;
    }
    
    return 0;
}
```

## 🎯 同步规则

### 1. 字段分类
- **所属部分**: `Orderable` 部分
- **字段类型**: 必需字段
- **数据格式**: 字符串格式的数字

### 2. 验证规则
```php
'ShippingWeight' => [
    'type' => 'number',
    'required' => true,
    'section' => 'Orderable',
    'validation' => [
        'min' => 0, 
        'max' => 10000000000000000, 
        'decimal_places' => 3
    ],
    'comments' => '@group=Required to sell on Walmart website'
]
```

### 3. 错误处理
- 如果重量为0或负数，使用默认值1
- 如果无法提取数字，使用默认值1
- 确保返回值为字符串格式

## 📝 配置示例

### 1. 产品属性配置
在WooCommerce产品编辑页面的"Attributes"部分添加：

```
属性名: Package Weight
属性值: 26.4 lb

或者

属性名: Package 1 Weight  
属性值: 15.2
属性名: Package 2 Weight
属性值: 11.2
```

### 2. 分类映射配置
在沃尔玛分类映射中：
```json
{
  "name": ["ShippingWeight"],
  "type": ["auto_generate"],
  "source": [""],
  "format": ["auto"]
}
```

### 3. 自定义字段配置
也可以通过自定义字段设置：
```php
// 在产品页面添加自定义字段
add_post_meta($product_id, 'Package Weight', '26.4 lb');
```

## 🔧 重置属性规则

### 1. 重置触发条件
- 产品属性更新时
- 分类映射规则变更时
- 手动重新同步时

### 2. 重置处理逻辑
```php
// 重新计算ShippingWeight
$weight = null;

// 按优先级重新获取
foreach ($standard_weight_fields as $field_name) {
    $attr_weight = $product->get_attribute($field_name);
    if (!empty($attr_weight)) {
        $weight = $this->extract_numeric_weight($attr_weight);
        if ($weight > 0) break;
    }
}

// 如果没有找到，计算多包裹重量
if (!$weight) {
    $weight = $this->calculate_multi_package_weight($product);
}

// 最终返回
return $weight ? (string) $weight : '1';
```

### 3. 缓存处理
- 重置时清除相关缓存
- 重新计算所有依赖字段
- 更新产品映射数据

## ⚠️ 注意事项

### 1. 数据格式
- API要求数字类型，但系统返回字符串格式
- 确保数字精度不超过3位小数
- 重量必须大于0

### 2. 单位处理
- 系统假设所有重量都是磅(lb)
- 不进行单位转换
- 需要确保产品属性中的重量已经是磅单位

### 3. 多包裹处理
- 支持无限数量的包裹
- 包裹编号可以不连续
- 自动累加所有找到的包裹重量

### 4. 错误处理
- 无效数据时使用默认值1
- 记录处理过程到日志
- 确保不会返回null或空值

## 🚀 最佳实践

### 1. 产品属性命名
推荐使用标准命名：
- 单包裹：`Package Weight`
- 多包裹：`Package 1 Weight`, `Package 2 Weight`

### 2. 数据格式
推荐格式：
- `"26.4"` (纯数字)
- `"26.4 lb"` (带单位)

### 3. 质量控制
- 定期检查重量数据的准确性
- 确保重量符合实际产品情况
- 监控同步日志中的重量相关错误

这就是 `ShippingWeight` 字段的完整分析，包括其重置属性规则、同步规则和处理逻辑。
