/**
 * 修复商品的 channelAttributes 数据
 * 将商品表中的关键字段合并到 channelAttributes 中
 * 
 * 运行方式: npx ts-node apps/api/scripts/fix-channel-attributes.ts
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('开始修复商品 channelAttributes 数据...\n');

  // 查找所有商品
  const products = await prisma.listingProduct.findMany({
    where: {
      // 只处理有 channelAttributes 的商品
      channelAttributes: { not: { equals: null } },
    },
    select: {
      id: true,
      sku: true,
      title: true,
      description: true,
      mainImageUrl: true,
      imageUrls: true,
      videoUrls: true,
      price: true,
      stock: true,
      currency: true,
      channelAttributes: true,
    },
  });

  console.log(`找到 ${products.length} 个商品需要检查\n`);

  let fixedCount = 0;
  let skippedCount = 0;

  for (const product of products) {
    const channelAttrs = (product.channelAttributes as any) || {};
    
    // 检查是否缺少关键字段
    const needsFix = !channelAttrs.mainImageUrl || 
                     !channelAttrs.title || 
                     !channelAttrs.sku ||
                     channelAttrs.price === undefined;

    if (!needsFix) {
      skippedCount++;
      continue;
    }

    // 合并关键字段到 channelAttributes
    const updatedChannelAttrs = {
      // 保留原有属性
      ...channelAttrs,
      // 添加/覆盖关键字段
      sku: product.sku,
      title: product.title || channelAttrs.title || '',
      description: product.description || channelAttrs.description || '',
      mainImageUrl: product.mainImageUrl || channelAttrs.mainImageUrl || '',
      imageUrls: product.imageUrls || channelAttrs.imageUrls || [],
      videoUrls: product.videoUrls || channelAttrs.videoUrls || [],
      price: product.price ?? channelAttrs.price ?? 0,
      stock: product.stock ?? channelAttrs.stock ?? 0,
      currency: product.currency || channelAttrs.currency || 'USD',
    };

    // 更新数据库
    await prisma.listingProduct.update({
      where: { id: product.id },
      data: { channelAttributes: updatedChannelAttrs },
    });

    console.log(`✅ 修复: ${product.sku}`);
    fixedCount++;
  }

  console.log(`\n修复完成！`);
  console.log(`  - 已修复: ${fixedCount}`);
  console.log(`  - 已跳过: ${skippedCount}`);
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
