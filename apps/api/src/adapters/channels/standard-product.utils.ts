/**
 * 标准化商品数据转换工具
 * 
 * 提供通用的数据转换和验证函数
 * 便于各渠道适配器将数据转换为标准格式
 */

import {
  StandardProduct,
  PackageInfo,
  CustomAttribute,
  ProductType,
  calculateTotalPrice,
  calculateSaleTotalPrice,
} from './standard-product.interface';

// ==================== 数值转换 ====================

/**
 * 安全解析数字
 * 处理 "Not Applicable"、null、undefined 等特殊值
 */
export function parseNumber(value: any): number | undefined {
  if (value === 'Not Applicable' || value === 'N/A' || value === null || value === undefined || value === '') {
    return undefined;
  }
  const num = parseFloat(value);
  return isNaN(num) ? undefined : num;
}

/**
 * 安全解析整数
 */
export function parseInteger(value: any): number | undefined {
  if (value === 'Not Applicable' || value === 'N/A' || value === null || value === undefined || value === '') {
    return undefined;
  }
  const num = parseInt(value, 10);
  return isNaN(num) ? undefined : num;
}

/**
 * 安全解析布尔值
 */
export function parseBoolean(value: any): boolean | undefined {
  if (value === null || value === undefined) return undefined;
  if (typeof value === 'boolean') return value;
  if (typeof value === 'string') {
    const lower = value.toLowerCase();
    if (lower === 'true' || lower === 'yes' || lower === '1') return true;
    if (lower === 'false' || lower === 'no' || lower === '0') return false;
  }
  if (typeof value === 'number') return value !== 0;
  return undefined;
}

// ==================== 字符串处理 ====================

/**
 * 清理字符串（去除首尾空格，空字符串返回 undefined）
 */
export function cleanString(value: any): string | undefined {
  if (value === null || value === undefined) return undefined;
  const str = String(value).trim();
  return str === '' ? undefined : str;
}

/**
 * 清理 HTML 内容
 */
export function cleanHtml(html: string | undefined): string | undefined {
  if (!html) return undefined;
  return html.trim();
}

/**
 * 提取纯文本（移除 HTML 标签）
 */
export function stripHtml(html: string | undefined): string | undefined {
  if (!html) return undefined;
  return html.replace(/<[^>]*>/g, '').trim();
}

// ==================== 数组处理 ====================

/**
 * 确保返回数组
 */
export function ensureArray<T>(value: T | T[] | null | undefined): T[] {
  if (value === null || value === undefined) return [];
  return Array.isArray(value) ? value : [value];
}

/**
 * 过滤空值并去重
 */
export function cleanArray<T>(arr: (T | null | undefined)[]): T[] {
  return [...new Set(arr.filter((item): item is T => item !== null && item !== undefined && item !== ''))];
}

// ==================== 属性处理 ====================

/**
 * 从对象中安全获取嵌套属性
 * @param obj 源对象
 * @param path 属性路径（如 'a.b.c' 或 'a.b[0].c'）
 */
export function getNestedValue(obj: any, path: string): any {
  if (!obj || !path) return undefined;
  
  const keys = path.replace(/\[(\d+)\]/g, '.$1').split('.');
  let result = obj;
  
  for (const key of keys) {
    if (result === null || result === undefined) return undefined;
    result = result[key];
  }
  
  return result;
}

/**
 * 从 customAttributes 数组中获取指定属性的值
 * @param channelAttributes 渠道属性对象
 * @param attrName 属性名称
 */
export function getCustomAttributeValue(channelAttributes: Record<string, any>, attrName: string): any {
  const customAttrs = channelAttributes?.customAttributes;
  if (!Array.isArray(customAttrs)) return undefined;
  
  const attr = customAttrs.find((a: any) => a.name === attrName);
  return attr?.value;
}

/**
 * 构建自定义属性
 */
export function buildCustomAttribute(
  name: string,
  value: string | number | boolean | string[],
  options?: { label?: string; visible?: boolean }
): CustomAttribute {
  return {
    name,
    value,
    label: options?.label,
    visible: options?.visible ?? true,
  };
}

/**
 * 从键值对对象构建自定义属性数组
 */
export function buildCustomAttributesFromObject(
  obj: Record<string, any>,
  options?: { visible?: boolean }
): CustomAttribute[] {
  if (!obj) return [];
  
  return Object.entries(obj)
    .filter(([_, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => buildCustomAttribute(
      key,
      Array.isArray(value) ? value : (typeof value === 'object' ? JSON.stringify(value) : value),
      { visible: options?.visible }
    ));
}

// ==================== 包裹处理 ====================

/**
 * 构建包裹信息
 */
export function buildPackageInfo(
  index: number,
  data: { length?: number; width?: number; height?: number; weight?: number; quantity?: number; sku?: string }
): PackageInfo {
  return {
    index,
    length: parseNumber(data.length),
    width: parseNumber(data.width),
    height: parseNumber(data.height),
    weight: parseNumber(data.weight),
    quantity: parseInteger(data.quantity) || 1,
    sku: data.sku,
  };
}

/**
 * 计算多包裹总重量
 */
export function calculateTotalWeight(packages: PackageInfo[]): number {
  return packages.reduce((sum, pkg) => {
    return sum + (pkg.weight || 0) * (pkg.quantity || 1);
  }, 0);
}

/**
 * 获取多包裹最大尺寸
 */
export function getMaxDimensions(packages: PackageInfo[]): {
  maxLength: number;
  maxWidth: number;
  maxHeight: number;
} {
  let maxLength = 0, maxWidth = 0, maxHeight = 0;
  
  for (const pkg of packages) {
    if (pkg.length && pkg.length > maxLength) maxLength = pkg.length;
    if (pkg.width && pkg.width > maxWidth) maxWidth = pkg.width;
    if (pkg.height && pkg.height > maxHeight) maxHeight = pkg.height;
  }
  
  return { maxLength, maxWidth, maxHeight };
}

// ==================== 商品性质判断 ====================

/**
 * 判断商品性质
 */
export function determineProductType(data: {
  packages?: PackageInfo[];
  packageWeight?: number;
  packageLength?: number;
  packageWidth?: number;
  packageHeight?: number;
  overSizeFlag?: boolean;
}): ProductType {
  // 多包裹
  if (data.packages && data.packages.length > 1) {
    return 'multiPackage';
  }
  
  // 超大件（根据标记或尺寸判断）
  if (data.overSizeFlag) {
    return 'oversized';
  }
  
  // 根据重量和尺寸判断
  const weight = data.packageWeight || 0;
  const maxDim = Math.max(data.packageLength || 0, data.packageWidth || 0, data.packageHeight || 0);
  
  // 轻小件：重量 < 2lb 且 最大边 < 18in
  if (weight < 2 && maxDim < 18) {
    return 'small';
  }
  
  // 超大件：重量 > 150lb 或 最大边 > 108in
  if (weight > 150 || maxDim > 108) {
    return 'oversized';
  }
  
  return 'normal';
}

// ==================== 图片处理 ====================

/**
 * 清理图片 URL 列表
 */
export function cleanImageUrls(urls: any): string[] {
  if (!urls) return [];
  const arr = ensureArray(urls);
  return arr.filter((url): url is string => 
    typeof url === 'string' && url.trim() !== '' && url.startsWith('http')
  );
}

/**
 * 提取主图（优先使用指定的主图，否则取第一张）
 */
export function extractMainImage(mainImage: any, imageUrls: any): string | undefined {
  if (mainImage && typeof mainImage === 'string' && mainImage.startsWith('http')) {
    return mainImage;
  }
  const urls = cleanImageUrls(imageUrls);
  return urls[0];
}

// ==================== 价格处理 ====================

/**
 * 格式化价格（保留2位小数）
 */
export function formatPrice(price: any): number | undefined {
  const num = parseNumber(price);
  if (num === undefined) return undefined;
  return Math.round(num * 100) / 100;
}

// ==================== 验证工具 ====================

/**
 * 验证 UPC 格式（12位数字）
 */
export function isValidUpc(upc: string | undefined): boolean {
  if (!upc) return false;
  return /^\d{12}$/.test(upc);
}

/**
 * 验证 EAN 格式（13位数字）
 */
export function isValidEan(ean: string | undefined): boolean {
  if (!ean) return false;
  return /^\d{13}$/.test(ean);
}

/**
 * 验证 GTIN 格式（8/12/13/14位数字）
 */
export function isValidGtin(gtin: string | undefined): boolean {
  if (!gtin) return false;
  return /^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/.test(gtin);
}

// ==================== 导出 ====================

export {
  StandardProduct,
  PackageInfo,
  CustomAttribute,
  ProductType,
  calculateTotalPrice,
  calculateSaleTotalPrice,
};
