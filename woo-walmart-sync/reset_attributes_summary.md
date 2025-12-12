# 重置属性完整设置和取值规则总结

## 📋 **所有重置属性列表**

基于 `generate_special_attribute_value` 方法中的 switch case 分析，以下是所有已设置的重置属性：

---

## 🔧 **基础产品信息属性**

### **1. 产品标识类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `productname` / `product_name` | 使用产品标题，如果为空则使用SKU | 产品标题或SKU | string (最多199字符) |
| `brand` | 从品牌属性获取，没有则使用 "Unbranded" | "Unbranded" | string (最多60字符) |
| `condition` | 固定值 | "New" | string |
| `sku` | 使用产品SKU | 产品SKU | string |

### **2. 描述和特征类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `shortdescription` / `short_description` | 从产品完整描述格式化 | 格式化的产品完整描述 | string (最多100000字符) |
| `keyfeatures` / `key_features` | 从产品描述提取段落，智能生成 | 智能生成的特征列表 | array (3-6个元素) |
| `material` | 从 "Main Material" 属性获取 | ["Wood"] | array |
| `color` | 从 "Main Color" 属性或标题提取 | null | string |
| `colorcategory` / `color_category` | 基于主颜色推断标准颜色 | "Multicolor" | string |
| `size` | 从尺寸属性获取 | null | string |

### **3. 图片类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `mainimageurl` / `main_image_url` | 获取产品主图URL | null | string |
| `productsecondaryimageurl` / `product_secondary_image_url` | 从产品图库获取，不足3张不补足 | 图库图片数组 | array |

---

## 📏 **尺寸和重量属性**

### **4. 组装后尺寸类 (JSONObject格式)**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `assembledproductlength` / `assembled_product_length` | 从产品尺寸第1个维度解析 | {"measure": 1.0, "unit": "in"} | measurement_object |
| `assembledproductwidth` / `assembled_product_width` | 从产品尺寸第2个维度解析 | {"measure": 1.0, "unit": "in"} | measurement_object |
| `assembledproductheight` / `assembled_product_height` | 从产品尺寸第3个维度解析 | {"measure": 1.0, "unit": "in"} | measurement_object |
| `assembledproductweight` / `assembled_product_weight` | 从 "Product Weight" 属性解析 | {"measure": 1.0, "unit": "lb"} | measurement_object |

### **5. 特殊尺寸类 (JSONObject格式)**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `armheight` / `arm_height` | 从扶手高度属性或描述提取 | {"measure": 1.0, "unit": "in"} | measurement_object |
| `seat_depth` / `seatdepth` | 只从指定的三个属性获取：'Seat Depth', 'seat_depth', 'SeatDepth' | {"measure": 1.0, "unit": "in"} | measurement_object |

### **6. 重量和运输类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `shippingweight` | 从多个重量字段计算，支持多包裹 | "1" | string |
| `maximumloadweight` / `maximum_load_weight` | 从最大承重属性获取 | null | string |

---

## 🛒 **商务和库存属性**

### **7. 价格和库存类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `price` | 使用产品价格，最多两位小数 | 1 | number |
| `quantity` | 获取WooCommerce库存数量 | 0 | integer |

### **8. 履行和物流类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `lagtime` / `fulfillmentlagtime` | 使用设置的备货时间 | 1 | integer (0-1) |
| `fulfillmentcenterid` / `fulfillment_center_id` | 根据市场选择履行中心ID | 根据市场而定 | string |
| `mustshipalone` / `must_ship_alone` | 固定值 | "No" | string |
| `shipsinoriginalpackaging` / `ships_in_original_packaging` | 固定值 | "Yes" | string |

---

## 📅 **日期和时间属性**

### **9. 日期类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `startdate` / `sitestartdate` | 当天往前推一天 | 昨天的ISO 8601格式 | string |
| `enddate` / `siteenddate` | 10年后 | 10年后的ISO 8601格式 | string |
| `releasedate` / `release_date` | 当天往前推一天 | 昨天的ISO 8601格式 | string |
| `inventoryavailabilitydate` / `inventory_availability_date` | 从属性获取 | null | string |

---

## ⚖️ **法规和合规属性**

### **10. 安全和认证类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `isprop65warningrequired` / `is_prop65_warning_required` | 固定值 | "No" | string |
| `prop65warningtext` / `prop65_warning_text` | 从Prop65警告属性获取 | null | string |
| `electronicsIndicator` / `electronics_indicator` | 固定值 | "No" | string |
| `chemicalAerosolPesticide` / `chemical_aerosol_pesticide` | 固定值 | "No" | string |
| `batterytechnologytype` / `battery_technology_type` | 从电池类型属性映射 | "Does Not Contain a Battery" | string |

### **11. 限制类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `salerestrictions` | 固定值 | "NONE" | string |
| `staterestrictions` / `state_restrictions` | 从属性获取或默认无限制 | [["stateRestrictionsText": "None"]] | array |

---

## 🔧 **产品特性属性**

### **12. 组装和包装类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `assemblyrequired` | 从组装属性获取 | "false" | string |
| `assemblyinstructions` / `assembly_instructions` | 从产品文档获取或占位符 | 占位符PDF URL | string |
| `countperpack` / `count_per_pack` | 从属性获取 | 1 | integer |
| `count` | 从属性获取 | 1 | integer |
| `multipackquantity` / `multipack_quantity` | 固定值 | 1 | integer |
| `piececount` / `piece_count` | 从属性获取 | 1 | integer |

### **13. 内容和净含量类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `netcontent` / `net_content` | 返回净含量对象结构 | 净含量对象 | object |
| `netcontentstatement` / `net_content_statement` | 从属性获取 | null | string |
| `itemsincluded` / `items_included` | 从包含物品属性获取 | [] | array |

---

## 🏷️ **制造商和产品线属性**

### **14. 制造商信息类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `manufacturer` | 从制造商属性获取 | null | string |
| `manufacturerpartnumber` / `manufacturer_part_number` | 从制造商零件号属性获取 | null | string |
| `modelnumber` / `model_number` | 从型号属性获取 | null | string |
| `productline` / `product_line` | 从产品线属性获取 | null | array |

### **15. 保修类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `haswrittenwarranty` / `has_written_warranty` | 根据保修属性判断 | "No" | string |
| `warrantytext` / `warranty_text` | 从保修文本属性获取 | null | string |
| `warrantyurl` / `warranty_url` | 从保修URL属性获取 | null | string |

---

## 🎯 **特殊用途属性**

### **16. 体育和娱乐类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `sportsleague` / `sports_league` | 从体育联盟属性获取 | null | string |
| `sportsteam` / `sports_team` | 从体育团队属性获取 | null | string |
| `occasion` | 从使用场合属性获取 | [] | array |

### **17. 认证和标识类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `thirdpartyaccreditationsymbolonproductpackagecode` / `third_party_accreditation_symbol` | 从第三方认证属性获取 | null | string |

---

## 🔄 **变体和更新属性**

### **18. 变体管理类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `isprimaryvariant` / `is_primary_variant` | 固定值 | "Yes" | string |
| `variantgroupid` / `variant_group_id` | 从变体组ID属性获取 | null | string |
| `variantattributenames` / `variant_attribute_names` | 固定值 | [] | array |
| `ispreorder` / `is_preorder` | 固定值 | "No" | string |

### **19. 更新控制类**
| 属性名 | 取值规则 | 默认值 | 数据类型 |
|--------|----------|--------|----------|
| `productidupddate` / `product_id_update` | 固定值 | "No" | string |
| `skuupdate` / `sku_update` | 固定值 | "No" | string |

---

## 📊 **统计总结**

- **总属性数量**: 约 60+ 个属性
- **测量对象类型**: 5个 (长宽高重量、扶手高度、座椅深度)
- **数组类型**: 8个 (材质、特征、图片、限制等)
- **字符串类型**: 40+ 个
- **数值类型**: 8个
- **布尔类型**: 多个 (以字符串形式返回)

## 🎯 **关键特点**

1. **智能默认值**: 大部分属性都有合理的默认值
2. **多源数据**: 优先从产品属性获取，然后从描述提取，最后使用默认值
3. **格式验证**: 支持V5.0 API的字符长度限制
4. **类型转换**: 自动转换为API要求的数据格式
5. **市场适配**: 根据不同市场调整特定属性值
