/**
 * 检查商品的所有数据
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

  console.log('=== 商品基本信息 ===');
  console.log('id:', product.id);
  console.log('sku:', product.sku);
  console.log('title:', product.title);
  console.log('description:', product.description ? product.description.substring(0, 200) + '...' : '(空)');
  console.log('price:', product.price);
  console.log('listingStatus:', product.listingStatus);

  console.log('\n=== 数据库字段列表 ===');
  const keys = Object.keys(product);
  console.log(keys.join(', '));

  console.log('\n=== channelAttributes ===');
  const channelAttrs = product.channelAttributes as any;
  if (channelAttrs) {
    console.log('字段数:', Object.keys(channelAttrs).length);
    for (const [key, value] of Object.entries(channelAttrs)) {
      const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
      const truncated = valueStr.length > 100 ? valueStr.substring(0, 100) + '...' : valueStr;
      console.log(`  ${key}: ${truncated}`);
    }
  }

  console.log('\n=== platformAttributes (关键字段) ===');
  const platformAttrs = product.platformAttributes as any;
  if (platformAttrs) {
    console.log('字段数:', Object.keys(platformAttrs).length);
    const keyFields = ['sku', 'productName', 'brand', 'shortDescription', 'keyFeatures', 'features', 'manufacturer', 'ShippingWeight', 'price', 'mainImageUrl'];
    for (const key of keyFields) {
      const value = platformAttrs[key];
      const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
      const truncated = valueStr.length > 100 ? valueStr.substring(0, 100) + '...' : valueStr;
      console.log(`  ${key}: ${truncated}`);
    }
  }

  // 检查是否有 AI 生成的数据存储在其他地方
  console.log('\n=== 检查 channelRawData ===');
  const rawData = product.channelRawData as any;
  if (rawData) {
    console.log('字段数:', Object.keys(rawData).length);
    console.log('字段列表:', Object.keys(rawData).slice(0, 20).join(', '));
  } else {
    console.log('(空)');
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
