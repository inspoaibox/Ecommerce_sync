/**
 * 检查映射规则中的 seatingCapacity 和 keywords
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  // 获取 CA furniture_other 的映射配置
  const mapping = await prisma.categoryAttributeMapping.findFirst({
    where: { categoryId: 'furniture_other', country: 'CA' },
  });

  if (!mapping) {
    console.log('未找到映射配置');
    return;
  }

  const rules = (mapping.mappingRules as any)?.rules || [];
  console.log(`总规则数: ${rules.length}`);

  // 查找 seatingCapacity 规则
  const seatingRule = rules.find((r: any) => r.attributeId === 'seatingCapacity');
  console.log('\n=== seatingCapacity 规则 ===');
  if (seatingRule) {
    console.log(JSON.stringify(seatingRule, null, 2));
  } else {
    console.log('未找到 seatingCapacity 规则');
  }

  // 查找 keywords 规则
  const keywordsRule = rules.find((r: any) => r.attributeId === 'keywords');
  console.log('\n=== keywords 规则 ===');
  if (keywordsRule) {
    console.log(JSON.stringify(keywordsRule, null, 2));
  } else {
    console.log('未找到 keywords 规则');
  }

  // 检查商品的 channelAttributes.keywords
  const product = await prisma.listingProduct.findFirst({
    where: { sku: 'N891Q372281A' },
  });

  if (product) {
    const channelAttrs = product.channelAttributes as any;
    console.log('\n=== 商品 channelAttributes.keywords ===');
    console.log('类型:', typeof channelAttrs?.keywords);
    console.log('值:', JSON.stringify(channelAttrs?.keywords));
    console.log('是否为空数组:', Array.isArray(channelAttrs?.keywords) && channelAttrs?.keywords.length === 0);
  }

  // 列出所有规则的 attributeId
  console.log('\n=== 所有规则的 attributeId ===');
  const attributeIds = rules.map((r: any) => r.attributeId).sort();
  console.log(attributeIds.join(', '));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
