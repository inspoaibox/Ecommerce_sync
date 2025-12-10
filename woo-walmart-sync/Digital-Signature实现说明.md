# Digital Signature 认证实现完成

## 实现概述

已完成 Walmart Marketplace API 的 **Digital Signature（旧版）认证** 完整实现，现在插件支持两种认证方式：

1. **OAuth 2.0（新版 - 推荐）**：使用 Client ID + Client Secret
2. **Digital Signature（旧版）**：使用 Consumer ID + Private Key + RSA 签名

## 主要修改文件

### 1. [woo-walmart-sync.php](woo-walmart-sync.php) (设置页面)

**新增功能**：
- 认证方式选择器（OAuth 2.0 / Digital Signature）
- 旧版认证凭证输入字段：
  - Consumer ID (旧版)
  - Private Key (旧版)
  - Channel Type (旧版)
- JavaScript 动态显示/隐藏旧版字段
- 保存逻辑支持新字段

**位置**：
- UI 显示：Lines 11766-11835
- 保存逻辑：Lines 11575-11590

### 2. [includes/class-api-key-auth.php](includes/class-api-key-auth.php) (核心认证类)

#### 修改 1: 构造函数 (Lines 13-42)
**新增私有属性**：
```php
private $consumer_id;      // 旧版认证
private $private_key;      // 旧版认证
private $auth_method;      // 认证方式
private $market_code;      // 当前市场代码
```

**动态加载凭证**：
- 根据 `woo_walmart_{market}_auth_method` 选项决定加载哪种凭证
- signature 模式：加载 Consumer ID 和 Private Key
- oauth 模式：加载 Client ID 和 Client Secret

#### 修改 2: 新增签名生成方法 (Lines 44-99)
```php
private function generate_signature()
```

**功能**：
- 生成 RSA-SHA256 签名
- 签名字符串格式：`Consumer ID + "\n" + Timestamp + "\n" + Key Version + "\n"`
- 使用 openssl_sign() 函数签名
- 返回 Base64 编码的签名、时间戳、密钥版本

**签名算法**：
```
消息 = Consumer ID + "\n" + Timestamp(毫秒) + "\n" + "1" + "\n"
签名 = RSA_SHA256(消息, Private Key)
编码 = Base64(签名)
```

#### 修改 3: make_request() 方法 (Lines 169-220)
**双认证支持**：
- 检查 `$this->auth_method`
- 如果是 `signature`：
  - 调用 `generate_signature()` 生成签名
  - 使用签名相关请求头（不使用 Access Token）
- 如果是 `oauth`：
  - 调用 `get_access_token()` 获取 Token
  - 使用 OAuth 2.0 请求头

**签名模式请求头**：
```php
'WM_CONSUMER.ID'           => Consumer ID
'WM_CONSUMER.INTIMESTAMP'  => 时间戳(毫秒)
'WM_SEC.KEY_VERSION'       => "1"
'WM_SEC.AUTH_SIGNATURE'    => Base64签名
'WM_SVC.NAME'              => 'Walmart Marketplace'
'WM_QOS.CORRELATION_ID'    => UUID
'WM_CONSUMER.CHANNEL.TYPE' => Channel Type UUID
'Content-Type'             => 'application/json'
'Accept'                   => 'application/json'
```

**OAuth 模式请求头**：
```php
'WM_SEC.ACCESS_TOKEN'      => Access Token
'WM_SVC.NAME'              => 'Walmart Marketplace'
'WM_QOS.CORRELATION_ID'    => UUID
'WM_CONSUMER.CHANNEL.TYPE' => Client ID
'Content-Type'             => 'application/json'
'Accept'                   => 'application/json'
```

#### 修改 4: make_file_upload_request() 方法 (Lines 469-546)
**批量上传双认证支持**：
- 与 make_request() 类似的双认证逻辑
- 支持 multipart/form-data 文件上传
- 签名模式和 OAuth 模式使用相同的头部结构
- Content-Type 设置为 `multipart/form-data; boundary=...`

#### 修改 5: get_market_channel_type() 方法 (Lines 248-272)
**智能 Channel Type 选择**：
- 签名模式：返回 `woo_walmart_{market}_legacy_channel_type` 配置的 UUID
- OAuth 模式：返回 Client ID
- 降级方案：使用 business_unit 名称

## 实现细节

### RSA 签名过程

1. **构建签名字符串**：
   ```
   $sign_string = $consumer_id . "\n" . $timestamp . "\n" . "1" . "\n"
   ```
   示例：
   ```
   e2461fa0-739b-4764-9d22-f73f990ab431
   1737383450123
   1

   ```

2. **加载私钥**：
   ```php
   $private_key_resource = openssl_pkey_get_private($this->private_key);
   ```

3. **生成签名**：
   ```php
   openssl_sign($sign_string, $signature_binary, $private_key_resource, OPENSSL_ALGO_SHA256);
   ```

4. **Base64 编码**：
   ```php
   $signature = base64_encode($signature_binary);
   ```

### 认证方式切换

用户可以在后台随时切换认证方式：

1. 进入 **Walmart 同步 → 设置**
2. 找到加拿大市场（或其他市场）配置
3. 选择认证方式：
   - ✓ OAuth 2.0 (新版 - 推荐)
   - ⚠️ Digital Signature (旧版 - 即将弃用)
4. 填写对应凭证
5. 保存设置
6. 清除 OPcache（访问 clear-opcache.php）

## 测试工具

### 1. [test-signature-auth.php](test-signature-auth.php)
**功能**：
- 检查当前认证配置
- 验证旧版凭证完整性
- 测试签名生成功能
- 检查代码实现状态

**访问**：
```
http://canda.localhost/wp-content/plugins/woo-walmart-sync/test-signature-auth.php
```

### 2. [clear-opcache.php](clear-opcache.php)
**功能**：清除 PHP OPcache，使代码修改生效

**访问**：
```
http://canda.localhost/wp-content/plugins/woo-walmart-sync/clear-opcache.php
```

### 3. [get-full-error.php](get-full-error.php)
**功能**：查看完整的 API 错误日志和请求头

**访问**：
```
http://canda.localhost/wp-content/plugins/woo-walmart-sync/get-full-error.php
```

## 使用步骤

### 配置旧版认证（Digital Signature）

1. 登录 Walmart 加拿大开发者门户：https://developer.walmart.com/ca/

2. 获取旧版凭证：
   - **Consumer ID**：`e2461fa0-739b-4764-9d22-f73f990ab431`
   - **Private Key**：完整的 PEM 格式私钥（包括 BEGIN/END 标记）
   - **Channel Type**：`d62e611e-606e-41b9-96cf-38ee37331c47`

3. 在 WordPress 后台配置：
   - 进入 **产品 → Walmart 同步 → 设置**
   - 找到 **加拿大 (CA) 市场配置**
   - 选择认证方式：**Digital Signature (旧版)**
   - 填写上述三个字段
   - 点击保存

4. 清除缓存：
   - 访问 `clear-opcache.php` 清除 PHP 缓存
   - 或在 phpstudy 控制面板重启 PHP

5. 测试实现：
   - 访问 `test-signature-auth.php` 验证配置
   - 在产品列表页面测试批量同步

### 测试批量同步

1. 进入 **产品** 列表页面
2. 勾选要同步的产品
3. 选择 **批量同步到 Walmart**
4. 点击 **应用**
5. 查看日志确认使用的认证方式

## 技术规格

### 签名算法
- **算法**：SHA256withRSA (PKCS#1 v1.5)
- **密钥格式**：PKCS#8 PEM
- **编码**：Base64

### 请求头差异

| 请求头 | OAuth 2.0 | Digital Signature |
|--------|-----------|-------------------|
| WM_SEC.ACCESS_TOKEN | ✓ (Access Token) | ✗ |
| WM_CONSUMER.ID | ✗ | ✓ (Consumer ID) |
| WM_CONSUMER.INTIMESTAMP | ✗ | ✓ (毫秒时间戳) |
| WM_SEC.KEY_VERSION | ✗ | ✓ (固定为 "1") |
| WM_SEC.AUTH_SIGNATURE | ✗ | ✓ (Base64签名) |
| WM_CONSUMER.CHANNEL.TYPE | ✓ (Client ID) | ✓ (Legacy UUID) |

### 兼容性

- ✅ 美国市场 (WALMART_US)
- ✅ 加拿大市场 (WALMART_CA)
- ✅ 墨西哥市场 (WALMART_MX)
- ✅ 智利市场 (WALMART_CL)

每个市场可以独立选择认证方式。

## 日志记录

所有认证相关操作都会记录到数据库：

- **生成签名**：记录成功/失败、时间戳、签名预览
- **API请求**：记录请求头、响应内容
- **批量Feed上传**：记录完整的请求和响应

查看日志：
```php
SELECT * FROM wp_woo_walmart_sync_logs
WHERE action LIKE '%签名%' OR action LIKE '%Feed%'
ORDER BY created_at DESC
LIMIT 10;
```

## 注意事项

⚠️ **Digital Signature 认证已于 2019年8月28日弃用**

Walmart 官方建议所有新账户使用 OAuth 2.0 认证。Digital Signature 仅用于：
- 旧账户迁移
- 尚未启用 OAuth 2.0 的账户
- 特殊情况下的兼容性需求

**建议**：
1. 优先使用 OAuth 2.0（新版）认证
2. Digital Signature 仅作为临时方案
3. 尽快联系 Walmart 支持启用 OAuth 2.0

## 故障排除

### 1. 签名生成失败
**症状**：日志显示 "生成签名失败"

**解决方案**：
- 检查 Private Key 格式（必须包含 `-----BEGIN PRIVATE KEY-----` 和 `-----END PRIVATE KEY-----`）
- 确认 Consumer ID 已填写
- 查看 PHP 错误日志中的 openssl 错误信息

### 2. API 返回 401 Unauthorized
**症状**：批量同步失败，错误信息包含 "Unauthorized"

**解决方案**：
- 验证 Consumer ID 是否正确
- 检查 Private Key 是否完整
- 确认时间戳生成正确（系统时间是否准确）

### 3. Channel Type 错误
**症状**：API 返回 "WM_CONSUMER.CHANNEL.TYPE set null or invalid"

**解决方案**：
- 检查 "Channel Type (旧版)" 字段是否已填写
- 确认填写的值与 Walmart 后台显示的一致
- 访问 `test-signature-auth.php` 查看配置状态

## 完成状态

✅ **已完成**：
1. 设置页面 UI（认证方式选择器）
2. 旧版凭证存储和加载
3. RSA-SHA256 签名生成算法
4. make_request() 双认证支持
5. make_file_upload_request() 双认证支持
6. 智能 Channel Type 选择
7. 完整的日志记录
8. 测试工具和诊断页面

🎯 **可以开始测试**：
- 配置旧版凭证
- 清除 OPcache
- 测试批量同步功能
- 查看错误日志验证认证方式

---

**最后更新**：2025-01-20
**实现版本**：v1.0 - Digital Signature 完整实现
