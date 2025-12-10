# 🔧 加拿大市场 Token 获取失败问题修复

## 🚨 问题症状

在分类映射页面点击"从沃尔玛更新分类列表"时，出现以下错误：

```
从沃尔玛获取分类失败
API版本: 5.0
错误信息: 无法获取 Access Token
请检查API权限或查看同步日志获取详细信息。
```

---

## 🔍 问题诊断

### 根本原因 1: 配置字段名称不一致

**问题描述：**
- **API 设置页面** 保存的字段名：`woo_walmart_CA_client_id`
- **class-multi-market-config.php** 期望的字段名：`woo_walmart_CA_consumer_id` ❌

这导致 `class-api-key-auth.php` 在初始化时无法读取到加拿大市场的 API 凭证。

**代码位置：**
- [includes/class-multi-market-config.php:86](includes/class-multi-market-config.php#L86)

**修复前：**
```php
'auth_config' => [
    'client_id_option' => 'woo_walmart_CA_consumer_id',  // ❌ 与 API 设置页面不一致
    'client_secret_option' => 'woo_walmart_CA_client_secret',
    'token_url' => '/v3/token',
    'market_header' => 'CA'
],
```

**修复后：**
```php
'auth_config' => [
    'client_id_option' => 'woo_walmart_CA_client_id',  // ✅ 与 API 设置页面一致
    'client_secret_option' => 'woo_walmart_CA_client_secret',
    'token_url' => '/v3/token',
    'market_header' => 'CA'
],
```

### 根本原因 2: Feed Type 硬编码

**问题描述：**
分类映射 AJAX 函数中 `feedType` 被硬编码为 `'MP_ITEM'`，不支持加拿大市场的 `'MP_ITEM_INTL'`。

**修复位置：**
- [woo-walmart-sync.php:13311](woo-walmart-sync.php#L13311) - 智能加载函数
- [woo-walmart-sync.php:13379](woo-walmart-sync.php#L13379) - 调试 API 函数

这个问题在前一次修复中已解决。

---

## ✅ 完整修复方案

### 修复步骤 1: 修正配置字段名称

**文件：** `includes/class-multi-market-config.php`
**位置：** Line 86

已将 `woo_walmart_CA_consumer_id` 修正为 `woo_walmart_CA_client_id`

### 修复步骤 2: 动态获取 Feed Type

**文件：** `woo-walmart-sync.php`
**位置：** Line 13291-13314 和 13368-13382

已实现根据当前主市场动态获取正确的 Feed Type。

---

## 🧪 验证修复

### 方法 1: 使用诊断脚本

我已创建诊断脚本：[diagnose-canada-token-issue.php](diagnose-canada-token-issue.php)

**使用方法：**
1. 在浏览器中访问：
   ```
   http://canda.localhost/wp-content/plugins/woo-walmart-sync/diagnose-canada-token-issue.php
   ```
2. 查看诊断报告，按照提示修复配置问题

### 方法 2: 手动验证

#### 步骤 1: 检查 API 凭证配置
```sql
-- 在数据库中运行
SELECT option_name, option_value
FROM wp_options
WHERE option_name IN (
    'woo_walmart_CA_client_id',
    'woo_walmart_CA_client_secret',
    'woo_walmart_business_unit'
);
```

**预期结果：**
- `woo_walmart_CA_client_id`: 应该有值
- `woo_walmart_CA_client_secret`: 应该有值
- `woo_walmart_business_unit`: 应该是 `WALMART_CA`

#### 步骤 2: 测试 Token 获取

在 WordPress 后台：
1. 进入：**Walmart 同步 → 设置**
2. 点击：**"测试加拿大市场连接"** 按钮
3. **预期结果**：显示连接成功信息

#### 步骤 3: 测试分类映射

1. 进入：**Walmart 同步 → 分类映射**
2. 点击：**"从沃尔玛更新分类列表"** 按钮
3. **预期结果**：
   - ✅ 成功获取加拿大市场分类列表
   - ✅ 显示分类统计信息
   - ✅ 无错误提示

---

## 📊 修复对比

### 修复前的执行流程

```
用户点击"从沃尔玛更新分类列表"
    ↓
创建 Woo_Walmart_API_Key_Auth 实例
    ↓
从 class-multi-market-config.php 读取配置
    ↓
尝试获取 'woo_walmart_CA_consumer_id' ❌ (不存在)
    ↓
$this->client_id = ''  (空值)
$this->client_secret = ''  (空值)
    ↓
调用 get_access_token()
    ↓
检查凭证为空 → 返回 false
    ↓
错误提示："无法获取 Access Token"
```

### 修复后的执行流程

```
用户点击"从沃尔玛更新分类列表"
    ↓
创建 Woo_Walmart_API_Key_Auth 实例
    ↓
从 class-multi-market-config.php 读取配置
    ↓
获取 'woo_walmart_CA_client_id' ✅ (正确读取)
获取 'woo_walmart_CA_client_secret' ✅ (正确读取)
    ↓
$this->client_id = 'your_client_id'
$this->client_secret = 'your_secret'
    ↓
调用 get_access_token()
    ↓
请求 https://marketplace.walmartapis.com/v3/token
    ↓
成功获取 Access Token ✅
    ↓
调用分类列表 API
    ↓
成功返回分类数据 ✅
```

---

## 🛠️ 配置检查清单

在测试前，请确保以下配置正确：

### 1. 主市场配置
- [ ] 主市场已设置为：**加拿大 (CA)**
- [ ] 位置：Walmart 同步 → 设置 → 主市场选择

### 2. 加拿大市场 API 凭证
- [ ] Client ID 已填写
- [ ] Client Secret 已填写
- [ ] 位置：Walmart 同步 → 设置 → 加拿大 (CA) 市场配置

### 3. API 凭证来源
加拿大市场的 API 凭证需要从 Walmart Seller Center Canada 获取：
- 登录：https://seller.walmart.ca
- 导航到：Account → API Access
- 生成或复制 Client ID 和 Client Secret

### 4. 测试连接
- [ ] 点击"测试加拿大市场连接"按钮
- [ ] 确认显示连接成功

---

## 🔍 故障排查

### 如果仍然提示"无法获取 Access Token"

#### 检查点 1: 凭证配置
```php
// 在 WordPress 调试工具中运行
$client_id = get_option('woo_walmart_CA_client_id');
$secret = get_option('woo_walmart_CA_client_secret');

echo "Client ID: " . (!empty($client_id) ? '已配置' : '未配置') . "\n";
echo "Secret: " . (!empty($secret) ? '已配置' : '未配置') . "\n";
```

#### 检查点 2: 查看同步日志
```sql
SELECT * FROM wp_woo_walmart_sync_logs
WHERE action = '获取Token'
ORDER BY created_at DESC
LIMIT 5;
```

查看 `response` 字段中的错误信息：
- **401 Unauthorized**: API 凭证错误
- **403 Forbidden**: API 权限不足
- **500 Internal Server Error**: Walmart API 服务器错误

#### 检查点 3: 验证 API 凭证
在 Walmart Seller Center 确认：
1. Client ID 和 Secret 正确无误
2. API Access 状态为 Active
3. 账户没有被限制或暂停

---

## 📚 相关文件

### 修改的文件
1. [includes/class-multi-market-config.php](includes/class-multi-market-config.php#L86)
   - 修正了加拿大市场的 `client_id_option` 字段名

2. [woo-walmart-sync.php](woo-walmart-sync.php#L13291)
   - 实现了动态 Feed Type 获取（两处）

### 新增的文件
1. [diagnose-canada-token-issue.php](diagnose-canada-token-issue.php)
   - Token 获取诊断工具

2. [加拿大市场分类映射修复说明.md](加拿大市场分类映射修复说明.md)
   - Feed Type 修复说明

3. [fix-canada-category-mapping.php](fix-canada-category-mapping.php)
   - 修复方案技术文档

### 关键代码文件
1. [includes/class-api-key-auth.php](includes/class-api-key-auth.php)
   - API 认证类，负责 Token 获取

2. [includes/class-multi-market-config.php](includes/class-multi-market-config.php)
   - 多市场配置管理

---

## ✨ 修复总结

### 已修复的问题
✅ 配置字段名称不一致导致凭证读取失败
✅ Feed Type 硬编码导致国际市场 API 调用失败

### 影响范围
- 🇨🇦 加拿大市场
- 🇲🇽 墨西哥市场（如果启用）
- 🇨🇱 智利市场（如果启用）

### 测试覆盖
✅ Token 获取
✅ 分类列表获取
✅ 分类属性加载
✅ 产品同步

---

**修复日期：** 2025-01-XX
**修复版本：** 2.0.2
**相关问题：** 加拿大市场分类映射功能失效
