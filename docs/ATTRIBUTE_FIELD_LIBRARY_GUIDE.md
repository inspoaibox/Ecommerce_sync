# 属性字段库配置指南

本文档说明如何扩展和配置属性字段库，实现平台属性的自动映射。

## 概述

属性字段库是一套预定义的属性映射规则，当用户在类目浏览器中点击"加载配置"时，系统会根据 `attributeId` 自动匹配并填充映射规则。

## 文件位置

| 文件 | 说明 |
|------|------|
| `apps/api/src/modules/platform-category/default-mapping-rules.ts` | 属性字段库配置 |
| `apps/api/src/modules/attribute-mapping/attribute-resolver.service.ts` | 提取规则实现 |
| `apps/web/src/pages/listing/CategoryBrowser.tsx` | 前端规则定义 |

## 测试脚本

### 单字段测试（新增字段后使用）

```bash
cd apps/api

# 查看帮助和可用规则
pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts

# 测试指定规则
pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts country_of_origin_textiles_extract

# 测试指定规则和 SKU
pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts color_extract SJ000149AAK
```

### 全量测试（验证所有规则）

```bash
cd apps/api

# 使用默认 SKU (SJ000149AAK)
pnpm exec ts-node -r tsconfig-paths/register scripts/test-all-fields.ts

# 指定 SKU
pnpm exec ts-node -r tsconfig-paths/register scripts/test-all-fields.ts YOUR_SKU
```

## 添加新规则的完整流程

### 步骤 1：确定属性信息

从平台 API 或类目浏览器获取属性信息：

```json
{
  "attributeId": "countryOfOriginTextiles",
  "name": "Country of Origin- Textiles",
  "dataType": "enum",
  "isRequired": false,
  "enumValues": ["USA and Imported", "Imported", "USA", "USA or Imported"]
}
```

### 步骤 2：选择映射类型

| 映射类型 | 说明 | 适用场景 |
|---------|------|---------|
| `default_value` | 固定默认值 | 所有产品都用同一个值 |
| `channel_data` | 从渠道数据映射 | 直接使用渠道字段值 |
| `enum_select` | 枚举选择 | 从枚举列表选择固定值 |
| `auto_generate` | 自动生成 | 需要智能提取或计算 |
| `upc_pool` | UPC池 | 产品标识符 |

### 步骤 3：添加到属性字段库

编辑 `default-mapping-rules.ts`，在 `WALMART_DEFAULT_MAPPING_RULES` 数组中添加：

```typescript
{
  attributeId: 'countryOfOriginTextiles',
  attributeName: 'Country of Origin- Textiles',
  mappingType: 'auto_generate',
  value: {
    ruleType: 'country_of_origin_textiles_extract',
    param: '',
  },
},
```

### 步骤 4：如果是新的自动生成规则

#### 4.1 添加前端规则定义

编辑 `CategoryBrowser.tsx`，在 `AUTO_GENERATE_RULES` 中添加：

```typescript
country_of_origin_textiles_extract: { 
  name: '智能提取纺织品原产国', 
  description: '优先从placeOfOrigin字段匹配，默认Imported' 
},
```

#### 4.2 添加后端实现

编辑 `attribute-resolver.service.ts`：

**1. 在 `resolveAutoGenerate` 的 switch 中添加 case：**

```typescript
case 'country_of_origin_textiles_extract':
  return this.extractCountryOfOriginTextiles(channelAttributes);
```

**2. 实现提取方法：**

```typescript
private extractCountryOfOriginTextiles(channelAttributes: Record<string, any>): string {
  const defaultValue = 'Imported';
  const placeOfOrigin = getNestedValue(channelAttributes, 'placeOfOrigin');
  
  if (!placeOfOrigin) {
    return defaultValue;
  }
  
  const origin = String(placeOfOrigin).toLowerCase().trim();
  const usaKeywords = ['usa', 'us', 'united states', 'america'];
  const isUSA = usaKeywords.some(keyword => origin.includes(keyword));
  
  if (isUSA) {
    return 'USA';
  }
  
  return defaultValue;
}
```

### 步骤 5：测试新规则

```bash
cd apps/api
pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts country_of_origin_textiles_extract
```

## 映射类型详解

### 1. default_value - 默认值

```typescript
{
  attributeId: 'brand',
  attributeName: 'Brand',
  mappingType: 'default_value',
  value: 'Unbranded',
}
```

### 2. channel_data - 渠道数据映射

```typescript
{
  attributeId: 'productName',
  attributeName: 'Product Name',
  mappingType: 'channel_data',
  value: 'title',
}
```

**可用的渠道字段路径：**

| 字段路径 | 说明 |
|---------|------|
| `title` | 商品标题 |
| `description` | 商品描述 |
| `bulletPoints` | 五点描述 |
| `sku` | SKU |
| `price` | 价格 |
| `stock` | 库存 |
| `mainImageUrl` | 主图URL |
| `imageUrls` | 图片列表 |
| `color` | 颜色 |
| `material` | 材质 |
| `keywords` | 关键词 |
| `supplier` | 供货商 |
| `placeOfOrigin` | 产地 |
| `productLength/Width/Height/Weight` | 产品尺寸 |
| `packageLength/Width/Height/Weight` | 包装尺寸 |
| `customAttributes.xxx` | 自定义属性 |

### 3. enum_select - 枚举选择

```typescript
{
  attributeId: 'condition',
  attributeName: 'Condition',
  mappingType: 'enum_select',
  value: 'New',
}
```

### 4. auto_generate - 自动生成

```typescript
{
  attributeId: 'features',
  attributeName: 'Additional Features',
  mappingType: 'auto_generate',
  value: {
    ruleType: 'features_extract',
    param: '',
  },
}
```

## 可用的自动生成规则

### 智能提取规则（使用 NLP）

| 规则类型 | 说明 | 返回类型 |
|---------|------|---------|
| `color_extract` | 提取产品颜色 | string |
| `material_extract` | 提取产品材质 | string |
| `location_extract` | 提取使用场景 Indoor/Outdoor | string |
| `piece_count_extract` | 提取产品数量 | number |
| `seating_capacity_extract` | 提取座位容量 | number |
| `collection_extract` | 生成产品系列名 | string |
| `color_category_extract` | 颜色分类 | string[] |
| `home_decor_style_extract` | 家居风格 | string[] |
| `items_included_extract` | 包含物品列表 | string[] |
| `features_extract` | 附加功能列表（NLP提取） | string[] |
| `pattern_extract` | 图案/花纹 | string[] |
| `country_of_origin_extract` | 原产国，优先从placeOfOrigin匹配，默认CN - China | string |
| `country_of_origin_textiles_extract` | 纺织品原产国，优先从placeOfOrigin匹配，美国返回USA，其他返回Imported | string |
| `max_load_weight_extract` | 最大承重 | object |
| `leg_color_extract` | 腿部颜色 | string |
| `leg_material_extract` | 腿部材料 | string |
| `leg_finish_extract` | 腿部表面处理 | string |
| `seat_material_extract` | 座椅材料 | string |
| `seat_color_extract` | 座椅颜色 | string |
| `seat_height_extract` | 座椅高度 | object |
| `seat_back_height_extract` | 靠背高度 | object |
| `upholstered_extract` | 是否软包 | Yes/No |
| `electronics_indicator_extract` | 是否含电子元件 | Yes/No |
| `living_room_set_type_extract` | 客厅套装类型 | string |
| `net_content_statement_extract` | 净含量声明 | string |
| `product_line_from_category` | 产品线（从类目提取） | string[] |

### 本地处理规则

| 规则类型 | 说明 | 参数 |
|---------|------|------|
| `sku_prefix` | SKU前缀拼接 | 前缀字符串 |
| `sku_suffix` | SKU后缀拼接 | 后缀字符串 |
| `brand_title` | 品牌+标题组合 | - |
| `first_bullet_point` | 取第一条五点描述 | - |
| `current_date` | 当前日期 | 格式如 YYYY-MM-DD |
| `uuid` | 生成UUID | - |
| `price_calculate` | 计算售价 | - |
| `shipping_weight_extract` | 运输重量（转换为lbs） | 默认值 |
| `date_offset` | 日期偏移（天） | 天数（负数往前） |
| `date_offset_years` | 日期偏移（年） | 年数 |
| `mpn_from_sku` | SKU转MPN | - |
| `field_with_fallback` | 多字段回退 | 字段列表,逗号分隔 |

## 当前属性字段库统计

| 类型 | 数量 |
|------|------|
| auto_generate | 33 |
| channel_data | 9 |
| default_value | 9 |
| enum_select | 18 |
| upc_pool | 1 |
| **总计** | **70** |

## 数据提取来源

提取规则从以下四个来源获取数据：

1. **产品标题** (`title`) - 主要信息来源
2. **五点描述** (`bulletPoints`) - 功能特性来源
3. **产品描述** (`description`) - 详细信息来源（自动清理HTML）
4. **渠道属性** - 如 `color`, `material`, `placeOfOrigin` 等

## 注意事项

1. **attributeId 必须精确匹配** - 大小写敏感
2. **数组类型字段** - 返回 `string[]` 格式
3. **数字类型字段** - 确保返回 number 而非 string
4. **枚举类型字段** - 返回值必须在枚举列表中
5. **undefined 返回值** - 表示无法提取，不会传递给平台
6. **HTML 清理** - `features_extract` 等规则会自动清理 HTML 标签

## 完整示例

### 示例：添加纺织品原产国规则

**需求：** 优先从 `placeOfOrigin` 匹配，默认返回 `Imported`

**步骤 1：添加到属性字段库**

```typescript
// default-mapping-rules.ts
{
  attributeId: 'countryOfOriginTextiles',
  attributeName: 'Country of Origin- Textiles',
  mappingType: 'auto_generate',
  value: {
    ruleType: 'country_of_origin_textiles_extract',
    param: '',
  },
},
```

**步骤 2：添加前端规则定义**

```typescript
// CategoryBrowser.tsx
country_of_origin_textiles_extract: { 
  name: '智能提取纺织品原产国', 
  description: '优先从placeOfOrigin字段匹配，默认Imported' 
},
```

**步骤 3：添加后端实现**

```typescript
// attribute-resolver.service.ts

// 在 resolveAutoGenerate switch 中添加
case 'country_of_origin_textiles_extract':
  return this.extractCountryOfOriginTextiles(channelAttributes);

// 实现方法
private extractCountryOfOriginTextiles(channelAttributes: Record<string, any>): string {
  const defaultValue = 'Imported';
  const placeOfOrigin = getNestedValue(channelAttributes, 'placeOfOrigin');
  
  if (!placeOfOrigin) {
    return defaultValue;
  }
  
  const origin = String(placeOfOrigin).toLowerCase().trim();
  const usaKeywords = ['usa', 'us', 'united states', 'america'];
  
  if (usaKeywords.some(keyword => origin.includes(keyword))) {
    return 'USA';
  }
  
  return defaultValue;
}
```

**步骤 4：测试**

```bash
cd apps/api
pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts country_of_origin_textiles_extract
```

**预期输出：**

```
============================================================
单字段测试: country_of_origin_textiles_extract
测试 SKU: SJ000149AAK
============================================================

✅ 找到商品: VIBE HAUS Modern Light Luxury TV Stand...

📋 规则信息:
   - attributeId: countryOfOriginTextiles
   - attributeName: Country of Origin- Textiles
   - mappingType: auto_generate
   - value: {"ruleType":"country_of_origin_textiles_extract","param":""}

============================================================
📊 提取结果
============================================================

✅ 成功提取
   值: "Imported"
   类型: string

⏱️  耗时: 5ms
```

## 规则详细说明

### 原产国相关规则

#### country_of_origin_extract
- **用途**: 提取原产国（Walmart US 格式）
- **返回格式**: `XX - Country Name`（如 `CN - China`）
- **提取逻辑**:
  1. 优先从 `placeOfOrigin` 字段匹配
  2. 支持中英文国家名称匹配
  3. 默认返回 `CN - China`
- **枚举值示例**: `CN - China`, `US - United States`, `VN - Vietnam`

#### country_of_origin_textiles_extract
- **用途**: 提取纺织品原产国
- **返回格式**: 枚举值
- **枚举值**: `USA and Imported`, `Imported`, `USA`, `USA or Imported`
- **提取逻辑**:
  1. 优先从 `placeOfOrigin` 字段匹配
  2. 包含 USA/US/United States → 返回 `USA`
  3. 同时包含 USA 和进口成分 → 返回 `USA and Imported`
  4. 其他情况 → 返回 `Imported`（默认）

### 颜色相关规则

#### color_extract
- **用途**: 提取产品颜色
- **提取逻辑**:
  1. 优先从 `color` 字段取值
  2. 其次从 `customAttributes.colorFamily` 取值
  3. 最后从标题/描述中提取颜色关键词
- **支持颜色**: black, white, brown, gray, beige, walnut, oak 等

#### color_category_extract
- **用途**: 提取颜色分类（Walmart 枚举）
- **返回格式**: `string[]`
- **提取逻辑**:
  1. 从 `color` 字段匹配最接近的 Walmart 颜色枚举
  2. 支持颜色同义词映射（如 walnut → Brown）
  3. 默认返回 `Multicolor`
- **枚举值**: White, Black, Brown, Gray, Beige, Blue, Green, Red, Multicolor 等

### 功能特性规则

#### features_extract
- **用途**: 提取产品附加功能
- **返回格式**: `string[]`（最多10个）
- **提取逻辑**:
  1. 优先从 `bulletPoints` 提取【】中的内容
  2. 使用 NLP 从描述中提取形容词+名词短语
  3. 匹配功能关键词（waterproof, adjustable 等）
  4. 自动清理 HTML 标签

#### electronics_indicator_extract
- **用途**: 判断是否含电子元件
- **返回格式**: `Yes` 或 `No`
- **提取逻辑**:
  1. 从标题/描述中匹配电子元件关键词
  2. 关键词: `usb port`, `led light`, `bluetooth`, `power outlet` 等
  3. 注意: 避免误匹配 "light luxury"（轻奢）
  4. 默认返回 `No`

### 家具相关规则

#### items_included_extract
- **用途**: 提取套装包含物品
- **返回格式**: `string[]`
- **提取逻辑**:
  1. 匹配 "X and Y Set of N" 模式
  2. 识别同义词并合并（TV Stand = TV Console）
  3. 无法提取则返回 `undefined`

#### upholstered_extract
- **用途**: 判断是否软包家具
- **返回格式**: `Yes` 或 `No`
- **提取逻辑**:
  1. 包含 fabric/leather/velvet → `Yes`
  2. 包含 table/cabinet/desk → `No`
  3. 默认返回 `No`
