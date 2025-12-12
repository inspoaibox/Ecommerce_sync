# AI 商品优化功能需求文档

## Introduction

本功能旨在为电商同步系统添加 AI 大模型支持，用于优化商品的标题、描述、五点描述等核心属性。通过集成多种 AI 模型（OpenAI、Gemini、第三方兼容接口），帮助卖家快速生成高质量的商品文案，提升商品在各平台的搜索排名和转化率。

## Glossary

- **AI Model**: AI 大模型配置，包含 API 密钥、端点等连接信息
- **Prompt Template**: 提示词模板，用于指导 AI 生成特定类型的内容
- **Optimization Task**: AI 优化任务，对商品属性进行 AI 处理的工作单元
- **Optimization Log**: 优化日志，记录 AI 优化的历史和结果

## Requirements

### Requirement 1: AI 模型配置管理

**User Story:** As a 系统管理员, I want to 配置多个 AI 模型连接, so that 系统可以灵活使用不同的 AI 服务提供商。

#### Acceptance Criteria

1. WHEN 管理员访问 AI 模型配置页面 THEN THE 系统 SHALL 显示已配置的模型列表，包含名称、类型、状态
2. WHEN 管理员添加新模型 THEN THE 系统 SHALL 支持三种类型：OpenAI、Gemini、OpenAI 兼容接口
3. WHEN 管理员配置 OpenAI 类型模型 THEN THE 系统 SHALL 要求填写 API Key、模型名称（如 gpt-4、gpt-3.5-turbo）
4. WHEN 管理员配置 Gemini 类型模型 THEN THE 系统 SHALL 要求填写 API Key、模型名称（如 gemini-pro）
5. WHEN 管理员配置 OpenAI 兼容接口 THEN THE 系统 SHALL 要求填写 API Key、Base URL、模型名称
6. WHEN 管理员测试模型连接 THEN THE 系统 SHALL 发送测试请求并显示连接状态
7. WHEN 管理员设置默认模型 THEN THE 系统 SHALL 在优化时优先使用该模型
8. WHEN 管理员禁用某个模型 THEN THE 系统 SHALL 不再使用该模型进行优化

### Requirement 2: Prompt 模板管理

**User Story:** As a 运营人员, I want to 创建和管理多种 Prompt 模板, so that 可以针对不同属性类型生成最优内容。

#### Acceptance Criteria

1. WHEN 用户访问 Prompt 模板页面 THEN THE 系统 SHALL 显示模板列表，包含名称、类型、创建时间
2. WHEN 用户创建新模板 THEN THE 系统 SHALL 要求选择模板类型：标题优化、描述优化、五点描述、关键词提取、通用
3. WHEN 用户编辑模板内容 THEN THE 系统 SHALL 提供变量占位符支持：{{title}}、{{description}}、{{characteristics}}、{{category}}、{{brand}}
4. WHEN 用户保存模板 THEN THE 系统 SHALL 验证模板内容不为空且包含必要的变量占位符
5. WHEN 用户设置模板为默认 THEN THE 系统 SHALL 在对应类型优化时自动使用该模板
6. WHEN 用户复制模板 THEN THE 系统 SHALL 创建模板副本供修改
7. WHEN 用户删除模板 THEN THE 系统 SHALL 检查是否为系统预设模板，预设模板不可删除

### Requirement 3: AI 优化工作台

**User Story:** As a 运营人员, I want to 使用 AI 优化商品属性, so that 可以快速生成高质量的商品文案。

#### Acceptance Criteria

1. WHEN 用户访问 AI 优化页面 THEN THE 系统 SHALL 显示商品选择区域和优化配置区域
2. WHEN 用户选择商品来源 THEN THE 系统 SHALL 支持从商品池或刊登商品中选择
3. WHEN 用户选择优化字段 THEN THE 系统 SHALL 支持多选：标题、描述、五点描述、关键词
4. WHEN 用户选择 AI 模型 THEN THE 系统 SHALL 显示可用模型列表，默认选中默认模型
5. WHEN 用户选择 Prompt 模板 THEN THE 系统 SHALL 根据优化字段类型筛选可用模板
6. WHEN 用户点击预览 THEN THE 系统 SHALL 显示将发送给 AI 的完整 Prompt 内容
7. WHEN 用户点击开始优化 THEN THE 系统 SHALL 创建优化任务并显示进度
8. WHEN 优化完成 THEN THE 系统 SHALL 显示原始内容和优化后内容的对比
9. WHEN 用户确认采用优化结果 THEN THE 系统 SHALL 更新商品的 aiOptimizedData 字段
10. WHEN 用户批量优化多个商品 THEN THE 系统 SHALL 按队列顺序处理并显示整体进度

### Requirement 4: 优化日志记录

**User Story:** As a 运营人员, I want to 查看 AI 优化历史记录, so that 可以追踪优化效果和排查问题。

#### Acceptance Criteria

1. WHEN 用户访问优化日志页面 THEN THE 系统 SHALL 显示日志列表，包含时间、商品、字段、模型、状态
2. WHEN 系统执行优化任务 THEN THE 系统 SHALL 记录：商品信息、优化字段、使用的模型、使用的模板、原始内容、优化结果、Token 消耗、耗时
3. WHEN 优化任务失败 THEN THE 系统 SHALL 记录错误信息和失败原因
4. WHEN 用户筛选日志 THEN THE 系统 SHALL 支持按时间范围、商品 SKU、优化字段、状态筛选
5. WHEN 用户查看日志详情 THEN THE 系统 SHALL 显示完整的请求和响应内容
6. WHEN 用户导出日志 THEN THE 系统 SHALL 支持导出为 CSV 格式

### Requirement 5: 与现有系统集成

**User Story:** As a 系统用户, I want to AI 优化功能与现有商品管理无缝集成, so that 可以在商品编辑时直接使用 AI 优化。

#### Acceptance Criteria

1. WHEN 用户在商品池详情页 THEN THE 系统 SHALL 显示"AI 优化"按钮
2. WHEN 用户在刊登商品编辑页 THEN THE 系统 SHALL 显示"AI 优化"按钮
3. WHEN 商品已有 AI 优化数据 THEN THE 系统 SHALL 显示"使用 AI 优化版本"开关
4. WHEN 用户启用 AI 优化版本 THEN THE 系统 SHALL 在刊登时使用 aiOptimizedData 中的内容
5. WHEN 用户在商品列表批量选择 THEN THE 系统 SHALL 支持批量发起 AI 优化任务
