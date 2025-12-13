/**
 * Walmart 平台默认属性映射规则
 *
 * 这些规则会在属性字段库为空时作为初始配置
 * 当加载平台属性时，系统会根据 attributeId 匹配这些规则
 *
 * 注意：attributeId 必须与 Walmart V5.0 Spec API 返回的字段名完全一致
 */

import { MappingRule } from '@/modules/attribute-mapping/interfaces/mapping-rule.interface';

/**
 * 默认映射规则配置（只包含映射相关字段）
 */
export interface DefaultMappingConfig {
  attributeId: string;
  attributeName: string;
  mappingType: MappingRule['mappingType'];
  value: any;
}

/**
 * Walmart 默认映射规则
 * attributeId 必须与 Walmart V5.0 Spec API 返回的 JSON Schema 属性名完全一致
 * Walmart API 返回的属性名格式：大部分是 camelCase，部分是 snake_case
 */
export const WALMART_DEFAULT_MAPPING_RULES: DefaultMappingConfig[] = [
  // ==================== 品牌和制造商 ====================
  {
    attributeId: 'brand',
    attributeName: 'Brand',
    mappingType: 'default_value',
    value: 'Unbranded',
  },

  // ==================== 商品基本信息 ====================
  {
    attributeId: 'productName',
    attributeName: 'Product Name',
    mappingType: 'channel_data',
    value: 'title',
  },
  {
    attributeId: 'shortDescription',
    attributeName: 'Site Description',
    mappingType: 'channel_data',
    value: 'description',
  },
  {
    attributeId: 'keyFeatures',
    attributeName: 'Key Features',
    mappingType: 'channel_data',
    value: 'bulletPoints',
  },

  // ==================== 图片 ====================
  {
    attributeId: 'mainImageUrl',
    attributeName: 'Main Image URL',
    mappingType: 'channel_data',
    value: 'mainImageUrl',
  },
  {
    attributeId: 'productSecondaryImageURL',
    attributeName: 'Additional Image URL',
    mappingType: 'channel_data',
    value: 'imageUrls',
  },

  // ==================== 合规信息 ====================
  {
    attributeId: 'isProp65WarningRequired',
    attributeName: 'Is Prop 65 Warning Required',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== 外观属性 ====================
  {
    attributeId: 'color',
    attributeName: 'Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'color_extract',
      param: '',
    },
  },
  {
    attributeId: 'material',
    attributeName: 'Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'material_extract',
      param: '',
    },
  },

  // ==================== 商品状态 ====================
  {
    attributeId: 'condition',
    attributeName: 'Condition',
    mappingType: 'enum_select',
    value: 'New',
  },
  {
    attributeId: 'has_written_warranty',
    attributeName: 'Has Written Warranty',
    mappingType: 'enum_select',
    value: 'Yes - Warranty Text',
  },
  {
    attributeId: 'warrantyText',
    attributeName: 'Warranty Text',
    mappingType: 'default_value',
    value: 'This warranty does not cover damages caused by misuse, drops, or human error.',
  },

  // ==================== 组装和安装 ====================
  {
    attributeId: 'isAssemblyRequired',
    attributeName: 'Is Assembly Required',
    mappingType: 'enum_select',
    value: 'Yes',
  },

  // ==================== 数量和规格 ====================
  {
    attributeId: 'pieceCount',
    attributeName: 'Number of Pieces',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'piece_count_extract',
      param: '1',
    },
  },
  {
    attributeId: 'netContent',
    attributeName: 'Net Content',
    mappingType: 'default_value',
    value: '1 Count',
  },

  // ==================== 使用场景 ====================
  {
    attributeId: 'recommendedLocations',
    attributeName: 'Recommended Locations',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'location_extract',
      param: 'Indoor,Outdoor',
    },
  },

  // ==================== 座位容量 ====================
  {
    attributeId: 'seatingCapacity',
    attributeName: 'Seating Capacity',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'seating_capacity_extract',
      param: '1',
    },
  },

  // ==================== 安全警告 ====================
  {
    attributeId: 'smallPartsWarnings',
    attributeName: 'Small Parts Warning Code',
    mappingType: 'enum_select',
    value: '0 - No warning applicable',
  },

  // ==================== 年龄分组 ====================
  {
    attributeId: 'ageGroup',
    attributeName: 'Age Group',
    mappingType: 'enum_select',
    value: 'Adult',
  },

  // ==================== 产品系列 ====================
  {
    attributeId: 'collection',
    attributeName: 'Collection',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'collection_extract',
      param: '',
    },
  },

  // ==================== 颜色分类 ====================
  {
    attributeId: 'colorCategory',
    attributeName: 'Color Category',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'color_category_extract',
      param: 'Multicolor',
    },
  },

  // ==================== 每包数量 ====================
  {
    attributeId: 'countPerPack',
    attributeName: 'Count Per Pack',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'piece_count_extract',
      param: '1',
    },
  },

  // ==================== 家居装饰风格 ====================
  {
    attributeId: 'homeDecorStyle',
    attributeName: 'Home Decor Style',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'home_decor_style_extract',
      param: 'Minimalist',
    },
  },

  // ==================== SKU ====================
  {
    attributeId: 'sku',
    attributeName: 'SKU',
    mappingType: 'channel_data',
    value: 'sku',
  },

  // ==================== 产品标识 ====================
  {
    attributeId: 'productIdentifiers',
    attributeName: 'Product Identifiers',
    mappingType: 'upc_pool',
    value: '',
  },

  // ==================== 价格 ====================
  {
    attributeId: 'price',
    attributeName: 'Selling Price',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'price_calculate',
      param: '',
    },
  },

  // ==================== 库存数量 ====================
  {
    attributeId: 'inventory',
    attributeName: 'Inventory',
    mappingType: 'channel_data',
    value: 'stock',
  },

  // ==================== 运输重量 ====================
  {
    attributeId: 'ShippingWeight',
    attributeName: 'Shipping Weight (lbs)',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'shipping_weight_extract',
      param: '1',
    },
  },

  // ==================== 套件组件 ====================
  {
    attributeId: 'inflexKitComponent',
    attributeName: 'Inflex Kit Component',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== 包含物品 ====================
  {
    attributeId: 'items_included',
    attributeName: 'Items Included',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'items_included_extract',
      param: '',
    },
  },

  // ==================== 家具腿颜色 ====================
  {
    attributeId: 'leg_color',
    attributeName: 'Leg Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'leg_color_extract',
      param: '',
    },
  },

  // ==================== 家具腿表面处理 ====================
  {
    attributeId: 'leg_finish',
    attributeName: 'Leg Finish',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'leg_finish_extract',
      param: '',
    },
  },

  // ==================== 家具腿材料 ====================
  {
    attributeId: 'leg_material',
    attributeName: 'Leg Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'leg_material_extract',
      param: '',
    },
  },

  // ==================== 制造商零件号 ====================
  {
    attributeId: 'manufacturerPartNumber',
    attributeName: 'Manufacturer Part Number',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'mpn_from_sku',
      param: '',
    },
  },

  // ==================== 客厅家具套装类型 ====================
  {
    attributeId: 'living_room_furniture_set_type',
    attributeName: 'Living Room Furniture Set Type',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'living_room_set_type_extract',
      param: 'Living Room Set',
    },
  },

  // ==================== 最大承重 ====================
  {
    attributeId: 'maximumLoadWeight',
    attributeName: 'Maximum Load Weight',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'max_load_weight_extract',
      param: '',
    },
  },

  // ==================== 多包装数量 ====================
  {
    attributeId: 'multipackQuantity',
    attributeName: 'Multipack Quantity',
    mappingType: 'default_value',
    value: 1,
  },

  // ==================== 净含量声明 ====================
  {
    attributeId: 'netContentStatement',
    attributeName: 'Net Content Statement',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'net_content_statement_extract',
      param: '',
    },
  },

  // ==================== 场合 ====================
  {
    attributeId: 'occasion',
    attributeName: 'Occasion',
    mappingType: 'default_value',
    value: ['Housewarming', 'Home Office', 'Living Room Refresh', 'Back to School', 'Christmas', 'Birthday'],
  },

  // ==================== 图案 ====================
  {
    attributeId: 'pattern',
    attributeName: 'Pattern',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'pattern_extract',
      param: '',
    },
  },

  // ==================== 产品线 ====================
  {
    attributeId: 'productLine',
    attributeName: 'Product Line',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'product_line_from_category',
      param: '',
    },
  },

  // ==================== 座椅靠背高度 ====================
  {
    attributeId: 'seatBackHeight',
    attributeName: 'Seat Back Height',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'seat_back_height_extract',
      param: '',
    },
  },

  // ==================== 座椅颜色 ====================
  {
    attributeId: 'seat_color',
    attributeName: 'Seat Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'seat_color_extract',
      param: '',
    },
  },

  // ==================== 座椅高度 ====================
  {
    attributeId: 'seatHeight',
    attributeName: 'Seat Height',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'seat_height_extract',
      param: '',
    },
  },

  // ==================== 座椅材料 ====================
  {
    attributeId: 'seat_material',
    attributeName: 'Seat Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'seat_material_extract',
      param: '',
    },
  },

  // ==================== 建议组装人数 ====================
  {
    attributeId: 'suggested_number_of_people_for_assembly',
    attributeName: 'Suggested Number of People for Assembly',
    mappingType: 'default_value',
    value: 2,
  },

  // ==================== 总数量 ====================
  {
    attributeId: 'count',
    attributeName: 'Total Count',
    mappingType: 'default_value',
    value: 1,
  },

  // ==================== 是否软包 ====================
  {
    attributeId: 'upholstered',
    attributeName: 'Upholstered',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'upholstered_extract',
      param: 'No',
    },
  },

  // ==================== 是否主变体 ====================
  {
    attributeId: 'isPrimaryVariant',
    attributeName: 'Is Primary Variant',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== 是否含电子元件 ====================
  {
    attributeId: 'electronicsIndicator',
    attributeName: 'Product is or Contains an Electronic Component?',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'electronics_indicator_extract',
      param: 'No',
    },
  },

  // ==================== 是否含化学品/气雾剂/杀虫剂 ====================
  {
    attributeId: 'chemicalAerosolPesticide',
    attributeName: 'Product is or Contains a Chemical, Aerosol or Pesticide?',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== 电池类型 ====================
  {
    attributeId: 'batteryTechnologyType',
    attributeName: 'Product is or Contains this Battery Type',
    mappingType: 'enum_select',
    value: 'Does Not Contain a Battery',
  },

  // ==================== 履约延迟时间 ====================
  {
    attributeId: 'fulfillmentLagTime',
    attributeName: 'Fulfillment Lag Time',
    mappingType: 'default_value',
    value: 1,
  },

  // ==================== 是否原包装发货 ====================
  {
    attributeId: 'shipsInOriginalPackaging',
    attributeName: 'Ships in Original Packaging',
    mappingType: 'enum_select',
    value: 'Yes',
  },

  // ==================== 是否必须单独发货 ====================
  {
    attributeId: 'MustShipAlone',
    attributeName: 'Must ship alone?',
    mappingType: 'enum_select',
    value: 'Yes',
  },

  // ==================== 是否预售 ====================
  {
    attributeId: 'IsPreorder',
    attributeName: 'Is Preorder',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== 发布日期 ====================
  {
    attributeId: 'releaseDate',
    attributeName: 'Release Date',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'date_offset',
      param: '-1', // 当前日期往前1天
    },
  },

  // ==================== 站点开始日期 ====================
  {
    attributeId: 'startDate',
    attributeName: 'Site Start Date',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'date_offset',
      param: '-1', // 当前日期往前1天
    },
  },

  // ==================== 站点结束日期 ====================
  {
    attributeId: 'endDate',
    attributeName: 'Site End Date',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'date_offset_years',
      param: '10', // 当前日期往后10年
    },
  },

  // ==================== 产品ID更新 ====================
  {
    attributeId: 'ProductIdUpdate',
    attributeName: 'Product Id Update',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== SKU更新 ====================
  {
    attributeId: 'SkuUpdate',
    attributeName: 'SKU Update',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== 原产国 ====================
  {
    attributeId: 'countryOfOriginAssembly',
    attributeName: 'Country of Origin',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'country_of_origin_extract',
      param: '',
    },
  },

  // ==================== 纺织品原产国 ====================
  {
    attributeId: 'countryOfOriginTextiles',
    attributeName: 'Country of Origin- Textiles',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'country_of_origin_textiles_extract',
      param: '',
    },
  },

  // ==================== 表面处理 ====================
  {
    attributeId: 'finish',
    attributeName: 'Finish',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'finish_extract',
      param: 'New',
    },
  },

  // ==================== 性别 ====================
  {
    attributeId: 'gender',
    attributeName: 'Gender',
    mappingType: 'enum_select',
    value: 'Unisex',
  },

  // ==================== 温度敏感 ====================
  {
    attributeId: 'isTemperatureSensitive',
    attributeName: 'Is Temperature-Sensitive',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_temperature_sensitive_extract',
      param: 'No',
    },
  },

  // ==================== 产品尺寸 ====================
  {
    attributeId: 'size',
    attributeName: 'Size',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'size_extract',
      param: '',
    },
  },

  // ==================== 床尺寸 ====================
  {
    attributeId: 'bedSize',
    attributeName: 'Bed Size',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'bed_size_extract',
      param: '',
    },
  },

  // ==================== 抽屉数量 ====================
  {
    attributeId: 'numberOfDrawers',
    attributeName: 'Number of Drawers',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'number_of_drawers_extract',
      param: '0',
    },
  },

  // ==================== 搁板数量 ====================
  {
    attributeId: 'numberOfShelves',
    attributeName: 'Number of Shelves',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'number_of_shelves_extract',
      param: '0',
    },
  },

  // ==================== 主题 ====================
  {
    attributeId: 'theme',
    attributeName: 'Theme',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'theme_extract',
      param: 'Casual Home Comfort Setting',
    },
  },

  // ==================== 形状 ====================
  {
    attributeId: 'shape',
    attributeName: 'Shape',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'shape_extract',
      param: 'Rectangular',
    },
  },

  // ==================== 直径 ====================
  {
    attributeId: 'diameter',
    attributeName: 'Diameter',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'diameter_extract',
      param: '',
    },
  },

  // ==================== 床风格 ====================
  {
    attributeId: 'bedStyle',
    attributeName: 'Bed Style',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'bed_style_extract',
      param: 'Platform Bed Style',
    },
  },

  // ==================== 安装类型 ====================
  {
    attributeId: 'mountType',
    attributeName: 'Mount Type',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'mount_type_extract',
      param: 'Freestanding',
    },
  },

  // ==================== 税码（加拿大） ====================
  {
    attributeId: 'productTaxCode',
    attributeName: 'Product Tax Code',
    mappingType: 'default_value',
    value: 2038710, // Furniture - General 税码
  },

  // ==================== 附加功能 ====================
  {
    attributeId: 'features',
    attributeName: 'Additional Features',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'features_extract',
      param: '',
    },
  },

  // ==================== 制造商 ====================
  {
    attributeId: 'manufacturer',
    attributeName: 'Manufacturer',
    mappingType: 'channel_data',
    value: 'supplier',
  },

  // ==================== 关键词 ====================
  {
    attributeId: 'keywords',
    attributeName: 'Keywords',
    mappingType: 'channel_data',
    value: 'keywords',
  },

  // ==================== 合规信息（加拿大） ====================
  {
    attributeId: 'isChemical',
    attributeName: 'Contains Chemical',
    mappingType: 'enum_select',
    value: 'No',
  },
  {
    attributeId: 'isPesticide',
    attributeName: 'Contains Pesticide',
    mappingType: 'enum_select',
    value: 'No',
  },
  {
    attributeId: 'isAerosol',
    attributeName: 'Contains Aerosol',
    mappingType: 'enum_select',
    value: 'No',
  },

  // ==================== 组装说明 ====================
  {
    attributeId: 'assemblyInstructions',
    attributeName: 'Assembly Instructions',
    mappingType: 'channel_data',
    value: 'productDescription',
  },

  // ==================== 面料成分 ====================
  {
    attributeId: 'fabricContent',
    attributeName: 'Fabric Content',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'fabric_content_extract',
      param: '',
    },
  },

  // ==================== 面料护理说明 ====================
  {
    attributeId: 'fabricCareInstructions',
    attributeName: 'Fabric Care Instructions',
    mappingType: 'default_value',
    value: 'Wipe clean with a damp cloth',
  },

  // ==================== 配置/规格 ====================
  {
    attributeId: 'configuration',
    attributeName: 'Configuration',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'configuration_extract',
      param: '',
    },
  },

  // ==================== 布艺颜色 ====================
  {
    attributeId: 'fabricColor',
    attributeName: 'Fabric Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'fabric_color_extract',
      param: '',
    },
  },

  // ==================== 装饰色/次要颜色 ====================
  {
    attributeId: 'accentColor',
    attributeName: 'Accent Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'accent_color_extract',
      param: '',
    },
  },

  // ==================== 坐垫颜色 ====================
  {
    attributeId: 'cushionColor',
    attributeName: 'Cushion Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'cushion_color_extract',
      param: '',
    },
  },
  // Number of Panels - 面板数量
  {
    attributeId: 'numberOfPanels',
    attributeName: 'Number of Panels',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'number_of_panels_extract',
      param: '',
    },
  },
  // Seat Back Style - 靠背样式
  {
    attributeId: 'seatBackStyle',
    attributeName: 'Seat Back Style',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'seat_back_style_extract',
      param: '',
    },
  },
  // Power Type - 供电类型
  {
    attributeId: 'powerType',
    attributeName: 'Power Type',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'power_type_extract',
      param: '',
    },
  },
  // Is Powered - 是否需要供电
  {
    attributeId: 'isPowered',
    attributeName: 'Is Powered',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_powered_extract',
      param: '',
    },
  },
  // Recommended Uses - 推荐使用场景
  {
    attributeId: 'recommendedUses',
    attributeName: 'Recommended Use',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'recommended_uses_extract',
      param: '',
    },
  },
  // Recommended Rooms - 推荐房间（三层兜底策略）
  {
    attributeId: 'recommendedRooms',
    attributeName: 'Recommended Rooms',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'recommended_rooms_extract',
      param: '',
    },
  },
  // Mattress Firmness - 床垫硬度
  {
    attributeId: 'mattressFirmness',
    attributeName: 'Mattress Firmness',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'mattress_firmness_extract',
      param: '',
    },
  },
  // Mattress Thickness - 床垫厚度
  {
    attributeId: 'mattressThickness',
    attributeName: 'Mattress Thickness',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'mattress_thickness_extract',
      param: '',
    },
  },
  // Pump Included - 是否包含气泵
  {
    attributeId: 'pumpIncluded',
    attributeName: 'Pump Included',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'pump_included_extract',
      param: '',
    },
  },
  // Fill Material - 填充材料
  {
    attributeId: 'fillMaterial',
    attributeName: 'Fill Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'fill_material_extract',
      param: '',
    },
  },
  // Frame Material - 框架材料
  {
    attributeId: 'frameMaterial',
    attributeName: 'Frame Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'frame_material_extract',
      param: '',
    },
  },
  // Seat Material - 座面材质
  {
    attributeId: 'seatMaterial',
    attributeName: 'Seat Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'seat_material_extract',
      param: '',
    },
  },
  // Table Height - 桌子高度
  {
    attributeId: 'tableHeight',
    attributeName: 'Table Height',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'table_height_extract',
      param: '',
    },
  },
  // Top Material - 顶部材质
  {
    attributeId: 'topMaterial',
    attributeName: 'Top Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'top_material_extract',
      param: '',
    },
  },
  // Top Dimensions - 顶部尺寸
  {
    attributeId: 'topDimensions',
    attributeName: 'Top Dimensions',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'top_dimensions_extract',
      param: '',
    },
  },
  // Top Finish - 顶部表面处理
  {
    attributeId: 'topFinish',
    attributeName: 'Top Finish',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'top_finish_extract',
      param: '',
    },
  },
  // Hardware Finish - 五金表面处理
  {
    attributeId: 'hardwareFinish',
    attributeName: 'Hardware Finish',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'hardware_finish_extract',
      param: '',
    },
  },
  // Base Material - 底座材质
  {
    attributeId: 'baseMaterial',
    attributeName: 'Base Material',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'base_material_extract',
      param: '',
    },
  },
  // Base Color - 底座颜色
  {
    attributeId: 'baseColor',
    attributeName: 'Base Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'base_color_extract',
      param: '',
    },
  },
  // Base Finish - 底座表面处理
  {
    attributeId: 'baseFinish',
    attributeName: 'Base Finish',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'base_finish_extract',
      param: '',
    },
  },
  // Door Opening Style - 门开启方式
  {
    attributeId: 'doorOpeningStyle',
    attributeName: 'Door Opening Style',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'door_opening_style_extract',
      param: '',
    },
  },
  // Door Style - 门板样式
  {
    attributeId: 'doorStyle',
    attributeName: 'Door Style',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'door_style_extract',
      param: '',
    },
  },
  // Slat Width - 板条宽度
  {
    attributeId: 'slatWidth',
    attributeName: 'Slat Width',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'slat_width_extract',
      param: '',
    },
  },
  // Number of Hooks - 挂钩数量
  {
    attributeId: 'numberOfHooks',
    attributeName: 'Number of Hooks',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'number_of_hooks_extract',
      param: '',
    },
  },
  // Headboard Style - 床头板样式
  {
    attributeId: 'headboardStyle',
    attributeName: 'Headboard Style',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'headboard_style_extract',
      param: '',
    },
  },
  // Frame Color - 框架颜色
  {
    attributeId: 'frameColor',
    attributeName: 'Frame Color',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'frame_color_extract',
      param: '',
    },
  },
  // Is Smart - 是否智能家具
  {
    attributeId: 'isSmart',
    attributeName: 'Is Smart',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_smart_extract',
      param: '',
    },
  },
  // Is Antique - 是否古董
  {
    attributeId: 'isAntique',
    attributeName: 'Is Antique',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_antique_extract',
      param: '',
    },
  },
  // Is Foldable - 是否可折叠
  {
    attributeId: 'isFoldable',
    attributeName: 'Is Foldable',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_foldable_extract',
      param: '',
    },
  },
  // Is Inflatable - 是否充气
  {
    attributeId: 'isInflatable',
    attributeName: 'Is Inflatable',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_inflatable_extract',
      param: '',
    },
  },
  // Is Wheeled - 是否带轮
  {
    attributeId: 'isWheeled',
    attributeName: 'Is Wheeled',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_wheeled_extract',
      param: '',
    },
  },
  // Is Industrial - 是否工业用途
  {
    attributeId: 'isIndustrial',
    attributeName: 'Is Industrial',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'is_industrial_extract',
      param: '',
    },
  },
  // Assembled Product Length - 组装后产品长度
  {
    attributeId: 'assembledProductLength',
    attributeName: 'Assembled Product Length',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'assembled_product_length_extract',
      param: '',
    },
  },
  // Assembled Product Width - 组装后产品宽度
  {
    attributeId: 'assembledProductWidth',
    attributeName: 'Assembled Product Width',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'assembled_product_width_extract',
      param: '',
    },
  },
  // Assembled Product Height - 组装后产品高度
  {
    attributeId: 'assembledProductHeight',
    attributeName: 'Assembled Product Height',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'assembled_product_height_extract',
      param: '',
    },
  },
  // Assembled Product Weight - 组装后产品重量
  {
    attributeId: 'assembledProductWeight',
    attributeName: 'Assembled Product Weight',
    mappingType: 'auto_generate',
    value: {
      ruleType: 'assembled_product_weight_extract',
      param: '',
    },
  },
];

/**
 * 获取平台默认映射规则
 * @param platformCode 平台代码（如 'walmart'）
 */
export function getDefaultMappingRules(platformCode: string): DefaultMappingConfig[] {
  switch (platformCode.toLowerCase()) {
    case 'walmart':
      return WALMART_DEFAULT_MAPPING_RULES;
    default:
      return [];
  }
}
