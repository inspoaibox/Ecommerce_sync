/**
 * 检查商品完整数据
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const product = await prisma.listingProduct.findFirst({
    where: { sku: 'SJ000149AAK' },
  });

  if (!product) {
    console.log('未找到商品');
    return;
  }

  console.log('商品基本信息:');
  console.log('  - ID:', product.id);
  console.log('  - SKU:', product.sku);
  console.log('  - 标题:', product.title);
  console.log('  - 价格:', product.price);
  
  console.log('\n商品 channelAttributes 完整数据:');
  console.log(JSON.stringify(product.channelAttributes, null, 2));
  
  console.log('\n商品 platformAttributes 完整数据:');
  console.log(JSON.stringify(product.platformAttributes, null, 2));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
