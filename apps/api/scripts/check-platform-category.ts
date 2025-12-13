/**
 * 检查平台类目配置
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  // 获取 CA 店铺的平台 ID
  const caShop = await prisma.shop.findFirst({
    where: { region: 'CA' },
    include: { platform: true },
  });

  if (!caShop) {
    console.log('未找到 CA 店铺');
    return;
  }

  console.log('=== CA 店铺信息 ===');
  console.log('店铺名称:', caShop.name);
  console.log('平台ID:', caShop.platformId);
  console.log('平台名称:', caShop.platform?.name);

  // 查找 furniture_other 类目
  const category = await prisma.platformCategory.findFirst({
    where: {
      platformId: caShop.platformId,
      categoryId: 'furniture_other',
    },
  });

  console.log('\n=== furniture_other 类目 ===');
  if (category) {
    console.log('找到类目:');
    console.log('  id:', category.id);
    console.log('  categoryId:', category.categoryId);
    console.log('  name:', category.name);
    console.log('  country:', (category as any).country);
  } else {
    console.log('未找到 furniture_other 类目');
    
    // 列出所有类目
    const allCategories = await prisma.platformCategory.findMany({
      where: { platformId: caShop.platformId },
      take: 20,
    });
    
    console.log('\n=== 现有类目列表 (前20个) ===');
    for (const cat of allCategories) {
      console.log(`  ${cat.categoryId}: ${cat.name}`);
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
