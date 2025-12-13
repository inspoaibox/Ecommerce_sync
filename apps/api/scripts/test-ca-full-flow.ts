/**
 * 完整测试 CA 市场提交流程
 * 1. 调用 AttributeResolverService 解析属性
 * 2. 调用 convertToWalmartV5Format 转换格式
 * 3. 验证最终 Feed 数据格式
 * 
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-full-flow.ts
 */

import { PrismaClient } from '@prisma/client';
import { NestFactory } from '@nestjs/core';
import { AppModule } from '../src/app.module';
import { AttributeResolverService } from '../src/modules/attribute-mapping/attribute-resolver.service';
import * as path from 'path';
import * as fs from 'fs';

const prisma = new PrismaClient();

// CA spec 解析（复制自 listing.service.ts）
interface CASpecInfo {
  multiLangFields: Set<string>;
  arrayMultiLangFields: Set<string>;
  weightFields: Set<string>;
  arrayFields: Set<string>;
}

const parseCASpecFields = (): CASpecInfo => {
  const multiLangFields = new Set<string>();
  const arrayMultiLangFields = new Set<string>();
  const weightFields = new Set<string>();
  const arrayFields = new Set<string>();

  const possiblePaths = [
    path.join(process.cwd(), 'src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
    path.join(process.cwd(), 'API-doc/CA_MP_ITEM_INTL_SPEC.json'),
  ];

  let spec: any = null;
  for (const specPath of possiblePaths) {
    try {
      if (fs.existsSync(specPath)) {
        spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));
        console.log(`[Spec] Loaded from: ${specPath}`);
        break;
      }
    } catch (e) {}
  }

  if (!spec) {
    console.warn('[Spec] File not found, using fallback');
    return {
      multiLangFields: new Set(['productName', 'brand', 'shortDescription', 'manufacturer']),
      arrayMultiLangFields: new Set(['keyFeatures', 'features']),
      weightFields: new Set(['ShippingWeight']),
      arrayFields: new Set(['countryOfOriginAssembly']),
    };
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

  return { multiLangFields, arrayMultiLangFields, weightFields, arrayFields };
};

// 转换为多语言格式
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
  if (typeof value === 'string') {
    return { en: value };
  }
  return value;
};

// 转换 CA 特殊字段
const convertCASpecialFields = (attrs: Record<string, any>, specInfo: CASpecInfo): Record<string, any> => {
  const result = { ...attrs };
  
  for (const weightField of specInfo.weightFields) {
    if (result[weightField] !== undefined && typeof result[weightField] === 'number') {
      result[weightField] = { unit: 'lb', measure: result[weightField] };
    } else if (result[weightField] !== undefined && typeof result[weightField] === 'string') {
      const num = parseFloat(result[weightField]);
      if (!isNaN(num)) {
        result[weightField] = { unit: 'lb', measure: num };
      }
    }
  }
  
  for (const arrayField of specInfo.arrayFields) {
    if (result[arrayField] !== undefined && typeof result[arrayField] === 'string') {
      result[arrayField] = [result[arrayField]];
    }
  }
  
  return result;
};

// 模拟 convertToWalmartV5Format
const convertToWalmartV5Format = (
  platformAttrs: Record<string, any>,
  categoryId: string | null,
  categoryName: string | null,
  specInfo: CASpecInfo,
): Record<string, any> => {
  const orderable: Record<string, any> = {};

  for (const [key, value] of Object.entries(platformAttrs)) {
    if (value === undefined || value === null || value === '') continue;

    let processedValue = value;

    // 多语言字段转换
    if (specInfo.multiLangFields.has(key)) {
      processedValue = convertToMultiLangFormat(key, value);
    } else if (specInfo.arrayMultiLangFields.has(key)) {
      processedValue = convertToMultiLangFormat(key, value);
    }

    orderable[key] = processedValue;
  }

  // 特殊字段转换
  const convertedOrderable = convertCASpecialFields(orderable, specInfo);
  for (const [k, v] of Object.entries(convertedOrderable)) {
    orderable[k] = v;
  }

  const categoryKey = categoryName || categoryId || 'Default';
  
  return {
    Orderable: orderable,
    Visible: { [categoryKey]: {} },
  };
};

async function main() {
  const SKU = 'N891Q372281A';
  
  console.log('='.repeat(80));
  console.log(`CA 市场完整提交流程测试: ${SKU}`);
  console.log('='.repeat(80));

  const app = await NestFactory.createApplicationContext(AppModule, { logger: false });
  const attributeResolver = app.get(AttributeResolverService);

  // 1. 获取商品和映射配置
  const product = await prisma.listingProduct.findFirst({
    where: { sku: SKU },
    include: { shop: { include: { platform: true } } },
  });

  if (!product) {
    console.log('商品不存在');
    await app.close();
    return;
  }

  const categoryMapping = await prisma.categoryAttributeMapping.findUnique({
    where: {
      platformId_country_categoryId: {
        platformId: product.shop.platformId,
        country: product.shop.region || 'US',
        categoryId: product.platformCategoryId || '',
      },
    },
  });

  if (!categoryMapping) {
    console.log('未找到类目映射配置');
    await app.close();
    return;
  }

  // 查询类目名称（从 platformCategory 表）
  const platformCategory = await prisma.platformCategory.findFirst({
    where: {
      platformId: product.shop.platformId,
      country: product.shop.region || 'US',
      categoryId: product.platformCategoryId || '',
    },
    select: { name: true },
  });
  const categoryName = platformCategory?.name || product.platformCategoryId;
  
  console.log(`\n[商品] SKU: ${product.sku}`);
  console.log(`[商品] 店铺: ${product.shop.name} (${product.shop.region})`);
  console.log(`[商品] 平台类目ID: ${product.platformCategoryId}`);
  console.log(`[商品] 平台类目名称: ${categoryName}`);

  // 2. 解析 CA spec
  const specInfo = parseCASpecFields();
  console.log(`\n[Spec] 多语言字段: ${Array.from(specInfo.multiLangFields).join(', ')}`);
  console.log(`[Spec] 数组多语言字段: ${Array.from(specInfo.arrayMultiLangFields).join(', ')}`);
  console.log(`[Spec] 重量字段: ${Array.from(specInfo.weightFields).join(', ')}`);
  console.log(`[Spec] 数组字段: ${Array.from(specInfo.arrayFields).join(', ')}`);

  // 3. 调用属性解析
  const channelAttrs = product.channelAttributes as any || {};
  const resolveResult = await attributeResolver.resolveAttributes(
    categoryMapping.mappingRules as any,
    channelAttrs,
    {
      productSku: product.sku,
      shopId: product.shopId,
      productPrice: Number(product.price) || 0,
    },
  );

  console.log(`\n[解析] 成功: ${resolveResult.success}`);
  console.log(`[解析] 属性数量: ${Object.keys(resolveResult.attributes).length}`);

  // 4. 转换为 V5 格式
  const v5Format = convertToWalmartV5Format(
    resolveResult.attributes,
    product.platformCategoryId,
    categoryName,
    specInfo,
  );

  // 5. 验证关键字段格式
  console.log('\n' + '='.repeat(80));
  console.log('关键字段格式验证');
  console.log('='.repeat(80));

  const orderable = v5Format.Orderable || {};
  
  const checkFields = [
    { name: 'productName', expectedType: 'multi-lang', check: (v: any) => v?.en !== undefined },
    { name: 'brand', expectedType: 'multi-lang', check: (v: any) => v?.en !== undefined },
    { name: 'shortDescription', expectedType: 'multi-lang', check: (v: any) => v?.en !== undefined },
    { name: 'manufacturer', expectedType: 'multi-lang', check: (v: any) => v?.en !== undefined },
    { name: 'keyFeatures', expectedType: 'array-multi-lang', check: (v: any) => Array.isArray(v) && v[0]?.en !== undefined },
    { name: 'features', expectedType: 'array-multi-lang', check: (v: any) => Array.isArray(v) && v[0]?.en !== undefined },
    { name: 'ShippingWeight', expectedType: 'weight-object', check: (v: any) => v?.unit && v?.measure !== undefined },
    { name: 'countryOfOriginAssembly', expectedType: 'array', check: (v: any) => Array.isArray(v) },
    { name: 'productIdentifiers', expectedType: 'object', check: (v: any) => v?.productIdType && v?.productId },
  ];

  let allPassed = true;
  for (const field of checkFields) {
    const value = orderable[field.name];
    const passed = value !== undefined && field.check(value);
    const status = passed ? '✅' : '❌';
    allPassed = allPassed && passed;
    
    console.log(`\n${status} ${field.name} (期望: ${field.expectedType})`);
    if (value === undefined) {
      console.log(`   值: undefined`);
    } else {
      const preview = JSON.stringify(value).substring(0, 100);
      console.log(`   值: ${preview}${JSON.stringify(value).length > 100 ? '...' : ''}`);
    }
  }

  // 6. 输出最终 Feed 结构
  console.log('\n' + '='.repeat(80));
  console.log('最终 Feed 结构 (MPItem)');
  console.log('='.repeat(80));

  const mpItem = {
    Orderable: orderable,
    Visible: v5Format.Visible,
  };

  // 只输出关键字段
  const keyFieldsPreview: Record<string, any> = {};
  const keyFieldNames = ['sku', 'productName', 'brand', 'shortDescription', 'keyFeatures', 'features', 
                         'manufacturer', 'ShippingWeight', 'countryOfOriginAssembly', 'productIdentifiers', 'price'];
  for (const f of keyFieldNames) {
    if (orderable[f] !== undefined) {
      keyFieldsPreview[f] = orderable[f];
    }
  }

  console.log('\n关键字段预览:');
  console.log(JSON.stringify(keyFieldsPreview, null, 2));

  console.log('\nVisible 结构:');
  console.log(JSON.stringify(v5Format.Visible, null, 2));

  // 7. 总结
  console.log('\n' + '='.repeat(80));
  console.log('测试结果');
  console.log('='.repeat(80));
  console.log(allPassed ? '✅ 所有关键字段格式正确' : '❌ 部分字段格式不正确');

  await app.close();
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
