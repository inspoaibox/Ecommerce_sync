# 属性映射字段拓展开发指南

本文档详细说明如何为 Walmart 平台拓展不同映射类型的属性字段。

## 目录

- [概述](#概述)
- [映射类型说明](#映射类型说明)
- [拓展步骤](#拓展步骤)
- [各类型拓展示例](#各类型拓展示例)
- [相关文件](#相关文件)

---

## 概述

属性映射系统支持 5 种映射类型，用于将渠道数据转换为 Walmart 平台属性：

| 映射类型 | 说明 | 适用场景 |
|---------|------|---------|
| `default_value` | 固定默认值 | 所有产品使用相同值的字段 |
| `channel_data` | 渠道数据映射 | 直接从渠道数据取值 |
| `enum_select` | 枚举选择 | 从预定义选项中选择固定值 |
| `auto_generate` | 自动生成 | 需要智能提取或计算的字段 |
| `upc_pool` | UPC 池 | 产品标识符（UPC/EAN/GTIN） |

---

## 映射类型说明

### 1. default_value（默认值）

直接返回配置的固定值，适用于所有产品都使用相同值的场景。

**特点：**
- 最简单的映射类型
- 值在配置时确定，运行时不变
- 支持字符串、数字、布尔值

**示例字段：** `warrantyText`, `netContent`, `brand`

### 2. channel_data（渠道数据）

从渠道数据中按字段路径取值，支持嵌套路径。

**特点：**
- 直接映射渠道数据字段
- 支持点号分隔的嵌套路径（如 `dimensions.width`）
- 值来自产品的 `channelAttributes`

**示例字段：** `productName` → `title`, `mainImageUrl` → `mainImageUrl`

### 3. enum_select（枚举选择）

从 Walmart Schema 定义的枚举值中选择一个固定值。

**特点：**
- 值必须是 Schema 中定义的有效枚举值
- 适用于有限选项的字段
- 前端会显示下拉选择框

**示例字段：** `condition` → `New`, `isProp65WarningRequired` → `No`

### 4. auto_generate（自动生成）

根据规则从渠道数据中智能提取或计算值。

**特点：**
- 最灵活的映射类型
- 需要在后端实现提取逻辑
- 需要在前端注册规则显示名称

**示例字段：** `color`, `material`, `pieceCount`, `collection`

### 5. upc_pool（UPC 池）

从 UPC 池中自动分配产品标识符。

**特点：**
- 特殊处理，自动分配 UPC/EAN/GTIN
- 返回 `{ productIdType, productId }` 格式
- 需要预先导入 UPC 码

---

## 拓展步骤

### 步骤 1：确定映射类型

根据字段特性选择合适的映射类型：

```
字段值是否固定？
├── 是 → 所有产品相同？
│   ├── 是 → default_value
│   └── 否（从枚举选择）→ enum_select
└── 否 → 值来源？
    ├── 直接从渠道数据取 → channel_data
    ├── 需要智能提取/计算 → auto_generate
    └── 产品标识符 → upc_pool
```

### 步骤 2：添加默认映射规则

编辑 `apps/api/src/modules/platform-category/default-mapping-rules.ts`：

```typescript
export const WALMART_DEFAULT_MAPPING_RULES: DefaultMappingConfig[] = [
  // ... 现有规则

  // 新增规则
  {
    attributeId: 'fieldName',        // 必须与 Walmart Schema 属性名一致
    attributeName: 'Field Name',      // 显示名称
    mappingType: 'xxx',               // 映射类型
    value: '...',                     // 值或配置
  },
];
```

### 步骤 3：实现后端逻辑（仅 auto_generate）

如果是 `auto_generate` 类型，需要在 `attribute-resolver.service.ts` 中：

1. 在 `resolveAutoGenerate` 方法的 switch 中添加 case
2. 实现对应的提取方法

### 步骤 4：注册前端规则（仅 auto_generate）

编辑 `apps/web/src/pages/listing/CategoryBrowser.tsx`：

```typescript
const AUTO_GENERATE_RULES: Record<string, { name: string; description: string }> = {
  // ... 现有规则
  new_rule: { name: '规则名称', description: '规则描述' },
};
```

### 步骤 5：重启后端服务

```bash
# 重启后端
pnpm --filter api dev
```

### 步骤 6：测试验证

1. 进入 `/listing/categories` 页面
2. 选择类目，点击"加载平台属性"（勾选强制刷新）
3. 点击"加载配置"应用默认规则
4. 验证新字段是否正确显示和配置

---

## 各类型拓展示例

### 示例 1：default_value（默认值）

**需求：** 添加 `warrantyText` 字段，固定值为保修声明文本

**步骤：**

1. 在 `default-mapping-rules.ts` 添加：

```typescript
{
  attributeId: 'warrantyText',
  attributeName: 'Warranty Text',
  mappingType: 'default_value',
  value: 'This warranty does not cover damages caused by misuse, drops, or human error.',
},
```

完成！无需其他修改。

---

### 示例 2：channel_data（渠道数据）

**需求：** 添加 `productSecondaryImageURL` 字段，从渠道的附图列表获取

**步骤：**

1. 在 `default-mapping-rules.ts` 添加：

```typescript
{
  attributeId: 'productSecondaryImageURL',
  attributeName: 'Additional Image URL',
  mappingType: 'channel_data',
  value: 'imageUrls',  // 渠道数据中的字段路径
},
```

**支持的渠道数据字段：**
- `title` - 产品标题
- `description` - 产品描述
- `bulletPoints` - 卖点列表
- `mainImageUrl` - 主图 URL
- `imageUrls` - 附图 URL 列表
- `sku` - SKU
- `price` - 价格
- `color` - 颜色
- `material` - 材质
- `weight` / `weightKg` - 重量
- `dimensions.width/height/depth` - 尺寸

---

### 示例 3：enum_select（枚举选择）

**需求：** 添加 `ageGroup` 字段，固定选择 "Adult"

**步骤：**

1. 在 `default-mapping-rules.ts` 添加：

```typescript
{
  attributeId: 'ageGroup',
  attributeName: 'Age Group',
  mappingType: 'enum_select',
  value: 'Adult',  // 必须是 Schema 中定义的有效枚举值
},
```

**注意：** `value` 必须是 Walmart Schema 中该字段定义的有效枚举值之一。

---

### 示例 4：auto_generate（自动生成）

**需求：** 添加 `homeDecorStyle` 字段，从标题/描述智能提取家居风格

**步骤：**

#### 4.1 添加默认映射规则

```typescript
// default-mapping-rules.ts
{
  attributeId: 'homeDecorStyle',
  attributeName: 'Home Decor Style',
  mappingType: 'auto_generate',
  value: {
    ruleType: 'home_decor_style_extract',  // 规则类型标识
    param: 'Minimalist',                    // 默认值参数
  },
},
```

#### 4.2 在 resolveAutoGenerate 添加 case

```typescript
// attribute-resolver.service.ts - resolveAutoGenerate 方法
case 'home_decor_style_extract':
  return this.extractHomeDecorStyle(param, channelAttributes);
```

#### 4.3 实现提取方法

```typescript
// attribute-resolver.service.ts
/**
 * 提取家居装饰风格
 * @param param 默认值
 */
private extractHomeDecorStyle(
  param: string | undefined,
  channelAttributes: Record<string, any>,
): string[] {
  const defaultValue = param || 'Minimalist';

  // Walmart 支持的风格枚举值
  const walmartStyles = [
    'Modern', 'Contemporary', 'Traditional', 'Farmhouse',
    'Industrial', 'Mid-Century', 'Scandinavian', 'Bohemian',
    'Coastal', 'Rustic/Lodge', 'Minimalist', 'Glam',
  ];

  // 风格关键词映射
  const styleMapping: Record<string, string> = {
    'modern': 'Modern',
    'contemporary': 'Contemporary',
    'farmhouse': 'Farmhouse',
    'industrial': 'Industrial',
    'mid-century': 'Mid-Century',
    'scandinavian': 'Scandinavian',
    'bohemian': 'Bohemian',
    'boho': 'Bohemian',
    'coastal': 'Coastal',
    'rustic': 'Rustic/Lodge',
    'minimalist': 'Minimalist',
    // ... 更多映射
  };

  const title = getNestedValue(channelAttributes, 'title') || '';
  const description = getNestedValue(channelAttributes, 'description') || '';
  const text = `${title} ${description}`.toLowerCase();

  // 匹配风格关键词
  for (const [keyword, style] of Object.entries(styleMapping)) {
    if (text.includes(keyword)) {
      return [style];  // 返回数组格式
    }
  }

  return [defaultValue];
}
```

#### 4.4 注册前端规则

```typescript
// CategoryBrowser.tsx
const AUTO_GENERATE_RULES = {
  // ... 现有规则
  home_decor_style_extract: {
    name: '智能提取家居风格',
    description: '从标题/描述提取家居装饰风格，如Modern、Farmhouse等',
  },
};
```

---

### 示例 5：复杂 auto_generate（计算类）

**需求：** 添加 `price` 字段，根据店铺配置的价格倍率计算

**步骤：**

#### 5.1 添加默认映射规则

```typescript
{
  attributeId: 'price',
  attributeName: 'Selling Price',
  mappingType: 'auto_generate',
  value: {
    ruleType: 'price_calculate',
    param: '',
  },
},
```

#### 5.2 实现计算方法

```typescript
private async calculatePrice(
  channelAttributes: Record<string, any>,
  context: ResolveContext,
): Promise<number | undefined> {
  // 1. 获取店铺同步配置
  const shop = await this.prisma.shop.findUnique({
    where: { id: context.shopId },
    select: { syncConfig: true },
  });
  
  const priceConfig = shop?.syncConfig?.price || {
    defaultMultiplier: 1.0,
    defaultAdjustment: 0,
  };

  // 2. 获取基础价格
  const productPrice = Number(channelAttributes.price) || 0;
  const shippingFee = Number(channelAttributes.shippingFee) || 0;
  const basePrice = productPrice + shippingFee;

  // 3. 应用倍率计算
  const finalPrice = basePrice * priceConfig.defaultMultiplier 
                   + priceConfig.defaultAdjustment;

  return Math.round(finalPrice * 100) / 100;
}
```

---

## 相关文件

| 文件 | 说明 |
|-----|------|
| `apps/api/src/modules/platform-category/default-mapping-rules.ts` | 默认映射规则配置 |
| `apps/api/src/modules/attribute-mapping/attribute-resolver.service.ts` | 属性解析服务（auto_generate 实现） |
| `apps/api/src/modules/attribute-mapping/interfaces/mapping-rule.interface.ts` | 映射规则接口定义 |
| `apps/web/src/pages/listing/CategoryBrowser.tsx` | 类目属性映射前端页面 |
| `apps/api/src/adapters/channels/standard-product.interface.ts` | 标准产品数据接口 |

---

## 常见问题

### Q: 新增字段后不显示？

1. 确保 `attributeId` 与 Walmart Schema 属性名完全一致（区分大小写）
2. 重启后端服务
3. 在页面点击"加载平台属性"时勾选"强制刷新"

### Q: auto_generate 规则不生效？

1. 检查 `resolveAutoGenerate` 中是否添加了对应的 case
2. 检查方法是否正确实现并返回值
3. 检查前端 `AUTO_GENERATE_RULES` 是否注册

### Q: 如何调试提取逻辑？

在提取方法中添加日志：

```typescript
this.logger.debug(`[extractXxx] input: ${JSON.stringify(channelAttributes)}`);
this.logger.debug(`[extractXxx] result: ${result}`);
```

### Q: 字段值格式不正确？

检查 `convertValueByDataType` 方法，确保数据类型转换正确：
- `array` 类型需要返回数组
- `number` 类型需要返回数字
- `measurement` 类型需要返回 `{ measure, unit }` 对象
