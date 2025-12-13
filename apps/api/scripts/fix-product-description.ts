/**
 * 修复商品的 channelAttributes.description
 * 将 product.description 同步到 channelAttributes.description
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const SKU = 'N891Q372281A';
  
  const product = await prisma.listingProduct.findFirst({
    where: { sku: SKU },
  });

  if (!product) {
    console.log('商品不存在');
    return;
  }

  console.log('=== 修复前 ===');
  console.log('product.description:', product.description ? product.description.substring(0, 100) + '...' : '(空)');
  
  const channelAttrs = product.channelAttributes as any || {};
  console.log('channelAttributes.description:', channelAttrs.description ? channelAttrs.description.substring(0, 100) + '...' : '(空)');

  // 如果 product.description 有值但 channelAttributes.description 为空，则同步
  if (product.description && !channelAttrs.description) {
    const updatedChannelAttrs = {
      ...channelAttrs,
      description: product.description,
    };

    await prisma.listingProduct.update({
      where: { id: product.id },
      data: {
        channelAttributes: updatedChannelAttrs,
      },
    });

    console.log('\n✅ 已同步 description 到 channelAttributes');
  } else {
    console.log('\n无需修复');
  }

  // 验证修复结果
  const updated = await prisma.listingProduct.findFirst({ where: { sku: SKU } });
  const updatedAttrs = updated?.channelAttributes as any || {};
  
  console.log('\n=== 修复后 ===');
  console.log('channelAttributes.description:', updatedAttrs.description ? updatedAttrs.description.substring(0, 100) + '...' : '(空)');
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
