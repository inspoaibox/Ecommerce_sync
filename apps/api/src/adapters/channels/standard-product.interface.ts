/**
 * 标准化商品数据结构
 * 
 * 重要：此结构一旦定义，禁止修改！
 * 所有渠道导入的商品数据都必须遵循此格式。
 * 
 * 设计原则：
 * 1. 核心字段固定，不可扩展
 * 2. 其他属性统一放入 customAttributes（类似 WooCommerce 自定义属性）
 * 3. 计算字段（如 totalPrice）由系统自动计算，不存储
 */

// ==================== 核心标准字段 ====================

/**
 * 单个包裹尺寸
 */
export interface PackageInfo {
  /** 包裹序号（从1开始） */
  index: number;
  /** 包裹长度 */
  length?: number;
  /** 包裹宽度 */
  width?: number;
  /** 包裹高度 */
  height?: number;
  /** 包裹重量 */
  weight?: number;
  /** 包裹数量（同规格包裹的数量） */
  quantity?: number;
  /** 包裹SKU（可选） */
  sku?: string;
}

/**
 * 自定义属性（渠道属性）
 * 类似 WooCommerce 的自定义属性字段
 */
export interface CustomAttribute {
  /** 属性名称 */
  name: string;
  /** 属性值 */
  value: string | number | boolean | string[];
  /** 属性显示名称（可选） */
  label?: string;
  /** 是否在前端显示 */
  visible?: boolean;
}

/**
 * 商品性质类型
 */
export type ProductType = 'oversized' | 'small' | 'normal' | 'multiPackage';

/**
 * 标准化商品数据结构
 * 
 * 核心字段列表（共31个基础字段）：
 * 
 * 【基础信息】
 * 1. title - 商品标题
 * 2. sku - SKU
 * 3. color - 商品颜色
 * 4. material - 商品材质
 * 5. description - 商品描述
 * 6. bulletPoints - 五点描述
 * 7. keywords - 搜索关键词
 * 
 * 【价格信息】
 * 8. price - 商品价格
 * 9. salePrice - 优惠价格
 * 10. shippingFee - 运费价格
 * 11. totalPrice - 商品总价（计算字段：price + shippingFee）
 * 12. saleTotalPrice - 优惠总价（计算字段：salePrice + shippingFee）
 * 13. platformPrice - 平台总价（刊登售价）
 * 14. currency - 货币
 * 
 * 【库存】
 * 15. stock - 库存数量
 * 
 * 【图片】
 * 16. mainImageUrl - 主图
 * 17. imageUrls - 商品产品图
 * 
 * 【产品尺寸】
 * 18. productLength - 商品长度
 * 19. productWidth - 商品宽度
 * 20. productHeight - 商品高度
 * 21. productWeight - 商品重量
 * 
 * 【包装尺寸】
 * 22. packageLength - 商品包裹长度
 * 23. packageWidth - 商品包裹宽度
 * 24. packageHeight - 商品包裹高度
 * 25. packageWeight - 商品包裹重量
 * 26. packages - 多包裹信息（自动延伸）
 * 
 * 【其他】
 * 27. placeOfOrigin - 商品产地
 * 28. productType - 商品性质
 * 29. supplier - 供货商家
 * 30. productDescription - 产品说明
 * 31. productCertification - 产品资质
 * 
 * 【扩展】
 * 32. customAttributes - 渠道属性（所有其他属性）
 */
export interface StandardProduct {
  // ==================== 基础信息（7个）====================
  /** 1. 商品标题 */
  title: string;
  /** 2. SKU */
  sku: string;
  /** 3. 商品颜色 */
  color?: string;
  /** 4. 商品材质 */
  material?: string;
  /** 5. 商品描述（支持HTML） */
  description?: string;
  /** 6. 五点描述 */
  bulletPoints?: string[];
  /** 7. 搜索关键词 */
  keywords?: string[];
  
  // ==================== 价格信息（5个存储 + 2个计算）====================
  /** 7. 商品价格（原价，不含运费） */
  price: number;
  /** 8. 优惠价格（促销价，不含运费） */
  salePrice?: number;
  /** 9. 运费价格 */
  shippingFee?: number;
  /** 12. 平台总价（刊登售价，用户设置） */
  platformPrice?: number;
  /** 13. 货币代码 */
  currency: string;
  // 注：totalPrice(10) 和 saleTotalPrice(11) 为计算字段，不存储
  
  // ==================== 库存信息（1个）====================
  /** 14. 库存数量 */
  stock: number;
  
  // ==================== 图片媒体（2个 + 3个可选）====================
  /** 15. 主图URL */
  mainImageUrl?: string;
  /** 16. 商品产品图URL列表 */
  imageUrls?: string[];
  /** 视频URL列表（可选） */
  videoUrls?: string[];
  /** 产品文档URL列表（说明书等） */
  documentUrls?: string[];
  /** 产品资质URL列表（认证证书等） */
  certificationUrls?: string[];
  
  // ==================== 产品尺寸（4个）====================
  /** 17. 商品长度（组装后/实际尺寸） */
  productLength?: number;
  /** 18. 商品宽度 */
  productWidth?: number;
  /** 19. 商品高度 */
  productHeight?: number;
  /** 20. 商品重量 */
  productWeight?: number;
  
  // ==================== 包装尺寸（5个）====================
  /** 21. 商品包裹长度（单包裹或最大包裹） */
  packageLength?: number;
  /** 22. 商品包裹宽度 */
  packageWidth?: number;
  /** 23. 商品包裹高度 */
  packageHeight?: number;
  /** 24. 商品包裹重量（单包裹或总重量） */
  packageWeight?: number;
  /** 25. 多包裹信息（如果是多包裹商品，自动延伸） */
  packages?: PackageInfo[];

  // ==================== 其他核心字段（5个）====================
  /** 26. 商品产地 */
  placeOfOrigin?: string;
  /** 27. 商品性质：超大件/轻小件/普通件/多包裹 */
  productType?: ProductType;
  /** 28. 供货商家 */
  supplier?: string;
  /** 29. 产品说明（详细的产品使用说明、安装指南等） */
  productDescription?: string;
  /** 30. 产品资质（认证信息、合规证书等） */
  productCertification?: string;
  
  // ==================== 渠道属性（扩展字段）====================
  /** 
   * 31. 渠道属性（自定义属性）
   * 所有非核心字段统一放入此处
   * 类似 WooCommerce 的自定义属性
   */
  customAttributes?: CustomAttribute[];
  
  // ==================== 元数据（系统字段）====================
  /** 来源渠道代码 */
  sourceChannel?: string;
  /** 渠道商品ID */
  channelProductId?: string;
  /** 原始数据（保留渠道返回的完整数据） */
  rawData?: Record<string, any>;
}

// ==================== 计算字段（不存储，运行时计算）====================

/**
 * 计算商品总价（商品价格 + 运费）
 */
export function calculateTotalPrice(product: StandardProduct): number {
  return (product.price || 0) + (product.shippingFee || 0);
}

/**
 * 计算优惠总价（优惠价格 + 运费）
 */
export function calculateSaleTotalPrice(product: StandardProduct): number | undefined {
  if (product.salePrice === undefined || product.salePrice === null) {
    return undefined;
  }
  return product.salePrice + (product.shippingFee || 0);
}

// ==================== 工具类型 ====================

/**
 * 渠道属性定义（用于描述渠道支持的属性字段）
 */
export interface ChannelAttributeDefinition {
  /** 属性键名 */
  key: string;
  /** 属性显示名称 */
  label: string;
  /** 数据类型 */
  type: 'string' | 'number' | 'boolean' | 'array' | 'object';
  /** 数据路径（用于从商品数据中提取） */
  path: string;
  /** 属性描述 */
  description?: string;
  /** 是否必填 */
  required?: boolean;
  /** 默认值 */
  defaultValue?: any;
}

/**
 * 标准字段路径常量
 */
export const STANDARD_FIELD_PATHS = {
  // 基础信息（7个）
  TITLE: 'title',
  SKU: 'sku',
  COLOR: 'color',
  MATERIAL: 'material',
  DESCRIPTION: 'description',
  BULLET_POINTS: 'bulletPoints',
  KEYWORDS: 'keywords',
  
  // 价格（5个存储 + 2个计算）
  PRICE: 'price',
  SALE_PRICE: 'salePrice',
  SHIPPING_FEE: 'shippingFee',
  PLATFORM_PRICE: 'platformPrice',
  CURRENCY: 'currency',
  
  // 库存（1个）
  STOCK: 'stock',
  
  // 图片媒体
  MAIN_IMAGE: 'mainImageUrl',
  IMAGE_URLS: 'imageUrls',
  VIDEO_URLS: 'videoUrls',
  DOCUMENT_URLS: 'documentUrls',
  CERTIFICATION_URLS: 'certificationUrls',
  
  // 产品尺寸（4个）
  PRODUCT_LENGTH: 'productLength',
  PRODUCT_WIDTH: 'productWidth',
  PRODUCT_HEIGHT: 'productHeight',
  PRODUCT_WEIGHT: 'productWeight',
  
  // 包装尺寸（5个）
  PACKAGE_LENGTH: 'packageLength',
  PACKAGE_WIDTH: 'packageWidth',
  PACKAGE_HEIGHT: 'packageHeight',
  PACKAGE_WEIGHT: 'packageWeight',
  PACKAGES: 'packages',

  // 其他（5个）
  PLACE_OF_ORIGIN: 'placeOfOrigin',
  PRODUCT_TYPE: 'productType',
  SUPPLIER: 'supplier',
  PRODUCT_DESCRIPTION: 'productDescription',
  PRODUCT_CERTIFICATION: 'productCertification',
  
  // 扩展
  CUSTOM_ATTRIBUTES: 'customAttributes',
} as const;
