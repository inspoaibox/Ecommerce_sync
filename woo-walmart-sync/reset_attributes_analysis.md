# 重置属性按钮功能分析

## 📋 **功能概述**

重置属性按钮是Walmart同步插件分类映射页面中的一个重要功能，用于清空现有属性配置并重新加载完整的V5.0规范属性。

## 🎯 **按钮位置**

### **页面位置**
- **路径**: `WooCommerce → Walmart同步 → 分类映射`
- **具体位置**: 每个分类映射区域的属性操作按钮组中

### **按钮外观**
```html
<button type="button" class="button button-secondary button-small force-replace-attributes-button" 
        title="清空现有属性，重新加载完整规范">
    <span class="dashicons dashicons-update"></span> 重置属性
</button>
```

## 🔧 **功能逻辑**

### **1. 触发条件**
- 用户点击"重置属性"按钮
- 必须已选择Walmart分类
- 支持普通映射和共享映射两种结构

### **2. 执行流程**

#### **步骤1: 验证和确认**
```javascript
// 检查是否已选择Walmart分类
if (!walmartCatId || !walmartCatName || walmartCatName === '-- 请选择一个沃尔玛分类 --') {
    alert('请先选择一个沃尔玛分类。');
    return;
}

// 如果存在现有属性，需要用户确认
if (existingCount > 0) {
    var confirmReplace = confirm(
        '确定要重置现有的 ' + existingCount + ' 个属性吗？\n\n' +
        '这将清空所有现有属性，重新加载 "' + walmartCatName + '" 的完整V5.0规范。\n\n' +
        '此操作不可撤销！'
    );
    
    if (!confirmReplace) {
        return;
    }
}
```

#### **步骤2: 清空现有属性**
```javascript
// 清空属性表格
tbody.empty();

// 更新按钮状态
button.text('重置中...').prop('disabled', true);
```

#### **步骤3: AJAX请求获取属性**
```javascript
$.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'get_walmart_category_attributes',
        nonce: '<?php echo wp_create_nonce('walmart_category_map_nonce'); ?>',
        category_id: walmartCatId,
        category_name: walmartCatName,
        force_refresh: true,    // 强制刷新缓存
        force_replace: true,    // 标记为强制替换
        use_database: true      // 优先从数据库读取已保存的属性
    },
    // ... 处理响应
});
```

#### **步骤4: 后端处理逻辑**
```php
// 检查是否应该从数据库读取已保存的属性
$use_database = isset($_POST['use_database']) ? $_POST['use_database'] : false;

if ($use_database) {
    // 从数据库读取已保存的属性
    $attributes = get_attributes_from_database($category_id);
    if (!empty($attributes)) {
        // 缓存结果并返回
        set_transient($transient_key, $attributes, DAY_IN_SECONDS);
        wp_send_json_success($attributes);
        return;
    }
}

// 如果数据库中没有，则调用Walmart API获取
if ($attributes === false) {
    // 使用V5.0沃尔玛 Get Spec API
    $endpoint = '/v3/items/spec';
    $body = [
        'feedType' => 'MP_ITEM',
        'version' => '5.0.20241118-04_39_24-api',
        'productTypes' => [$category_id]
    ];
    
    $result = $api_auth->make_request($endpoint, 'POST', $body);
    // ... 处理API响应
}
```

#### **步骤5: 属性重建**
```javascript
response.data.forEach(function(attr) {
    // 创建新的属性行
    var newRow = $('#attribute-row-template').html().replace(/{wc_cat_id}/g, wcCatId);
    var $newRow = $(newRow);
    
    // 设置属性名称（只读）
    $newRow.find('input[name*="[name]"]').val(attr.attributeName).prop('readonly', true);
    
    // 根据属性类型设置默认配置
    if (autoGenerateFields.includes(attr.attributeName)) {
        // 自动生成字段
        $newRow.find('.attr-type-selector').val('auto_generate');
        var generationRule = getAutoGenerationRule(attr.attributeName);
        sourceCell.html('<span style="color: #0073aa; font-weight: 500;">' + generationRule + '</span>');
        
    } else if (attr.enumValues && attr.enumValues.length > 0) {
        // 有枚举值的字段 - 创建下拉选择器
        $newRow.find('.attr-type-selector').val('walmart_field');
        var walmart_field_select = $('<select name="' + selectName + '" class="walmart-field-selector"></select>');
        
        attr.enumValues.forEach(function(enumValue) {
            walmart_field_select.append('<option value="' + enumValue + '">' + enumValue + '</option>');
        });
        
    } else if (walmartFields[attr.attributeName]) {
        // 预定义沃尔玛字段
        
    } else if (defaultValueFields[attr.attributeName]) {
        // 默认值字段
        $newRow.find('.attr-type-selector').val('default_value');
        sourceCell.html('<input type="text" name="' + selectName + '" value="' + defaultValueFields[attr.attributeName] + '">');
    }
    
    // 添加必填标识
    if (isRequired) {
        var requiredText = '■ 类目必填';  // 根据group不同显示不同标识
        $newRow.find('input[name*="[name]"]').after('<span class="is-required" style="color: #fd7e14;">' + requiredText + '</span>');
    }
    
    // 保存枚举值到隐藏字段
    if (attr.enumValues && attr.enumValues.length > 0) {
        var enumValuesJson = JSON.stringify(attr.enumValues);
        $newRow.append('<input type="hidden" name="enum_values[' + wcCatId + '][]" value="' + enumValuesJson + '">');
    }
    
    tbody.append($newRow);
});
```

## 🎯 **核心特性**

### **1. 智能属性分类**
重置时会根据属性特性自动设置不同的处理方式：

#### **自动生成字段**
```javascript
var autoGenerateFields = [
    'productName', 'brand', 'shortDescription', 'keyFeatures', 'mainImageUrl', 
    'material', 'bed_frame_type', 'bedSize', 'assembledProductLength', 
    'assembledProductWidth', 'assembledProductHeight', 'assembledProductWeight',
    'productSecondaryImageURL', 'productIdentifiers', 'netContent', 
    'box_spring_required', 'color', 'colorCategory', 'items_included', 
    'manufacturerPartNumber', 'maximumLoadWeight', 'modelNumber',
    'occasion', 'productLine', 'swatchImages', 'sku', 'price', 'ShippingWeight',
    'electronicsIndicator', 'fulfillmentCenterID', 'releaseDate', 'startDate', 'endDate'
];
```

#### **预定义Walmart字段**
```javascript
var walmartFields = {
    'isProp65WarningRequired': 'No',
    'condition': 'New',
    'has_written_warranty': 'Yes - Warranty Text',
    'smallPartsWarnings': '0 - No warning applicable'
};
```

#### **默认值字段**
```javascript
var defaultValueFields = {
    'warrantyText': 'This warranty does not cover damages caused by misuse, drops, or human error.',
    'assemblyInstructions': 'Assembly is effortless with our clear instructions...',
    'countPerPack': '1',
    'inflexKitComponent': 'No',
    'isAssemblyRequired': 'Yes',
    'multipackQuantity': '1',
    'pieceCount': '1',
    'preset_bed_positions': 'Flat',
    'profile': 'Profile',
    'suggested_number_of_people_for_assembly': '2',
    'count': '1',
    'stateRestrictions': 'None',
    'chemicalAerosolPesticide': 'No',
    'batteryTechnologyType': 'No',
    'fulfillmentLagTime': '1',
    'shipsInOriginalPackaging': 'Yes',
    'MustShipAlone': 'Yes',
    'IsPreorder': 'No'
};
```

### **2. 必填级别标识**
```javascript
// 根据属性分组显示不同的必填标识
switch(group) {
    case 'Visible':
        requiredText = '■ 类目必填';
        requiredColor = '#fd7e14';  // 橙色
        break;
    case 'Orderable':
        requiredText = '■ 通用必填';
        requiredColor = '#dc3545';  // 红色
        break;
    default:
        requiredText = '■ 必填';
        requiredColor = '#dc3545';
}
```

### **3. 枚举值处理**
```javascript
// 转换 allowed_values 为 enumValues
if (attr.allowed_values && !attr.enumValues) {
    if (typeof attr.allowed_values === 'string') {
        // 按 | 分割，过滤单位信息
        attr.enumValues = attr.allowed_values.split('|').filter(function(val) {
            return val.trim() && !val.startsWith('UNITS:') && !val.startsWith('DEFAULT_UNIT:');
        });
    } else if (Array.isArray(attr.allowed_values)) {
        attr.enumValues = attr.allowed_values;
    }
}
```

## 📊 **数据来源优先级**

### **1. 数据库优先**
```php
if ($use_database) {
    $attributes = get_attributes_from_database($category_id);
    if (!empty($attributes)) {
        // 从数据库读取成功，直接返回
        set_transient($transient_key, $attributes, DAY_IN_SECONDS);
        wp_send_json_success($attributes);
        return;
    }
}
```

### **2. API调用备用**
```php
if ($attributes === false) {
    // 数据库中没有数据，调用Walmart API
    $endpoint = '/v3/items/spec';
    $result = $api_auth->make_request($endpoint, 'POST', $body);
    // ... 处理API响应并保存到数据库
}
```

### **3. 数据库存储结构**
```sql
CREATE TABLE wp_walmart_product_attributes (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    product_type_id varchar(255) NOT NULL,
    attribute_name varchar(255) NOT NULL,
    is_required tinyint(1) DEFAULT 0,
    description text,
    attribute_type varchar(50),
    attribute_group varchar(50),
    allowed_values text,
    format varchar(100),
    validation_rules text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_attr (product_type_id, attribute_name)
);
```

## ⚠️ **注意事项**

### **1. 不可撤销操作**
- 重置操作会完全清空现有属性配置
- 用户的自定义映射设置会丢失
- 需要用户明确确认才能执行

### **2. 缓存管理**
- 重置时会强制清除属性缓存 (`force_refresh: true`)
- 新获取的属性会重新缓存24小时

### **3. 错误处理**
```javascript
success: function(response) {
    if(response.success) {
        alert('重置成功！已加载 ' + response.data.length + ' 个最新属性。');
    } else {
        alert('重置失败: ' + response.data.message);
    }
},
error: function() {
    alert('重置失败，请重试。');
},
complete: function() {
    button.text('重置属性').prop('disabled', false);
}
```

## 🎯 **使用场景**

1. **分类规范更新**：当Walmart更新分类规范时，重置获取最新属性
2. **配置错误修复**：当属性配置出现问题时，重置到默认状态
3. **批量重新配置**：需要重新配置所有属性映射时
4. **版本升级**：插件升级后需要更新属性规范时

**重置属性功能是一个强大但需要谨慎使用的工具，它能确保属性配置与Walmart最新规范保持同步。**
