/**
 * 测试 CA 市场 Feed 格式转换
 * 
 * 验证属性字段库提取的数据能否正确转换为 Walmart CA Feed 格式
 * 
 * 使用方法:
 *   cd apps/api
 *   npx ts-node scripts/test-ca-feed-format.ts
 */

import { PrismaClient } from '@prisma/client';
import * as path from 'path';

const prisma = new PrismaClient();

// 加载 CA spec 文件
let caSpecCache: any = null;
const loadCASpec = (): any => {
  if (!caSpecCache) {
    try {
      caSpecCache = require(path.join(__dirname, '../src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'));
      console.log('[Test] CA spec file loaded successfully');
    } catch (e) {
      console.error('[Test] Failed to load CA spec file:', e);
    }
  }
  return caSpecCache;
};

// 从 spec 文件动态解析多语言字段
const getMultiLangFieldsFromSpec = (): Set<string> => {
  const multiLangFields = new Set<string>();
  const spec = loadCASpec();
  if (!spec) return multiLangFields;

  const parseProperties = (props: any) => {
    if (!props || typeof props !== 'object') return;
    
    for (const [key, value] of Object.entries(props) as [string, any][]) {
      if (value?.properties?.en) {
        multiLangFields.add(key);
      }
      if (value?.type === 'array' && value?.items?.properties?.en) {
        multiLangFields.add(key);
      }
      if (value?.properties) {
        parseProperties(value.properties);
      }
    }
  };

  try {
    const mpItemProps = spec?.properties?.MPItem?.items?.properties;
    if (mpItemProps?.Orderable?.properties) {
      parseProperties(mpItemProps.Orderable.properties);
    }
    if (mpItemProps?.Visible?.properties) {
      for (const categoryProps of Object.values(mpItemProps.Visible.properties) as any[]) {
        if (categoryProps?.properties) {
          parseProperties(categoryProps.properties);
        }
      }
    }
  } catch (e) {
    console.error('[Test] Failed to parse multi-lang fields:', e);
  }

  return multiLangFields;
};

// 模拟 convertToWalmartV5Format 方法
function convertToWalmartV5Format(
  platformAttrs: Record<string, any>,
  categoryId: string | null,
  shopConfig?: {
    fulfillmentLagTime?: string;
    fulfillmentMode?: string;
    fulfillmentCenterId?: string;
    shippingTemplate?: string;
    region?: string;
  },
): Record<string, any> {
  if (platformAttrs.Orderable || platformAttrs.Visible) {
    return platformAttrs;
  }

  const region = shopConfig?.region || 'US';
  const isInternational = region !== 'US';

  // 从 spec 文件动态获取多语言字段
  const multiLangFields = getMultiLangFieldsFromSpec();

  const usOrderableFields = [
    'sku', 'productIdentifiers', 'price', 'msrp', 'quantity',
    'ShippingWeight', 'shippingWeight', 'fulfillmentLagTime',
    'stateRestrictions', 'electronicsIndicator', 'chemicalAerosolPesticide',
    'batteryTechnologyType', 'shipsInOriginalPackaging', 'MustShipAlone',
    'mustShipAlone', 'IsPreorder', 'isPreorder', 'releaseDate',
    'startDate', 'endDate', 'fulfillmentCenterID', 'inventoryAvailabilityDate',
    'ProductIdUpdate', 'productIdUpdate', 'SkuUpdate', 'skuUpdate',
  ];

  const caOrderableFields = [
    ...usOrderableFields,
    'productName', 'brand', 'shortDescription', 'keyFeatures', 'features',
    'mainImageUrl', 'productSecondaryImageURL', 'manufacturer', 'modelNumber',
    'countryOfOriginAssembly', 'countryOfOriginTextiles', 'productTaxCode', 'hsCode',
    'MinimumAdvertisedPrice',
  ];

  const orderable: Record<string, any> = {};
  const visible: Record<string, any> = {};
  const orderableFields = isInternational ? caOrderableFields : usOrderableFields;

  for (const [key, value] of Object.entries(platformAttrs)) {
    if (value === undefined || value === null || value === '') continue;

    const isOrderable = orderableFields.some(f => f.toLowerCase() === key.toLowerCase());
    let processedValue = value;

    // 使用 Set.has() 检查是否为多语言字段
    if (isInternational && multiLangFields.has(key)) {
      processedValue = convertToMultiLangFormat(key, value);
    }

    if (isOrderable) {
      orderable[key] = processedValue;
    } else {
      visible[key] = processedValue;
    }
  }

  if (shopConfig) {
    if (!orderable.fulfillmentLagTime && shopConfig.fulfillmentLagTime) {
      orderable.fulfillmentLagTime = String(shopConfig.fulfillmentLagTime);
    }
    if (!orderable.fulfillmentCenterID && shopConfig.fulfillmentCenterId) {
      orderable.fulfillmentCenterID = shopConfig.fulfillmentCenterId;
    }
  }

  const result: Record<string, any> = {};
  if (Object.keys(orderable).length > 0) {
    result.Orderable = orderable;
  }

  const categoryKey = categoryId || 'Default';
  if (isInternational) {
    result.Visible = { [categoryKey]: {} };
  } else if (Object.keys(visible).length > 0) {
    result.Visible = { [categoryKey]: visible };
  }

  return result;
}

function convertToMultiLangFormat(fieldName: string, value: any): any {
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
}

// 模拟 Feed Header 构建
function buildFeedHeader(region: string, subCategory?: string) {
  const isInternational = region !== 'US';
  
  if (isInternational) {
    const header: Record<string, any> = {
      version: '3.16',
      processMode: 'REPLACE',
      subset: 'EXTERNAL',
      mart: `WALMART_${region}`,
      sellingChannel: 'marketplace',
      locale: ['en', 'fr'],
    };
    if (subCategory) {
      header.subCategory = subCategory;
    }
    return header;
  } else {
    return {
      businessUnit: 'WALMART_US',
      locale: 'en',
      version: '5.0.20241118-04_39_24-api',
    };
  }
}

async function main() {
  console.log('='.repeat(60));
  console.log('CA 市场 Feed 格式转换测试');
  console.log('='.repeat(60));

  // 显示从 spec 文件解析出的多语言字段
  const multiLangFields = getMultiLangFieldsFromSpec();
  console.log(`\n📋 从 CA_MP_ITEM_INTL_SPEC.json 解析出 ${multiLangFields.size} 个多语言字段:`);
  console.log(Array.from(multiLangFields).sort().join(', '));

  // 模拟属性字段库提取的数据
  const platformAttrs = {
    sku: 'SJ000149AAK',
    productName: 'Modern Light Luxury TV Stand with Storage',
    brand: 'POVISON',
    shortDescription: 'Elegant TV stand featuring modern design with ample storage space.',
    keyFeatures: [
      'Spacious storage compartments',
      'Modern minimalist design',
      'Durable construction',
    ],
    mainImageUrl: 'https://example.com/image1.jpg',
    productSecondaryImageURL: [
      'https://example.com/image2.jpg',
      'https://example.com/image3.jpg',
    ],
    price: 299.99,
    productIdentifiers: {
      productIdType: 'GTIN',
      productId: '00123456789012',
    },
    shipsInOriginalPackaging: 'No',
    MustShipAlone: 'No',
    countryOfOriginTextiles: 'Imported',
    gender: 'Unisex',
    finish: 'Glossy',
    colorCategory: 'White',
    electronicsIndicator: 'No',
  };

  const categoryId = 'furniture_tv_stands';

  console.log('\n📦 原始属性数据:');
  console.log(JSON.stringify(platformAttrs, null, 2));

  // 测试 US 市场格式
  console.log('\n' + '='.repeat(60));
  console.log('🇺🇸 US 市场格式转换');
  console.log('='.repeat(60));

  const usConfig = { region: 'US', fulfillmentLagTime: '1' };
  const usItemData = convertToWalmartV5Format(platformAttrs, categoryId, usConfig);
  const usFeedHeader = buildFeedHeader('US');

  console.log('\n📋 Feed Header:');
  console.log(JSON.stringify(usFeedHeader, null, 2));

  console.log('\n📋 Item Data:');
  console.log(JSON.stringify(usItemData, null, 2));

  // 测试 CA 市场格式
  console.log('\n' + '='.repeat(60));
  console.log('🇨🇦 CA 市场格式转换');
  console.log('='.repeat(60));

  const caConfig = { region: 'CA', fulfillmentLagTime: '1' };
  const caItemData = convertToWalmartV5Format(platformAttrs, categoryId, caConfig);
  const caFeedHeader = buildFeedHeader('CA', categoryId);

  console.log('\n📋 Feed Header:');
  console.log(JSON.stringify(caFeedHeader, null, 2));

  console.log('\n📋 Item Data:');
  console.log(JSON.stringify(caItemData, null, 2));

  // 验证 CA 格式
  console.log('\n' + '='.repeat(60));
  console.log('✅ CA 格式验证');
  console.log('='.repeat(60));

  const checks = [
    {
      name: 'productName 是多语言格式',
      pass: caItemData.Orderable?.productName?.en !== undefined,
      actual: JSON.stringify(caItemData.Orderable?.productName),
    },
    {
      name: 'brand 是多语言格式',
      pass: caItemData.Orderable?.brand?.en !== undefined,
      actual: JSON.stringify(caItemData.Orderable?.brand),
    },
    {
      name: 'shortDescription 是多语言格式',
      pass: caItemData.Orderable?.shortDescription?.en !== undefined,
      actual: JSON.stringify(caItemData.Orderable?.shortDescription),
    },
    {
      name: 'keyFeatures 是多语言数组格式',
      pass: Array.isArray(caItemData.Orderable?.keyFeatures) && 
            caItemData.Orderable?.keyFeatures[0]?.en !== undefined,
      actual: JSON.stringify(caItemData.Orderable?.keyFeatures),
    },
    {
      name: 'mainImageUrl 在 Orderable 层级',
      pass: caItemData.Orderable?.mainImageUrl !== undefined,
      actual: caItemData.Orderable?.mainImageUrl,
    },
    {
      name: 'Visible 层级为空对象',
      pass: Object.keys(caItemData.Visible?.[categoryId] || {}).length === 0,
      actual: JSON.stringify(caItemData.Visible),
    },
    {
      name: 'Feed Header 包含 mart',
      pass: caFeedHeader.mart === 'WALMART_CA',
      actual: caFeedHeader.mart,
    },
    {
      name: 'Feed Header 包含 locale 数组',
      pass: Array.isArray(caFeedHeader.locale) && caFeedHeader.locale.includes('en'),
      actual: JSON.stringify(caFeedHeader.locale),
    },
    {
      name: 'Feed Header 包含 subCategory',
      pass: caFeedHeader.subCategory === categoryId,
      actual: caFeedHeader.subCategory,
    },
  ];

  let passCount = 0;
  for (const check of checks) {
    const status = check.pass ? '✅' : '❌';
    console.log(`${status} ${check.name}`);
    console.log(`   实际值: ${check.actual}`);
    if (check.pass) passCount++;
  }

  console.log('\n' + '='.repeat(60));
  console.log(`测试结果: ${passCount}/${checks.length} 通过`);
  console.log('='.repeat(60));

  // 输出完整的 CA Feed 示例
  console.log('\n📄 完整 CA Feed 示例:');
  const fullFeed = {
    MPItemFeedHeader: caFeedHeader,
    MPItem: [caItemData],
  };
  console.log(JSON.stringify(fullFeed, null, 2));

  await prisma.$disconnect();
}

main().catch(console.error);
