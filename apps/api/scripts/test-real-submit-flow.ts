/**
 * 测试真实的商品提交流程
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-real-submit-flow.ts
 */

import { PrismaClient } from '@prisma/client';
import * as path from 'path';
import * as fs from 'fs';

const prisma = new PrismaClient();

// ==================== CA Spec 解析 ====================

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
  ];

  let spec: any = null;
  for (const specPath of possiblePaths) {
    try {
      if (fs.existsSync(specPath)) {
        spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));
        console.log(`[Spec] 加载成功: ${specPath}`);
        break;
      }
    } catch (e) {}
  }

  if (!spec) {
    console.log('[Spec] 使用降级列表');
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

  console.log(`[Spec] 多语言字段: ${Array.from(multiLangFields).join(', ')}`);
  console.log(`[Spec] 数组多语言字段: ${Array.from(arrayMultiLangFields).join(', ')}`);

  caSpecCache = { multiLangFields, arrayMultiLangFields, weightFields, arrayFields };
  return caSpecCache;
};

// ==================== 字段转换函数 ====================

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

// 模拟 convertToWalmartV5Format
const convertToWalmartV5Format = (
  platformAttrs: Record<string, any>,
  categoryId: string | null,
  shopConfig: { region?: string },
  categoryName?: string | null,
): Record<string, any> => {
  const region = shopConfig?.region || 'US';
  const isInternational = region !== 'US';
  const specInfo = parseCASpecFields();
  const orderable: Record<string, any> = {};

  for (const [key, value] of Object.entries(platformAttrs)) {
    if (value === undefined || value === null || value === '') continue;

    let processedValue = value;

    if (isInternational) {
      if (specInfo.multiLangFields.has(key)) {
        processedValue = convertToMultiLangFormat(key, value);
        console.log(`[转换] 多语言字段 ${key}: ${typeof value} -> ${JSON.stringify(processedValue).substring(0, 50)}`);
      } else if (specInfo.arrayMultiLangFields.has(key)) {
        processedValue = convertToMultiLangFormat(key, value);
        console.log(`[转换] 数组多语言字段 ${key}: ${typeof value} -> ${JSON.stringify(processedValue).substring(0, 50)}`);
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

  const categoryKey = categoryName || categoryId || 'Default';
  return { Orderable: orderable, Visible: { [categoryKey]: {} } };
};

// ==================== 主测试流程 ====================

async function main() {
  const SKU = 'N891Q372281A';
  
  console.log('='.repeat(60));
  console.log(`测试商品提交流程: ${SKU}`);
  console.log('='.repeat(60));

  // 1. 获取商品信息
  const product = await prisma.listingProduct.findFirst({
    where: { sku: SKU },
    include: { shop: { include: { platform: true } } },
  });

  if (!product) {
    console.log('商品不存在');
    return;
  }

  console.log(`\n[商品] SKU: ${product.sku}`);
  console.log(`[商品] 店铺: ${product.shop.name} (${product.shop.region})`);
  console.log(`[商品] 平台类目: ${product.platformCategoryId}`);

  // 2. 获取类目映射配置
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
    console.log('\n❌ 未找到类目映射配置');
    return;
  }

  const rules = (categoryMapping.mappingRules as any)?.rules || [];
  console.log(`\n[映射] 共 ${rules.length} 条映射规则`);

  // 3. 检查关键字段的映射配置
  console.log('\n=== 关键字段映射配置 ===');
  const keyFields = ['sku', 'productName', 'brand', 'shortDescription', 'keyFeatures', 'features', 'manufacturer', 'ShippingWeight', 'price', 'mainImageUrl', 'productSecondaryImageURL'];
  
  for (const field of keyFields) {
    const rule = rules.find((r: any) => r.attributeId === field);
    if (rule) {
      console.log(`  ✅ ${field}: mappingType=${rule.mappingType}, value=${JSON.stringify(rule.value).substring(0, 50)}`);
    } else {
      console.log(`  ❌ ${field}: 未配置`);
    }
  }

  // 4. 模拟属性解析（简化版，只处理 channel_data 和 default_value）
  console.log('\n=== 模拟属性解析 ===');
  const channelAttrs = product.channelAttributes as any || {};
  const resolvedAttrs: Record<string, any> = {};

  for (const rule of rules) {
    const { attributeId, mappingType, value } = rule;
    
    if (mappingType === 'channel_data' && typeof value === 'string' && value) {
      // 从渠道数据获取
      const resolved = channelAttrs[value];
      if (resolved !== undefined && resolved !== null && resolved !== '') {
        resolvedAttrs[attributeId] = resolved;
      }
    } else if (mappingType === 'default_value' && value !== undefined && value !== null && value !== '') {
      // 使用默认值
      resolvedAttrs[attributeId] = value;
    }
    // 其他类型（auto_generate, upc_pool 等）需要完整的 AttributeResolverService
  }

  // 合并：使用数据库中的 platformAttributes 作为基础，但用新解析的 channel_data 覆盖
  const dbPlatformAttrs = product.platformAttributes as any || {};
  const platformAttrs = { ...dbPlatformAttrs, ...resolvedAttrs };
  
  console.log(`\n[解析] 从 channel_data 解析: ${Object.keys(resolvedAttrs).length} 个字段`);
  console.log(`[解析] 数据库 platformAttributes: ${Object.keys(dbPlatformAttrs).length} 个字段`);
  console.log(`[解析] 合并后: ${Object.keys(platformAttrs).length} 个字段`);
  
  // 检查 shortDescription 是否从 channel_data 解析到了
  console.log(`[解析] resolvedAttrs.shortDescription: ${resolvedAttrs.shortDescription ? '有值' : '(空)'}`);
  console.log(`[解析] channelAttrs.description: ${channelAttrs.description ? '有值 (' + channelAttrs.description.length + ' 字符)' : '(空)'}`);

  // 5. 检查关键字段的值
  console.log('\n=== 关键字段值检查 ===');
  for (const field of keyFields) {
    const value = platformAttrs[field];
    const type = Array.isArray(value) ? 'array' : typeof value;
    const preview = JSON.stringify(value)?.substring(0, 60) || '(空)';
    console.log(`  ${field}: [${type}] ${preview}`);
  }

  // 6. 获取类目名称
  const platformCategory = await prisma.platformCategory.findFirst({
    where: {
      platformId: product.shop.platformId,
      country: product.shop.region || 'US',
      categoryId: product.platformCategoryId || '',
    },
    select: { name: true },
  });
  const categoryName = platformCategory?.name || null;
  console.log(`\n[类目] 名称: ${categoryName}`);

  // 7. 转换为 Walmart V5.0 格式
  console.log('\n=== 转换为 Walmart V5.0 格式 ===');
  const itemData = convertToWalmartV5Format(
    platformAttrs,
    product.platformCategoryId,
    { region: product.shop.region || 'US' },
    categoryName,
  );

  // 8. 验证转换结果
  console.log('\n=== 转换结果验证 ===');
  const orderable = itemData.Orderable || {};
  
  const checks = [
    { field: 'productName', check: () => orderable.productName?.en !== undefined, expected: '{ en: "..." }' },
    { field: 'brand', check: () => orderable.brand?.en !== undefined, expected: '{ en: "..." }' },
    { field: 'shortDescription', check: () => orderable.shortDescription?.en !== undefined || orderable.shortDescription === undefined, expected: '{ en: "..." } 或 undefined' },
    { field: 'keyFeatures', check: () => !orderable.keyFeatures || (Array.isArray(orderable.keyFeatures) && (!orderable.keyFeatures[0] || orderable.keyFeatures[0]?.en !== undefined)), expected: '[{ en: "..." }]' },
    { field: 'features', check: () => !orderable.features || (Array.isArray(orderable.features) && (!orderable.features[0] || orderable.features[0]?.en !== undefined)), expected: '[{ en: "..." }]' },
    { field: 'manufacturer', check: () => orderable.manufacturer?.en !== undefined, expected: '{ en: "..." }' },
    { field: 'ShippingWeight', check: () => orderable.ShippingWeight?.unit === 'lb', expected: '{ unit: "lb", measure: number }' },
    { field: 'countryOfOriginAssembly', check: () => !orderable.countryOfOriginAssembly || Array.isArray(orderable.countryOfOriginAssembly), expected: '[string]' },
  ];

  let allPassed = true;
  for (const { field, check, expected } of checks) {
    const passed = check();
    const value = orderable[field];
    console.log(`  ${passed ? '✅' : '❌'} ${field}: ${passed ? '正确' : '错误'} (期望: ${expected})`);
    if (!passed) {
      console.log(`     实际值: ${JSON.stringify(value)}`);
      allPassed = false;
    }
  }

  // 9. 输出最终的 Feed 数据结构
  console.log('\n=== 最终 Feed 数据 (部分) ===');
  const feedData = {
    MPItemFeedHeader: {
      version: '3.16',
      processMode: 'REPLACE',
      subset: 'EXTERNAL',
      mart: 'WALMART_CA',
      sellingChannel: 'marketplace',
      locale: ['en', 'fr'],
      subCategory: product.platformCategoryId,
    },
    MPItem: [itemData],
  };

  // 只输出关键字段
  const keyOrderable: Record<string, any> = {};
  for (const field of keyFields) {
    if (orderable[field] !== undefined) {
      keyOrderable[field] = orderable[field];
    }
  }
  console.log(JSON.stringify({ ...feedData, MPItem: [{ Orderable: keyOrderable, Visible: itemData.Visible }] }, null, 2));

  console.log('\n' + '='.repeat(60));
  console.log(allPassed ? '✅ 所有字段格式正确!' : '❌ 部分字段格式错误');
  console.log('='.repeat(60));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
