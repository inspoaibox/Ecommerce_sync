# Requirements Document

## Introduction

商品刊登（Product Listing）模块是一个独立于现有商品同步系统的新功能，主要用于从渠道获取完整的商品详情数据（包括标题、描述、尺寸、属性、图片等），在本地进行加工整理后，刊登到目标平台（如 Walmart）。该模块与现有的价格/库存同步系统完全独立，拥有独立的商品数据存储和店铺关联。

## Glossary

- **Listing Product（刊登商品）**: 用于刊登的商品数据，包含完整的商品详情信息，与同步商品（Product）独立存储
- **Channel（渠道）**: 商品数据来源，如 GigaCloud，复用现有渠道管理
- **Platform（平台）**: 商品刊登的目标平台，如 Walmart，复用现有平台管理
- **Listing Shop（刊登店铺）**: 用于商品刊登的店铺，与同步店铺共享配置但商品数据独立
- **Category（类目）**: 平台的商品分类体系
- **Attribute（属性）**: 平台类目下的商品属性字段定义

## Requirements

### Requirement 1: 刊登商品查询

**User Story:** As a 运营人员, I want to 从渠道查询商品完整详情, so that 我可以获取刊登所需的全部商品资料。

#### Acceptance Criteria

1. WHEN 用户输入 SKU 列表并点击查询 THEN THE 系统 SHALL 调用渠道的商品详情、价格、库存三个接口获取完整数据
2. WHEN 查询完成 THEN THE 系统 SHALL 展示商品的标题、描述、尺寸、重量、图片、属性等完整信息
3. WHEN 查询结果返回 THEN THE 系统 SHALL 支持按 SKU、标题、类目进行筛选和排序
4. WHEN 用户选择商品 THEN THE 系统 SHALL 支持批量选择和全选操作
5. IF 渠道接口调用失败 THEN THE 系统 SHALL 显示具体错误信息并允许重试

### Requirement 2: 刊登商品导入

**User Story:** As a 运营人员, I want to 将查询到的商品导入到刊登店铺, so that 我可以在本地管理和编辑这些商品。

#### Acceptance Criteria

1. WHEN 用户选择商品并选择目标刊登店铺 THEN THE 系统 SHALL 将商品完整信息保存到刊登商品表
2. WHEN 导入商品 THEN THE 系统 SHALL 保存商品的所有详情字段（标题、描述、尺寸、属性、图片URL等）
3. WHEN 导入已存在的 SKU THEN THE 系统 SHALL 提示用户选择跳过或更新
4. WHEN 导入完成 THEN THE 系统 SHALL 显示导入结果统计（成功、失败、跳过数量）
5. WHEN 导入商品 THEN THE 系统 SHALL 记录商品来源渠道和导入时间

### Requirement 3: 刊登商品管理

**User Story:** As a 运营人员, I want to 管理已导入的刊登商品, so that 我可以查看、编辑和删除商品信息。

#### Acceptance Criteria

1. WHEN 用户访问刊登商品列表 THEN THE 系统 SHALL 展示商品的基本信息和刊登状态
2. WHEN 用户点击商品详情 THEN THE 系统 SHALL 展示商品的完整信息包括所有图片和属性
3. WHEN 用户编辑商品 THEN THE 系统 SHALL 允许修改标题、描述、属性等可编辑字段
4. WHEN 用户删除商品 THEN THE 系统 SHALL 支持单个删除和批量删除
5. WHEN 用户筛选商品 THEN THE 系统 SHALL 支持按店铺、渠道、刊登状态、类目进行筛选

### Requirement 4: 平台类目管理

**User Story:** As a 运营人员, I want to 获取和管理平台类目信息, so that 我可以为商品选择正确的刊登类目。

#### Acceptance Criteria

1. WHEN 用户请求同步类目 THEN THE 系统 SHALL 从平台 API 获取完整的类目树结构
2. WHEN 类目同步完成 THEN THE 系统 SHALL 将类目数据缓存到本地数据库
3. WHEN 用户浏览类目 THEN THE 系统 SHALL 以树形结构展示类目层级
4. WHEN 用户搜索类目 THEN THE 系统 SHALL 支持按类目名称模糊搜索
5. WHEN 用户选择类目 THEN THE 系统 SHALL 显示该类目下的必填和可选属性

### Requirement 5: 平台属性管理

**User Story:** As a 运营人员, I want to 获取平台类目的属性定义, so that 我可以正确填写商品的刊登属性。

#### Acceptance Criteria

1. WHEN 用户选择一个类目 THEN THE 系统 SHALL 获取该类目下的所有属性定义
2. WHEN 展示属性 THEN THE 系统 SHALL 区分必填属性和可选属性
3. WHEN 属性有枚举值 THEN THE 系统 SHALL 展示可选的枚举值列表
4. WHEN 属性有验证规则 THEN THE 系统 SHALL 展示字段长度、格式等限制
5. WHEN 用户填写属性 THEN THE 系统 SHALL 根据属性类型提供合适的输入控件

### Requirement 6: 商品刊登提交

**User Story:** As a 运营人员, I want to 将商品提交到平台刊登, so that 商品可以在平台上架销售。

#### Acceptance Criteria

1. WHEN 用户选择商品并点击刊登 THEN THE 系统 SHALL 验证商品信息的完整性
2. WHEN 商品信息不完整 THEN THE 系统 SHALL 提示缺少的必填字段
3. WHEN 提交刊登 THEN THE 系统 SHALL 调用平台 API 创建或更新商品
4. WHEN 刊登完成 THEN THE 系统 SHALL 更新商品的刊登状态和平台商品ID
5. WHEN 刊登失败 THEN THE 系统 SHALL 记录错误信息并支持重试

### Requirement 7: 刊登状态跟踪

**User Story:** As a 运营人员, I want to 跟踪商品的刊登状态, so that 我可以了解刊登进度和处理失败情况。

#### Acceptance Criteria

1. WHEN 商品提交刊登 THEN THE 系统 SHALL 记录刊登任务和状态
2. WHEN 刊登处理中 THEN THE 系统 SHALL 定期查询平台获取最新状态
3. WHEN 刊登成功 THEN THE 系统 SHALL 更新商品状态为已刊登并保存平台商品ID
4. WHEN 刊登失败 THEN THE 系统 SHALL 显示失败原因并支持修改后重新提交
5. WHEN 用户查看刊登历史 THEN THE 系统 SHALL 展示所有刊登记录和结果

### Requirement 8: 数据独立性

**User Story:** As a 系统管理员, I want to 刊登数据与同步数据完全独立, so that 两个功能互不影响。

#### Acceptance Criteria

1. WHEN 刊登商品导入 THEN THE 系统 SHALL 存储到独立的刊登商品表（ListingProduct）
2. WHEN 查询刊登商品 THEN THE 系统 SHALL 只查询刊登商品表不影响同步商品
3. WHEN 店铺关联商品 THEN THE 系统 SHALL 刊登商品和同步商品使用独立的关联关系
4. WHEN 删除刊登商品 THEN THE 系统 SHALL 不影响同步商品表中的数据
5. WHEN 同步商品更新 THEN THE 系统 SHALL 不影响已导入的刊登商品数据
