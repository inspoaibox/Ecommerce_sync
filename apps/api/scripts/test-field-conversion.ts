/**
 * 测试 CA 字段转换逻辑
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-field-conversion.ts
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

const parseCASpecFields = (): CASpecInfo => {
  if (caSpecCache) return caSpecCache;

  const multiLangFields = new Set<string>();
  const arrayMultiLangFields = new Set<string>();
  const weightFields = new Set<string>();
  const arrayFields = new Set<string>();

  // 尝试多个可能的路径
  const possiblePaths = [
    path.join(__dirname, '../src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(__dirname, '../dist/src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(process.cwd(), 'src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(process.cwd(), '../API-doc/CA_MP_ITEM_INTL_SPEC.json'),
  ];

  console.log('尝试加载 spec 文件...');
  console.log('__dirname:', __dirname);
  console.log('process.cwd():', process.cwd());

  let spec: any = null;
  for (const specPath of possiblePaths) {
    console.log(`  检查: ${specPath}`);
    try {
      if (fs.existsSync(specPath)) {
        spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));
        console.log(`  ✅ 找到并加载: ${specPath}`);
        break;
      } else {
        console.log(`  ❌ 不存在`);
      }
    } catch (e: any) {
      console.log(`  ❌ 错误: ${e.message}`);
    }
  }

  if (!spec) {
    console.log('\n⚠️ 未找到 spec 文件，使用降级列表');
    return {
      multiLangFields: new Set(['productName', 'brand', 'shortDescription', 'manufacturer', 'warrantyText', 'keywords']),
      arrayMultiLangFields: new Set(['keyFeatures', 'features']),
      weightFields: new Set(['ShippingWeight']),
      arrayFields: new Set(['countryOfOriginAssembly']),
    };
  }

  // 解析 Orderable 层级的字段定义
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

// 转换为多语言格式
const convertToMultiLangFormat = (fieldName: string, value: any): any => {
  if (value && typeof value === 'object' && ('en' in value || 'fr' in value)) {
    return value;
  }

  if (Array.isArray(value)) {
    return value.map(item => {
      if (typeof item === 'string') {
        return { en: item };
      }
      if (item && typeof item === 'object' && ('en' in item || 'fr' in item)) {
        return item;
      }
      return { en: String(item) };
    });
  }

  if (typeof value === 'string') {
    return { en: value };
  }

  return value;
};

// 转换特殊字段
const convertCASpecialFields = (attrs: Record<string, any>, specInfo: CASpecInfo): Record<string, any> => {
  const result = { ...attrs };
  
  // 转换重量字段
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
  
  // 转换普通数组字段
  for (const arrayField of specInfo.arrayFields) {
    if (result[arrayField] !== undefined && typeof result[arrayField] === 'string') {
      result[arrayField] = [result[arrayField]];
    }
  }
  
  delete result.countryOfOriginTextiles;
  
  return result;
};

async function main() {
  console.log('=== 测试 CA 字段转换逻辑 ===\n');

  const specInfo = parseCASpecFields();

  console.log('\n=== Spec 解析结果 ===');
  console.log('多语言字段:', Array.from(specInfo.multiLangFields).join(', '));
  console.log('数组多语言字段:', Array.from(specInfo.arrayMultiLangFields).join(', '));
  console.log('重量字段:', Array.from(specInfo.weightFields).join(', '));
  console.log('普通数组字段:', Array.from(specInfo.arrayFields).join(', '));

  // 模拟输入数据
  const testData = {
    sku: 'TEST-SKU-001',
    productName: 'Test Product Name',
    brand: 'Test Brand',
    shortDescription: 'This is a test description',
    keyFeatures: ['Feature 1', 'Feature 2', 'Feature 3'],
    features: ['Additional Feature 1'],
    manufacturer: 'Test Manufacturer',
    ShippingWeight: 10.5,
    countryOfOriginAssembly: 'CN - China',
    price: 99.99,
    mainImageUrl: 'https://example.com/image.jpg',
  };

  console.log('\n=== 输入数据 ===');
  console.log(JSON.stringify(testData, null, 2));

  // 转换数据
  const converted: Record<string, any> = {};
  for (const [key, value] of Object.entries(testData)) {
    if (value === undefined || value === null || value === '') continue;

    let processedValue = value;

    // 检查是否为多语言字段
    if (specInfo.multiLangFields.has(key)) {
      processedValue = convertToMultiLangFormat(key, value);
      console.log(`\n转换多语言字段 ${key}:`, JSON.stringify(processedValue));
    }
    // 检查是否为数组多语言字段
    else if (specInfo.arrayMultiLangFields.has(key)) {
      processedValue = convertToMultiLangFormat(key, value);
      console.log(`\n转换数组多语言字段 ${key}:`, JSON.stringify(processedValue));
    }

    converted[key] = processedValue;
  }

  // 转换特殊字段
  const finalData = convertCASpecialFields(converted, specInfo);

  console.log('\n=== 转换后数据 ===');
  console.log(JSON.stringify(finalData, null, 2));

  // 验证转换结果
  console.log('\n=== 验证结果 ===');
  
  const checks = [
    { field: 'productName', expected: 'object', check: (v: any) => v?.en === 'Test Product Name' },
    { field: 'brand', expected: 'object', check: (v: any) => v?.en === 'Test Brand' },
    { field: 'shortDescription', expected: 'object', check: (v: any) => v?.en === 'This is a test description' },
    { field: 'keyFeatures', expected: 'array of objects', check: (v: any) => Array.isArray(v) && v[0]?.en === 'Feature 1' },
    { field: 'features', expected: 'array of objects', check: (v: any) => Array.isArray(v) && v[0]?.en === 'Additional Feature 1' },
    { field: 'manufacturer', expected: 'object', check: (v: any) => v?.en === 'Test Manufacturer' },
    { field: 'ShippingWeight', expected: 'object with unit/measure', check: (v: any) => v?.unit === 'lb' && v?.measure === 10.5 },
    { field: 'countryOfOriginAssembly', expected: 'array', check: (v: any) => Array.isArray(v) && v[0] === 'CN - China' },
  ];

  let allPassed = true;
  for (const { field, expected, check } of checks) {
    const value = finalData[field];
    const passed = check(value);
    console.log(`  ${passed ? '✅' : '❌'} ${field}: ${passed ? '正确' : '错误'} (期望: ${expected})`);
    if (!passed) {
      console.log(`     实际值: ${JSON.stringify(value)}`);
      allPassed = false;
    }
  }

  console.log(`\n${allPassed ? '✅ 所有测试通过!' : '❌ 部分测试失败'}`);
}

main().catch(console.error);
