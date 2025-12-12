# Implementation Plan

## 属性映射配置系统实现任务

> **注意**：现有代码中已存在 `CategoryAttributeMapping` 模型和基础的 `generatePlatformAttributes` 方法。
> 本任务主要是：1) 添加类型定义 2) 抽取公共服务 3) 扩展功能

- [x] 1. 创建核心接口和类型定义
  - [x] 1.1 创建映射规则接口文件
    - 创建 `apps/api/src/modules/attribute-mapping/interfaces/mapping-rule.interface.ts`
    - 定义 `MappingType`、`BaseMappingRule`、`MappingRule`、`MappingRulesConfig` 等接口
    - 替代现有代码中的 `any` 类型
    - _Requirements: 1.1, 2.1, 3.1, 4.1, 5.1_
  - [ ]* 1.2 Write property test for mapping rule types
    - **Property 1: Default Value Resolution**
    - **Validates: Requirements 1.3**

- [x] 2. 实现属性解析器服务
  - [x] 2.1 创建 AttributeResolverService
    - 创建 `apps/api/src/modules/attribute-mapping/attribute-resolver.service.ts`
    - 实现 `resolveAttributes` 方法，遍历规则并解析
    - 实现 `resolveRule` 方法，根据 mappingType 分发处理
    - _Requirements: 1.3, 2.4, 3.4, 4.4, 5.2_
  - [x] 2.2 实现 default_value 类型解析
    - 直接返回配置的固定值
    - _Requirements: 1.3_
  - [ ]* 2.3 Write property test for default_value resolution
    - **Property 1: Default Value Resolution**
    - **Validates: Requirements 1.3**
  - [x] 2.4 实现 channel_data 类型解析
    - 使用 `getNestedValue` 从 channelAttributes 提取值
    - 支持嵌套路径和数组索引
    - _Requirements: 2.3, 2.4, 2.5_
  - [ ]* 2.5 Write property test for channel_data resolution
    - **Property 2: Channel Data Path Extraction**
    - **Property 3: Missing Path Returns Undefined**
    - **Property 4: Nested Path Support**
    - **Validates: Requirements 2.3, 2.4, 2.5**
  - [x] 2.6 实现 enum_select 类型解析
    - 直接返回选中的枚举值
    - _Requirements: 3.4_
  - [ ]* 2.7 Write property test for enum_select resolution
    - **Property 5: Enum Select Direct Return**
    - **Validates: Requirements 3.4**
  - [x] 2.8 实现 auto_generate 类型解析
    - 根据 value.ruleType 分发到对应处理器
    - 实现基础规则：sku_prefix、sku_suffix
    - _Requirements: 4.4_
  - [ ]* 2.9 Write property test for auto_generate resolution
    - **Property 7: Auto Generate Rule Execution**
    - **Validates: Requirements 4.4**
  - [x] 2.10 实现 upc_pool 类型解析
    - 调用 UpcService.autoAssignUpc 获取 UPC
    - _Requirements: 5.2, 5.3_
  - [ ]* 2.11 Write property test for upc_pool resolution
    - **Property 8: UPC Pool Assignment**
    - **Property 9: UPC Marked As Used**
    - **Validates: Requirements 5.2, 5.3**

- [ ] 3. Checkpoint - Make sure all tests are passing
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. 创建属性映射模块
  - [x] 4.1 创建 AttributeMappingModule
    - 创建 `apps/api/src/modules/attribute-mapping/attribute-mapping.module.ts`
    - 导入 UpcModule，导出 AttributeResolverService
    - _Requirements: 4.5_
  - [x] 4.2 在 AppModule 中注册 AttributeMappingModule
    - 更新 `apps/api/src/app.module.ts`
    - _Requirements: 4.5_

- [x] 5. 集成到现有服务（重构现有代码）
  - [x] 5.1 重构 ListingService.generatePlatformAttributes
    - 删除 `ListingService` 中的 `generatePlatformAttributes` 方法
    - 改为调用 `AttributeResolverService.resolveAttributes`
    - 保持向后兼容，支持现有的 mappingRules JSON 结构
    - _Requirements: 1.3, 2.4, 3.4, 4.4, 5.2_
  - [x] 5.2 重构 PlatformCategoryService.generatePlatformAttributes
    - 删除 `PlatformCategoryService` 中的 `generatePlatformAttributes` 方法
    - 改为调用 `AttributeResolverService.resolveAttributes`
    - _Requirements: 1.3, 2.4, 3.4, 4.4, 5.2_
  - [ ]* 5.3 Write integration tests for attribute resolution
    - 测试完整的属性解析流程
    - _Requirements: 1.3, 2.4, 3.4, 4.4, 5.2_

- [ ] 6. Checkpoint - Make sure all tests are passing
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. 实现映射配置 API
  - [x] 7.1 创建预览映射结果 API
    - 添加 `POST /attribute-mapping/preview` 端点
    - 接收 mappingRules 和 channelAttributes，返回解析结果
    - _Requirements: 4.4_
  - [x] 7.2 创建获取标准字段列表 API
    - 添加 `GET /attribute-mapping/standard-fields` 端点
    - 返回所有可用的标准字段路径
    - _Requirements: 2.2_
  - [ ]* 7.3 Write API tests
    - 测试预览和标准字段 API
    - _Requirements: 2.2, 4.4_

- [x] 8. 前端组件实现
  - [x] 8.1 创建 MappingTypeSelector 组件
    - 映射类型选择器，显示 5 种基础类型
    - _Requirements: 1.1, 2.1, 3.1, 4.1, 5.1_
  - [x] 8.2 创建 StandardFieldSelector 组件
    - 标准字段选择器，显示所有可用字段路径
    - 支持搜索和分组显示
    - _Requirements: 2.2_
  - [x] 8.3 更新 AttributeMappingEditor 组件
    - 集成 MappingTypeSelector 和 StandardFieldSelector
    - 根据映射类型显示不同的配置表单
    - _Requirements: 1.1, 2.1, 3.1, 4.1, 5.1_
  - [x] 8.4 创建 MappingPreview 组件
    - 显示映射规则应用到示例商品后的结果
    - 调用预览 API 获取结果
    - _Requirements: 4.4_

- [ ] 9. Checkpoint - Make sure all tests are passing
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. 验证类目枚举值功能
  - [x] 10.1 验证枚举值与类目绑定
    - 确保 PlatformAttribute.enumValues 正确存储类目特定的枚举值
    - _Requirements: 6.1, 6.2_
  - [ ]* 10.2 Write property test for category-specific enum values
    - **Property 6: Category-Specific Enum Values**
    - **Validates: Requirements 3.3**
  - [ ]* 10.3 Write property test for mapping uniqueness
    - **Property 10: Mapping Uniqueness**
    - **Property 11: Category Mapping Independence**
    - **Validates: Requirements 6.3, 6.4**

- [ ] 11. Final Checkpoint - Make sure all tests are passing
  - Ensure all tests pass, ask the user if questions arise.
