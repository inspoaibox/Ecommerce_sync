# Implementation Plan - 商品刊登模块

## Phase 1: 数据模型和基础架构

- [x] 1. 创建数据库模型
  - [x] 1.1 在 Prisma schema 中添加 ListingProduct 模型
    - 包含通用字段、渠道原始数据、渠道属性、平台属性、AI优化数据等
    - _Requirements: 2.1, 2.2, 8.1_
  - [x] 1.2 添加 PlatformCategory 和 PlatformAttribute 模型
    - 类目树结构、属性定义（必填/可选/枚举值）
    - _Requirements: 4.1, 4.2, 5.1, 5.2_
  - [x] 1.3 添加 ListingTask 模型
    - 刊登任务状态跟踪
    - _Requirements: 7.1_
  - [x] 1.4 添加 ListingStatus 和 ListingTaskStatus 枚举
    - _Requirements: 7.1_
  - [x] 1.5 运行 Prisma migration 生成数据库表
    - _Requirements: 8.1_

- [x] 2. Checkpoint - 确保数据库迁移成功

  - Ensure all tests pass, ask the user if questions arise.

## Phase 2: 渠道适配器扩展

- [-] 3. 扩展 GigaCloud 渠道适配器

  - [x] 3.1 实现 fetchProductDetails 方法


    - 调用详情、价格、库存三个接口并合并数据
    - 返回 ChannelProductDetail 结构
    - _Requirements: 1.1_
  - [ ] 3.2 编写属性测试：渠道查询数据完整性
    - **Property 1: Channel Query Data Completeness**
    - **Validates: Requirements 1.1**
  - [x] 3.3 实现 getAttributeDefinitions 方法


    - 返回 GigaCloud 渠道支持的属性字段定义
    - _Requirements: 1.1_

- [ ] 4. Checkpoint - 确保渠道适配器测试通过
  - Ensure all tests pass, ask the user if questions arise.

## Phase 3: 刊登模块后端服务

- [x] 5. 创建 Listing 模块基础结构

  - [x] 5.1 创建 listing.module.ts
    - _Requirements: 2.1_
  - [x] 5.2 创建 listing.controller.ts 和路由
    - _Requirements: 2.1, 3.1_
  - [x] 5.3 创建 listing.service.ts 基础框架
    - _Requirements: 2.1_
  - [x] 5.4 创建 DTO 文件（query, import, update, submit）
    - _Requirements: 1.1, 2.1, 3.3, 6.1_

- [x] 6. 实现商品查询功能
  - [x] 6.1 实现 queryFromChannel 方法
    - 从渠道查询商品完整详情
    - _Requirements: 1.1_
  - [x] 6.2 实现查询结果的数据转换
    - 将渠道数据转换为统一的 ChannelProductDetail 格式
    - _Requirements: 1.1_

- [x] 7. 实现商品导入功能
  - [x] 7.1 实现 importProducts 方法
    - 将商品保存到 ListingProduct 表
    - 处理重复 SKU（跳过或更新）
    - _Requirements: 2.1, 2.2, 2.3, 2.5_
  - [ ] 7.2 编写属性测试：导入数据完整性
    - **Property 3: Import Data Integrity**
    - **Validates: Requirements 2.1, 2.2, 2.5**
  - [ ] 7.3 编写属性测试：导入统计一致性
    - **Property 4: Import Statistics Consistency**
    - **Validates: Requirements 2.4**

- [x] 8. 实现商品管理功能
  - [x] 8.1 实现 getListingProducts 方法（分页查询）
    - 支持按店铺、渠道、状态、类目筛选
    - _Requirements: 3.1, 3.5_
  - [ ] 8.2 编写属性测试：商品筛选正确性
    - **Property 2: Product Filter Correctness**
    - **Validates: Requirements 1.3, 3.5**
  - [x] 8.3 实现 getListingProduct 方法（单个详情）
    - _Requirements: 3.2_
  - [x] 8.4 实现 updateListingProduct 方法
    - _Requirements: 3.3_
  - [ ] 8.5 编写属性测试：更新持久化
    - **Property 5: Update Persistence**
    - **Validates: Requirements 3.3**
  - [x] 8.6 实现 deleteListingProducts 方法
    - 支持单个和批量删除
    - _Requirements: 3.4_
  - [ ] 8.7 编写属性测试：删除有效性
    - **Property 6: Delete Effectiveness**
    - **Validates: Requirements 3.4**

- [ ] 9. Checkpoint - 确保商品管理功能测试通过
  - Ensure all tests pass, ask the user if questions arise.

## Phase 4: 平台类目模块

- [x] 10. 创建 PlatformCategory 模块
  - [x] 10.1 创建 platform-category.module.ts
    - _Requirements: 4.1_
  - [x] 10.2 创建 platform-category.controller.ts
    - _Requirements: 4.1, 4.3, 4.4_
  - [x] 10.3 创建 platform-category.service.ts
    - _Requirements: 4.1, 4.2_

- [x] 11. 实现类目同步功能
  - [x] 11.1 扩展 Walmart 适配器添加 getCategories 方法
    - _Requirements: 4.1_
  - [x] 11.2 实现 syncCategories 方法
    - 从平台获取类目并保存到本地
    - _Requirements: 4.1, 4.2_
  - [x] 11.3 实现 getCategoryTree 方法
    - 返回树形结构的类目数据
    - _Requirements: 4.3_
  - [x] 11.4 实现 searchCategories 方法
    - 按名称模糊搜索类目
    - _Requirements: 4.4_
  - [ ] 11.5 编写属性测试：类目搜索相关性
    - **Property 7: Category Search Relevance**
    - **Validates: Requirements 4.4**

- [x] 12. 实现属性管理功能
  - [x] 12.1 扩展 Walmart 适配器添加 getCategoryAttributes 方法
    - _Requirements: 5.1_
  - [x] 12.2 实现 getCategoryAttributes 服务方法
    - 获取并缓存类目属性定义
    - _Requirements: 5.1, 5.2_
  - [ ] 12.3 编写属性测试：属性必填标记
    - **Property 8: Attribute Required Flag**
    - **Validates: Requirements 5.2, 6.1, 6.2**

- [x] 12.5 实现类目属性映射配置功能
  - [x] 12.5.1 添加 CategoryAttributeMapping 数据模型
    - 存储类目属性映射规则（默认值、渠道数据、枚举选择、自动生成）
    - _Requirements: 新增需求_
  - [x] 12.5.2 实现映射配置 CRUD API
    - getCategoryAttributeMapping, saveCategoryAttributeMapping, deleteCategoryAttributeMapping
    - _Requirements: 新增需求_
  - [x] 12.5.3 实现 generatePlatformAttributes 方法
    - 根据映射规则自动生成平台属性
    - _Requirements: 新增需求_
  - [x] 12.5.4 更新 CategoryBrowser.tsx 页面
    - 添加属性映射配置表格和编辑功能
    - _Requirements: 新增需求_
  - [x] 12.5.5 更新 ListingQuery.tsx 导入弹窗
    - 添加类目选择器（必选）
    - _Requirements: 新增需求_
  - [x] 12.5.6 更新 importProducts 方法
    - 导入时自动应用类目映射规则生成平台属性
    - _Requirements: 新增需求_

- [ ] 13. Checkpoint - 确保类目模块测试通过
  - Ensure all tests pass, ask the user if questions arise.

## Phase 5: 商品刊登功能

- [ ] 14. 实现刊登验证
  - [ ] 14.1 实现商品信息完整性验证
    - 检查必填字段、平台属性
    - _Requirements: 6.1, 6.2_
  - [ ] 14.2 实现验证错误信息返回
    - 返回缺失的必填字段列表
    - _Requirements: 6.2_

- [ ] 15. 实现刊登提交
  - [ ] 15.1 扩展 Walmart 适配器添加 createItem/updateItem 方法
    - _Requirements: 6.3_
  - [ ] 15.2 实现 submitListing 方法
    - 创建 ListingTask，调用平台 API
    - _Requirements: 6.3, 7.1_
  - [ ] 15.3 编写属性测试：刊登任务创建
    - **Property 10: Listing Task Creation**
    - **Validates: Requirements 7.1**
  - [ ] 15.4 实现刊登状态更新
    - 成功时更新 listingStatus 和 platformItemId
    - _Requirements: 6.4, 7.3_
  - [ ] 15.5 编写属性测试：刊登状态更新
    - **Property 9: Listing Status Update**
    - **Validates: Requirements 6.4, 7.3**

- [ ] 16. 实现刊登任务管理
  - [ ] 16.1 实现 getListingTask 方法
    - _Requirements: 7.2_
  - [ ] 16.2 实现 getListingTasks 列表方法
    - _Requirements: 7.5_
  - [ ] 16.3 实现刊登失败处理
    - 记录错误信息，支持重试
    - _Requirements: 6.5, 7.4_

- [ ] 17. Checkpoint - 确保刊登功能测试通过
  - Ensure all tests pass, ask the user if questions arise.

## Phase 6: 数据独立性验证

- [ ] 18. 实现数据独立性
  - [ ] 18.1 确保 ListingProduct 和 Product 表完全独立
    - _Requirements: 8.1, 8.2_
  - [ ] 18.2 编写属性测试：数据独立性
    - **Property 11: Data Independence**
    - **Validates: Requirements 8.1, 8.2, 8.4, 8.5**

- [ ] 19. Checkpoint - 确保数据独立性测试通过
  - Ensure all tests pass, ask the user if questions arise.

## Phase 7: 前端页面开发

- [x] 20. 创建前端路由和菜单
  - [x] 20.1 在 App.tsx 中添加刊登模块路由
    - _Requirements: 1.1, 3.1_
  - [x] 20.2 添加侧边栏菜单项
    - _Requirements: 1.1, 3.1_

- [x] 21. 实现商品查询页面
  - [x] 21.1 创建 ListingQuery.tsx 页面
    - SKU 输入、渠道选择、查询按钮
    - _Requirements: 1.1_
  - [x] 21.2 实现查询结果展示
    - 表格展示商品详情、图片预览
    - _Requirements: 1.2_
  - [x] 21.3 实现商品选择和导入功能
    - 批量选择、选择店铺、导入按钮
    - _Requirements: 1.4, 2.1_

- [x] 22. 实现商品管理页面
  - [x] 22.1 创建 ListingProducts.tsx 页面
    - 商品列表、筛选、分页
    - _Requirements: 3.1, 3.5_
  - [x] 22.2 实现商品详情弹窗/页面
    - 展示完整信息、图片、属性
    - _Requirements: 3.2_
  - [x] 22.3 实现商品编辑功能
    - 编辑标题、描述、属性等
    - _Requirements: 3.3_
  - [x] 22.4 实现删除功能
    - 单个删除、批量删除
    - _Requirements: 3.4_

- [x] 23. 实现类目浏览页面
  - [x] 23.1 创建 CategoryBrowser.tsx 组件
    - 树形结构展示、搜索
    - _Requirements: 4.3, 4.4_
  - [x] 23.2 实现类目选择功能
    - 选择类目后展示属性
    - _Requirements: 4.5, 5.1_
  - [ ] 23.3 实现属性填写表单
    - 根据属性类型渲染不同控件
    - _Requirements: 5.3, 5.4, 5.5_

- [ ] 24. 实现刊登提交页面
  - [ ] 24.1 实现刊登验证和提交流程
    - 验证必填字段、提交到平台
    - _Requirements: 6.1, 6.2, 6.3_
  - [ ] 24.2 实现刊登任务列表
    - 展示任务状态、进度
    - _Requirements: 7.5_
  - [ ] 24.3 实现刊登结果展示
    - 成功/失败详情、重试按钮
    - _Requirements: 7.3, 7.4_

- [ ] 25. Checkpoint - 确保前端页面功能正常
  - Ensure all tests pass, ask the user if questions arise.

## Phase 8: AI 优化功能

- [ ] 26. 实现 AI 优化服务
  - [ ] 26.1 创建 ai-optimization.service.ts
    - _Requirements: AI 优化_
  - [ ] 26.2 实现 optimizeTitle 方法
    - _Requirements: AI 优化_
  - [ ] 26.3 实现 optimizeDescription 方法
    - _Requirements: AI 优化_
  - [ ] 26.4 实现 optimizeBulletPoints 方法
    - _Requirements: AI 优化_
  - [ ] 26.5 实现 optimizeAttributes 方法
    - _Requirements: AI 优化_

- [ ] 27. 实现 AI 优化前端
  - [ ] 27.1 在商品编辑页面添加 AI 优化按钮
    - _Requirements: AI 优化_
  - [ ] 27.2 实现优化结果预览和确认
    - _Requirements: AI 优化_
  - [ ] 27.3 实现批量优化功能
    - _Requirements: AI 优化_

- [ ] 28. Final Checkpoint - 确保所有功能测试通过
  - Ensure all tests pass, ask the user if questions arise.
