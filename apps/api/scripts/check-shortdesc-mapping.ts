/**
 * 检查 shortDescription 映射规则
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const mapping = await prisma.categoryAttributeMapping.findFirst({
    where: { country: 'CA', categoryId: 'furniture_other' },
  });

  if (!mapping) {
    console.log('未找到映射配置');
    return;
  }

  const rules = (mapping.mappingRules as any)?.rules || [];
  const rule = rules.find((x: any) => x.attributeId === 'shortDescription');
  
  console.log('shortDescription 映射规则:');
  console.log(JSON.stringify(rule, null, 2));
  
  // 检查是否有其他可用的描述字段
  console.log('\n可能的描述来源字段:');
  const product = await prisma.listingProduct.findFirst({ where: { sku: 'N891Q372281A' } });
  const channelAttrs = product?.channelAttributes as any;
  
  if (channelAttrs) {
    console.log('  description:', channelAttrs.description ? `有值 (${channelAttrs.description.length} 字符)` : '空');
    console.log('  title:', channelAttrs.title ? `有值 (${channelAttrs.title.length} 字符)` : '空');
    console.log('  bulletPoints:', channelAttrs.bulletPoints?.length ? `有值 (${channelAttrs.bulletPoints.length} 条)` : '空');
    
    // 显示 bulletPoints 的第一条
    if (channelAttrs.bulletPoints?.[0]) {
      console.log('\n  bulletPoints[0] 内容:');
      console.log('  ', channelAttrs.bulletPoints[0].substring(0, 200));
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
