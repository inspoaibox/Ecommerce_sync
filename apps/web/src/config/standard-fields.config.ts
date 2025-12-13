/**
 * 标准商品字段配置（前端）
 * 
 * 重要：此配置是系统的核心架构，禁止随意修改！
 * 所有需要展示商品字段的地方都必须从此配置读取。
 * 
 * 此文件与后端 standard-product.config.ts 保持同步
 */

/**
 * 字段类型
 */
export type FieldType = 'string' | 'number' | 'boolean' | 'array' | 'object' | 'html' | 'image' | 'currency';

/**
 * 字段定义
 */
export interface FieldDefinition {
  key: string;
  label: string;
  type: FieldType;
  required?: boolean;
  description?: string;
  unit?: string;
  computed?: boolean;
  computeFormula?: string;
}

/**
 * 基础信息字段（系统核心字段）
 */
export const BASIC_FIELDS: FieldDefinition[] = [
  // 基础信息
  { key: 'title', label: '商品标题', type: 'string', required: true, description: '商品的完整标题' },
  { key: 'sku', label: 'SKU', type: 'string', required: true, description: '商品唯一标识' },
  { key: 'color', label: '颜色', type: 'string', description: '商品颜色' },
  { key: 'material', label: '材质', type: 'string', description: '商品材质' },
  { key: 'description', label: '商品描述', type: 'html', description: '商品详细描述，支持HTML' },
  { key: 'bulletPoints', label: '五点描述', type: 'array', description: '商品卖点列表' },
  { key: 'keywords', label: '搜索关键词', type: 'array', description: '用于搜索的关键词' },
  
  // 价格信息
  { key: 'price', label: '商品价格', type: 'currency', required: true, description: '商品原价（不含运费）' },
  { key: 'salePrice', label: '优惠价格', type: 'currency', description: '促销价格（不含运费）' },
  { key: 'shippingFee', label: '运费', type: 'currency', description: '运费价格' },
  { key: 'totalPrice', label: '商品总价', type: 'currency', computed: true, computeFormula: 'price + shippingFee', description: '商品价格 + 运费' },
  { key: 'saleTotalPrice', label: '优惠总价', type: 'currency', computed: true, computeFormula: 'salePrice + shippingFee', description: '优惠价格 + 运费' },
  { key: 'platformPrice', label: '平台售价', type: 'currency', description: '刊登到平台的售价' },
  { key: 'currency', label: '货币', type: 'string', required: true, description: '货币代码，如 USD' },
  
  // 库存
  { key: 'stock', label: '库存', type: 'number', required: true, description: '库存数量' },
  
  // 图片
  { key: 'mainImageUrl', label: '主图', type: 'image', description: '商品主图URL' },
  { key: 'imageUrls', label: '产品图', type: 'array', description: '商品附图URL列表' },
  
  // 产品尺寸（组装后）
  { key: 'productLength', label: '产品长', type: 'number', unit: 'in', description: '产品长度（组装后）' },
  { key: 'productWidth', label: '产品宽', type: 'number', unit: 'in', description: '产品宽度（组装后）' },
  { key: 'productHeight', label: '产品高', type: 'number', unit: 'in', description: '产品高度（组装后）' },
  { key: 'productWeight', label: '产品重', type: 'number', unit: 'lb', description: '产品重量（组装后）' },
  
  // 包装尺寸
  { key: 'packageLength', label: '包装长', type: 'number', unit: 'in', description: '包装长度' },
  { key: 'packageWidth', label: '包装宽', type: 'number', unit: 'in', description: '包装宽度' },
  { key: 'packageHeight', label: '包装高', type: 'number', unit: 'in', description: '包装高度' },
  { key: 'packageWeight', label: '包装重', type: 'number', unit: 'lb', description: '包装重量' },
  { key: 'packages', label: '多包裹', type: 'object', description: '多包裹商品的包裹信息' },
  
  // 其他
  { key: 'placeOfOrigin', label: '产地', type: 'string', description: '商品产地' },
  { key: 'productType', label: '商品性质', type: 'string', description: '超大件/轻小件/普通件/多包裹' },
  { key: 'supplier', label: '供货商', type: 'string', description: '供货商家名称' },
  { key: 'unAvailablePlatform', label: '不可售平台', type: 'array', description: '禁止销售的平台列表，如 [{id: "1", name: "Wayfair"}]' },
  { key: 'productDescription', label: '产品说明', type: 'string', description: '产品说明书链接或详细的产品使用说明、安装指南、注意事项等' },
  { key: 'productCertification', label: '产品资质', type: 'string', description: '产品认证信息、合规证书、质检报告等资质说明' },
];

/**
 * 商品性质选项
 */
export const PRODUCT_TYPE_OPTIONS = [
  { value: 'normal', label: '普通件', color: 'blue' },
  { value: 'small', label: '轻小件', color: 'green' },
  { value: 'oversized', label: '超大件', color: 'red' },
  { value: 'multiPackage', label: '多包裹', color: 'orange' },
] as const;

/**
 * 获取字段定义
 */
export function getFieldDefinition(key: string): FieldDefinition | undefined {
  return BASIC_FIELDS.find(f => f.key === key);
}

/**
 * 获取字段显示名称
 */
export function getFieldLabel(key: string): string {
  const field = getFieldDefinition(key);
  return field?.label || key;
}

/**
 * 获取商品性质标签
 */
export function getProductTypeLabel(value: string): string {
  const option = PRODUCT_TYPE_OPTIONS.find(o => o.value === value);
  return option?.label || value;
}

/**
 * 获取商品性质颜色
 */
export function getProductTypeColor(value: string): string {
  const option = PRODUCT_TYPE_OPTIONS.find(o => o.value === value);
  return option?.color || 'default';
}

/**
 * 字段配置版本号
 */
export const FIELD_CONFIG_VERSION = '1.1.0';
