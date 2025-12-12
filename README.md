# 电商多渠道库存价格同步系统

多渠道商品数据聚合、价格库存同步、多平台刊登的一站式解决方案。

## 功能特性

- **渠道管理** - 对接 GigaCloud、Saleyee 等供应商渠道
- **商品池** - 聚合多渠道商品，支持 Excel 批量导入
- **平台刊登** - 支持 Walmart 等电商平台商品刊登
- **类目映射** - 智能属性映射，自动生成平台属性
- **价格库存同步** - 自动/手动同步价格库存到平台
- **AI 优化** - AI 辅助优化商品标题、描述

## 快速开始

### 1. 环境要求

| 依赖 | 版本要求 | 说明 |
|------|---------|------|
| Node.js | >= 18 | 推荐 20.x LTS |
| PostgreSQL | >= 14 | 数据库 |
| Redis | >= 5 | 队列和缓存 |
| pnpm | >= 8 | 包管理器 |

### 2. 安装依赖

```bash
# 安装 pnpm（如果没有）
npm install -g pnpm

# 安装项目依赖
pnpm install
```

### 3. 配置环境变量

```bash
# 复制环境变量文件
cp apps/api/.env.example apps/api/.env
```

编辑 `apps/api/.env`：

```env
# 数据库配置（必填）
DATABASE_URL=postgresql://用户名:密码@主机:端口/数据库名

# Redis配置（必填）
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=你的Redis密码

# 应用端口
PORT=5275

# 加密密钥（生产环境必须修改！用于加密 API Key 等敏感信息）
ENCRYPTION_KEY=生成一个32位以上的随机字符串
```

### 4. 初始化数据库

```bash
# 生成 Prisma 客户端
pnpm db:generate

# 同步数据库结构（开发环境）
pnpm db:push

# 或使用迁移（生产环境推荐）
pnpm db:migrate
```

### 5. 启动服务

**开发环境：**
```bash
# 同时启动前后端（开发模式）
pnpm dev
```

**生产环境：**
```bash
# 构建
pnpm build:api
pnpm build:web

# 启动后端
cd apps/api && node dist/main.js

# 前端使用 nginx 或其他静态服务器托管 apps/web/dist
```

### 6. 访问

- 前端界面: http://localhost:5273
- 后端 API: http://localhost:5275
- API 文档: http://localhost:5275/api/docs

## 生产环境部署

### 使用 PM2 部署后端

```bash
# 安装 PM2
npm install -g pm2

# 构建后端
pnpm build:api

# 启动服务
cd apps/api
pm2 start dist/main.js --name "ecommerce-sync-api"

# 保存进程列表
pm2 save

# 设置开机自启
pm2 startup
```

### Nginx 配置示例

```nginx
# 前端静态文件
server {
    listen 80;
    server_name your-domain.com;
    
    root /path/to/apps/web/dist;
    index index.html;
    
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    # API 代理
    location /api {
        proxy_pass http://127.0.0.1:5275;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_cache_bypass $http_upgrade;
    }
}
```

### Docker 部署（可选）

```bash
# 构建镜像
docker build -t ecommerce-sync-api -f apps/api/Dockerfile .

# 运行容器
docker run -d \
  --name ecommerce-sync-api \
  -p 5275:5275 \
  -e DATABASE_URL="postgresql://..." \
  -e REDIS_HOST="redis" \
  ecommerce-sync-api
```

## 项目结构

```
├── apps/
│   ├── api/                    # NestJS 后端
│   │   ├── src/
│   │   │   ├── adapters/       # 渠道/平台适配器
│   │   │   ├── modules/        # 业务模块
│   │   │   ├── queues/         # 任务队列处理器
│   │   │   └── common/         # 公共模块
│   │   └── prisma/             # 数据库 Schema
│   └── web/                    # React 前端
│       └── src/
│           ├── pages/          # 页面组件
│           ├── services/       # API 服务
│           └── config/         # 配置文件
├── docs/                       # 开发文档
└── API-doc/                    # 第三方 API 文档
```

## 主要功能模块

| 模块 | 路径 | 说明 |
|------|------|------|
| 渠道管理 | /channels | 配置供应商渠道 API |
| 平台管理 | /platforms | 管理电商平台 |
| 店铺管理 | /shops | 管理目标店铺及同步配置 |
| 商品池 | /listing/product-pool | 聚合商品，支持 Excel 导入 |
| 商品查询 | /listing/query | 从渠道查询商品 |
| 类目映射 | /listing/categories | 配置平台类目属性映射 |
| 自动同步 | /shops/auto-sync | 配置定时自动同步 |
| 同步日志 | /sync-logs | 查看同步记录 |

## 常见问题

### Q: 数据库连接失败？
确保 PostgreSQL 服务已启动，DATABASE_URL 配置正确。

### Q: Redis 连接失败？
确保 Redis 服务已启动，检查 REDIS_HOST、REDIS_PORT、REDIS_PASSWORD 配置。

### Q: 自动同步不生效？
1. 检查店铺的自动同步配置是否已启用
2. 确保 Redis 正常运行（队列依赖 Redis）
3. 查看后端日志中的 `[AutoSyncSchedulerService]` 相关信息

### Q: 平台 API 调用失败？
1. 检查店铺的 API 凭证配置是否正确
2. 查看同步日志中的错误详情
3. 确认 API 权限和配额

## 开发文档

- [开发指南](docs/DEVELOPMENT.md)
- [属性映射扩展指南](docs/ATTRIBUTE_MAPPING_EXTENSION_GUIDE.md)
- [Walmart 字段映射指南](docs/WALMART_FIELD_MAPPING_GUIDE.md)
- [标准商品字段说明](apps/web/src/pages/help/StandardFields.tsx)

## License

MIT
