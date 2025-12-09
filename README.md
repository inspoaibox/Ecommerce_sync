# 电商多渠道库存价格同步系统

## 快速开始

### 1. 环境要求
- Node.js >= 18
- PostgreSQL >= 15
- Redis >= 7

### 2. 安装依赖
```bash
pnpm install
```

### 3. 配置环境变量
```bash
# 复制环境变量文件
cp apps/api/.env.example apps/api/.env

# 编辑 apps/api/.env 配置数据库和Redis连接
```

### 4. 初始化数据库
```bash
# 生成Prisma客户端
pnpm db:generate

# 同步数据库结构
pnpm db:push
```

### 5. 启动服务
```bash
# 同时启动前后端
pnpm dev

# 或分别启动
pnpm dev:api   # 后端 (端口5274)
pnpm dev:web   # 前端 (端口5273)
```

### 6. 访问
- 前端: http://localhost:5273
- API文档: http://localhost:5274/api/docs

## 项目结构
```
apps/
├── api/          # NestJS后端
│   ├── src/
│   │   ├── modules/     # 业务模块
│   │   ├── adapters/    # 渠道/平台适配器
│   │   └── queues/      # 任务队列
│   └── prisma/          # 数据库Schema
└── web/          # React前端
    └── src/
        ├── pages/       # 页面
        └── services/    # API服务
```

## 功能模块
- 渠道管理 - 配置数据来源
- 平台管理 - 管理电商平台
- 店铺管理 - 管理目标店铺
- 同步规则 - 配置渠道→店铺映射、价格/库存规则
- 商品管理 - 查看同步的商品
- 同步日志 - 查看同步记录
