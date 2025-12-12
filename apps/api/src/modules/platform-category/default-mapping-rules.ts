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
