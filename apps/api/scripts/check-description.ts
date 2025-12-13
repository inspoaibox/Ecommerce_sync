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
  
  console.log('=== 渠道数据中的描述相关字段 ===');
  console.log('description:', channelAttrs?.description ? `"${channelAttrs.description.substring(0, 200)}..."` : '(空或undefined)');
  console.log('description 类型:', typeof channelAttrs?.description);
  console.log('description 长度:', channelAttrs?.description?.length || 0);
  
  console.log('\n=== 所有渠道字段 ===');
  if (channelAttrs) {
    for (const [key, value] of Object.entries(channelAttrs)) {
      const valueStr = typeof value === 'object' ? JSON.stringify(value) : String(value);
      const truncated = valueStr.length > 80 ? valueStr.substring(0, 80) + '...' : valueStr;
      console.log(`  ${key}: ${truncated}`);
    }
  }

  console.log('\n=== platformAttributes 中的 shortDescription ===');
  const platformAttrs = product.platformAttributes as any;
  console.log('shortDescription:', platformAttrs?.shortDescription ? `"${platformAttrs.shortDescription.substring(0, 200)}..."` : '(空或undefined)');
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
