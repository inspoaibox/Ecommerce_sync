/**
 * 属性映射规则接口定义
 *
 * 定义商品数据如何从渠道标准字段（channelAttributes）映射到目标平台属性
 */

/**
 * 映射类型枚举（5种基础类型）
 */
export type MappingType =
  | 'default_value' // 固定默认值
  | 'channel_data' // 从渠道数据提取
  | 'enum_select' // 选择枚举值
  | 'auto_generate' // 自动生成（包含多种子规则）
  | 'upc_pool'; // 从UPC池获取

/**
 * 基础映射规则
 */
export interface BaseMappingRule {
  /** 平台属性ID */
  attributeId: string;
  /** 平台属性名称 */
  attributeName: string;
  /** 映射类型 */
  mappingType: MappingType;
  /** 是否必填 */
  isRequired: boolean;
  /** 数据类型 */
  dataType: string;
}

/**
 * 默认值规则
 */
export interface DefaultValueRule extends BaseMappingRule {
  mappingType: 'default_value';
  value: string | number | boolean;
}

/**
 * 渠道数据规则
 */
export interface ChannelDataRule extends BaseMappingRule {
  mappingType: 'channel_data';
  /** 字段路径，如 'brand', 'packaging.weight', 'bulletPoints[0]' */
  value: string;
}

/**
 * 枚举选择规则
 */
export interface EnumSelectRule extends BaseMappingRule {
  mappingType: 'enum_select';
  /** 选中的枚举值 */
  value: string;
  /** 可选枚举值列表（从平台API获取） */
  enumValues?: string[];
}

/**
 * 自动生成规则配置
 *
 * 基础规则类型：
 * - sku_prefix: SKU前缀拼接
 * - sku_suffix: SKU后缀拼接
 * - brand_title: 品牌+标题组合
 * - first_characteristic: 取第一个特点
 * - current_date: 当前日期
 * - uuid: 生成UUID
 *
 * 后续可扩展支持：conditional, concat, unit_convert 等高级规则
 */
export interface AutoGenerateConfig {
  /** 规则类型 */
  ruleType: string;
  /** 规则参数（根据规则类型不同而不同） */
  param?: string;
  /** 其他配置（用于扩展） */
  [key: string]: any;
}

/**
 * 自动生成规则
 */
export interface AutoGenerateRule extends BaseMappingRule {
  mappingType: 'auto_generate';
  /** 自动生成配置 */
  value: AutoGenerateConfig;
}

/**
 * UPC池规则
 */
export interface UpcPoolRule extends BaseMappingRule {
  mappingType: 'upc_pool';
  /** 空字符串 */
  value: '';
}

/**
 * 映射规则联合类型
 */
export type MappingRule =
  | DefaultValueRule
  | ChannelDataRule
  | EnumSelectRule
  | AutoGenerateRule
  | UpcPoolRule;

/**
 * 映射规则集合
 */
export interface MappingRulesConfig {
  /** 规则列表 */
  rules: MappingRule[];
  /** 配置版本 */
  version?: string;
  /** 更新时间 */
  updatedAt?: string;
}

/**
 * 解析上下文
 */
export interface ResolveContext {
  /** 店铺ID */
  shopId?: string;
  /** 商品SKU */
  productSku?: string;
  /** 平台ID */
  platformId?: string;
  /** 国家代码 */
  country?: string;
  /** 商品原价（用于价格计算，当 channelAttributes 中没有 price 时使用） */
  productPrice?: number;
  /** 分类名称（用于产品线等字段） */
  categoryName?: string;
}

/**
 * 解析错误
 */
export interface ResolveError {
  /** 属性ID */
  attributeId: string;
  /** 属性名称 */
  attributeName: string;
  /** 错误类型 */
  errorType: string;
  /** 错误消息 */
  message: string;
}

/**
 * 解析结果
 */
export interface ResolveResult {
  /** 是否成功 */
  success: boolean;
  /** 解析后的属性 */
  attributes: Record<string, any>;
  /** 错误列表 */
  errors: ResolveError[];
  /** 警告列表 */
  warnings: string[];
}
