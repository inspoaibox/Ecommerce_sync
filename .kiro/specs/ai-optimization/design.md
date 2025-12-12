# AI 商品优化功能设计文档

## Overview

AI 商品优化功能为电商同步系统提供智能文案生成能力，通过集成多种 AI 大模型，自动优化商品标题、描述、五点描述等核心属性。系统采用模块化设计，支持灵活配置 AI 模型和 Prompt 模板，并与现有商品管理流程无缝集成。

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (React)                          │
├─────────────────────────────────────────────────────────────────┤
│  AI Models  │  Prompt Templates  │  AI Workbench  │  Opt Logs   │
└──────┬──────┴────────┬───────────┴───────┬────────┴──────┬──────┘
       │               │                   │               │
       ▼               ▼                   ▼               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Backend (NestJS)                            │
├─────────────────────────────────────────────────────────────────┤
│                      AI Module                                   │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │ AI Service  │  │ Prompt Svc  │  │ Optimization Service    │  │
│  └──────┬──────┘  └──────┬──────┘  └───────────┬─────────────┘  │
│         │                │                     │                 │
│         ▼                ▼                     ▼                 │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    AI Adapter Layer                          ││
│  │  ┌──────────┐  ┌──────────┐  ┌────────────────────────────┐ ││
│  │  │ OpenAI   │  │ Gemini   │  │ OpenAI Compatible (中转)   │ ││
│  │  │ Adapter  │  │ Adapter  │  │ Adapter                    │ ││
│  │  └──────────┘  └──────────┘  └────────────────────────────┘ ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
       │               │                   │
       ▼               ▼                   ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Database (PostgreSQL)                       │
│  ┌───────────┐  ┌────────────────┐  ┌─────────────────────────┐ │
│  │ AiModel   │  │ PromptTemplate │  │ AiOptimizationLog       │ │
│  └───────────┘  └────────────────┘  └─────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. AI Adapter Layer

统一的 AI 模型调用接口，屏蔽不同 AI 服务商的差异。

```typescript
// AI 适配器基类
interface AiAdapter {
  // 测试连接
  testConnection(): Promise<boolean>;
  
  // 生成内容
  generate(prompt: string, options?: GenerateOptions): Promise<GenerateResult>;
  
  // 流式生成（可选）
  generateStream?(prompt: string, options?: GenerateOptions): AsyncGenerator<string>;
}

interface GenerateOptions {
  maxTokens?: number;      // 最大 Token 数
  temperature?: number;    // 温度参数 0-1
  topP?: number;           // Top-P 采样
  stopSequences?: string[]; // 停止序列
}

interface GenerateResult {
  content: string;         // 生成的内容
  usage: {
    promptTokens: number;  // 输入 Token
    completionTokens: number; // 输出 Token
    totalTokens: number;   // 总 Token
  };
  finishReason: string;    // 完成原因
}
```

### 2. AI Service

管理 AI 模型配置和调用。

```typescript
interface AiService {
  // 模型管理
  listModels(): Promise<AiModel[]>;
  createModel(data: CreateAiModelDto): Promise<AiModel>;
  updateModel(id: string, data: UpdateAiModelDto): Promise<AiModel>;
  deleteModel(id: string): Promise<void>;
  testModel(id: string): Promise<TestResult>;
  setDefaultModel(id: string): Promise<void>;
  
  // 内容生成
  generate(modelId: string, prompt: string, options?: GenerateOptions): Promise<GenerateResult>;
}
```

### 3. Prompt Service

管理 Prompt 模板和变量替换。

```typescript
interface PromptService {
  // 模板管理
  listTemplates(type?: PromptType): Promise<PromptTemplate[]>;
  createTemplate(data: CreatePromptTemplateDto): Promise<PromptTemplate>;
  updateTemplate(id: string, data: UpdatePromptTemplateDto): Promise<PromptTemplate>;
  deleteTemplate(id: string): Promise<void>;
  duplicateTemplate(id: string): Promise<PromptTemplate>;
  
  // 模板渲染
  renderPrompt(templateId: string, variables: Record<string, any>): Promise<string>;
  
  // 变量提取
  extractVariables(templateContent: string): string[];
}

enum PromptType {
  TITLE = 'title',           // 标题优化
  DESCRIPTION = 'description', // 描述优化
  BULLET_POINTS = 'bullet_points', // 五点描述
  KEYWORDS = 'keywords',     // 关键词提取
  GENERAL = 'general',       // 通用
}
```

### 4. Optimization Service

执行 AI 优化任务。

```typescript
interface OptimizationService {
  // 单商品优化
  optimizeProduct(params: OptimizeParams): Promise<OptimizeResult>;
  
  // 批量优化
  batchOptimize(params: BatchOptimizeParams): Promise<BatchOptimizeResult>;
  
  // 应用优化结果
  applyOptimization(productId: string, fields: string[]): Promise<void>;
  
  // 日志查询
  listLogs(query: LogQueryParams): Promise<PaginatedResult<AiOptimizationLog>>;
  getLogDetail(id: string): Promise<AiOptimizationLog>;
  exportLogs(query: LogQueryParams): Promise<Buffer>;
}

interface OptimizeParams {
  productId: string;        // 商品ID（商品池或刊登商品）
  productType: 'pool' | 'listing'; // 商品来源
  fields: OptimizeField[];  // 优化字段
  modelId?: string;         // AI 模型ID（可选，默认使用默认模型）
  templateIds?: Record<OptimizeField, string>; // 各字段使用的模板
}

type OptimizeField = 'title' | 'description' | 'bulletPoints' | 'keywords';

interface OptimizeResult {
  productId: string;
  results: {
    field: OptimizeField;
    original: string | string[];
    optimized: string | string[];
    tokenUsage: number;
  }[];
  totalTokens: number;
  duration: number;
}
```

## Data Models

### 数据库模型扩展（Prisma Schema）

```prisma
// ==================== AI 优化模块 ====================

// AI 模型配置
model AiModel {
  id              String        @id @default(uuid())
  name            String        @db.VarChar(100)
  type            AiModelType   // openai, gemini, openai_compatible
  
  // 连接配置
  apiKey          String        @map("api_key") @db.VarChar(500)
  baseUrl         String?       @map("base_url") @db.VarChar(255)  // OpenAI 兼容接口需要
  modelName       String        @map("model_name") @db.VarChar(100) // gpt-4, gemini-pro 等
  
  // 默认参数
  maxTokens       Int           @default(2000) @map("max_tokens")
  temperature     Decimal       @default(0.7) @db.Decimal(3, 2)
  
  // 状态
  isDefault       Boolean       @default(false) @map("is_default")
  status          Status        @default(active)
  
  // 时间戳
  createdAt       DateTime      @default(now()) @map("created_at")
  updatedAt       DateTime      @updatedAt @map("updated_at")
  
  // 关联
  optimizationLogs AiOptimizationLog[]
  
  @@map("ai_models")
}

enum AiModelType {
  openai
  gemini
  openai_compatible
}

// Prompt 模板
model PromptTemplate {
  id              String        @id @default(uuid())
  name            String        @db.VarChar(100)
  type            PromptType    // title, description, bullet_points, keywords, general
  content         String        @db.Text
  description     String?       @db.VarChar(500)
  
  // 是否系统预设
  isSystem        Boolean       @default(false) @map("is_system")
  isDefault       Boolean       @default(false) @map("is_default")
  
  // 状态
  status          Status        @default(active)
  
  // 时间戳
  createdAt       DateTime      @default(now()) @map("created_at")
  updatedAt       DateTime      @updatedAt @map("updated_at")
  
  // 关联
  optimizationLogs AiOptimizationLog[]
  
  @@map("prompt_templates")
}

enum PromptType {
  title
  description
  bullet_points
  keywords
  general
}

// AI 优化日志
model AiOptimizationLog {
  id              String              @id @default(uuid())
  
  // 关联商品
  productType     String              @map("product_type") @db.VarChar(20) // pool, listing
  productId       String              @map("product_id")
  productSku      String              @map("product_sku") @db.VarChar(100)
  
  // 优化配置
  modelId         String              @map("model_id")
  templateId      String?             @map("template_id")
  field           String              @db.VarChar(50) // title, description, bullet_points, keywords
  
  // 内容
  originalContent String?             @map("original_content") @db.Text
  optimizedContent String?            @map("optimized_content") @db.Text
  promptUsed      String?             @map("prompt_used") @db.Text
  
  // 统计
  promptTokens    Int                 @default(0) @map("prompt_tokens")
  completionTokens Int                @default(0) @map("completion_tokens")
  totalTokens     Int                 @default(0) @map("total_tokens")
  duration        Int                 @default(0) // 毫秒
  
  // 状态
  status          AiOptLogStatus      @default(pending)
  errorMessage    String?             @map("error_message")
  
  // 是否已应用
  isApplied       Boolean             @default(false) @map("is_applied")
  appliedAt       DateTime?           @map("applied_at")
  
  // 时间戳
  createdAt       DateTime            @default(now()) @map("created_at")
  
  // 关联
  model           AiModel             @relation(fields: [modelId], references: [id])
  template        PromptTemplate?     @relation(fields: [templateId], references: [id])
  
  @@index([productType, productId])
  @@index([status])
  @@index([createdAt])
  @@map("ai_optimization_logs")
}

enum AiOptLogStatus {
  pending
  processing
  completed
  failed
}
```

## Error Handling

### 错误类型

| 错误码 | 错误类型 | 描述 | 处理方式 |
|--------|----------|------|----------|
| AI_001 | ModelNotFound | AI 模型不存在 | 返回 404，提示配置模型 |
| AI_002 | ModelDisabled | AI 模型已禁用 | 返回 400，提示选择其他模型 |
| AI_003 | ConnectionFailed | AI 服务连接失败 | 返回 503，记录日志，提示重试 |
| AI_004 | RateLimited | API 调用频率限制 | 返回 429，延迟重试 |
| AI_005 | TokenExceeded | Token 超出限制 | 返回 400，提示缩短输入 |
| AI_006 | InvalidApiKey | API Key 无效 | 返回 401，提示检查配置 |
| AI_007 | TemplateNotFound | Prompt 模板不存在 | 返回 404 |
| AI_008 | TemplateRenderError | 模板渲染失败 | 返回 400，提示检查变量 |
| AI_009 | ProductNotFound | 商品不存在 | 返回 404 |
| AI_010 | OptimizationFailed | 优化任务失败 | 记录日志，返回错误详情 |

## Testing Strategy

### 单元测试

1. AI Adapter 测试
   - 各适配器的请求构建
   - 响应解析
   - 错误处理

2. Prompt Service 测试
   - 模板变量提取
   - 模板渲染
   - 边界情况处理

3. Optimization Service 测试
   - 优化流程
   - 结果应用
   - 日志记录

### 集成测试

1. API 端点测试
2. 数据库操作测试
3. 与现有商品模块的集成测试

## API Endpoints

### AI 模型管理

```
GET    /api/ai/models              # 获取模型列表
POST   /api/ai/models              # 创建模型
GET    /api/ai/models/:id          # 获取模型详情
PUT    /api/ai/models/:id          # 更新模型
DELETE /api/ai/models/:id          # 删除模型
POST   /api/ai/models/:id/test     # 测试模型连接
POST   /api/ai/models/:id/default  # 设置为默认模型
```

### Prompt 模板管理

```
GET    /api/ai/templates           # 获取模板列表
POST   /api/ai/templates           # 创建模板
GET    /api/ai/templates/:id       # 获取模板详情
PUT    /api/ai/templates/:id       # 更新模板
DELETE /api/ai/templates/:id       # 删除模板
POST   /api/ai/templates/:id/duplicate # 复制模板
POST   /api/ai/templates/:id/default   # 设置为默认模板
POST   /api/ai/templates/preview   # 预览渲染结果
```

### AI 优化

```
POST   /api/ai/optimize            # 执行优化
POST   /api/ai/optimize/batch      # 批量优化
POST   /api/ai/optimize/apply      # 应用优化结果
GET    /api/ai/optimize/logs       # 获取优化日志
GET    /api/ai/optimize/logs/:id   # 获取日志详情
GET    /api/ai/optimize/logs/export # 导出日志
```

## Frontend Pages

### 1. AI 模型配置页面 (`/listing/ai/models`)

- 模型列表表格
- 添加/编辑模型弹窗
- 测试连接按钮
- 设置默认模型

### 2. Prompt 模板页面 (`/listing/ai/templates`)

- 模板列表（按类型分组）
- 模板编辑器（支持变量高亮）
- 变量说明面板
- 预览功能

### 3. AI 优化工作台 (`/listing/ai/optimize`)

- 商品选择区（支持搜索、筛选）
- 优化配置区（字段选择、模型选择、模板选择）
- 预览区（显示完整 Prompt）
- 结果对比区（原始 vs 优化后）
- 批量操作进度条

### 4. 优化日志页面 (`/listing/ai/logs`)

- 日志列表表格
- 筛选条件（时间、SKU、字段、状态）
- 详情弹窗
- 导出按钮

## 预设 Prompt 模板

### 标题优化模板

```
你是一位专业的电商文案专家，擅长撰写吸引人的商品标题。

请根据以下商品信息，生成一个优化后的商品标题：

商品原标题：{{title}}
商品类目：{{category}}
品牌：{{brand}}
商品特点：{{characteristics}}

要求：
1. 标题长度控制在 80-150 个字符
2. 包含核心关键词，便于搜索
3. 突出产品主要卖点
4. 使用专业但易懂的语言
5. 不要使用夸张或虚假宣传词汇

请直接输出优化后的标题，不需要解释。
```

### 五点描述模板

```
你是一位专业的电商文案专家，擅长撰写商品卖点描述。

请根据以下商品信息，生成 5 条商品卖点描述（Bullet Points）：

商品标题：{{title}}
商品描述：{{description}}
商品类目：{{category}}
品牌：{{brand}}
商品特点：{{characteristics}}

要求：
1. 每条卖点 150-200 个字符
2. 突出产品的核心优势和特点
3. 使用具体的数据和细节
4. 首字母大写，避免全大写
5. 自然融入搜索关键词
6. 按重要性排序

请以 JSON 数组格式输出 5 条卖点，例如：
["卖点1", "卖点2", "卖点3", "卖点4", "卖点5"]
```

### 描述优化模板

```
你是一位专业的电商文案专家，擅长撰写详细的商品描述。

请根据以下商品信息，生成一段优化后的商品描述：

商品标题：{{title}}
商品原描述：{{description}}
商品类目：{{category}}
品牌：{{brand}}
商品特点：{{characteristics}}
尺寸信息：{{dimensions}}

要求：
1. 描述长度 500-1000 个字符
2. 结构清晰，分段合理
3. 包含产品特点、使用场景、材质说明
4. 使用 HTML 格式（支持 <p>、<ul>、<li>、<strong> 标签）
5. 语言专业但易懂
6. 不要使用夸张或虚假宣传词汇

请直接输出优化后的描述，使用 HTML 格式。
```

## 与现有系统集成点

### 1. 商品池集成

- 在 `ProductPool.tsx` 详情弹窗中添加"AI 优化"按钮
- 优化结果存储到 `channelAttributes.aiOptimized` 字段

### 2. 刊登商品集成

- 在 `ListingProductEdit.tsx` 中添加"AI 优化"按钮
- 优化结果存储到 `aiOptimizedData` 字段
- 添加"使用 AI 优化版本"开关（`useAiOptimized` 字段）

### 3. 菜单集成

在 `Layout.tsx` 的商品刊登菜单下添加：

```typescript
{
  key: '/listing/ai',
  icon: <RobotOutlined />,
  label: 'AI 大模型',
  children: [
    { key: '/listing/ai/models', label: 'AI 模型' },
    { key: '/listing/ai/templates', label: 'Prompt 模板' },
    { key: '/listing/ai/optimize', label: 'AI 优化' },
    { key: '/listing/ai/logs', label: '优化日志' },
  ],
}
```

## 安全考虑

1. **API Key 加密存储**：使用 AES 加密存储 API Key
2. **访问控制**：AI 配置功能仅管理员可访问
3. **请求限流**：防止 API 滥用
4. **日志脱敏**：日志中不记录完整的 API Key
5. **输入验证**：防止 Prompt 注入攻击
