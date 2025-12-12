# 属性映射配置系统需求规格

## 概述

属性映射配置系统用于定义商品数据如何映射到目标平台（如 Walmart）的属性字段。系统需要支持多种映射类型，并能正确处理不同类目下相同属性名但枚举值不同的情况。

---

## 用户故事

### US-1: 配置固定默认值

**作为** 运营人员  
**我希望** 为某些平台属性设置固定的默认值  
**以便** 所有商品在该属性上使用统一的值

**验收标准：**
- [ ] 可以选择「默认值」映射类型
- [ ] 可以输入任意文本作为默认值
- [ ] 同步时该属性使用配置的固定值

---

### US-2: 从渠道数据提取属性值

**作为** 运营人员  
**我希望** 将平台属性映射到商品的标准字段  
**以便** 自动从商品数据中提取对应的值

**验收标准：**
- [ ] 可以选择「渠道数据」映射类型
- [ ] 可以从下拉列表选择标准字段（如 brand、mpn、weight 等）
- [ ] 支持嵌套字段路径（如 packaging.weight、productDimensions.length）
- [ ] 同步时从商品的 channelAttributes 中提取对应值
- [ ] 字段不存在时返回空值

---

### US-3: 选择平台枚举值

**作为** 运营人员  
**我希望** 从平台提供的枚举值列表中选择一个固定值  
**以便** 确保提交的值符合平台要求

**验收标准：**
- [ ] 可以选择「枚举选择」映射类型
- [ ] 显示该属性在当前类目下的枚举值列表
- [ ] 同一属性在不同类目下显示不同的枚举值列表
- [ ] 选中的枚举值在同步时直接使用

---

### US-4: 自动生成属性值

**作为** 运营人员  
**我希望** 配置自动生成规则来生成属性值  
**以便** 根据商品数据动态生成符合要求的值

**验收标准：**
- [ ] 可以选择「自动生成」映射类型
- [ ] 可以选择生成规则类型（如 SKU前缀、品牌+标题、取第一个特点等）
- [ ] 可以为某些规则配置参数（如前缀字符串）
- [ ] 同步时根据规则和商品数据动态生成值
- [ ] 生成规则可扩展，支持后续添加新规则

---

### US-5: 从 UPC 池获取 UPC

**作为** 运营人员  
**我希望** 将 UPC 属性配置为从 UPC 池自动获取  
**以便** 为没有 UPC 的商品自动分配可用的 UPC

**验收标准：**
- [ ] 可以选择「UPC池」映射类型
- [ ] 同步时自动从店铺的 UPC 池获取未使用的 UPC
- [ ] 获取的 UPC 标记为已使用并关联到商品
- [ ] UPC 池为空时返回错误

---

### US-6: 处理类目特定的枚举值

**作为** 系统  
**我需要** 正确处理同一属性在不同类目下有不同枚举值的情况  
**以便** 确保映射配置的准确性

**验收标准：**
- [ ] 枚举值从 PlatformAttribute 表获取，该表与 PlatformCategory 关联
- [ ] 切换类目时，枚举值列表自动更新
- [ ] 映射配置按 platformId + country + categoryId 唯一存储
- [ ] 不同类目的映射配置相互独立

---

## 数据模型

### 映射规则结构

```typescript
interface MappingRule {
  attributeId: string;           // 平台属性ID
  attributeName: string;         // 平台属性名称
  mappingType: MappingType;      // 映射类型
  value: string;                 // 值/配置（根据类型不同含义不同）
  isRequired: boolean;           // 是否必填
  dataType: string;              // 数据类型
  enumValues?: string[];         // 枚举值列表（从平台API获取，与类目绑定）
}

type MappingType = 
  | 'default_value'    // 固定默认值
  | 'channel_data'     // 从渠道数据提取
  | 'enum_select'      // 选择枚举值
  | 'auto_generate'    // 自动生成
  | 'upc_pool';        // 从UPC池获取
```

### 各映射类型的 value 含义

| 映射类型 | value 含义 | 示例 |
|---------|-----------|------|
| `default_value` | 固定值文本 | `"Generic Brand"` |
| `channel_data` | 标准字段路径 | `"brand"` 或 `"packaging.weight"` |
| `enum_select` | 选中的枚举值 | `"lb"` |
| `auto_generate` | 生成规则配置 | `"sku_prefix:WM-"` |
| `upc_pool` | 空字符串 | `""` |

---

## 标准字段路径列表

> 基于 `StandardProduct` 接口定义（参见 `apps/api/src/adapters/channels/standard-product.interface.ts`）

### 基础信息
- `sku` - SKU（商品唯一标识）
- `title` - 商品标题
- `description` - 商品描述（支持HTML）
- `shortDescription` - 简短描述
- `bulletPoints` - 五点描述（数组）
- `bulletPoints[0]` - 第一条五点描述
- `status` - 商品状态
- `type` - 商品类型

### 产品标识
- `brand` - 品牌
- `manufacturer` - 制造商
- `mpn` - 制造商零件号
- `model` - 型号
- `upc` - UPC（12位）
- `ean` - EAN（13位）
- `gtin` - GTIN（8/12/13/14位）
- `isbn` - ISBN（书籍）
- `asin` - ASIN（Amazon）

### 图片媒体
- `mainImageUrl` - 主图URL
- `imageUrls` - 附图URL列表
- `imageUrls[0]` - 第一张附图
- `videoUrls` - 视频URL列表
- `documentUrls` - 文档URL列表

### 价格信息
- `price` - 常规价格
- `salePrice` - 促销价格
- `costPrice` - 成本价
- `mapPrice` - MAP价格
- `currency` - 货币代码
- `shippingFee` - 运费

### 库存信息
- `stock` - 库存数量
- `stockStatus` - 库存状态

### 包装尺寸（运输尺寸）
- `packaging.weight` - 包装重量
- `packaging.weightUnit` - 重量单位（lb/kg/oz/g）
- `packaging.length` - 包装长度
- `packaging.width` - 包装宽度
- `packaging.height` - 包装高度
- `packaging.lengthUnit` - 长度单位（in/cm/m）
- `packaging.isMultiPackage` - 是否多包裹
- `packaging.packageCount` - 包裹数量

### 产品尺寸（组装后）
- `productDimensions.weight` - 产品重量
- `productDimensions.length` - 产品长度
- `productDimensions.width` - 产品宽度
- `productDimensions.height` - 产品高度

### 分类信息
- `categoryCode` - 分类代码
- `categoryName` - 分类名称
- `categoryPath` - 分类路径
- `tags` - 标签列表

### 产地信息
- `placeOfOrigin` - 产地
- `countryOfOrigin` - 原产国代码（ISO 3166-1 alpha-2）
- `madeIn` - 制造地

### 物流信息
- `shipping.shippingFee` - 运费
- `shipping.freeShipping` - 是否免运费
- `shipping.shippingClass` - 运输类别
- `shipping.leadTime` - 发货时间（天）
- `shipping.oversized` - 是否超大件
- `shipping.fragile` - 是否易碎品
- `shipping.hazardous` - 是否危险品
- `shipping.lithiumBattery` - 是否含锂电池

### 商品特点
- `characteristics` - 渠道特点（数组）
- `characteristics[0]` - 第一个特点
- `keyFeatures` - 关键特性（数组）
- `attributes` - 属性列表

### 卖家信息
- `seller.sellerId` - 卖家ID
- `seller.sellerName` - 卖家名称
- `seller.storeName` - 店铺名称

### SEO信息
- `metaTitle` - SEO标题
- `metaDescription` - SEO描述
- `metaKeywords` - SEO关键词
- `slug` - URL别名

---

> **与代码一致性说明**：以上字段路径与 `STANDARD_FIELD_PATHS` 常量和 `StandardProduct` 接口完全一致。
> 
> 字段路径支持：
> - 简单路径：`brand`、`title`
> - 嵌套路径：`packaging.weight`、`seller.storeName`
> - 数组索引：`bulletPoints[0]`、`characteristics[0]`

---

## 自动生成规则

### 当前支持的规则

| 规则标识 | 说明 | 参数 | 示例 |
|---------|------|------|------|
| `sku_prefix` | SKU前缀拼接 | 前缀字符串 | `sku_prefix:WM-` → `WM-ABC123` |
| `sku_suffix` | SKU后缀拼接 | 后缀字符串 | `sku_suffix:-US` → `ABC123-US` |
| `brand_title` | 品牌+标题组合 | 无 | `ACME Product Name` |
| `first_characteristic` | 取第一个特点 | 无 | `characteristics[0]` |
| `current_date` | 当前日期 | 格式（可选） | `2024-01-15` |
| `uuid` | 生成UUID | 无 | `550e8400-e29b-41d4-a716-446655440000` |

### 规则配置格式

```
规则标识:参数
```

示例：
- `sku_prefix:WM-` - 在SKU前添加 "WM-"
- `brand_title` - 无参数，直接使用规则标识
- `current_date:YYYY-MM-DD` - 指定日期格式

### 扩展新规则

后续可添加的规则类型：
- `ai_title` - AI优化标题
- `ai_description` - AI优化描述
- `ai_bullet_points` - AI优化五点描述
- `template` - 模板字符串，如 `{brand} - {title}`
- `concat` - 多字段拼接
- `lookup` - 查表映射

---

## 条件判断规则（Fallback）

### 需求说明

支持多条件判断，当某个字段为空时使用默认值或备选字段。

### 配置格式

```typescript
interface ConditionalRule {
  type: 'conditional';
  conditions: ConditionItem[];
}

interface ConditionItem {
  field: string;           // 字段路径
  operator: 'empty' | 'not_empty' | 'equals' | 'not_equals';
  value?: any;             // 比较值（equals/not_equals 时使用）
  then: string | number;   // 条件满足时的值（可以是字段路径或固定值）
  isFieldPath?: boolean;   // then 是否为字段路径
}
```

### 示例场景

**场景1：品牌为空时使用默认值**
```json
{
  "type": "conditional",
  "conditions": [
    { "field": "brand", "operator": "empty", "then": "Generic Brand", "isFieldPath": false },
    { "field": "brand", "operator": "not_empty", "then": "brand", "isFieldPath": true }
  ]
}
```
逻辑：如果 `brand` 为空，返回 "Generic Brand"；否则返回 `brand` 字段的值。

**场景2：尺寸为空时使用默认值**
```json
{
  "type": "conditional",
  "conditions": [
    { "field": "packaging.length", "operator": "empty", "then": 1, "isFieldPath": false },
    { "field": "packaging.length", "operator": "not_empty", "then": "packaging.length", "isFieldPath": true }
  ]
}
```

**场景3：优先使用 UPC，为空时使用 EAN**
```json
{
  "type": "conditional",
  "conditions": [
    { "field": "upc", "operator": "not_empty", "then": "upc", "isFieldPath": true },
    { "field": "ean", "operator": "not_empty", "then": "ean", "isFieldPath": true },
    { "field": "gtin", "operator": "not_empty", "then": "gtin", "isFieldPath": true }
  ]
}
```
逻辑：按顺序检查，返回第一个非空的值。

### 简化语法（Fallback Chain）

对于常见的"优先取A，为空取B，再为空取C"场景，提供简化语法：

```
fallback:brand|manufacturer|"Generic Brand"
```

解析规则：
- 用 `|` 分隔多个选项
- 不带引号的是字段路径
- 带引号的是固定值
- 按顺序检查，返回第一个非空的值

---

## 字段组合映射

### 需求说明

支持将多个字段组合成一个值，或将一个值拆分到多个字段。

### 组合映射（Concat）

将多个字段值拼接成一个字符串。

**配置格式：**
```typescript
interface ConcatRule {
  type: 'concat';
  fields: string[];        // 字段路径列表
  separator: string;       // 分隔符
  template?: string;       // 模板字符串（可选，更灵活）
}
```

**示例1：尺寸组合（长x宽x高）**
```json
{
  "type": "concat",
  "fields": ["packaging.length", "packaging.width", "packaging.height"],
  "separator": "x"
}
```
结果：`30x20x15`

**示例2：使用模板**
```json
{
  "type": "concat",
  "template": "{packaging.length}\" L x {packaging.width}\" W x {packaging.height}\" H"
}
```
结果：`30" L x 20" W x 15" H`

**示例3：品牌+型号组合**
```json
{
  "type": "concat",
  "fields": ["brand", "model"],
  "separator": " - "
}
```
结果：`ACME - ABC123`

### 拆分映射（Split）

将一个字符串值拆分并提取部分。

**配置格式：**
```typescript
interface SplitRule {
  type: 'split';
  field: string;           // 源字段路径
  separator: string;       // 分隔符
  index: number;           // 取第几个部分（从0开始）
  trim?: boolean;          // 是否去除空格
}
```

**示例：从尺寸字符串提取长度**

源数据：`"30 x 20 x 15"`
```json
{
  "type": "split",
  "field": "dimensionString",
  "separator": "x",
  "index": 0,
  "trim": true
}
```
结果：`30`

### 正则提取（Regex）

使用正则表达式从字符串中提取值。

**配置格式：**
```typescript
interface RegexRule {
  type: 'regex';
  field: string;           // 源字段路径
  pattern: string;         // 正则表达式
  group?: number;          // 捕获组索引（默认0，整个匹配）
}
```

**示例：从标题提取尺寸**

源数据：`"Dining Table 60 inch Oak Wood"`
```json
{
  "type": "regex",
  "field": "title",
  "pattern": "(\\d+)\\s*inch",
  "group": 1
}
```
结果：`60`

---

## 数组类型字段处理

### 需求说明

对于数组类型字段（如 `bulletPoints`、`characteristics`、`imageUrls`），需要支持多种处理方式。

### 处理方式

| 方式 | 配置 | 说明 | 示例 |
|------|------|------|------|
| 取全部 | `bulletPoints` | 返回整个数组 | `["点1", "点2", "点3"]` |
| 指定索引 | `bulletPoints[0]` | 取第N个元素 | `"点1"` |
| 范围切片 | `bulletPoints[0:3]` | 取前3个元素 | `["点1", "点2", "点3"]` |
| 拼接成字符串 | `join:bulletPoints:\n` | 用分隔符拼接 | `"点1\n点2\n点3"` |
| 取数组长度 | `length:bulletPoints` | 返回数组长度 | `5` |

### 配置格式

**1. 直接路径（取全部）**
```
bulletPoints
```

**2. 索引访问（取单个）**
```
bulletPoints[0]      // 第一个
bulletPoints[-1]     // 最后一个
```

**3. 切片访问（取范围）**
```
bulletPoints[0:3]    // 前3个
bulletPoints[2:]     // 从第3个到最后
bulletPoints[:5]     // 前5个
```

**4. 拼接规则**
```typescript
interface JoinRule {
  type: 'join';
  field: string;           // 数组字段路径
  separator: string;       // 分隔符
  maxItems?: number;       // 最大项数（可选）
}
```

示例：
```json
{
  "type": "join",
  "field": "bulletPoints",
  "separator": "\n",
  "maxItems": 5
}
```

**5. 长度规则**
```typescript
interface LengthRule {
  type: 'length';
  field: string;           // 数组字段路径
}
```

### 使用场景

| 平台属性类型 | 推荐处理方式 | 示例 |
|-------------|-------------|------|
| 数组（如 Walmart bulletPoints） | 取全部或切片 | `bulletPoints` 或 `bulletPoints[0:5]` |
| 单个字符串（如某些平台的 feature1） | 指定索引 | `bulletPoints[0]` |
| 长文本（如描述补充） | 拼接成字符串 | `join:bulletPoints:\n` |
| 数字（如特点数量） | 取长度 | `length:bulletPoints` |

---

## 单位转换规则

### 需求说明

支持尺寸和重量单位的自动转换，确保数据符合目标平台的单位要求。

### 支持的转换类型

#### 长度单位转换

| 源单位 | 目标单位 | 转换系数 | 示例 |
|--------|---------|---------|------|
| cm | in | ÷ 2.54 | 100 cm → 39.37 in |
| in | cm | × 2.54 | 39.37 in → 100 cm |
| m | in | × 39.37 | 1 m → 39.37 in |
| m | cm | × 100 | 1 m → 100 cm |
| cm | m | ÷ 100 | 100 cm → 1 m |
| in | m | ÷ 39.37 | 39.37 in → 1 m |

#### 重量单位转换

| 源单位 | 目标单位 | 转换系数 | 示例 |
|--------|---------|---------|------|
| kg | lb | × 2.205 | 10 kg → 22.05 lb |
| lb | kg | ÷ 2.205 | 22.05 lb → 10 kg |
| g | lb | ÷ 453.6 | 1000 g → 2.205 lb |
| g | kg | ÷ 1000 | 1000 g → 1 kg |
| kg | g | × 1000 | 1 kg → 1000 g |
| oz | lb | ÷ 16 | 32 oz → 2 lb |
| lb | oz | × 16 | 2 lb → 32 oz |
| oz | kg | ÷ 35.274 | 35.274 oz → 1 kg |
| kg | oz | × 35.274 | 1 kg → 35.274 oz |

### 配置格式

```typescript
interface UnitConvertRule {
  type: 'unit_convert';
  field: string;              // 数值字段路径
  unitField?: string;         // 单位字段路径（可选，用于自动检测源单位）
  fromUnit?: string;          // 源单位（如果不指定 unitField）
  toUnit: string;             // 目标单位
  precision?: number;         // 小数位数（默认2）
}
```

### 示例场景

**场景1：cm 转 in（已知源单位）**

源数据：`{ packaging: { length: 100, lengthUnit: 'cm' } }`

```json
{
  "type": "unit_convert",
  "field": "packaging.length",
  "fromUnit": "cm",
  "toUnit": "in",
  "precision": 2
}
```
结果：`39.37`

**场景2：自动检测源单位**

源数据：`{ packaging: { weight: 10, weightUnit: 'kg' } }`

```json
{
  "type": "unit_convert",
  "field": "packaging.weight",
  "unitField": "packaging.weightUnit",
  "toUnit": "lb",
  "precision": 2
}
```
结果：`22.05`（自动检测到源单位是 kg）

**场景3：kg 转 lb**

源数据：`{ packaging: { weight: 25 } }`

```json
{
  "type": "unit_convert",
  "field": "packaging.weight",
  "fromUnit": "kg",
  "toUnit": "lb",
  "precision": 1
}
```
结果：`55.1`

### 简化语法

对于常见的单位转换，提供简化语法：

```
convert:packaging.length:cm:in
convert:packaging.weight:kg:lb:1
```

格式：`convert:字段路径:源单位:目标单位[:精度]`

### 转换逻辑伪代码

```typescript
const CONVERSION_FACTORS = {
  // 长度转换（基准：米）
  length: {
    m: 1,
    cm: 0.01,
    in: 0.0254,
  },
  // 重量转换（基准：千克）
  weight: {
    kg: 1,
    g: 0.001,
    lb: 0.453592,
    oz: 0.0283495,
  },
};

function convertUnit(
  value: number,
  fromUnit: string,
  toUnit: string,
  precision: number = 2
): number {
  // 确定转换类型（长度或重量）
  const type = getUnitType(fromUnit);
  const factors = CONVERSION_FACTORS[type];
  
  if (!factors[fromUnit] || !factors[toUnit]) {
    throw new Error(`Unsupported unit conversion: ${fromUnit} to ${toUnit}`);
  }
  
  // 先转换为基准单位，再转换为目标单位
  const baseValue = value * factors[fromUnit];
  const result = baseValue / factors[toUnit];
  
  // 保留指定小数位
  return Number(result.toFixed(precision));
}

function getUnitType(unit: string): 'length' | 'weight' {
  if (['m', 'cm', 'in'].includes(unit)) return 'length';
  if (['kg', 'g', 'lb', 'oz'].includes(unit)) return 'weight';
  throw new Error(`Unknown unit: ${unit}`);
}
```

### 与其他规则组合使用

单位转换可以与条件判断、字段组合等规则组合使用：

**示例：转换后拼接单位**
```json
{
  "type": "concat",
  "template": "{convert:packaging.weight:kg:lb:1} lb"
}
```
结果：`55.1 lb`

**示例：转换后带条件判断**
```json
{
  "type": "conditional",
  "conditions": [
    { 
      "field": "packaging.weight", 
      "operator": "not_empty", 
      "then": { "type": "unit_convert", "field": "packaging.weight", "fromUnit": "kg", "toUnit": "lb" },
      "isFieldPath": false 
    },
    { "field": "packaging.weight", "operator": "empty", "then": 1, "isFieldPath": false }
  ]
}
```

### 平台单位要求参考

| 平台 | 重量单位 | 长度单位 | 备注 |
|------|---------|---------|------|
| Walmart US | lb | in | 美国市场标准 |
| Amazon US | lb | in | 美国市场标准 |
| Amazon EU | kg | cm | 欧洲市场标准 |
| eBay | 可配置 | 可配置 | 根据站点不同 |

---

## 同步时的属性解析流程

```
商品数据 (ListingProduct)
    │
    ├── channelAttributes (标准化字段，符合 StandardProduct 接口)
    │     ├── brand: "ACME"
    │     ├── mpn: "ABC123"
    │     ├── bulletPoints: ["Feature 1", "Feature 2", ...]
    │     ├── packaging: { weight: 55, length: 30, ... }
    │     └── characteristics: ["Feature 1", "Feature 2", ...]
    │
    └── 类目映射规则 (CategoryAttributeMapping)
          │
          ▼
    属性值解析器 (resolveAttributeValue)
          │
          ▼
    平台属性 (platformAttributes)
```

### 解析逻辑伪代码

```typescript
function resolveAttributeValue(
  rule: MappingRule, 
  product: ListingProduct,
  context: { shopId: string }
): any {
  
  switch (rule.mappingType) {
    case 'default_value':
      // 直接返回配置的固定值
      return rule.value;
    
    case 'channel_data':
      // 从 channelAttributes 中按路径提取
      const path = rule.value;
      return getNestedValue(product.channelAttributes, path);
    
    case 'enum_select':
      // 直接返回选中的枚举值
      return rule.value;
    
    case 'auto_generate':
      // 解析规则并生成
      return executeAutoGenerate(rule.value, product);
    
    case 'upc_pool':
      // 从UPC池获取
      return await getAvailableUpc(context.shopId, product.sku);
  }
}

function executeAutoGenerate(ruleConfig: string, product: any): any {
  const [ruleType, param] = ruleConfig.split(':');
  const attrs = product.channelAttributes || {};
  
  switch (ruleType) {
    case 'sku_prefix':
      return `${param || ''}${product.sku}`;
    
    case 'sku_suffix':
      return `${product.sku}${param || ''}`;
    
    case 'brand_title':
      return `${attrs.brand || ''} ${attrs.title}`.trim();
    
    case 'first_characteristic':
      return attrs.characteristics?.[0] || '';
    
    case 'current_date':
      return formatDate(new Date(), param || 'YYYY-MM-DD');
    
    case 'uuid':
      return generateUUID();
    
    default:
      return '';
  }
}

// 使用 standard-product.utils.ts 中的 getNestedValue 函数
function getNestedValue(obj: any, path: string): any {
  if (!obj || !path) return undefined;
  
  // 支持数组索引，如 "characteristics[0]" 或 "bulletPoints[0]"
  const keys = path.replace(/\[(\d+)\]/g, '.$1').split('.');
  let result = obj;
  
  for (const key of keys) {
    if (result === null || result === undefined) return undefined;
    result = result[key];
  }
  
  return result;
}
```

---

## 枚举值与类目的关系

### 数据存储结构

```
PlatformCategory (平台类目)
  ├── id: "cat_001"
  ├── platformId: "walmart"
  ├── country: "US"
  ├── categoryId: "furniture_sofas"
  └── attributes: PlatformAttribute[]
        ├── { attributeId: "color", enumValues: ["Black", "White", "Brown"] }
        └── { attributeId: "material", enumValues: ["Leather", "Fabric", "Wood"] }

PlatformCategory (另一个类目)
  ├── id: "cat_002"
  ├── platformId: "walmart"
  ├── country: "US"
  ├── categoryId: "electronics_tvs"
  └── attributes: PlatformAttribute[]
        ├── { attributeId: "color", enumValues: ["Black", "Silver"] }  // 不同的枚举值
        └── { attributeId: "screenSize", enumValues: ["32", "43", "55", "65"] }
```

### 关键设计点

1. **枚举值与类目绑定**：`PlatformAttribute.enumValues` 存储的是该属性在特定类目下的枚举值
2. **映射配置独立**：`CategoryAttributeMapping` 按 `platformId + country + categoryId` 唯一，不同类目的配置相互独立
3. **无需枚举映射转换**：直接使用平台返回的枚举值，不做额外转换

---

## 非功能需求

### 性能
- 属性解析应在 100ms 内完成
- 批量同步时支持并行解析

### 可扩展性
- 自动生成规则可通过配置添加，无需修改核心代码
- 标准字段路径列表可动态扩展

### 兼容性
- 向后兼容现有的映射配置数据
- 新增映射类型不影响已有配置

---

## 已确认需求

1. ✅ **条件判断支持**：需要支持多条件判断，如品牌为空则使用默认值，尺寸为空则使用默认值等
2. ✅ **组合映射支持**：支持多字段组合（如 长x宽x高），也支持拆分（从组合值中提取单个字段）
3. ✅ **数组字段处理**：支持取全部、指定索引、范围切片、拼接成字符串等多种方式
4. ✅ **单位转换支持**：支持尺寸单位转换（cm↔in、m↔cm）和重量单位转换（kg↔lb、g↔oz）
