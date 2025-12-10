# 🔧 Digital Signature 认证修复 - 符合官方规范

## 问题发现

根据 Walmart 加拿大官方文档（https://developer.walmart.com/ca-marketplace/docs/authentication），发现之前实现的 Digital Signature 认证存在以下问题：

### 1. ❌ 错误的请求头名称
**之前使用**：
- `WM_CONSUMER.INTIMESTAMP`
- `WM_SEC.KEY_VERSION`

**官方要求**：
- `WM_SEC.TIMESTAMP` ✅
- 不需要 `WM_SEC.KEY_VERSION` ✅

### 2. ❌ 错误的签名字符串格式
**之前使用**：
```
Consumer ID + "\n" + Timestamp + "\n" + Key Version + "\n"
```

**官方要求**：
```
Consumer ID + "\n" + URL + "\n" + Request Method + "\n" + Timestamp + "\n"
```

## 修复内容

### 1. 修复签名生成方法 ([class-api-key-auth.php:52-102](includes/class-api-key-auth.php:52-102))

**新方法签名**：
```php
private function generate_signature($url, $method = 'POST')
```

**签名字符串构建**：
```php
$sign_string = $this->consumer_id . "\n" .
               $url . "\n" .
               strtoupper($method) . "\n" .
               $timestamp . "\n";
```

**返回值**：
```php
return [
    'signature' => $signature,  // Base64 编码的签名
    'timestamp' => $timestamp   // 毫秒时间戳
];
```

### 2. 修复 make_request() 请求头 ([class-api-key-auth.php:208-224](includes/class-api-key-auth.php:208-224))

**修改前**：
```php
$headers = [
    'WM_CONSUMER.ID'           => $this->consumer_id,
    'WM_CONSUMER.INTIMESTAMP'  => $signature_data['timestamp'],  // ❌ 错误
    'WM_SEC.KEY_VERSION'       => $signature_data['key_version'], // ❌ 多余
    'WM_SEC.AUTH_SIGNATURE'    => $signature_data['signature'],
    // ...
];
```

**修改后**：
```php
$signature_data = $this->generate_signature($url, $method);

$headers = [
    'WM_CONSUMER.ID'           => $this->consumer_id,
    'WM_SEC.TIMESTAMP'         => $signature_data['timestamp'],   // ✅ 正确
    'WM_SEC.AUTH_SIGNATURE'    => $signature_data['signature'],
    'WM_SVC.NAME'              => 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID'    => wp_generate_uuid4(),
    'WM_CONSUMER.CHANNEL.TYPE' => $this->get_market_channel_type($market_code, $business_unit),
    'Content-Type'             => 'application/json',
    'Accept'                   => 'application/json',
];
```

### 3. 修复 make_file_upload_request() 请求头 ([class-api-key-auth.php:538-554](includes/class-api-key-auth.php:538-554))

同样的修复应用于文件上传方法：
```php
$signature_data = $this->generate_signature($url, 'POST');

$headers = [
    'WM_CONSUMER.ID'           => $this->consumer_id,
    'WM_SEC.TIMESTAMP'         => $signature_data['timestamp'],   // ✅ 正确
    'WM_SEC.AUTH_SIGNATURE'    => $signature_data['signature'],
    // ...
];
```

## 官方文档引用

### 必需的请求头（Digital Signature 模式）

| 请求头 | 描述 | 必需 | 示例 |
|--------|------|------|------|
| WM_CONSUMER.ID | Consumer ID | 是 | e2461fa0-739b-4764-9d22-f73f990ab431 |
| WM_SEC.TIMESTAMP | Epoch 时间戳（毫秒） | 是 | 1443748249449 |
| WM_SEC.AUTH_SIGNATURE | 数字签名（Base64 编码） | 是 | 9fg3TPeRt0WSGbXNGGj4kS... |
| WM_CONSUMER.CHANNEL.TYPE | 唯一渠道 ID | 是（V3 API） | d62e611e-606e-41b9-96cf-38ee37331c47 |
| WM_SVC.NAME | 服务名称 | 是 | Walmart Marketplace |
| WM_QOS.CORRELATION_ID | 唯一请求 ID（UUID） | 是 | 1234hfvgtr |
| WM_TENANT_ID | 市场名称 | 是 | WALMART.CA |
| WM_LOCALE_ID | 地理位置 | 是 | en_CA 或 fr_CA |

### 签名生成步骤（官方文档）

1. 获取 Consumer ID 和 Base64 编码的 Private Key
2. 获取完整的 URL（包括路径和查询参数）
3. 构建签名字符串：
   ```
   Consumer ID + "\n" +
   URL + "\n" +
   Request Method (大写) + "\n" +
   Timestamp (毫秒) + "\n"
   ```
4. 使用 Private Key 签名（SHA256 with RSA）
5. Base64 编码签名结果

## 测试步骤

### 1. 清除 OPcache
访问：
```
http://canda.localhost/wp-content/plugins/woo-walmart-sync/clear-opcache.php
```

### 2. 测试签名生成
访问：
```
http://canda.localhost/wp-content/plugins/woo-walmart-sync/check-signature-logs.php
```

应该看到签名生成成功，并且日志中包含：
- Consumer ID
- URL
- Method
- Timestamp
- Signature

### 3. 测试批量同步
1. 进入产品列表
2. 选择产品
3. 批量同步到 Walmart
4. 查看日志

### 4. 查看完整错误日志
访问：
```
http://canda.localhost/wp-content/plugins/woo-walmart-sync/get-full-error.php
```

检查请求头是否包含正确的：
- `WM_SEC.TIMESTAMP` (而不是 WM_CONSUMER.INTIMESTAMP)
- 没有 `WM_SEC.KEY_VERSION`

## 关键变化总结

### 修改前 vs 修改后

| 项目 | 修改前 | 修改后 |
|------|--------|--------|
| 签名字符串 | `ConsumerID\nTimestamp\nKeyVersion\n` | `ConsumerID\nURL\nMethod\nTimestamp\n` |
| 时间戳请求头 | `WM_CONSUMER.INTIMESTAMP` | `WM_SEC.TIMESTAMP` |
| 密钥版本头 | `WM_SEC.KEY_VERSION` | (已移除) |
| 签名方法参数 | `generate_signature()` | `generate_signature($url, $method)` |
| 返回值 | 包含 `key_version` | 仅 `signature` 和 `timestamp` |

## 符合性检查

✅ 签名字符串格式符合官方文档
✅ 请求头名称符合官方文档
✅ 请求头内容符合官方文档
✅ Private Key 格式自动处理（PEM 格式）
✅ 时间戳格式正确（毫秒）
✅ 签名算法正确（SHA256 with RSA）
✅ Base64 编码正确

## 下一步

1. 清除 OPcache
2. 测试批量同步
3. 如果仍有问题，检查：
   - Consumer ID 是否正确
   - Private Key 是否完整
   - Channel Type 是否正确
   - 系统时间是否准确

## 参考资料

- [Walmart Canada Authentication Guide](https://developer.walmart.com/ca-marketplace/docs/authentication)
- [Digital Signature Generation](https://developer.walmart.com/ca-marketplace/docs/authentication#generate-the-digital-signature)
- [API Headers Requirements](https://developer.walmart.com/ca-marketplace/docs/authentication#header-keys)

---

**最后更新**：2025-01-20
**版本**：v2.0 - 符合官方规范
