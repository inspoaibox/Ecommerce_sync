/**
 * 检查 CA 类目属性映射
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  // 查询所有 CA 的映射配置
  const mappings = await prisma.categoryAttributeMapping.findMany({
    where: { country: 'CA' },
  });

  console.log(`=== CA 映射配置 (共 ${mappings.length} 个类目) ===\n`);

  if (mappings.length === 0) {
    console.log('没有找到 CA 的映射配置');
    return;
  }

  for (const mapping of mappings) {
    console.log(`\n--- 类目: ${mapping.categoryId} ---`);
    
    const mappingRules = mapping.mappingRules as any;
    
    // 检查 mappingRules 的结构
    console.log(`mappingRules 类型: ${typeof mappingRules}`);
    console.log(`mappingRules keys: ${Object.keys(mappingRules || {}).join(', ')}`);
    
    let rules: any[] = [];
    if (Array.isArray(mappingRules)) {
      rules = mappingRules;
    } else if (mappingRules?.rules && Array.isArray(mappingRules.rules)) {
      // 结构是 { rules: [...] }
      rules = mappingRules.rules;
    } else if (mappingRules && typeof mappingRules === 'object') {
      // 可能是对象格式 { fieldName: rule }
      rules = Object.entries(mappingRules).map(([key, value]: [string, any]) => ({
        targetField: key,
        ...value,
      }));
    }
    
    console.log(`共 ${rules.length} 条映射规则`);
    
    // 打印前 3 条规则的完整结构
    console.log(`\n前 3 条规则的完整结构:`);
    for (let i = 0; i < Math.min(3, rules.length); i++) {
      console.log(`  ${i + 1}. ${JSON.stringify(rules[i], null, 2)}`);
    }

    // 检查关键字段的映射
    const keyFields = ['shortDescription', 'productName', 'brand', 'keyFeatures', 'features', 'manufacturer', 'ShippingWeight', 'sku', 'price', 'mainImageUrl'];
    
    console.log(`\n关键字段映射检查:`);
    for (const field of keyFields) {
      // 使用 attributeId 作为目标字段名
      const rule = rules.find(r => r.attributeId === field || r.attributeId?.toLowerCase() === field.toLowerCase());
      if (rule) {
        console.log(`  ✅ ${field}: value=${rule.value}, mappingType=${rule.mappingType}`);
      } else {
        console.log(`  ❌ ${field}: 未配置映射`);
      }
    }
    
    // 打印所有配置的字段
    console.log(`\n所有配置的字段 (attributeId):`);
    const allFields = rules.map(r => r.attributeId).filter(Boolean);
    console.log(allFields.join(', '));
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
