# Walmart 平台字段映射配置指南

本文档说明如何在属性字段库中添加和配置 Walmart 平台的字段映射规则。

---

## 概述

属性字段库是一个**全局通用的预设配置**，当加载任意 Walmart 类目的平台属性时，系统会自动根据 `attributeId` 匹配并填充映射类型和值。

### 工作流程

```
1. 在"属性字段库"中预先配置常见字段的映射规则
2. 选择一个 Walmart 类目，点击"加载平台属性"
3. 系统自动从属性字段库匹配 attributeId，填充映射类型和值
4. 用户可以微调后保存该类目的映射配置
```

---

## 映射类型说明

| 映射类型 | 说明 | 适用场景 |
|---------|------|---------|
| `default_value` | 固定默认值 | 所有商品使用相同值，如品牌、产地 |
| `channel_data` | 从渠道数据提取 | 从商品数据中自动提取，如标题、重量 |
| `enum_select` | 枚举选择 | 从平台允许的值中选择，如单位 |
| `auto_generate` | 自动生成 | 根据规则动态生成，如 SKU 前缀 |
| `upc_pool` | UPC 池 | 从 UPC 池自动分配 |

---

## Walmart 常用字段配置参考

### 必填字段（Required）

| attributeId | 字段名称 | 推荐映射类型 | 推荐值/来源 | 说明 |
|-------------|---------|-------------|------------|------|
| `brand` | 品牌 | `default_value` | `nobrand` 或实际品牌 | 无品牌商品填 nobrand |
| `shortDescription` | 简短描述 | `channel_data` | `description` | 从渠道描述提取 |
| `mainImageUrl` | 主图 | `channel_data` | `mainImageUrl` | 商品主图 |
| `productIdType` | 产品ID类型 | `default_value` | `UPC` | 固定为 UPC |
| `productId` | 产品ID (UPC) | `upc_pool` | - | 从 UPC 池获取 |
| `price` | 价格 | `channel_data` | `price` | 从渠道价格提取 |
| `ShippingWeight` | 运输重量 | `channel_data` | `packaging.weight` | 包装重量 |
| `ShippingWeightUnit` | 重量单位 | `default_value` | `lb` | Walmart US 使用磅 |

### 尺寸字段

| attributeId | 字段名称 | 推荐映射类型 | 推荐值/来源 |
|-------------|---------|-------------|------------|
| `shippingLength` | 运输长度 | `channel_data` | `packaging.length` |
| `shippingWidth` | 运输宽度 | `channel_data` | `packaging.width` |
| `shippingHeight` | 运输高度 | `channel_data` | `packaging.height` |
| `shippingLengthUnit` | 长度单位 | `default_value` | `in` |
| `assembledProductWeight` | 组装后重量 | `channel_data` | `productDimensions.weight` |
| `assembledProductLength` | 组装后长度 | `channel_data` | `productDimensions.length` |
| `assembledProductWidth` | 组装后宽度 | `channel_data` | `productDimensions.width` |
| `assembledProductHeight` | 组装后高度 | `channel_data` | `productDimensions.height` |

### 产地和制造商

| attributeId | 字段名称 | 推荐映射类型 | 推荐值/来源 |
|-------------|---------|-------------|------------|
| `countryOfOriginAssembly` | 原产国 | `default_value` | `CN` 或 `China` |
| `manufacturer` | 制造商 | `channel_data` | `manufacturer` |
| `mpn` | 制造商零件号 | `channel_data` | `mpn` |
| `modelNumber` | 型号 | `channel_data` | `model` |

### 商品描述

| attributeId | 字段名称 | 推荐映射类型 | 推荐值/来源 |
|-------------|---------|-------------|------------|
| `productName` | 商品名称 | `channel_data` | `title` |
| `keyFeatures` | 关键特性 | `channel_data` | `bulletPoints` |
| `mainImageUrl` | 主图URL | `channel_data` | `mainImageUrl` |
| `productSecondaryImageURL` | 附图URL | `channel_data` | `imageUrls` |

---

## 渠道数据字段路径

以下是可用的渠道数据字段路径（基于 `StandardProduct` 接口）：

### 基础信息
```
sku                    - SKU
title                  - 商品标题
description            - 商品描述
shortDescription       - 简短描述
bulletPoints           - 五点描述（数组）
bulletPoints[0]        - 第一条五点描述
```

### 产品标识
```
brand                  - 品牌
manufacturer           - 制造商
mpn                    - 制造商零件号
model                  - 型号
upc                    - UPC
ean                    - EAN
gtin                   - GTIN
```

### 外观属性
```
color                  - 颜色
colorFamily            - 颜色系列/颜色家族
material               - 材质
pattern                - 图案/花纹
style                  - 风格
finish                 - 表面处理/饰面
shape                  - 形状
```

### 包装尺寸（运输尺寸）
```
packaging.weight       - 包装重量
packaging.weightUnit   - 重量单位
packaging.length       - 包装长度
packaging.width        - 包装宽度
packaging.height       - 包装高度
packaging.lengthUnit   - 长度单位
```

### 产品尺寸（组装后）
```
productDimensions.weight  - 产品重量
productDimensions.length  - 产品长度
productDimensions.width   - 产品宽度
productDimensions.height  - 产品高度
```

### 图片媒体
```
mainImageUrl           - 主图URL
imageUrls              - 附图URL列表
imageUrls[0]           - 第一张附图
```

### 价格库存
```
price                  - 价格
salePrice              - 促销价
stock                  - 库存数量
```

### 产地
```
placeOfOrigin          - 产地
countryOfOrigin        - 原产国代码
```

### 商品特点
```
characteristics        - 商品特点（数组）
characteristics[0]     - 第一个特点
keyFeatures            - 关键特性
```

---

## 添加新字段的步骤

### 方法一：通过界面添加

1. 进入 **刊登管理 → 类目浏览**
2. 选择平台和国家
3. 点击 **属性字段库** 按钮
4. 点击 **+ 添加字段规则**
5. 填写：
   - **属性ID**：Walmart 的 attributeId（如 `brand`）
   - **属性名称**：显示名称（如 `品牌`）
   - **映射类型**：选择合适的类型
   - **值/来源**：根据映射类型填写
6. 点击 **保存字段库**

### 方法二：通过 API 添加

```bash
# 获取当前默认配置
GET /api/platform-categories/{platformId}/default-mapping?country=US

# 保存默认配置
POST /api/platform-categories/{platformId}/default-mapping?country=US
Content-Type: application/json

{
  "mappingRules": {
    "rules": [
      {
        "attributeId": "brand",
        "attributeName": "品牌",
        "mappingType": "default_value",
        "value": "nobrand",
        "isRequired": true,
        "dataType": "string"
      },
      {
        "attributeId": "ShippingWeight",
        "attributeName": "运输重量",
        "mappingType": "channel_data",
        "value": "packaging.weight",
        "isRequired": true,
        "dataType": "number"
      }
    ]
  }
}
```

---

## 常见配置示例

### 示例 1：品牌字段（固定值）

```json
{
  "attributeId": "brand",
  "attributeName": "品牌",
  "mappingType": "default_value",
  "value": "nobrand",
  "isRequired": true,
  "dataType": "string"
}
```

### 示例 2：重量字段（渠道数据）

```json
{
  "attributeId": "ShippingWeight",
  "attributeName": "运输重量",
  "mappingType": "channel_data",
  "value": "packaging.weight",
  "isRequired": true,
  "dataType": "number"
}
```

### 示例 3：重量单位（枚举选择）

```json
{
  "attributeId": "ShippingWeightUnit",
  "attributeName": "重量单位",
  "mappingType": "enum_select",
  "value": "lb",
  "isRequired": true,
  "dataType": "string",
  "enumValues": ["lb", "kg", "oz", "g"]
}
```

### 示例 4：UPC（UPC池）

```json
{
  "attributeId": "productId",
  "attributeName": "UPC",
  "mappingType": "upc_pool",
  "value": "",
  "isRequired": true,
  "dataType": "string"
}
```

### 示例 5：SKU 前缀（自动生成）

```json
{
  "attributeId": "sellerSku",
  "attributeName": "卖家SKU",
  "mappingType": "auto_generate",
  "value": {
    "ruleType": "sku_prefix",
    "param": "WM-"
  },
  "isRequired": true,
  "dataType": "string"
}
```

### 示例 6：颜色（智能提取）

```json
{
  "attributeId": "color",
  "attributeName": "Color",
  "mappingType": "auto_generate",
  "value": {
    "ruleType": "color_extract",
    "param": ""
  },
  "isRequired": false,
  "dataType": "string"
}
```

### 示例 7：材质（智能提取）

```json
{
  "attributeId": "material",
  "attributeName": "Material",
  "mappingType": "auto_generate",
  "value": {
    "ruleType": "material_extract",
    "param": ""
  },
  "isRequired": false,
  "dataType": "string"
}
```

### 示例 8：多字段回退取值

```json
{
  "attributeId": "productColor",
  "attributeName": "产品颜色",
  "mappingType": "auto_generate",
  "value": {
    "ruleType": "field_with_fallback",
    "param": "color,colorFamily,attributes.color"
  },
  "isRequired": false,
  "dataType": "string"
}
```

---

## 自动生成规则类型

| 规则类型 | 名称 | 说明 | 参数 |
|---------|------|------|------|
| `sku_prefix` | SKU前缀拼接 | 在SKU前添加指定前缀 | 前缀字符串 |
| `sku_suffix` | SKU后缀拼接 | 在SKU后添加指定后缀 | 后缀字符串 |
| `brand_title` | 品牌+标题组合 | 将品牌和标题组合成一个字符串 | 无 |
| `first_characteristic` | 取第一个特点 | 从商品特点列表中取第一个 | 无 |
| `first_bullet_point` | 取第一条五点描述 | 从五点描述列表中取第一条 | 无 |
| `current_date` | 当前日期 | 生成当前日期 | 日期格式 |
| `uuid` | 生成UUID | 生成唯一标识符 | 无 |
| `color_extract` | 智能提取颜色 | 优先从颜色字段取值，否则从标题/描述提取 | 无 |
| `material_extract` | 智能提取材质 | 优先从材质字段取值，否则从标题/描述提取 | 无 |
| `field_with_fallback` | 多字段回退取值 | 按顺序尝试多个字段，返回第一个非空值 | 字段列表(逗号分隔) |

---

## 注意事项

1. **attributeId 大小写**：匹配时不区分大小写，但建议保持与 Walmart API 返回的一致

2. **单位转换**：Walmart US 使用英制单位（lb、in），如果渠道数据是公制单位，需要在导入前转换

3. **必填字段**：确保所有 Walmart 必填字段都有配置，否则刊登会失败

4. **枚举值**：`enum_select` 类型的值必须是平台允许的枚举值之一

5. **数组字段**：如 `bulletPoints`，可以使用索引访问单个元素（如 `bulletPoints[0]`）

---

## 相关文件

- 映射规则接口：`apps/api/src/modules/attribute-mapping/interfaces/mapping-rule.interface.ts`
- 属性解析器：`apps/api/src/modules/attribute-mapping/attribute-resolver.service.ts`
- 标准字段接口：`apps/api/src/adapters/channels/standard-product.interface.ts`
- 设计文档：`.kiro/specs/attribute-mapping/design.md`
