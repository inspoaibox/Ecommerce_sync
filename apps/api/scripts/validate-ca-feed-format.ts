/**
 * 验证 CA Feed 格式是否符合 Walmart 官方规范
 * 
 * 对比生成的 Feed 与官方示例 MP_ITEM_INTL.json
 * 
 * 使用方法:
 *   cd apps/api
 *   npx ts-node scripts/validate-ca-feed-format.ts
 */

import * as path from 'path';

// 官方示例（来自 MP_ITEM_INTL.json）
const officialExample = {
  MPItemFeedHeader: {
    subCategory: 'clothing_other',
    sellingChannel: 'marketplace',
    processMode: 'REPLACE',
    mart: 'WALMART_CA',
    locale: ['en', 'fr'],
    version: '3.15',
    subset: 'EXTERNAL',
  },
  MPItem: [
    {
      Orderable: {
        sku: 'CLOTHING_22112022_C101',
        shortDescription: { en: 'Test001 - Good in quality...' },
        keyFeatures: [{ en: 'Famous dress which can be used casual - 001' }],
        shipsInOriginalPackaging: 'No',
        MustShipAlone: 'No',
        price: 60,
        startDate: '2023-01-06',
        endDate: '2049-12-31',
        productSecondaryImageURL: ['https://example.com/image2.jpg'],
        productIdentifiers: { productIdType: 'GTIN', productId: '00213656080788' },
        productName: { en: 'Rest in peace after wearing the product...' },
        mainImageUrl: 'https://example.com/image1.jpg',
        brand: { en: 'M&M' },
        productTaxCode: 12345678,
        ShippingWeight: { unit: 'lb', measure: 8 },
      },
      Visible: {
        Clothing: {},
      },
    },
  ],
};

// 我们生成的 Feed（模拟）
const generatedFeed = {
  MPItemFeedHeader: {
    version: '3.16',
    processMode: 'REPLACE',
    subset: 'EXTERNAL',
    mart: 'WALMART_CA',
    sellingChannel: 'marketplace',
    locale: ['en', 'fr'],
    subCategory: 'furniture_tv_stands',
  },
  MPItem: [
    {
      Orderable: {
        sku: 'SJ000149AAK',
        productName: { en: 'Modern Light Luxury TV Stand with Storage' },
        brand: { en: 'POVISON' },
        shortDescription: { en: 'Elegant TV stand featuring modern design...' },
        keyFeatures: [
          { en: 'Spacious storage compartments' },
          { en: 'Modern minimalist design' },
          { en: 'Durable construction' },
        ],
        mainImageUrl: 'https://example.com/image1.jpg',
        productSecondaryImageURL: ['https://example.com/image2.jpg', 'https://example.com/image3.jpg'],
        price: 299.99,
        productIdentifiers: { productIdType: 'GTIN', productId: '00123456789012' },
        shipsInOriginalPackaging: 'No',
        MustShipAlone: 'No',
        countryOfOriginTextiles: 'Imported',
        electronicsIndicator: 'No',
        fulfillmentLagTime: '1',
      },
      Visible: {
        furniture_tv_stands: {},
      },
    },
  ],
};

console.log('='.repeat(70));
console.log('CA Feed 格式验证 - 对比官方示例');
console.log('='.repeat(70));

// 验证项目
const validations: Array<{ name: string; check: () => boolean; details: string }> = [];

// 1. MPItemFeedHeader 验证
validations.push({
  name: 'Header: version 字段存在',
  check: () => !!generatedFeed.MPItemFeedHeader.version,
  details: `值: ${generatedFeed.MPItemFeedHeader.version}`,
});

validations.push({
  name: 'Header: processMode = REPLACE',
  check: () => generatedFeed.MPItemFeedHeader.processMode === 'REPLACE',
  details: `值: ${generatedFeed.MPItemFeedHeader.processMode}`,
});

validations.push({
  name: 'Header: subset = EXTERNAL',
  check: () => generatedFeed.MPItemFeedHeader.subset === 'EXTERNAL',
  details: `值: ${generatedFeed.MPItemFeedHeader.subset}`,
});

validations.push({
  name: 'Header: mart = WALMART_CA',
  check: () => generatedFeed.MPItemFeedHeader.mart === 'WALMART_CA',
  details: `值: ${generatedFeed.MPItemFeedHeader.mart}`,
});

validations.push({
  name: 'Header: sellingChannel = marketplace',
  check: () => generatedFeed.MPItemFeedHeader.sellingChannel === 'marketplace',
  details: `值: ${generatedFeed.MPItemFeedHeader.sellingChannel}`,
});

validations.push({
  name: 'Header: locale 是数组且包含 en',
  check: () =>
    Array.isArray(generatedFeed.MPItemFeedHeader.locale) &&
    generatedFeed.MPItemFeedHeader.locale.includes('en'),
  details: `值: ${JSON.stringify(generatedFeed.MPItemFeedHeader.locale)}`,
});

validations.push({
  name: 'Header: subCategory 字段存在',
  check: () => !!generatedFeed.MPItemFeedHeader.subCategory,
  details: `值: ${generatedFeed.MPItemFeedHeader.subCategory}`,
});

// 2. MPItem 结构验证
const item = generatedFeed.MPItem[0];
const officialItem = officialExample.MPItem[0];

validations.push({
  name: 'Item: 有 Orderable 层级',
  check: () => !!item.Orderable,
  details: `存在: ${!!item.Orderable}`,
});

validations.push({
  name: 'Item: 有 Visible 层级',
  check: () => !!item.Visible,
  details: `存在: ${!!item.Visible}`,
});

// 3. Orderable 字段验证
validations.push({
  name: 'Orderable: sku 是字符串',
  check: () => typeof item.Orderable.sku === 'string',
  details: `值: ${item.Orderable.sku}`,
});

validations.push({
  name: 'Orderable: productName 是多语言格式 {en: ...}',
  check: () => typeof item.Orderable.productName === 'object' && 'en' in item.Orderable.productName,
  details: `格式: ${JSON.stringify(item.Orderable.productName)}`,
});

validations.push({
  name: 'Orderable: brand 是多语言格式 {en: ...}',
  check: () => typeof item.Orderable.brand === 'object' && 'en' in item.Orderable.brand,
  details: `格式: ${JSON.stringify(item.Orderable.brand)}`,
});

validations.push({
  name: 'Orderable: shortDescription 是多语言格式 {en: ...}',
  check: () =>
    typeof item.Orderable.shortDescription === 'object' && 'en' in item.Orderable.shortDescription,
  details: `格式: ${JSON.stringify(item.Orderable.shortDescription).substring(0, 50)}...`,
});

validations.push({
  name: 'Orderable: keyFeatures 是多语言数组 [{en: ...}]',
  check: () =>
    Array.isArray(item.Orderable.keyFeatures) &&
    item.Orderable.keyFeatures.length > 0 &&
    'en' in item.Orderable.keyFeatures[0],
  details: `格式: ${JSON.stringify(item.Orderable.keyFeatures[0])}`,
});

validations.push({
  name: 'Orderable: price 是数字',
  check: () => typeof item.Orderable.price === 'number',
  details: `值: ${item.Orderable.price}`,
});

validations.push({
  name: 'Orderable: mainImageUrl 是字符串（非多语言）',
  check: () => typeof item.Orderable.mainImageUrl === 'string',
  details: `值: ${item.Orderable.mainImageUrl}`,
});

validations.push({
  name: 'Orderable: productSecondaryImageURL 是字符串数组（非多语言）',
  check: () =>
    Array.isArray(item.Orderable.productSecondaryImageURL) &&
    typeof item.Orderable.productSecondaryImageURL[0] === 'string',
  details: `格式: ${JSON.stringify(item.Orderable.productSecondaryImageURL[0])}`,
});

validations.push({
  name: 'Orderable: productIdentifiers 结构正确',
  check: () =>
    !!(item.Orderable.productIdentifiers &&
    item.Orderable.productIdentifiers.productIdType &&
    item.Orderable.productIdentifiers.productId),
  details: `格式: ${JSON.stringify(item.Orderable.productIdentifiers)}`,
});

validations.push({
  name: 'Orderable: shipsInOriginalPackaging 是 Yes/No',
  check: () => ['Yes', 'No'].includes(item.Orderable.shipsInOriginalPackaging),
  details: `值: ${item.Orderable.shipsInOriginalPackaging}`,
});

validations.push({
  name: 'Orderable: MustShipAlone 是 Yes/No',
  check: () => ['Yes', 'No'].includes(item.Orderable.MustShipAlone),
  details: `值: ${item.Orderable.MustShipAlone}`,
});

// 4. Visible 层级验证
validations.push({
  name: 'Visible: 类目对象为空 {}',
  check: () => {
    const categoryKey = Object.keys(item.Visible)[0];
    return Object.keys((item.Visible as any)[categoryKey]).length === 0;
  },
  details: `格式: ${JSON.stringify(item.Visible)}`,
});

// 运行验证
console.log('\n📋 验证结果:\n');
let passCount = 0;
let failCount = 0;

for (const v of validations) {
  const passed = v.check();
  const status = passed ? '✅' : '❌';
  console.log(`${status} ${v.name}`);
  console.log(`   ${v.details}`);
  if (passed) passCount++;
  else failCount++;
}

console.log('\n' + '='.repeat(70));
console.log(`总计: ${passCount} 通过, ${failCount} 失败`);
console.log('='.repeat(70));

// 对比官方示例和生成的 Feed 结构
console.log('\n📊 结构对比:\n');
console.log('官方示例 Header 字段:', Object.keys(officialExample.MPItemFeedHeader).sort().join(', '));
console.log('生成的 Header 字段:  ', Object.keys(generatedFeed.MPItemFeedHeader).sort().join(', '));
console.log('');
console.log('官方示例 Orderable 字段:', Object.keys(officialItem.Orderable).sort().join(', '));
console.log('生成的 Orderable 字段:  ', Object.keys(item.Orderable).sort().join(', '));

// 检查是否有遗漏的必需字段
console.log('\n⚠️ 潜在问题检查:\n');

const issues: string[] = [];

// 检查 startDate 和 endDate
if (!(item.Orderable as any).startDate) {
  issues.push('缺少 startDate 字段（官方示例有此字段）');
}
if (!(item.Orderable as any).endDate) {
  issues.push('缺少 endDate 字段（官方示例有此字段）');
}

// 检查 ShippingWeight
if (!(item.Orderable as any).ShippingWeight) {
  issues.push('缺少 ShippingWeight 字段（官方示例有此字段）');
}

// 检查 productTaxCode
if (!(item.Orderable as any).productTaxCode) {
  issues.push('缺少 productTaxCode 字段（官方示例有此字段，但可能非必填）');
}

if (issues.length === 0) {
  console.log('✅ 没有发现明显问题');
} else {
  for (const issue of issues) {
    console.log(`⚠️ ${issue}`);
  }
}

console.log('\n📄 生成的完整 Feed:\n');
console.log(JSON.stringify(generatedFeed, null, 2));
