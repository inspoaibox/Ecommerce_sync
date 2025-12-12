# AI 商品优化功能 - 实现任务列表

## Implementation Plan

- [x] 1. 数据库模型设计


  - [x] 1.1 添加 AI 模型配置表 (AiModel)


    - 字段：id, name, type, apiKey, baseUrl, modelName, maxTokens, temperature, isDefault, status
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.7, 1.8_
  - [x] 1.2 添加 Prompt 模板表 (PromptTemplate)

    - 字段：id, name, type, content, description, isSystem, isDefault, status
    - _Requirements: 2.1, 2.2, 2.3, 2.5, 2.7_
  - [x] 1.3 添加 AI 优化日志表 (AiOptimizationLog)

    - 字段：id, productType, productId, productSku, modelId, templateId, field, originalContent, optimizedContent, promptUsed, tokens, duration, status, errorMessage, isApplied
    - _Requirements: 4.1, 4.2, 4.3_
  - [x] 1.4 执行数据库迁移


    - _Requirements: 1.1, 2.1, 4.1_

- [x] 2. AI 适配器层实现


  - [x] 2.1 创建 AI 适配器基类和接口


    - 定义 AiAdapter 接口、GenerateOptions、GenerateResult
    - _Requirements: 1.2_
  - [x] 2.2 实现 OpenAI 适配器


    - 支持 gpt-4、gpt-3.5-turbo 等模型
    - _Requirements: 1.3_
  - [x] 2.3 实现 Gemini 适配器


    - 支持 gemini-pro 等模型
    - _Requirements: 1.4_
  - [x] 2.4 实现 OpenAI 兼容适配器


    - 支持自定义 baseUrl 的第三方中转接口
    - _Requirements: 1.5_
  - [ ]* 2.5 编写适配器单元测试
    - _Requirements: 1.6_

- [x] 3. 后端服务实现


  - [x] 3.1 实现 AI 模型服务 (AiModelService)


    - CRUD 操作、测试连接、设置默认模型
    - _Requirements: 1.1, 1.6, 1.7, 1.8_
  - [x] 3.2 实现 Prompt 模板服务 (PromptTemplateService)


    - CRUD 操作、模板渲染、变量提取、复制模板
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_
  - [x] 3.3 实现 AI 优化服务 (AiOptimizationService)


    - 单商品优化、批量优化、应用结果、日志管理
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10_
  - [x] 3.4 创建预设 Prompt 模板


    - 标题优化、描述优化、五点描述、关键词提取
    - _Requirements: 2.2, 2.7_
  - [ ]* 3.5 编写服务层单元测试
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 4. 后端 API 实现

  - [x] 4.1 实现 AI 模型管理 API

    - GET/POST/PUT/DELETE /api/ai/models
    - POST /api/ai/models/:id/test
    - POST /api/ai/models/:id/default
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_
  - [x] 4.2 实现 Prompt 模板管理 API

    - GET/POST/PUT/DELETE /api/ai/templates
    - POST /api/ai/templates/:id/duplicate
    - POST /api/ai/templates/:id/default
    - POST /api/ai/templates/preview
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_
  - [x] 4.3 实现 AI 优化 API

    - POST /api/ai/optimize
    - POST /api/ai/optimize/batch
    - POST /api/ai/optimize/apply
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10_
  - [x] 4.4 实现优化日志 API

    - GET /api/ai/optimize/logs
    - GET /api/ai/optimize/logs/:id
    - GET /api/ai/optimize/logs/export
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  - [ ]* 4.5 编写 API 集成测试
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [x] 5. Checkpoint - 后端功能验证
  - 后端和前端 TypeScript 编译通过

- [x] 6. 前端页面实现

  - [x] 6.1 创建 AI 模型配置页面 (/listing/ai/models)


    - 模型列表、添加/编辑弹窗、测试连接、设置默认
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_
  - [x] 6.2 创建 Prompt 模板页面 (/listing/ai/templates)


    - 模板列表、编辑器、变量说明、预览功能
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_
  - [x] 6.3 创建 AI 优化工作台页面 (/listing/ai/optimize)


    - 商品选择、优化配置、预览、结果对比、批量操作
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10_
  - [x] 6.4 创建优化日志页面 (/listing/ai/logs)


    - 日志列表、筛选、详情弹窗、导出
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  - [x] 6.5 添加前端 API 服务


    - aiModelApi, promptTemplateApi, aiOptimizationApi
    - _Requirements: 1.1, 2.1, 3.1, 4.1_
  - [x] 6.6 更新路由和菜单
    - 在商品刊登下添加 AI 大模型子菜单
    - _Requirements: 1.1, 2.1, 3.1, 4.1_

- [x] 7. 与现有系统集成
  - [x] 7.1 商品池详情页集成
    - 添加"AI 优化"按钮，支持快速优化
    - _Requirements: 5.1_
  - [x] 7.2 刊登商品编辑页集成
    - 添加"AI 优化"按钮和"使用 AI 优化版本"开关
    - _Requirements: 5.2, 5.3, 5.4_
  - [x] 7.3 商品列表批量操作集成
    - 支持批量选择商品发起 AI 优化
    - _Requirements: 5.5_

- [x] 8. Checkpoint - 功能完整性验证
  - 前后端编译通过，功能完整

- [x] 9. 优化和完善
  - [x] 9.1 添加 API Key 加密存储
    - 使用 AES-256-GCM 加密敏感信息
    - 创建 crypto.util.ts 工具类
    - _Requirements: 1.3, 1.4, 1.5_
  - [ ] 9.2 添加请求限流
    - 防止 API 滥用
    - _Requirements: 3.10_
  - [ ] 9.3 优化错误处理和用户提示
    - 友好的错误信息展示
    - _Requirements: 4.3_
  - [ ]* 9.4 编写端到端测试
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ] 10. Final Checkpoint - 确保所有测试通过
  - Ensure all tests pass, ask the user if questions arise.

## 文件结构预览

```
apps/api/src/
├── adapters/
│   └── ai/
│       ├── base.adapter.ts          # AI 适配器基类
│       ├── openai.adapter.ts        # OpenAI 适配器
│       ├── gemini.adapter.ts        # Gemini 适配器
│       └── openai-compatible.adapter.ts # OpenAI 兼容适配器
├── modules/
│   └── ai/
│       ├── ai.module.ts
│       ├── ai-model/
│       │   ├── ai-model.service.ts
│       │   ├── ai-model.controller.ts
│       │   └── dto/
│       ├── prompt-template/
│       │   ├── prompt-template.service.ts
│       │   ├── prompt-template.controller.ts
│       │   └── dto/
│       └── optimization/
│           ├── optimization.service.ts
│           ├── optimization.controller.ts
│           └── dto/
└── prisma/
    └── schema.prisma                 # 新增 AI 相关模型

apps/web/src/
├── pages/
│   └── listing/
│       └── ai/
│           ├── AiModels.tsx          # AI 模型配置
│           ├── PromptTemplates.tsx   # Prompt 模板
│           ├── AiOptimize.tsx        # AI 优化工作台
│           └── OptimizationLogs.tsx  # 优化日志
├── services/
│   └── api.ts                        # 新增 AI 相关 API
└── components/
    └── Layout.tsx                    # 更新菜单
```

## 技术要点

### 1. AI 适配器工厂模式

```typescript
class AiAdapterFactory {
  static create(model: AiModel): AiAdapter {
    switch (model.type) {
      case 'openai':
        return new OpenAiAdapter(model);
      case 'gemini':
        return new GeminiAdapter(model);
      case 'openai_compatible':
        return new OpenAiCompatibleAdapter(model);
      default:
        throw new Error(`Unsupported model type: ${model.type}`);
    }
  }
}
```

### 2. Prompt 模板变量替换

```typescript
function renderPrompt(template: string, variables: Record<string, any>): string {
  return template.replace(/\{\{(\w+)\}\}/g, (match, key) => {
    const value = variables[key];
    if (value === undefined) return match;
    if (Array.isArray(value)) return value.join(', ');
    return String(value);
  });
}
```

### 3. 批量优化队列处理

```typescript
async function batchOptimize(products: Product[], options: OptimizeOptions) {
  const results = [];
  for (const product of products) {
    try {
      const result = await optimizeProduct(product, options);
      results.push({ productId: product.id, status: 'success', result });
    } catch (error) {
      results.push({ productId: product.id, status: 'failed', error: error.message });
    }
    // 添加延迟避免 API 限流
    await sleep(1000);
  }
  return results;
}
```

## 依赖说明

### 后端依赖

```json
{
  "openai": "^4.x",           // OpenAI SDK
  "@google/generative-ai": "^0.x", // Google Gemini SDK
  "crypto-js": "^4.x"         // API Key 加密
}
```

### 前端依赖

```json
{
  "@monaco-editor/react": "^4.x"  // Prompt 模板编辑器（可选）
}
```
