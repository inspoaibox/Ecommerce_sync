# Requirements Document

## Introduction

黑名单库功能用于管理和过滤商品数据中的敏感词、违禁词、品牌词等需要屏蔽或替换的内容。该功能支持大规模数据（数万至十几万条记录），提供高效的匹配、筛选、批量处理能力，并可在商品同步和刊登流程中自动应用。

## Glossary

- **Blacklist_Library**: 黑名单库，存储需要屏蔽或替换的词条集合
- **Blacklist_Entry**: 黑名单词条，单个需要匹配的关键词或短语
- **Match_Type**: 匹配类型，包括精确匹配、模糊匹配、正则匹配
- **Action_Type**: 处理动作，包括标记、删除、替换
- **Scan_Result**: 扫描结果，记录商品中匹配到的黑名单词条

## Requirements

### Requirement 1: 黑名单库管理

**User Story:** As a 运营人员, I want to 创建和管理多个黑名单库, so that 我可以按类别组织不同类型的敏感词（如品牌词、违禁词、敏感词等）。

#### Acceptance Criteria

1. WHEN 用户创建黑名单库 THEN the system SHALL 生成唯一标识并存储库名称、描述、类型和状态
2. WHEN 用户查看黑名单库列表 THEN the system SHALL 显示所有库的名称、词条数量、状态和最后更新时间
3. WHEN 用户编辑黑名单库 THEN the system SHALL 更新库的名称、描述和状态
4. WHEN 用户删除黑名单库 THEN the system SHALL 同时删除该库下的所有词条
5. WHEN 用户启用或禁用黑名单库 THEN the system SHALL 更新状态并影响后续匹配行为

### Requirement 2: 黑名单词条管理

**User Story:** As a 运营人员, I want to 批量导入和管理黑名单词条, so that 我可以高效维护大量敏感词数据。

#### Acceptance Criteria

1. WHEN 用户添加单个词条 THEN the system SHALL 存储词条内容、匹配类型、处理动作和替换值
2. WHEN 用户批量导入词条（CSV/Excel） THEN the system SHALL 解析文件并批量创建词条，支持10万条以上数据
3. WHEN 用户导入重复词条 THEN the system SHALL 跳过重复项并返回导入统计
4. WHEN 用户搜索词条 THEN the system SHALL 支持关键词模糊搜索并分页返回结果
5. WHEN 用户删除词条 THEN the system SHALL 从数据库移除该词条
6. WHEN 用户批量删除词条 THEN the system SHALL 支持按条件或选中项批量删除

### Requirement 3: 商品黑名单扫描

**User Story:** As a 运营人员, I want to 扫描商品数据中的黑名单词条, so that 我可以发现并处理包含敏感词的商品。

#### Acceptance Criteria

1. WHEN 用户发起商品扫描 THEN the system SHALL 检查商品的标题、描述、五点描述等文本字段
2. WHEN 扫描发现匹配词条 THEN the system SHALL 记录匹配位置、匹配词条和所属字段
3. WHEN 扫描完成 THEN the system SHALL 返回匹配商品列表和匹配详情
4. WHEN 用户筛选扫描结果 THEN the system SHALL 支持按黑名单库、匹配类型、商品状态筛选
5. WHEN 扫描大量商品（1万+） THEN the system SHALL 采用异步任务处理并显示进度

### Requirement 4: 黑名单匹配处理

**User Story:** As a 运营人员, I want to 对匹配到黑名单的商品进行批量处理, so that 我可以快速清理或修正敏感内容。

#### Acceptance Criteria

1. WHEN 用户选择批量替换 THEN the system SHALL 将匹配词条替换为预设的替换值
2. WHEN 用户选择批量删除词条 THEN the system SHALL 从商品文本中移除匹配的词条
3. WHEN 用户选择标记商品 THEN the system SHALL 为商品添加黑名单标记状态
4. WHEN 处理完成 THEN the system SHALL 记录处理日志并更新商品数据
5. WHEN 用户撤销处理 THEN the system SHALL 支持恢复到处理前的状态（保留原始数据）

### Requirement 5: 同步时自动匹配

**User Story:** As a 系统管理员, I want to 在商品同步时自动进行黑名单匹配, so that 敏感商品可以在入库时被自动标记或过滤。

#### Acceptance Criteria

1. WHEN 商品从渠道同步入库 THEN the system SHALL 自动执行黑名单扫描
2. WHEN 同步商品匹配到黑名单 THEN the system SHALL 根据配置执行标记、跳过或自动替换
3. WHEN 同步规则配置黑名单策略 THEN the system SHALL 支持选择应用的黑名单库
4. WHEN 自动处理完成 THEN the system SHALL 记录处理结果到同步日志

### Requirement 6: 高性能匹配引擎

**User Story:** As a 系统架构师, I want to 实现高效的黑名单匹配算法, so that 系统可以快速处理大量词条和商品数据。

#### Acceptance Criteria

1. WHEN 加载黑名单数据 THEN the system SHALL 构建高效的匹配索引结构（如 Trie 树或 Aho-Corasick 算法）
2. WHEN 执行单商品匹配 THEN the system SHALL 在 100ms 内完成 10 万词条的匹配
3. WHEN 黑名单数据更新 THEN the system SHALL 增量更新匹配索引
4. WHEN 系统启动 THEN the system SHALL 预加载活跃黑名单库到内存缓存

