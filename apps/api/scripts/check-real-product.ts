/**
 * 检查真实产品数据
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  // 查询 CA 店铺的最新商品
  const caShop = await prisma.shop.findFirst({ where: { region: 'CA' } });
  if (!caShop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  const product = await prisma.listingProduct.findFirst({
    where: { shopId: caShop.id },
    orderBy: { createdAt: 'desc' },
  });

  if (!product) {
    console.log('没有找到商品');
    return;
  }

  console.log('=== 商品基本信息 ===');
  console.log('SKU:', product.sku);
  console.log('Title:', product.title);
  console.log('platformCategoryId:', product.platformCategoryId);

  console.log('\n=== channelAttributes 完整内容 ===');
  const channelAttrs = product.channelAttributes as any;
  if (channelAttrs) {
    for (const [key, value] of Object.entries(channelAttrs)) {
      const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
      const truncated = valueStr.length > 100 ? valueStr.substring(0, 100) + '...' : valueStr;
      console.log(`  ${key}: ${truncated}`);
    }
  }

  console.log('\n=== platformAttributes 完整内容 ===');
  const platformAttrs = product.platformAttributes as any;
  if (platformAttrs) {
    for (const [key, value] of Object.entries(platformAttrs)) {
      const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
      const truncated = valueStr.length > 100 ? valueStr.substring(0, 100) + '...' : valueStr;
      console.log(`  ${key}: ${truncated}`);
    }
  }

  // 检查关键字段
  console.log('\n=== 关键字段检查 ===');
  const keyFields = ['productName', 'brand', 'shortDescription', 'keyFeatures', 'features', 'manufacturer', 'ShippingWeight'];
  for (const field of keyFields) {
    const value = platformAttrs?.[field];
    const type = Array.isArray(value) ? 'array' : typeof value;
    const isMultiLang = value && typeof value === 'object' && ('en' in value || (Array.isArray(value) && value[0]?.en));
    console.log(`  ${field}: type=${type}, isMultiLang=${isMultiLang}, value=${JSON.stringify(value)?.substring(0, 80)}`);
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
