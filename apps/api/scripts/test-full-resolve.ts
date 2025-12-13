/**
 * 完整测试属性解析流程
 * 调用真实的 AttributeResolverService 并显示每个规则的提取结果
 * 
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-full-resolve.ts
 */

import { PrismaClient } from '@prisma/client';
import { NestFactory } from '@nestjs/core';
import { AppModule } from '../src/app.module';
import { AttributeResolverService } from '../src/modules/attribute-mapping/attribute-resolver.service';

const prisma = new PrismaClient();

async function main() {
  const SKU = 'N891Q372281A';
  
  console.log('='.repeat(80));
  console.log(`完整属性解析测试: ${SKU}`);
  console.log('='.repeat(80));

  // 创建 NestJS 应用上下文
  const app = await NestFactory.createApplicationContext(AppModule, { logger: false });
  const attributeResolver = app.get(AttributeResolverService);

  // 1. 获取商品信息
  const product = await prisma.listingProduct.findFirst({
    where: { sku: SKU },
    include: { shop: { include: { platform: true } } },
  });

  if (!product) {
    console.log('商品不存在');
    await app.close();
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
    await app.close();
    return;
  }

  const rules = (categoryMapping.mappingRules as any)?.rules || [];
  console.log(`\n[映射] 共 ${rules.length} 条映射规则`);

  // 3. 获取渠道属性
  const channelAttrs = product.channelAttributes as any || {};
  
  console.log('\n' + '='.repeat(80));
  console.log('渠道数据 (channelAttributes) 关键字段');
  console.log('='.repeat(80));
  
  const channelKeyFields = ['sku', 'title', 'description', 'bulletPoints', 'keywords', 'mainImageUrl', 'imageUrls', 'supplier', 'price', 'material', 'color'];
  for (const field of channelKeyFields) {
    const value = channelAttrs[field];
    if (value === undefined || value === null) {
      console.log(`  ${field}: (undefined)`);
    } else if (value === '') {
      console.log(`  ${field}: (空字符串)`);
    } else if (Array.isArray(value)) {
      console.log(`  ${field}: [${value.length} 项] ${JSON.stringify(value).substring(0, 80)}...`);
    } else if (typeof value === 'string') {
      console.log(`  ${field}: "${value.substring(0, 80)}${value.length > 80 ? '...' : ''}"`);
    } else {
      console.log(`  ${field}: ${JSON.stringify(value).substring(0, 80)}`);
    }
  }

  // 4. 调用真实的属性解析服务
  console.log('\n' + '='.repeat(80));
  console.log('调用 AttributeResolverService.resolveAttributes()');
  console.log('='.repeat(80));

  const resolveResult = await attributeResolver.resolveAttributes(
    categoryMapping.mappingRules as any,
    channelAttrs,
    {
      productSku: product.sku,
      shopId: product.shopId,
      productPrice: Number(product.price) || 0,
    },
  );

  console.log(`\n解析结果: success=${resolveResult.success}`);
  console.log(`解析的属性数量: ${Object.keys(resolveResult.attributes).length}`);
  
  if (resolveResult.errors.length > 0) {
    console.log(`\n❌ 错误 (${resolveResult.errors.length}):`);
    for (const err of resolveResult.errors) {
      console.log(`  - ${err.attributeId}: ${err.message}`);
    }
  }
  
  if (resolveResult.warnings.length > 0) {
    console.log(`\n⚠️ 警告 (${resolveResult.warnings.length}):`);
    for (const warn of resolveResult.warnings) {
      console.log(`  - ${warn}`);
    }
  }

  // 5. 显示关键字段的解析结果
  console.log('\n' + '='.repeat(80));
  console.log('关键字段解析结果');
  console.log('='.repeat(80));

  const keyFields = [
    'sku', 'productName', 'brand', 'shortDescription', 'keyFeatures', 'features',
    'manufacturer', 'ShippingWeight', 'price', 'mainImageUrl', 'productSecondaryImageURL',
    'productIdentifiers', 'countryOfOriginAssembly', 'productTaxCode',
  ];

  for (const field of keyFields) {
    const rule = rules.find((r: any) => r.attributeId === field);
    const value = resolveResult.attributes[field];
    
    console.log(`\n${field}:`);
    if (rule) {
      console.log(`  映射类型: ${rule.mappingType}`);
      console.log(`  映射配置: ${JSON.stringify(rule.value).substring(0, 60)}`);
    } else {
      console.log(`  映射类型: (未配置)`);
    }
    
    if (value === undefined) {
      console.log(`  解析结果: ❌ undefined`);
    } else if (value === null || value === '') {
      console.log(`  解析结果: ❌ 空值`);
    } else if (Array.isArray(value)) {
      console.log(`  解析结果: ✅ [${value.length} 项]`);
      if (value.length > 0) {
        const preview = JSON.stringify(value[0]).substring(0, 60);
        console.log(`  第一项: ${preview}...`);
      }
    } else if (typeof value === 'object') {
      console.log(`  解析结果: ✅ ${JSON.stringify(value).substring(0, 80)}`);
    } else {
      const str = String(value);
      console.log(`  解析结果: ✅ "${str.substring(0, 60)}${str.length > 60 ? '...' : ''}"`);
    }
  }

  // 6. 显示所有解析的属性
  console.log('\n' + '='.repeat(80));
  console.log(`所有解析的属性 (${Object.keys(resolveResult.attributes).length} 个)`);
  console.log('='.repeat(80));

  const sortedKeys = Object.keys(resolveResult.attributes).sort();
  for (const key of sortedKeys) {
    const value = resolveResult.attributes[key];
    let preview: string;
    
    if (value === undefined || value === null) {
      preview = '(空)';
    } else if (Array.isArray(value)) {
      preview = `[${value.length} 项]`;
    } else if (typeof value === 'object') {
      preview = JSON.stringify(value).substring(0, 50);
    } else {
      const str = String(value);
      preview = str.length > 50 ? str.substring(0, 50) + '...' : str;
    }
    
    console.log(`  ${key}: ${preview}`);
  }

  await app.close();
  console.log('\n' + '='.repeat(80));
  console.log('测试完成');
  console.log('='.repeat(80));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
