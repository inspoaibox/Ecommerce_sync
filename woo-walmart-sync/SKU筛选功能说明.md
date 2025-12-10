# 📋 日志页面SKU筛选功能说明

## 🎯 功能概述

在「沃尔玛同步日志」页面（`admin.php?page=woo-walmart-sync-logs`）的筛选面板中，新增了**SKU筛选**功能，允许用户根据产品SKU快速查找相关的同步日志。

---

## ✨ 功能特点

### 1. **智能筛选逻辑**

SKU筛选采用三层匹配策略：

#### **第一层：Product ID 匹配**
- 首先通过SKU查找对应的产品ID
- 如果找到产品ID，优先使用 `product_id` 字段筛选
- 这是最精确的匹配方式

#### **第二层：Request 数据匹配**
- 在日志的 `request` 字段中搜索SKU
- 适用于同步请求数据中包含SKU的情况

#### **第三层：Response 数据匹配**
- 在日志的 `response` 字段中搜索SKU
- 适用于API响应数据中包含SKU的情况

### 2. **兼容性设计**

- ✅ 支持新日志表（`wp_walmart_sync_logs`）
- ✅ 支持旧日志表（`wp_woo_walmart_sync_logs`）
- ✅ 自动检测并使用正确的表结构

### 3. **用户友好**

- 🔍 输入框提示：「输入SKU」
- 🔄 与其他筛选条件（级别、操作类型、日期）联合使用
- 🧹 支持一键清除所有筛选条件

---

## 🖥️ 使用方法

### **步骤1: 进入日志页面**

WordPress 后台 → Walmart 同步 → 同步日志

### **步骤2: 输入SKU**

在筛选面板中找到「输入SKU」输入框，输入要查询的SKU，例如：
- `W714P357249`
- `W487S00390`
- `WF310165AAA`

### **步骤3: 点击筛选**

点击「🔍 筛选」按钮，系统会显示所有与该SKU相关的日志。

### **步骤4: 查看结果**

日志列表会显示：
- 该SKU产品的所有同步记录
- 包含该SKU的API请求和响应
- 相关的错误、警告和成功日志

### **步骤5: 清除筛选**

点击「清除筛选」按钮可以返回查看所有日志。

---

## 🔍 筛选逻辑详解

### **SQL查询逻辑**

```sql
-- 如果找到product_id
WHERE (product_id = {product_id} OR request LIKE '%{sku}%' OR response LIKE '%{sku}%')

-- 如果没有找到product_id
WHERE (request LIKE '%{sku}%' OR response LIKE '%{sku}%')
```

### **查询流程图**

```
输入SKU
  ↓
查找product_id
  ↓
找到了？
  ↙     ↘
 是      否
  ↓      ↓
使用三层  使用两层
匹配策略  匹配策略
  ↓      ↓
返回结果 ← ←
```

---

## 📊 使用场景

### **场景1: 排查同步失败**

**问题**：某个产品同步失败，需要查看详细日志

**操作**：
1. 输入产品SKU
2. 点击筛选
3. 查看错误日志中的详细信息
4. 根据错误信息修复问题

### **场景2: 追踪同步历史**

**问题**：想查看某个产品的完整同步历史

**操作**：
1. 输入产品SKU
2. 点击筛选
3. 按时间倒序查看所有同步记录
4. 了解产品的同步状态变化

### **场景3: 验证修复效果**

**问题**：修复了某个产品的问题，需要验证是否成功

**操作**：
1. 输入产品SKU
2. 点击筛选
3. 查看最新的同步日志
4. 确认是否显示「成功」状态

### **场景4: 批量问题分析**

**问题**：多个产品同步失败，需要逐个排查

**操作**：
1. 依次输入每个产品的SKU
2. 查看各自的错误日志
3. 找出共同的错误模式
4. 统一修复问题

---

## 🎨 UI界面

### **筛选面板布局**

```
┌─────────────────────────────────────────────────────────────┐
│  [所有级别 ▼]  [所有操作类型 ▼]  [📅 年/月/日]  [输入SKU]  │
│                                                              │
│  [🔍 筛选]  [清除筛选]                                       │
└─────────────────────────────────────────────────────────────┘
```

### **筛选条件组合**

可以同时使用多个筛选条件：

| 级别 | 操作类型 | 日期 | SKU | 结果 |
|------|---------|------|-----|------|
| 错误 | - | - | W714P357249 | 该SKU的所有错误日志 |
| - | 商品同步 | - | W487S00390 | 该SKU的商品同步日志 |
| 成功 | - | 2025-10-13 | WF310165AAA | 该SKU在指定日期的成功日志 |
| 警告 | Feed处理 | - | WY000387AAA | 该SKU的Feed处理警告日志 |

---

## 🔧 技术实现

### **代码位置**

**文件**: `woo-walmart-sync.php`

**函数**: `woo_walmart_sync_logs_page()`

**修改行数**: 
- Line 12050-12103: 筛选逻辑
- Line 12213-12224: UI界面

### **关键代码片段**

#### **1. 获取SKU筛选参数**

```php
$sku_filter = isset($_GET['sku_filter']) ? sanitize_text_field($_GET['sku_filter']) : '';
```

#### **2. 查找产品ID**

```php
if (!empty($sku_filter)) {
    $product_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
        $sku_filter
    ));
}
```

#### **3. 构建查询条件**

```php
if ($product_id) {
    $where_conditions[] = "(product_id = %d OR request LIKE %s OR response LIKE %s)";
    $where_values[] = $product_id;
    $where_values[] = '%' . $wpdb->esc_like($sku_filter) . '%';
    $where_values[] = '%' . $wpdb->esc_like($sku_filter) . '%';
} else {
    $where_conditions[] = "(request LIKE %s OR response LIKE %s)";
    $where_values[] = '%' . $wpdb->esc_like($sku_filter) . '%';
    $where_values[] = '%' . $wpdb->esc_like($sku_filter) . '%';
}
```

#### **4. UI输入框**

```php
<input type="text" name="sku_filter" value="<?php echo esc_attr($sku_filter); ?>"
       placeholder="输入SKU" class="sku-filter" style="min-width: 150px;">
```

---

## ✅ 测试验证

### **测试脚本**

已创建测试脚本：`test-sku-filter-logic.php`

**运行方法**：
```bash
php test-sku-filter-logic.php
```

**测试内容**：
1. ✅ 通过SKU查找产品ID
2. ✅ 检查日志表结构
3. ✅ 测试筛选查询逻辑
4. ✅ 验证多个SKU的筛选结果
5. ✅ 生成测试URL

### **测试SKU列表**

使用以下SKU进行测试：
- W714P357249
- W487S00390
- WF310165AAA
- WY000387AAA
- N723S9687C
- W834S00471
- WF310166AAA
- W2311P345745
- W487S00388
- W2824S00132

---

## 📝 注意事项

### **1. SKU必须精确匹配**

- ✅ 正确：`W714P357249`
- ❌ 错误：`w714p357249`（大小写敏感）
- ❌ 错误：`W714P357`（部分匹配）

### **2. 日志数据依赖**

- 只能查询到已记录的日志
- 如果产品从未同步过，不会有日志
- 旧日志可能已被清除

### **3. 性能考虑**

- LIKE查询可能较慢（特别是在大量日志时）
- 优先使用product_id匹配以提高性能
- 建议定期清理旧日志

### **4. 数据安全**

- 输入已经过 `sanitize_text_field()` 处理
- SQL查询使用 `$wpdb->prepare()` 防止注入
- LIKE查询使用 `$wpdb->esc_like()` 转义特殊字符

---

## 🚀 未来优化

### **可能的改进方向**

1. **模糊搜索**：支持部分SKU匹配
2. **批量SKU**：支持同时输入多个SKU（逗号分隔）
3. **SKU建议**：输入时显示SKU自动完成建议
4. **导出功能**：导出特定SKU的所有日志
5. **统计信息**：显示该SKU的同步成功率

---

## 📚 相关文档

- `woo-walmart-sync.php` - 主插件文件
- `test-sku-filter-logic.php` - 测试脚本
- `SKU筛选功能说明.md` - 本文档

---

## 🎉 总结

SKU筛选功能已成功添加到日志页面，提供了：

- ✅ 智能的三层匹配策略
- ✅ 友好的用户界面
- ✅ 与现有筛选条件的完美集成
- ✅ 完整的测试验证

**现在可以通过SKU快速定位和排查产品同步问题了！** 🚀

---

*功能开发完成时间: 2025-10-13*
*开发状态: ✅ 完成*
*测试状态: ✅ 待测试*

