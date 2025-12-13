/**
 * 验证 CA 字段格式转换是否正确
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/verify-ca-field-format.ts
 * 
 * 此脚本模拟 listing.service.ts 中的 convertToWalmartV5Format 方法
 * 用于验证 CA 市场的字段格式转换是否正确
 */

import * as path from 'path';
import * as fs from 'fs';

interface CASpecInfo {
  multiLangFields: Set<string>;
  arrayMultiLangFields: Set<string>;
  weightFields: Set<string>;
  arrayFields: Set<string>;
}

let caSpecCache: CASpecInfo | null = null;

const getFallbackCASpecInfo = (): CASpecInfo => ({
  multiLangFields: new Set(['productName', 'brand', 'shortDescription', 'manufacturer', 'warrantyText', 'keywords']),
  arrayMultiLangFields: new Set(['keyFeatures', 'features']),
  weightFields: new Set(['ShippingWeight']),
  arrayFields: new Set(['countryOfOriginAssembly']),
});

const parseCASpecFields = (): CASpecInfo => {
  if (caSpecCache) return caSpecCache;

  const multiLangFields = new Set<string>();
  const arrayMultiLangFields = new Set<string>();
  const weightFields = new Set<string>();
  const arrayFields = new Set<string>();

  const possiblePaths = [
    path.join(__dirname, '../src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(__dirname, '../dist/src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(process.cwd(), 'src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
  ];

  let spec: any = null;
  for (const specPath of possiblePaths) {
    try {
      if (fs.existsSync(specPath)) {
        spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));
        console.log(`✅ Spec 文件加载成功: ${specPath}`);
        break;
      }
    } catch (e) {}
  }

  if (!spec) {
    console.log('⚠️ 使用降级字段列表');
    return getFallbackCASpecInfo();
  }

  const orderableProps = spec?.properties?.MPItem?.items?.properties?.Orderable?.properties || {};
  
  for (const [fieldName, fieldDef] of Object.entries(orderableProps) as [string, any][]) {
    if (fieldDef.type === 'object' && fieldDef.properties?.en) {
      multiLangFields.add(fieldName);
    } else if (fieldDef.type === 'array' && fieldDef.items?.type === 'object' && fieldDef.items?.properties?.en) {
      arrayMultiLangFields.add(fieldName);
    } else if (fieldDef.type === 'object' && fieldDef.properties?.unit && fieldDef.properties?.measure) {
      weightFields.add(fieldName);
    } else if (fieldDef.type === 'array' && fieldDef.items?.type === 'string') {
      arrayFields.add(fieldName);
    }
  }

  caSpecCache = { multiLangFields, arrayMultiLangFields, weightFields, arrayFields };
  return caSpecCache;
};

const convertToMultiLangFormat = (fieldName: string, value: any): any => {
  if (value && typeof value === 'object' && ('en' in value || 'fr' in value)) {
    return value;
  }
  if (Array.isArray(value)) {
    return value.map(item => {
      if (typeof item === 'string') return { en: item };
      if (item && typeof item === 'object' && ('en' in item || 'fr' in item)) return item;
      return { en: String(item) };
    });
  }
  if (typeof value === 'string') return { en: value };
  return value;
};

const convertCASpecialFields = (attrs: Record<string, any>, specInfo: CASpecInfo): Record<string, any> => {
  const result = { ...attrs };
  
  for (const weightField of specInfo.weightFields) {
    const lowerField = weightField.charAt(0).toLowerCase() + weightField.slice(1);
    if (result[weightField] !== undefined && typeof result[weightField] === 'number') {
      result[weightField] = { unit: 'lb', measure: result[weightField] };
    }
    if (result[lowerField] !== undefined && typeof result[lowerField] === 'number') {
      result[weightField] = { unit: 'lb', measure: result[lowerField] };
      delete result[lowerField];
    }
  }
  
  for (const arrayField of specInfo.arrayFields) {
    if (result[arrayField] !== undefined && typeof result[arrayField] === 'string') {
      result[arrayField] = [result[arrayField]];
    }
  }
  
  delete result.countryOfOriginTextiles;
  return result;
};

// 模拟 convertToWalmartV5Format 的核心逻辑
const convertToWalmartV5Format = (platformAttrs: Record<string, any>, region: string): Record<string, any> => {
  const isInternational = region !== 'US';
  const specInfo = parseCASpecFields();
  const orderable: Record<string, any> = {};

  for (const [key, value] of Object.entries(platformAttrs)) {
    if (value === undefined || value === null || value === '') continue;

    let processedValue = value;

    if (isInternational) {
      if (specInfo.multiLangFields.has(key)) {
        processedValue = convertToMultiLangFormat(key, value);
      } else if (specInfo.arrayMultiLangFields.has(key)) {
        processedValue = convertToMultiLangFormat(key, value);
      }
      orderable[key] = processedValue;
    }
  }

  if (isInternational && Object.keys(orderable).length > 0) {
    const converted = convertCASpecialFields(orderable, specInfo);
    for (const [k, v] of Object.entries(converted)) {
      orderable[k] = v;
    }
  }

  return { Orderable: orderable, Visible: { Furniture: {} } };
};

async function main() {
  console.log('=== 验证 CA 字段格式转换 ===\n');

  // 模拟实际的 platformAttrs 数据
  const platformAttrs = {
    sku: 'N891Q372281A',
    productName: 'Elegant Boucle Upholstered Bed',
    brand: 'Unbranded',
    shortDescription: 'A luxurious and comfortable bedroom centerpiece',
    keyFeatures: ['Experience exceptional comfort', 'Luxurious feel', 'Queen size'],
    features: ['High-Density Foam'],
    manufacturer: 'N891',
    ShippingWeight: 116.93,
    countryOfOriginAssembly: 'CN - China',
    price: 431.15,
    mainImageUrl: 'https://example.com/image.jpg',
  };

  console.log('输入数据:');
  console.log(JSON.stringify(platformAttrs, null, 2));

  const result = convertToWalmartV5Format(platformAttrs, 'CA');

  console.log('\n转换后数据:');
  console.log(JSON.stringify(result, null, 2));

  // 验证关键字段
  console.log('\n=== 验证结果 ===');
  const orderable = result.Orderable;
  
  const checks = [
    { field: 'productName', check: () => orderable.productName?.en === 'Elegant Boucle Upholstered Bed' },
    { field: 'brand', check: () => orderable.brand?.en === 'Unbranded' },
    { field: 'shortDescription', check: () => orderable.shortDescription?.en !== undefined },
    { field: 'keyFeatures', check: () => Array.isArray(orderable.keyFeatures) && orderable.keyFeatures[0]?.en !== undefined },
    { field: 'features', check: () => Array.isArray(orderable.features) && orderable.features[0]?.en !== undefined },
    { field: 'manufacturer', check: () => orderable.manufacturer?.en === 'N891' },
    { field: 'ShippingWeight', check: () => orderable.ShippingWeight?.unit === 'lb' && orderable.ShippingWeight?.measure === 116.93 },
    { field: 'countryOfOriginAssembly', check: () => Array.isArray(orderable.countryOfOriginAssembly) },
  ];

  let allPassed = true;
  for (const { field, check } of checks) {
    const passed = check();
    console.log(`  ${passed ? '✅' : '❌'} ${field}`);
    if (!passed) allPassed = false;
  }

  console.log(`\n${allPassed ? '✅ 所有字段格式正确!' : '❌ 部分字段格式错误'}`);
  console.log('\n提示: 请重启 API 服务器并重新提交商品以应用修复');
}

main().catch(console.error);
