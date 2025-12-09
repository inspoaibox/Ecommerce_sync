# 电商多渠道库存价格同步系统 - 开发文档

## 1. 项目概述

### 1.1 项目名称
E-Commerce Sync System (ecommerce-sync)

### 1.2 项目目标
构建一个支持多渠道数据源、多电商平台、多店铺的商品库存价格同步系统，支持自定义同步周期、价格/库存倍率调整。

### 1.3 核心功能
- 多渠道数据拉取（A/B/C/D等数据源）
- 多平台多店铺管理（Amazon、Walmart、eBay等）
- 商品数据本地存储（SKU、标题、价格、库存、可扩展字段）
- 灵活的同步规则配置（渠道→店铺映射）
- 价格/库存倍率及增减调整
- 全量/增量同步支持
- 自定义同步周期（1天/2天/3天/5天/7天等）
- Web管理界面

---

## 2. 技术架构

### 2.1 技术栈
| 层面 | 技术 | 版本 |
|------|------|------|
| 后端框架 | NestJS | ^10.0 |
| 前端框架 | React + Ant Design Pro | React 18 |
| 数据库 | PostgreSQL | ^15.0 |
| 缓存/队列 | Redis | ^7.0 |
| 任务队列 | BullMQ | ^4.0 |
| ORM | Prisma | ^5.0 |
| 语言 | TypeScript | ^5.0 |
| 包管理 | pnpm | ^8.0 |

### 2.2 项目结构

```
ecommerce-sync/
├── apps/
│   ├── api/                      # NestJS 后端服务
│   │   ├── src/
│   │   │   ├── modules/          # 业务模块
│   │   │   │   ├── channel/      # 渠道管理
│   │   │   │   ├── platform/     # 平台管理
│   │   │   │   ├── shop/         # 店铺管理
│   │   │   │   ├── product/      # 商品管理
│   │   │   │   ├── sync-rule/    # 同步规则
│   │   │   │   ├── sync-task/    # 同步任务执行
│   │   │   │   └── sync-log/     # 同步日志
│   │   │   ├── adapters/         # 适配器层
│   │   │   │   ├── channels/     # 渠道适配器
│   │   │   │   │   ├── base.adapter.ts
│   │   │   │   │   ├── channel-a.adapter.ts
│   │   │   │   │   └── channel-b.adapter.ts
│   │   │   │   └── platforms/    # 平台适配器
│   │   │   │       ├── base.adapter.ts
│   │   │   │       ├── amazon.adapter.ts
│   │   │   │       └── walmart.adapter.ts
│   │   │   ├── queues/           # 队列处理器
│   │   │   │   ├── sync-scheduler.processor.ts
│   │   │   │   ├── fetch.processor.ts
│   │   │   │   ├── transform.processor.ts
│   │   │   │   └── push.processor.ts
│   │   │   ├── common/           # 公共模块
│   │   │   │   ├── decorators/
│   │   │   │   ├── filters/
│   │   │   │   ├── guards/
│   │   │   │   ├── interceptors/
│   │   │   │   └── utils/
│   │   │   ├── config/           # 配置
│   │   │   └── main.ts
│   │   ├── prisma/
│   │   │   └── schema.prisma     # 数据库模型
│   │   └── package.json
│   └── web/                      # React 前端
│       ├── src/
│       │   ├── pages/
│       │   │   ��── channel/      # 渠道管理页面
│       │   │   ├── platform/     # 平台管理页面
│       │   │   ├── shop/         # 店铺管理页面
│       │   │   ├── product/      # 商品管理页面
│       │   │   ├── sync-rule/    # 同步规则页面
│       │   │   ├── sync-log/     # 同步日志页面
│       │   │   └── dashboard/    # 仪表盘
│       │   ├── components/
│       │   ├── services/         # API调用
│       │   └── utils/
│       └── package.json
├── docs/                         # 文档
├── .env.example                  # 环境变量示例
└── package.json                  # 根目录配置
```

---

## 3. 数据库设计

### 3.1 ER图关系
```
Channel (渠道) 1 ──────┐
                       │
                       ├──> SyncRule (同步规则) ──> Product (商品)
                       │           │
Platform (平台) 1 ─> Shop (店铺) 1─┘           │
                                              ▼
                                        SyncLog (日志)
```

### 3.2 数据表详细设计

#### 3.2.1 Channel (渠道表)
| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| name | VARCHAR(100) | 渠道名称 |
| code | VARCHAR(50) | 渠道编码(唯一) |
| type | VARCHAR(50) | 渠道类型 |
| api_config | JSONB | API配置(url, key, secret等) |
| description | TEXT | 描述 |
| status | ENUM | 状态: active/inactive |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 3.2.2 Platform (平台表)
| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| name | VARCHAR(100) | 平台名称(Amazon/Walmart) |
| code | VARCHAR(50) | 平台编码(唯一) |
| api_base_url | VARCHAR(255) | API基础地址 |
| description | TEXT | 描述 |
| status | ENUM | 状态: active/inactive |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 3.2.3 Shop (店铺表)
| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| platform_id | UUID | 关联平台ID |
| name | VARCHAR(100) | 店铺名称 |
| code | VARCHAR(50) | 店铺编码(唯一) |
| api_credentials | JSONB | API凭证(加密存储) |
| region | VARCHAR(50) | 区域(US/EU/JP等) |
| description | TEXT | 描述 |
| status | ENUM | 状态: active/inactive |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 3.2.4 SyncRule (同步规则表)
| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| name | VARCHAR(100) | 规则名称 |
| channel_id | UUID | 来源渠道ID |
| shop_id | UUID | 目标店铺ID |
| sync_type | ENUM | 同步类型: full/incremental |
| interval_days | INT | 同步间隔(天) |
| price_multiplier | DECIMAL(10,4) | 价格倍率(默认1.0) |
| price_adjustment | DECIMAL(10,2) | 价格增减(默认0) |
| stock_multiplier | DECIMAL(10,4) | 库存倍率(默认1.0) |
| stock_adjustment | INT | 库存增减(默认0) |
| field_mapping | JSONB | 字段映射配置 |
| last_sync_at | TIMESTAMP | 上次同步时间 |
| next_sync_at | TIMESTAMP | 下次同步时间 |
| status | ENUM | 状态: active/paused/inactive |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 3.2.5 Product (商品表)
| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| sync_rule_id | UUID | 关联同步规则ID |
| shop_id | UUID | 关联店铺ID |
| channel_product_id | VARCHAR(100) | 渠道商品ID |
| sku | VARCHAR(100) | SKU编码 |
| title | VARCHAR(500) | 商品标题 |
| original_price | DECIMAL(10,2) | 原始价格 |
| final_price | DECIMAL(10,2) | 计算后价格 |
| original_stock | INT | 原始库存 |
| final_stock | INT | 计算后库存 |
| currency | VARCHAR(10) | 货币(USD/EUR等) |
| extra_fields | JSONB | 扩展字段 |
| platform_product_id | VARCHAR(100) | 平台商品ID(同步后) |
| sync_status | ENUM | 同步状态: pending/synced/failed |
| last_sync_at | TIMESTAMP | 最后同步时间 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

#### 3.2.6 SyncLog (同步日志表)
| 字段 | 类型 | 说明 |
|------|------|------|
| id | UUID | 主键 |
| sync_rule_id | UUID | 关联同步规则ID |
| sync_type | ENUM | 同步类型: full/incremental |
| trigger_type | ENUM | 触发类型: scheduled/manual |
| started_at | TIMESTAMP | 开始时间 |
| finished_at | TIMESTAMP | 结束时间 |
| total_count | INT | 总数量 |
| success_count | INT | 成功数量 |
| fail_count | INT | 失败数量 |
| status | ENUM | 状态: running/success/partial/failed |
| error_message | TEXT | 错误信息 |
| details | JSONB | 详细日志 |
| created_at | TIMESTAMP | 创建时间 |

---

## 4. API接口设计

### 4.1 渠道管理 (Channel)
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/channels | 获取渠道列表 |
| GET | /api/channels/:id | 获取渠道详情 |
| POST | /api/channels | 创建渠道 |
| PUT | /api/channels/:id | 更新渠道 |
| DELETE | /api/channels/:id | 删除渠道 |
| POST | /api/channels/:id/test | 测试渠道连接 |

### 4.2 平台管理 (Platform)
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/platforms | 获取平台列表 |
| GET | /api/platforms/:id | 获取平台详情 |
| POST | /api/platforms | 创建平台 |
| PUT | /api/platforms/:id | 更新平台 |
| DELETE | /api/platforms/:id | 删除平台 |

### 4.3 店铺管理 (Shop)
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/shops | 获取店铺列表 |
| GET | /api/shops/:id | 获取店铺详情 |
| POST | /api/shops | 创建店铺 |
| PUT | /api/shops/:id | 更新店铺 |
| DELETE | /api/shops/:id | 删除店铺 |
| POST | /api/shops/:id/test | 测试店铺API连接 |

### 4.4 同步规则 (SyncRule)
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/sync-rules | 获取同步规则列表 |
| GET | /api/sync-rules/:id | 获取规则详情 |
| POST | /api/sync-rules | 创建同步规则 |
| PUT | /api/sync-rules/:id | 更新同步规则 |
| DELETE | /api/sync-rules/:id | 删除同步规则 |
| POST | /api/sync-rules/:id/execute | 手动执行同步 |
| PUT | /api/sync-rules/:id/pause | 暂停规则 |
| PUT | /api/sync-rules/:id/resume | 恢复规则 |

### 4.5 商品管理 (Product)
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/products | 获取商品列表(支持筛选) |
| GET | /api/products/:id | 获取商品详情 |
| PUT | /api/products/:id | 更新商品信息 |
| DELETE | /api/products/:id | 删除商品 |
| POST | /api/products/batch-sync | 批量同步商品 |
| GET | /api/products/export | 导出商品数据 |

### 4.6 同步日志 (SyncLog)
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/sync-logs | 获取日志列表 |
| GET | /api/sync-logs/:id | 获取日志详情 |
| GET | /api/sync-logs/stats | 获取同步统计 |

### 4.7 仪表盘 (Dashboard)
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/dashboard/overview | 总览数据 |
| GET | /api/dashboard/sync-stats | 同步统计 |
| GET | /api/dashboard/recent-logs | 最近同步记录 |

---

## 5. 核心业务逻辑

### 5.1 价格/库存计算规则

```typescript
// 价格计算
finalPrice = originalPrice * priceMultiplier + priceAdjustment

// 库存计算
finalStock = Math.floor(originalStock * stockMultiplier + stockAdjustment)

// 示例
// 原价: 100, 倍率: 1.2, 增减: +5 => 最终价格: 125
// 原库存: 50, 倍率: 0.8, 增减: -10 => 最终库存: 30
```

### 5.2 同步流程

```
┌─────────────────────────────────────────────────────────────┐
│                      同步执行流程                             │
├─────────────────────────────────────────────────────────────┤
│ 1. 调度器检查 → 扫描 next_sync_at <= now 的规则              │
│ 2. 创建任务   → 生成 SyncLog 记录，状态: running             │
│ 3. 数据拉取   → 调用渠道适配器获取商品数据                    │
│ 4. 数据转换   → 应用价格/库存规则，字段映射                   │
│ 5. 数据推送   → 调用平台适配器同步到目标店铺                  │
│ 6. 结果记录   → 更新 SyncLog，更新 next_sync_at              │
└─────────────────────────────────────────────────────────────┘
```

### 5.3 全量同步 vs 增量同步

| 类型 | 说明 | 适用场景 |
|------|------|---------|
| 全量同步 | 拉取渠道所有商品，覆盖本地数据 | 首次同步、数据修复 |
| 增量同步 | 只拉取变更的商品(基于时间戳) | 日常同步、减少API调用 |

### 5.4 任务队列设计

```typescript
// 队列定义
const QUEUES = {
  SYNC_SCHEDULER: 'sync-scheduler',  // 定时调度
  FETCH: 'fetch',                     // 数据拉取
  TRANSFORM: 'transform',             // 数据转换
  PUSH: 'push',                       // 数据推送
};

// 队列配置
const queueConfig = {
  'sync-scheduler': { 
    repeat: { cron: '*/5 * * * *' }  // 每5分钟检查一次
  },
  'fetch': { 
    concurrency: 5,                   // 并发数
    limiter: { max: 10, duration: 1000 } // 限流
  },
  'push': { 
    concurrency: 3,
    attempts: 3,                      // 重试次数
    backoff: { type: 'exponential', delay: 5000 }
  }
};
```

### 5.5 适配器接口设计

```typescript
// 渠道适配器接口
interface IChannelAdapter {
  // 测试连接
  testConnection(): Promise<boolean>;
  // 获取商品列表
  fetchProducts(options: FetchOptions): Promise<ChannelProduct[]>;
  // 获取单个商品
  fetchProduct(productId: string): Promise<ChannelProduct>;
}

// 平台适配器接口
interface IPlatformAdapter {
  // 测试连接
  testConnection(): Promise<boolean>;
  // 同步商品
  syncProduct(product: Product): Promise<SyncResult>;
  // 批量同步
  batchSyncProducts(products: Product[]): Promise<BatchSyncResult>;
  // 更新库存
  updateStock(sku: string, stock: number): Promise<boolean>;
  // 更新价格
  updatePrice(sku: string, price: number): Promise<boolean>;
}
```

---

## 6. 前端页面设计

### 6.1 页面列表

| 页面 | 路径 | 功能 |
|------|------|------|
| 仪表盘 | /dashboard | 数据总览、同步统计、最近日志 |
| 渠道管理 | /channels | 渠道CRUD、连接测试 |
| 平台管理 | /platforms | 平台CRUD |
| 店铺管理 | /shops | 店铺CRUD、API配置、连接测试 |
| 同步规则 | /sync-rules | 规则CRUD、手动执行、暂停/恢复 |
| 商品管理 | /products | 商品列表、筛选、详情、批量操作 |
| 同步日志 | /sync-logs | 日志列表、详情、统计 |

### 6.2 仪表盘设计

```
┌─────────────────────────────────────────────────────────────┐
│                        仪表盘                                │
├──────────────┬──────────────┬──────────────┬───────────────┤
│   渠道数量    │   店铺数量    │   商品总数    │  今日同步次数  │
│      5       │      12      │    15,234    │      48       │
├──────────────┴──────────────┴──────────────┴───────────────┤
│                    同步成功率趋势图                          │
│  [====================================] 98.5%              │
├─────────────────────────────────────────────────────────────┤
│                    最近同步记录                              │
│  规则名称    │ 状态  │ 成功/失败 │ 耗时  │ 时间             │
│  A→Amazon1  │ 成功  │ 120/0    │ 45s  │ 10:30           │
│  B→Walmart1 │ 部分  │ 98/2     │ 38s  │ 10:25           │
└─────────────────────────────────────────────────────────────┘
```

### 6.3 同步规则配置表单

```
┌─────────────────────────────────────────────────────────────┐
│                    创建同步规则                              │
├─────────────────────────────────────────────────────────────┤
│ 规则名称:    [________________________]                     │
│                                                             │
│ 数据来源:    [渠道A ▼]                                      │
│ 目标店铺:    [Amazon-US店铺1 ▼]                             │
│                                                             │
│ 同步类型:    ○ 全量同步  ● 增量同步                          │
│ 同步周期:    [每天 ▼]  (1天/2天/3天/5天/7天/自定义)          │
│                                                             │
│ ─────────── 价格规则 ───────────                            │
│ 价格倍率:    [1.2___]  (最终价格 = 原价 × 倍率 + 增减)       │
│ 价格增减:    [+5.00__]                                      │
│                                                             │
│ ─────────── 库存规则 ───────────                            │
│ 库存倍率:    [0.8___]  (最终库存 = 原库存 × 倍率 + 增减)     │
│ 库存增减:    [-10___]                                       │
│                                                             │
│              [取消]  [保存]                                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 7. 开发计划

### 7.1 阶段一: 基础架构 (Week 1)
- [x] 项目初始化 (NestJS + React)
- [ ] 数据库设计 (Prisma Schema)
- [ ] 基础模块搭建 (Channel/Platform/Shop)
- [ ] 通用组件开发 (CRUD模板)

### 7.2 阶段二: 核心功能 (Week 2-3)
- [ ] 同步规则模块
- [ ] 商品管理模块
- [ ] 适配器框架搭建
- [ ] 示例适配器实现

### 7.3 阶段三: 任务调度 (Week 4)
- [ ] BullMQ队列集成
- [ ] 定时调度器
- [ ] 同步执行流程
- [ ] 日志记录

### 7.4 阶段四: 前端界面 (Week 5-6)
- [ ] 仪表盘页面
- [ ] 各管理页面
- [ ] 同步规则配置
- [ ] 日志查看

### 7.5 阶段五: 优化完善 (Week 7)
- [ ] 错误处理优化
- [ ] 性能优化
- [ ] 测试用例
- [ ] 文档完善

---

## 8. 环境配置

### 8.1 环境变量 (.env)

```bash
# 应用配置
NODE_ENV=development
PORT=3000
API_PREFIX=api

# 数据库配置
DATABASE_URL=postgresql://user:password@localhost:5432/ecommerce_sync

# Redis配置
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

# JWT配置 (如需认证)
JWT_SECRET=your-secret-key
JWT_EXPIRES_IN=7d

# 日志配置
LOG_LEVEL=debug
```

### 8.2 本地开发环境要求
- Node.js >= 18.0
- PostgreSQL >= 15.0
- Redis >= 7.0
- pnpm >= 8.0

### 8.3 常用命令

```bash
# 安装依赖
pnpm install

# 启动开发服务（前后端同时启动）
pnpm dev

# 仅启动后端 (端口 5275)
pnpm --filter api dev

# 仅启动前端 (端口 5276)
pnpm --filter web dev

# 重启服务
# 1. 在终端按 Ctrl+C 停止当前服务
# 2. 如果修改了 Prisma Schema，先运行迁移和生成
# 3. 重新运行 pnpm dev

# 完整重启流程（修改数据库后）
# Ctrl+C
# cd apps/api && npx prisma migrate dev --name your_migration_name
# cd apps/api && npx prisma generate  (如果上一步报权限错误)
# pnpm dev

# 数据库迁移
pnpm --filter api prisma migrate dev

# 生成 Prisma Client
pnpm --filter api prisma generate

# 重置数据库（清空所有数据）
pnpm --filter api prisma migrate reset

# 查看数据库（Prisma Studio）
pnpm --filter api prisma studio
```

### 8.4 服务端口
| 服务 | 端口 | 说明 |
|------|------|------|
| 后端 API | 5275 | NestJS 服务 |
| 前端 Web | 5276 | Vite 开发服务器 |
| PostgreSQL | 5432 | 数据库 |
| Redis | 6379 | 缓存/队列 |

---

## 9. 扩展性设计

### 9.1 新增渠道适配器
1. 在 `adapters/channels/` 创建新适配器文件
2. 实现 `IChannelAdapter` 接口
3. 在适配器工厂注册

### 9.2 新增平台适配器
1. 在 `adapters/platforms/` 创建新适配器文件
2. 实现 `IPlatformAdapter` 接口
3. 在适配器工厂注册

### 9.3 扩展商品字段
- 使用 `extra_fields` JSONB字段存储
- 在同步规则中配置 `field_mapping` 映射

---

## 10. 注意事项

1. **API限流**: 各平台API有调用限制，需在适配器中实现限流
2. **数据安全**: API密钥需加密存储
3. **并发控制**: 同一规则不能同时执行多次
4. **失败重试**: 网络异常需支持重试机制
5. **日志追踪**: 每次同步需记录详细日志便于排查
