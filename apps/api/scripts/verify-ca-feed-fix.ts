/**
 * 验证 CA 市场 Feed 格式修复
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/verify-ca-feed-fix.ts
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('=== 验证 CA 市场 Feed 格式修复 ===\n');

  // 1. 查询 CA 店铺
  const caShop = await prisma.shop.findFirst({
    where: { region: 'CA' },
    include: { platform: true },
  });

  if (!caShop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  console.log(`店铺: ${caShop.name}`);
  console.log(`平台: ${caShop.platform?.name}`);
  console.log(`区域: ${caShop.region}`);

  // 2. 查询该店铺的 ListingProduct
  const listingProducts = await prisma.listingProduct.findMany({
    where: { shopId: caShop.id },
    take: 5,
  });

  console.log(`\n该店铺的 ListingProduct 数量: ${listingProducts.length}`);

  for (const product of listingProducts) {
    console.log(`\n--- 商品: ${product.sku} ---`);
    console.log(`platformCategoryId: ${product.platformCategoryId}`);

    // 3. 查询类目名称
    if (product.platformCategoryId && caShop.platformId) {
      const platformCategory = await prisma.platformCategory.findFirst({
        where: {
          platformId: caShop.platformId,
          country: caShop.region || 'US',
          categoryId: product.platformCategoryId,
        },
        select: { name: true, categoryId: true },
      });

      if (platformCategory) {
        console.log(`类目名称 (name): ${platformCategory.name}`);
        console.log(`类目ID (categoryId): ${platformCategory.categoryId}`);
        
        // 4. 验证 Feed 格式
        console.log('\n预期的 CA Feed 格式:');
        console.log(`  MPItemFeedHeader.subCategory: "${platformCategory.categoryId}"`);
        console.log(`  Visible key: "${platformCategory.name}"`);
      } else {
        console.log('未找到对应的平台类目');
      }
    }
  }

  // 5. 对比标准格式
  console.log('\n=== 标准 CA Feed 格式参考 (API-doc/MP_ITEM_INTL.json) ===');
  console.log('MPItemFeedHeader.subCategory: "clothing_other" (categoryId)');
  console.log('Visible: { "Clothing": {} } (类目名称)');
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
