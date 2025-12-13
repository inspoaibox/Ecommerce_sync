/**
 * 检查提交的 Feed 数据
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/check-submitted-feed-data.ts
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('=== 检查提交的 Feed 数据 ===\n');

  // 查询 CA 店铺
  const caShop = await prisma.shop.findFirst({ where: { region: 'CA' } });
  if (!caShop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  // 查询最近的 Feed 记录
  const feedRecord = await prisma.listingFeedRecord.findFirst({
    where: { shopId: caShop.id },
    orderBy: { createdAt: 'desc' },
  });

  if (!feedRecord) {
    console.log('没有找到 Feed 记录');
    return;
  }

  console.log(`Feed ID: ${feedRecord.feedId}`);
  console.log(`创建时间: ${feedRecord.createdAt}`);
  console.log('');

  // 检查 submittedData
  const submittedData = feedRecord.submittedData as any;
  if (!submittedData) {
    console.log('没有 submittedData');
    return;
  }

  console.log('=== Feed Header ===');
  console.log(JSON.stringify(submittedData.MPItemFeedHeader, null, 2));

  console.log('\n=== MPItem ===');
  const mpItems = submittedData.MPItem || [];
  console.log(`商品数量: ${mpItems.length}`);

  for (let i = 0; i < mpItems.length; i++) {
    const item = mpItems[i];
    console.log(`\n--- 商品 ${i + 1} ---`);
    
    console.log('\nOrderable 字段:');
    const orderable = item.Orderable || {};
    for (const [key, value] of Object.entries(orderable)) {
      const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
      const truncated = valueStr.length > 100 ? valueStr.substring(0, 100) + '...' : valueStr;
      console.log(`  ${key}: ${truncated}`);
    }

    console.log('\nVisible 字段:');
    const visible = item.Visible || {};
    for (const [categoryKey, categoryValue] of Object.entries(visible)) {
      console.log(`  类目: ${categoryKey}`);
      const categoryAttrs = categoryValue as any;
      if (typeof categoryAttrs === 'object') {
        for (const [key, value] of Object.entries(categoryAttrs)) {
          const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
          const truncated = valueStr.length > 100 ? valueStr.substring(0, 100) + '...' : valueStr;
          console.log(`    ${key}: ${truncated}`);
        }
      }
    }
  }

  // 检查 ListingProduct 的原始数据
  console.log('\n\n=== 检查 ListingProduct 原始数据 ===');
  const listingProduct = await prisma.listingProduct.findFirst({
    where: { shopId: caShop.id },
    orderBy: { createdAt: 'desc' },
  });

  if (listingProduct) {
    console.log(`SKU: ${listingProduct.sku}`);
    console.log(`Title: ${listingProduct.title}`);
    console.log(`Price: ${listingProduct.price}`);
    console.log(`platformCategoryId: ${listingProduct.platformCategoryId}`);
    
    console.log('\nchannelAttributes (前 20 个字段):');
    const channelAttrs = listingProduct.channelAttributes as any;
    if (channelAttrs) {
      const keys = Object.keys(channelAttrs).slice(0, 20);
      for (const key of keys) {
        const value = channelAttrs[key];
        const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
        const truncated = valueStr.length > 80 ? valueStr.substring(0, 80) + '...' : valueStr;
        console.log(`  ${key}: ${truncated}`);
      }
      console.log(`  ... 共 ${Object.keys(channelAttrs).length} 个字段`);
    }

    console.log('\nplatformAttributes (前 20 个字段):');
    const platformAttrs = listingProduct.platformAttributes as any;
    if (platformAttrs) {
      const keys = Object.keys(platformAttrs).slice(0, 20);
      for (const key of keys) {
        const value = platformAttrs[key];
        const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
        const truncated = valueStr.length > 80 ? valueStr.substring(0, 80) + '...' : valueStr;
        console.log(`  ${key}: ${truncated}`);
      }
      console.log(`  ... 共 ${Object.keys(platformAttrs).length} 个字段`);
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
