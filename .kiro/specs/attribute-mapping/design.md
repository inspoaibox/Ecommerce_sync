# 属性映射配置系统设计文档

## Overview

属性映射配置系统用于定义商品数据如何从渠道标准字段（`channelAttributes`）映射到目标平台的属性字段。系统需要支持多种映射类型，包括固定值、渠道数据提取、枚举选择、自动生成、UPC池获取，以及高级规则如条件判断、字段组合、单位转换等。

### 设计目标

1. **与现有系统深度集成**：复用现有的 `CategoryAttributeMapping` 模型和 `PlatformCategoryService`
2. **可扩展性**：支持新增映射类型和规则，无需修改核心代码
3. **类型安全**：使用 TypeScript 接口定义所有数据结构
4. **高性能**：属性解析在 100ms 内完成，支持批量处理

---

## Architecture

### 系统架构图

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           前端 (React + Ant Design)                      │
├─────────────────────────────────────────────────────────────────────────┤
│  CategoryBrowser.tsx  │  AttributeMappingEditor.tsx  │  MappingPreview  │
└───────────┬───────────────────────┬───────────────────────┬─────────────┘
            │                       │                       │
            ▼                       ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                           API 层 (NestJS)                                │
├─────────────────────────────────────────────────────────────────────────┤
│  PlatformCategoryController  │  ListingController  │  MappingController │
└───────────┬───────────────────────┬───────────────────────┬─────────────┘
            │                       │                       │
            ▼                       ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          服务层 (Services)                               │
├─────────────────────────────────────────────────────────────────────────┤
│  PlatformCategoryService  │  ListingService  │  AttributeResolverService│
└───────────┬───────────────────────┬───────────────────────┬─────────────┘
            │                       │                       │
            ▼                       ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          核心模块                                        │
├─────────────────────────────────────────────────────────────────────────┤
│  AttributeResolver  │  RuleExecutor  │  UnitConverter  │  UpcService    │
└───────────┬───────────────────────┬───────────────────────┬─────────────┘
            │                       │                       │
            ▼                       ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          数据层 (Prisma)                                 │
├─────────────────────────────────────────────────────────────────────────┤
│  CategoryAttributeMapping  │  PlatformAttribute  │  UpcPool             │
└─────────────────────────────────────────────────────────────────────────┘
```

### 数据流

```
商品导入/同步时：
┌──────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│ ProductPool  │───▶│ ListingProduct   │───▶│ AttributeResolver   │
│ (渠道数据)    │    │ (channelAttrs)   │    │ (解析映射规则)       │
└──────────────┘    └──────────────────┘    └──────────┬──────────┘
                                                       │
                    ┌──────────────────┐               │
                    │ CategoryAttribute│◀──────────────┘
                    │ Mapping (规则)   │
                    └──────────────────┘
                                                       │
                                                       ▼
                                            ┌─────────────────────┐
                                            │ platformAttributes  │
                                            │ (平台属性值)         │
                                            └─────────────────────┘
```

---

## Components and Interfaces

### 1. 映射规则数据结构

```typescript
// apps/api/src/modules/attribute-mapping/interfaces/mapping-rule.interface.ts

/**
 * 映射类型枚举（5种基础类型）
 */
export type MappingType = 
  | 'default_value'    // 固定默认值
  | 'channel_data'     // 从渠道数据提取
  | 'enum_select'      // 选择枚举值
  | 'auto_generate'    // 自动生成（包含多种子规则）
  | 'upc_pool';        // 从UPC池获取

/**
 * 基础映射规则
 */
export interface BaseMappingRule {
  attributeId: string;           // 平台属性ID
  attributeName: string;         // 平台属性名称
  mappingType: MappingType;      // 映射类型
  isRequired: boolean;           // 是否必填
  dataType: string;              // 数据类型
}

/**
 * 默认值规则
 */
export interface DefaultValueRule extends BaseMappingRule {
  mappingType: 'default_value';
  value: string | number | boolean;
}

/**
 * 渠道数据规则
 */
export interface ChannelDataRule extends BaseMappingRule {
  mappingType: 'channel_data';
  value: string;  // 字段路径，如 'brand', 'packaging.weight'
}

/**
 * 枚举选择规则
 */
export interface EnumSelectRule extends BaseMappingRule {
  mappingType: 'enum_select';
  value: string;           // 选中的枚举值
  enumValues?: string[];   // 可选枚举值列表
}

/**
 * 自动生成规则
 * 
 * value 为 JSON 配置，具体结构根据规则类型不同而不同
 * 后续可扩展支持更多规则类型（条件判断、字段组合、单位转换等）
 */
export interface AutoGenerateRule extends BaseMappingRule {
  mappingType: 'auto_generate';
  value: Record<string, any>;  // 灵活的 JSON 配置，便于后续扩展
}

/**
 * UPC池规则
 */
export interface UpcPoolRule extends BaseMappingRule {
  mappingType: 'upc_pool';
  value: '';  // 空字符串
}

/**
 * 映射规则联合类型
 */
export type MappingRule = 
  | DefaultValueRule
  | ChannelDataRule
  | EnumSelectRule
  | AutoGenerateRule
  | UpcPoolRule;

/**
 * 映射规则集合
 */
export interface MappingRulesConfig {
  rules: MappingRule[];
  version?: string;
  updatedAt?: string;
}
```

### 2. 属性解析器服务

```typescript
// apps/api/src/modules/attribute-mapping/attribute-resolver.service.ts

import { Injectable } from '@nestjs/common';
import { UpcService } from '@/modules/upc/upc.service';
import { MappingRule, MappingRulesConfig } from './interfaces/mapping-rule.interface';
import { getNestedValue } from '@/adapters/channels/standard-product.utils';

@Injectable()
export class AttributeResolverService {
  constructor(private upcService: UpcService) {}

  /**
   * 解析映射规则，生成平台属性
   */
  async resolveAttributes(
    mappingRules: MappingRulesConfig,
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): Promise<Record<string, any>> {
    const result: Record<string, any> = {};
    
    for (const rule of mappingRules.rules) {
      const value = await this.resolveRule(rule, channelAttributes, context);
      if (value !== undefined && value !== null && value !== '') {
        result[rule.attributeId] = value;
      }
    }
    
    return result;
  }

  /**
   * 解析单条规则
   */
  private async resolveRule(
    rule: MappingRule,
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): Promise<any> {
    switch (rule.mappingType) {
      case 'default_value':
        return rule.value;
        
      case 'channel_data':
        return getNestedValue(channelAttributes, rule.value);
        
      case 'enum_select':
        return rule.value;
        
      case 'auto_generate':
        return this.resolveAutoGenerate(rule.value, channelAttributes, context);
        
      case 'upc_pool':
        return this.upcService.autoAssignUpc(context.productSku, context.shopId);
        
      default:
        return undefined;
    }
  }

  /**
   * 解析自动生成规则
   * 
   * 根据 value.ruleType 执行不同的生成逻辑
   * 后续可扩展支持更多规则类型
   */
  private resolveAutoGenerate(
    config: Record<string, any>,
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): any {
    const ruleType = config.ruleType;
    
    // 基础规则实现，后续可扩展
    switch (ruleType) {
      case 'sku_prefix':
        return `${config.param || ''}${context.productSku}`;
      case 'sku_suffix':
        return `${context.productSku}${config.param || ''}`;
      default:
        // 其他规则类型后续实现
        return undefined;
    }
  }
}

export interface ResolveContext {
  shopId?: string;
  productSku?: string;
  platformId?: string;
  country?: string;
}
```

### 3. 单位转换器

```typescript
// apps/api/src/modules/attribute-mapping/unit-converter.ts

export const CONVERSION_FACTORS = {
  length: {
    m: 1,
    cm: 0.01,
    in: 0.0254,
  },
  weight: {
    kg: 1,
    g: 0.001,
    lb: 0.453592,
    oz: 0.0283495,
  },
} as const;

export function convertUnit(
  value: number,
  fromUnit: string,
  toUnit: string,
  precision: number = 2,
): number {
  const type = getUnitType(fromUnit);
  const factors = CONVERSION_FACTORS[type];
  
  const baseValue = value * factors[fromUnit as keyof typeof factors];
  const result = baseValue / factors[toUnit as keyof typeof factors];
  
  return Number(result.toFixed(precision));
}

function getUnitType(unit: string): 'length' | 'weight' {
  if (['m', 'cm', 'in'].includes(unit)) return 'length';
  if (['kg', 'g', 'lb', 'oz'].includes(unit)) return 'weight';
  throw new Error(`Unknown unit: ${unit}`);
}
```

---

## Data Models

### 现有模型（已存在于 schema.prisma）

```prisma
model CategoryAttributeMapping {
  id              String   @id @default(uuid())
  platformId      String   @map("platform_id")
  categoryId      String   @map("category_id")
  country         String   @default("US") @db.VarChar(10)
  mappingRules    Json     @map("mapping_rules")  // MappingRulesConfig
  createdAt       DateTime @default(now()) @map("created_at")
  updatedAt       DateTime @updatedAt @map("updated_at")
  
  platform        Platform @relation(fields: [platformId], references: [id], onDelete: Cascade)
  
  @@unique([platformId, country, categoryId])
  @@map("category_attribute_mappings")
}
```

### mappingRules JSON 结构示例

```json
{
  "version": "1.0",
  "rules": [
    {
      "attributeId": "brand",
      "attributeName": "品牌",
      "mappingType": "channel_data",
      "isRequired": true,
      "dataType": "string",
      "value": "brand"
    },
    {
      "attributeId": "productWeight",
      "attributeName": "商品重量",
      "mappingType": "channel_data",
      "isRequired": true,
      "dataType": "number",
      "value": "packaging.weight"
    },
    {
      "attributeId": "sellerSku",
      "attributeName": "卖家SKU",
      "mappingType": "auto_generate",
      "isRequired": true,
      "dataType": "string",
      "value": {
        "ruleType": "sku_prefix",
        "param": "WM-"
      }
    },
    {
      "attributeId": "mpn",
      "attributeName": "制造商零件号",
      "mappingType": "channel_data",
      "isRequired": false,
      "dataType": "string",
      "value": "mpn"
    },
    {
      "attributeId": "weightUnit",
      "attributeName": "重量单位",
      "mappingType": "enum_select",
      "isRequired": true,
      "dataType": "string",
      "value": "lb",
      "enumValues": ["lb", "kg", "oz", "g"]
    },
    {
      "attributeId": "countryOfOrigin",
      "attributeName": "原产国",
      "mappingType": "default_value",
      "isRequired": true,
      "dataType": "string",
      "value": "CN"
    },
    {
      "attributeId": "upc",
      "attributeName": "UPC",
      "mappingType": "upc_pool",
      "isRequired": true,
      "dataType": "string",
      "value": ""
    }
  ]
}
```

---


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Default Value Resolution
*For any* default_value mapping rule with a configured value V, resolving the rule should return exactly V.
**Validates: Requirements 1.3**

### Property 2: Channel Data Path Extraction
*For any* channel_data mapping rule with path P and channelAttributes object O, if path P exists in O, the resolved value should equal getNestedValue(O, P).
**Validates: Requirements 2.4**

### Property 3: Missing Path Returns Undefined
*For any* channel_data mapping rule with path P and channelAttributes object O, if path P does not exist in O, the resolved value should be undefined.
**Validates: Requirements 2.5**

### Property 4: Nested Path Support
*For any* valid nested path like 'a.b.c' or 'a.b[0].c', the getNestedValue function should correctly traverse the object structure and return the value at that path.
**Validates: Requirements 2.3**

### Property 5: Enum Select Direct Return
*For any* enum_select mapping rule with selected value V, resolving the rule should return exactly V.
**Validates: Requirements 3.4**

### Property 6: Category-Specific Enum Values
*For any* platform attribute that exists in multiple categories, the enumValues returned should be specific to the requested category.
**Validates: Requirements 3.3**

### Property 7: Auto Generate Rule Execution
*For any* auto_generate rule with type T and parameter P, the resolved value should match the expected output of rule T applied to the input data with parameter P.
**Validates: Requirements 4.4**

### Property 8: UPC Pool Assignment
*For any* upc_pool mapping rule, if the UPC pool has available UPCs, the resolved value should be a valid 12-digit UPC code.
**Validates: Requirements 5.2**

### Property 9: UPC Marked As Used
*For any* successful UPC pool resolution, the assigned UPC should be marked as used in the database with the correct productSku and shopId.
**Validates: Requirements 5.3**

### Property 10: Mapping Uniqueness
*For any* combination of (platformId, country, categoryId), there should be at most one CategoryAttributeMapping record.
**Validates: Requirements 6.3**

### Property 11: Category Mapping Independence
*For any* two different categories C1 and C2, updating the mapping for C1 should not affect the mapping for C2.
**Validates: Requirements 6.4**

### Property 12: Auto Generate Extensibility
*For any* auto_generate rule with ruleType T, the resolver should correctly dispatch to the corresponding rule handler.
**Validates: Requirements 4.5 (生成规则可扩展)**

> **注意**：条件判断、字段组合、单位转换等高级规则将作为 auto_generate 的子规则在后续迭代中实现，届时补充相应的正确性属性。

---

## Error Handling

### 错误类型

| 错误类型 | 场景 | 处理方式 |
|---------|------|---------|
| `PathNotFoundError` | 渠道数据路径不存在 | 返回 undefined，不中断处理 |
| `UpcPoolEmptyError` | UPC 池为空 | 抛出异常，记录错误日志 |
| `InvalidRuleError` | 规则配置无效 | 跳过该规则，记录警告日志 |
| `UnitConversionError` | 不支持的单位转换 | 抛出异常，提示用户 |
| `CategoryNotFoundError` | 类目不存在 | 抛出 NotFoundException |

### 错误处理策略

```typescript
interface ResolveResult {
  success: boolean;
  attributes: Record<string, any>;
  errors: ResolveError[];
  warnings: string[];
}

interface ResolveError {
  attributeId: string;
  attributeName: string;
  errorType: string;
  message: string;
}
```

---

## Testing Strategy

### 单元测试

使用 Jest 进行单元测试，覆盖以下模块：

1. **AttributeResolverService**
   - 各映射类型的解析逻辑
   - 错误处理和边界情况

2. **UnitConverter**
   - 所有支持的单位转换
   - 精度控制

3. **getNestedValue**
   - 简单路径
   - 嵌套路径
   - 数组索引
   - 不存在的路径

### 属性测试

使用 **fast-check** 库进行属性测试：

```typescript
import * as fc from 'fast-check';

// Property 1: Default Value Resolution
describe('AttributeResolver', () => {
  it('should return exact default value for default_value rules', () => {
    fc.assert(
      fc.property(
        fc.string(),
        (value) => {
          const rule: DefaultValueRule = {
            attributeId: 'test',
            attributeName: 'Test',
            mappingType: 'default_value',
            isRequired: false,
            dataType: 'string',
            value,
          };
          const result = resolver.resolveRule(rule, {}, {});
          return result === value;
        }
      ),
      { numRuns: 100 }
    );
  });
});

// Property 14: Unit Conversion Round Trip
describe('UnitConverter', () => {
  it('should round-trip convert within precision', () => {
    fc.assert(
      fc.property(
        fc.double({ min: 0.01, max: 10000, noNaN: true }),
        (value) => {
          const converted = convertUnit(value, 'kg', 'lb', 4);
          const roundTrip = convertUnit(converted, 'lb', 'kg', 4);
          return Math.abs(roundTrip - value) < 0.01;
        }
      ),
      { numRuns: 100 }
    );
  });
});
```

### 测试覆盖要求

- 单元测试覆盖率 > 80%
- 属性测试每个属性运行 100 次
- 集成测试覆盖主要业务流程

---

## API Endpoints

### 现有 API（已实现）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/platform-categories/:platformId/mapping/:categoryId` | 获取类目映射配置 |
| POST | `/platform-categories/mapping` | 保存类目映射配置 |
| DELETE | `/platform-categories/:platformId/mapping/:categoryId` | 删除类目映射配置 |
| GET | `/platform-categories/:platformId/mappings` | 获取平台所有映射配置 |

### 新增 API

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/attribute-mapping/preview` | 预览映射结果 |
| POST | `/attribute-mapping/validate` | 验证映射规则 |
| GET | `/attribute-mapping/standard-fields` | 获取标准字段列表 |
| GET | `/attribute-mapping/auto-generate-rules` | 获取自动生成规则列表 |

### 预览映射结果 API

```typescript
// POST /attribute-mapping/preview
interface PreviewRequest {
  mappingRules: MappingRulesConfig;
  channelAttributes: Record<string, any>;
  context?: {
    shopId?: string;
    productSku?: string;
  };
}

interface PreviewResponse {
  success: boolean;
  attributes: Record<string, any>;
  errors: ResolveError[];
  warnings: string[];
}
```

---

## Frontend Components

### 1. AttributeMappingEditor

属性映射配置编辑器组件，用于配置类目属性映射规则。

```tsx
// apps/web/src/components/AttributeMappingEditor.tsx

interface AttributeMappingEditorProps {
  platformId: string;
  categoryId: string;
  country?: string;
  attributes: PlatformAttribute[];
  onSave: (rules: MappingRulesConfig) => void;
}
```

### 2. MappingTypeSelector

映射类型选择器，根据选择的类型显示不同的配置表单。

```tsx
interface MappingTypeSelectorProps {
  value: MappingType;
  onChange: (type: MappingType) => void;
  attribute: PlatformAttribute;
}
```

### 3. StandardFieldSelector

标准字段选择器，显示所有可用的标准字段路径。

```tsx
interface StandardFieldSelectorProps {
  value: string;
  onChange: (path: string) => void;
  showNestedFields?: boolean;
}
```

### 4. MappingPreview

映射预览组件，显示映射规则应用到示例商品后的结果。

```tsx
interface MappingPreviewProps {
  rules: MappingRulesConfig;
  sampleProduct: Record<string, any>;
}
```

---

## Implementation Notes

### 与现有代码的集成点

1. **ListingService.generatePlatformAttributes**
   - 现有方法需要重构，使用新的 `AttributeResolverService`
   - 保持向后兼容，支持旧格式的映射规则

2. **PlatformCategoryService.generatePlatformAttributes**
   - 同样需要重构，复用 `AttributeResolverService`

3. **standard-product.utils.ts**
   - `getNestedValue` 函数已存在，可直接复用
   - 需要扩展支持数组切片语法

### 性能优化

1. **规则预编译**：首次加载时编译规则，避免重复解析
2. **批量处理**：支持批量解析多个商品的属性
3. **缓存**：缓存类目属性和映射配置

### 迁移策略

1. 新增 `AttributeResolverService`，不修改现有代码
2. 逐步将现有的 `generatePlatformAttributes` 方法迁移到新服务
3. 保持 `mappingRules` JSON 结构向后兼容
