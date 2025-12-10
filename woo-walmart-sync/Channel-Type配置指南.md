# 🔍 WM_CONSUMER.CHANNEL.TYPE 正确配置指南

## 问题现状

批量同步失败，错误信息：
```json
{
    "code": "INVALID_REQUEST_HEADER.GMP_GATEWAY_API",
    "field": "WM_CONSUMER.CHANNEL.TYPE",
    "description": "WM_CONSUMER.CHANNEL.TYPE set null or invalid"
}
```

当前配置的值：`d62e611e-606e-41b9-96cf-38ee37331c47`（UUID 格式）

---

## 根据官方文档的说明

### WM_CONSUMER.CHANNEL.TYPE 是什么？

根据 Walmart Marketplace 官方文档：

> **WM_CONSUMER.CHANNEL.TYPE** 是一个唯一 ID，用于按渠道类型跟踪 API 消费者请求，在所有 API 调用中使用，以便除了 OAuth 凭证之外还能识别 API 请求。

### 如何获取正确的值？

#### 方案 1: 直接集成（推荐）

如果您直接与 Walmart API 集成（不通过第三方平台）：

1. **登录 Walmart 开发者门户**
   - 美国市场: https://developer.walmart.com/
   - 加拿大市场: https://developer.walmart.com/ca/

2. **导航到 API 设置**
   - 点击 "My Account"
   - 选择您的市场区域（Canada）
   - 进入 "API" 部分

3. **查找 Channel Type Code**
   - 在 "Consumer IDs & Private Keys" 标签页
   - **Channel Type Code 位于 API 页面的底部**
   - 这个值与您的 Consumer ID 一起显示

4. **重要提示**
   - Consumer ID 和 Channel Type Code 在重新生成 Private Key 时不会改变
   - 不同的市场可能有不同的 Channel Type Code

#### 方案 2: 通过解决方案提供商

如果您通过解决方案提供商/渠道合作伙伴集成：

- 解决方案提供商应使用他们自己的值
- 该值需要通过向 Partner Support 提交工单获取

---

## 验证步骤

### 步骤 1: 检查当前配置

访问：**Walmart 同步 → 设置**

找到加拿大市场的配置部分，检查以下字段：

| 配置项 | 当前值 | 是否正确 |
|--------|--------|----------|
| Client ID | (已配置) | ✓ |
| Client Secret | (已配置) | ✓ |
| Channel Type | `d62e611e-606e-41b9-96cf-38ee37331c47` | ❓ 需要验证 |

### 步骤 2: 从开发者门户获取正确的值

1. 登录 Walmart 加拿大开发者门户: https://developer.walmart.com/ca/
2. 进入 API 设置页面
3. 查看 "Consumer IDs & Private Keys" 标签
4. 在页面底部找到 **Channel Type Code**
5. 复制该值

### 步骤 3: 对比并更新

- **如果 Channel Type Code 与当前配置的值一致**：
  - 问题可能不在 Channel Type 上
  - 需要检查其他可能的问题

- **如果 Channel Type Code 不同**：
  - 在插件设置中更新为正确的值
  - 保存设置
  - 清除缓存
  - 重新测试

---

## 可能的问题原因

根据 Stack Overflow 和官方论坛的讨论，即使 Channel Type 值正确，仍可能出现错误的原因：

### 1. 账户配置问题

- 您的 Walmart 账户可能需要额外的权限或配置
- 某些 API 功能可能需要账户审批

### 2. 市场特定要求

- 加拿大市场可能有与美国市场不同的要求
- 某些 API 端点可能对国际市场有限制

### 3. API 版本兼容性

- 当前使用的 API 版本可能不支持某些功能
- 需要确认使用的端点和 Feed Type 是否正确

### 4. 请求格式问题

- 可能不是 Channel Type 的问题
- 可能是 Feed 数据格式或其他请求头的问题

---

## 下一步建议

### 立即操作

1. **验证 Channel Type Code**
   - 登录开发者门户确认正确的值
   - 如果不同，在插件中更新

2. **检查完整的 API 响应**
   - 查看是否有其他错误信息
   - 检查响应的状态码

3. **测试单个产品同步**
   - 在批量同步之前，先测试单个产品
   - 确认单个同步是否正常工作

### 如果问题持续

1. **联系 Walmart 支持**
   - 开发者支持: https://developer.walmart.com/support
   - 提供完整的错误日志和请求详情
   - 询问加拿大市场的具体要求

2. **检查账户状态**
   - 确认您的账户已启用 API 访问
   - 确认您有加拿大市场的销售权限
   - 检查是否需要额外的审批或配置

3. **查看 API 文档**
   - 加拿大市场 API 文档: https://developer.walmart.com/doc/ca/
   - 查看 Feed API 的具体要求
   - 确认请求格式是否正确

---

## 参考资料

### 官方文档

- [Walmart Canada - Generate API Keys](https://marketplacelearn.walmart.com/ca/guides/Getting%20started/Getting%20ready%20to%20sell/generate-api-keys)
- [Walmart Developer Portal](https://developer.walmart.com/)
- [Walmart Marketplace API Documentation](https://developer.walmart.com/api/)

### 社区讨论

- [Stack Overflow: WM_CONSUMER.CHANNEL.TYPE Error](https://stackoverflow.com/questions/68928893/walmart-api-error-wm-consumer-channel-type-set-null-or-invalid)
- [Walmart Developer Forums](https://developer.walmart.com/forums/)

---

## 当前技术状态

### 已完成的修复

✅ Feed Type 动态获取（MP_ITEM_INTL）
✅ API 凭证配置修复
✅ 添加 WM_CONSUMER.CHANNEL.TYPE 请求头
✅ 添加 WM_MARKET 请求头
✅ 使用 get_market_channel_type() 方法获取配置的值

### 当前请求头（已确认）

```
WM_SEC.ACCESS_TOKEN: eyJraWQiOiIwNjFk...
WM_SVC.NAME: Walmart Marketplace
WM_QOS.CORRELATION_ID: 752ea9ed-b42e-444e-9251-8806c3006d84
WM_CONSUMER.CHANNEL.TYPE: d62e611e-606e-41b9-96cf-38ee37331c47
Content-Type: multipart/form-data; boundary=...
Accept: application/json
WM_MARKET: CA
```

### 待验证

❓ Channel Type Code `d62e611e-606e-41b9-96cf-38ee37331c47` 是否是从 Walmart 开发者门户获取的正确值
❓ 账户是否有加拿大市场的 API 访问权限
❓ 是否需要其他特定的配置或审批

---

**最后更新**: 2025-01-20
**状态**: 等待用户验证 Channel Type Code
