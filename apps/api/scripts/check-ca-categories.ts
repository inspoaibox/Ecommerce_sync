/**
 * 检查加拿大市场的类目数据
 * 运行: npx ts-node -r tsconfig-paths/register scripts/check-ca-categories.ts
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('=== 检查加拿大市场的类目数据 ===\n');

  // 1. 查询加拿大市场的类目数量
  const caCategories = await prisma.platformCategory.findMany({
    where: { country: 'CA' },
    take: 20,
    orderBy: { name: 'asc' },
  });

  console.log(`加拿大市场类目数量: ${caCategories.length}`);
  console.log('\n前20个类目:');
  caCategories.forEach((cat, i) => {
    console.log(`${i + 1}. categoryId: "${cat.categoryId}", name: "${cat.name}", isLeaf: ${cat.isLeaf}`);
  });

  // 2. 查询美国市场的类目数量（对比）
  const usCategories = await prisma.platformCategory.count({
    where: { country: 'US' },
  });
  console.log(`\n美国市场类目数量: ${usCategories}`);

  // 3. 检查 CA spec 文件中的 subCategory 枚举值是否与数据库匹配
  console.log('\n=== 检查 CA spec 中的 subCategory 枚举值 ===');
  
  // 从 spec 文件中提取 subCategory 枚举值
  const caSpec = require('../src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json');
  const subCategories = caSpec?.properties?.MPItemFeedHeader?.properties?.subCategory?.enum || [];
  console.log(`CA spec 中的 subCategory 数量: ${subCategories.length}`);
  console.log('示例:', subCategories.slice(0, 10).join(', '));

  // 4. 检查数据库中的 categoryId 是否在 spec 的 subCategory 枚举中
  console.log('\n=== 检查数据库 categoryId 与 spec subCategory 的匹配 ===');
  const subCategorySet = new Set(subCategories);
  
  const matchedCategories = caCategories.filter(cat => subCategorySet.has(cat.categoryId));
  const unmatchedCategories = caCategories.filter(cat => !subCategorySet.has(cat.categoryId));
  
  console.log(`匹配的类目数: ${matchedCategories.length}`);
  console.log(`不匹配的类目数: ${unmatchedCategories.length}`);
  
  if (unmatchedCategories.length > 0) {
    console.log('\n不匹配的类目:');
    unmatchedCategories.forEach(cat => {
      console.log(`  - categoryId: "${cat.categoryId}", name: "${cat.name}"`);
    });
  }

  // 5. 检查 Visible 层级的类目名称
  console.log('\n=== 检查 Visible 层级的类目名称 ===');
  const visibleProps = caSpec?.properties?.MPItem?.items?.properties?.Visible?.properties || {};
  const visibleCategoryNames = Object.keys(visibleProps);
  console.log(`Visible 层级的类目名称数量: ${visibleCategoryNames.length}`);
  console.log('示例:', visibleCategoryNames.slice(0, 10).join(', '));

  // 6. 检查数据库中的 name 是否与 Visible 层级的类目名称匹配
  console.log('\n=== 检查数据库 name 与 Visible 类目名称的匹配 ===');
  const visibleNameSet = new Set(visibleCategoryNames);
  
  const nameMatchedCategories = caCategories.filter(cat => visibleNameSet.has(cat.name));
  const nameUnmatchedCategories = caCategories.filter(cat => !visibleNameSet.has(cat.name));
  
  console.log(`name 匹配的类目数: ${nameMatchedCategories.length}`);
  console.log(`name 不匹配的类目数: ${nameUnmatchedCategories.length}`);
  
  if (nameUnmatchedCategories.length > 0 && nameUnmatchedCategories.length <= 10) {
    console.log('\nname 不匹配的类目:');
    nameUnmatchedCategories.forEach(cat => {
      console.log(`  - categoryId: "${cat.categoryId}", name: "${cat.name}"`);
    });
  }

  // 7. 检查 ListingProduct 中使用的 platformCategoryId
  console.log('\n=== 检查 ListingProduct 中使用的 platformCategoryId ===');
  const listingProducts = await prisma.listingProduct.findMany({
    where: {
      platformCategoryId: { not: null },
    },
    select: {
      id: true,
      sku: true,
      platformCategoryId: true,
      shop: {
        select: {
          name: true,
          region: true,
        },
      },
    },
    take: 10,
  });

  console.log(`有 platformCategoryId 的 ListingProduct 数量: ${listingProducts.length}`);
  listingProducts.forEach(lp => {
    console.log(`  - SKU: ${lp.sku}, categoryId: "${lp.platformCategoryId}", shop: ${lp.shop?.name} (${lp.shop?.region})`);
  });
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
