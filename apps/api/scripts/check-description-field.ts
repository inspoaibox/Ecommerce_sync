/**
 * 检查 description 字段
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const product = await prisma.listingProduct.findFirst({
    where: { sku: 'N891Q372281A' },
  });

  if (!product) {
    console.log('商品不存在');
    return;
  }

  const channelAttrs = product.channelAttributes as any;
  const platformAttrs = product.platformAttributes as any;

  console.log('=== 渠道属性 ===');
  console.log('description:', channelAttrs?.description ? channelAttrs.description.substring(0, 200) + '...' : '(空)');
  console.log('title:', channelAttrs?.title?.substring(0, 100));

  console.log('\n=== 平台属性 ===');
  console.log('shortDescription:', platformAttrs?.shortDescription ? platformAttrs.shortDescription.substring(0, 200) + '...' : '(空)');
  console.log('productName:', platformAttrs?.productName?.substring(0, 100));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
