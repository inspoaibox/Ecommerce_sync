/**
 * Walmart 多区域配置
 * 支持 US（美国）、CA（加拿大）、MX（墨西哥）、CL（智利）等市场
 * 
 * 重要：所有市场使用同一个 API 地址，通过 WM_MARKET Header 区分市场
 * 参考: https://developer.walmart.com/global-marketplace/reference/tokenapi
 */

export interface WalmartRegionConfig {
  /** 区域代码 */
  region: string;
  /** 区域名称 */
  name: string;
  /** API 基础地址（所有市场统一） */
  apiBaseUrl: string;
  /** WM_MARKET Header 值（小写：us, ca, mx, cl） */
  marketCode: string;
  /** 业务单元标识（用于 Feed Header） */
  businessUnit: string;
  /** 默认语言 */
  locale: string;
  /** 默认货币 */
  currency: string;
  /** Item Spec 版本 */
  specVersion: string;
  /** API 路径前缀（非美国市场需要，如 /ca/, /mx/） */
  apiPathPrefix: string;
  /** Feed 类型（美国用 MP_ITEM，其他市场用 MP_ITEM_INTL） */
  feedType: string;
}

/**
 * Walmart 各区域配置
 * 参考: https://developer.walmart.com/global-marketplace/reference/tokenapi
 * 
 * 注意：API 地址统一为 https://marketplace.walmartapis.com
 * 通过 WM_MARKET Header 区分不同市场
 */
export const WALMART_REGION_CONFIGS: Record<string, WalmartRegionConfig> = {
  // 美国市场
  US: {
    region: 'US',
    name: 'Walmart US',
    apiBaseUrl: 'https://marketplace.walmartapis.com',
    marketCode: 'us',
    businessUnit: 'WALMART_US',
    locale: 'en',
    currency: 'USD',
    specVersion: '5.0.20241118-04_39_24-api',
    apiPathPrefix: '',  // 美国市场不需要路径前缀
    feedType: 'MP_ITEM',
  },
  // 加拿大市场
  CA: {
    region: 'CA',
    name: 'Walmart Canada',
    apiBaseUrl: 'https://marketplace.walmartapis.com',
    marketCode: 'ca',
    businessUnit: 'WALMART_CA',
    locale: 'en',
    currency: 'CAD',
    specVersion: '3.16',  // 加拿大使用 3.16 版本
    apiPathPrefix: '/ca',  // 加拿大市场需要 /ca 路径前缀
    feedType: 'MP_ITEM_INTL',  // 加拿大使用国际版 Feed
  },
  // 墨西哥市场
  MX: {
    region: 'MX',
    name: 'Walmart Mexico',
    apiBaseUrl: 'https://marketplace.walmartapis.com',
    marketCode: 'mx',
    businessUnit: 'WALMART_MX',
    locale: 'es',
    currency: 'MXN',
    specVersion: '3.16',
    apiPathPrefix: '/mx',  // 墨西哥市场需要 /mx 路径前缀
    feedType: 'MP_ITEM_INTL',
  },
  // 智利市场
  CL: {
    region: 'CL',
    name: 'Walmart Chile',
    apiBaseUrl: 'https://marketplace.walmartapis.com',
    marketCode: 'cl',
    businessUnit: 'WALMART_CL',
    locale: 'es',
    currency: 'CLP',
    specVersion: '3.16',
    apiPathPrefix: '/cl',  // 智利市场需要 /cl 路径前缀
    feedType: 'MP_ITEM_INTL',
  },
};

/**
 * 获取区域配置
 * @param region 区域代码（US, CA, MX, CL），必填
 * @throws Error 如果区域不存在
 */
export function getWalmartRegionConfig(region?: string): WalmartRegionConfig {
  if (!region) {
    throw new Error('[Walmart] 区域代码不能为空');
  }
  
  const normalizedRegion = region.toUpperCase();
  const config = WALMART_REGION_CONFIGS[normalizedRegion];
  
  if (!config) {
    const supported = Object.keys(WALMART_REGION_CONFIGS).join(', ');
    throw new Error(`[Walmart] 不支持的区域: ${region}，支持的区域: ${supported}`);
  }
  
  return config;
}

/**
 * 获取所有支持的区域列表
 */
export function getSupportedWalmartRegions(): Array<{ code: string; name: string }> {
  return Object.values(WALMART_REGION_CONFIGS).map(config => ({
    code: config.region,
    name: config.name,
  }));
}
