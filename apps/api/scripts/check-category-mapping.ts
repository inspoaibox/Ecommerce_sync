/**
 * 检查类目映射配置
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const mapping = await prisma.categoryAttributeMapping.findFirst({
    where: { categoryId: 'furniture_other', country: 'CA' },
  });

  if (!mapping) {
    console.log('未找到映射配置');
    return;
  }

  console.log('=== 映射配置字段 ===');
  console.log('字段列表:', Object.keys(mapping));
  
  console.log('\n=== 映射配置详情 ===');
  console.log('id:', mapping.id);
  console.log('platformId:', mapping.platformId);
  console.log('categoryId:', mapping.categoryId);
  console.log('country:', mapping.country);
  
  // 检查是否有 categoryName 字段
  const mappingAny = mapping as any;
  console.log('categoryName:', mappingAny.categoryName || '(不存在)');
  
  // 检查 mappingRules 结构
  const rules = mapping.mappingRules as any;
  console.log('\n=== mappingRules 结构 ===');
  console.log('顶层字段:', Object.keys(rules || {}));
  
  if (rules?.categoryName) {
    console.log('rules.categoryName:', rules.categoryName);
  }
  if (rules?.categoryInfo) {
    console.log('rules.categoryInfo:', rules.categoryInfo);
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
