/**
 * 诊断脚本：检查类目映射配置和模拟同步
 * 
 * 运行方式: npx ts-node apps/api/scripts/diagnose-mapping.ts
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('='.repeat(80));
  console.log('诊断开始');
  console.log('='.repeat(80));

  // 1. 检查类目映射配置
  console.log('\n【1】检查类目 "Living Room Furniture Sets" 的映射配置\n');
  
  // 查找 Walmart 平台
  const platform = await prisma.platform.findFirst({
    where: { code: 'walmart' },
  });
  
  if (!platform) {
    console.log('❌ 未找到 Walmart 平台');
    return;
  }
  console.log(`✅ 找到 Walmart 平台: ${platform.id}`);

  // 查找类目映射
  const categoryMapping = await prisma.categoryAttributeMapping.findFirst({
    where: {
      platformId: platform.id,
      country: 'US',
      categoryId: 'Living Room Furniture Sets',
    },
  });

  if (!categoryMapping) {
    console.log('❌ 未找到类目映射配置');
    
    // 尝试模糊查找
    const allMappings = await prisma.categoryAttributeMapping.findMany({
      where: {
        platformId: platform.id,
        country: 'US',
      },
      select: {
        categoryId: true,
      },
    });
    console.log('\n已保存的类目映射列表:');
    allMappings.forEach(m => console.log(`  - ${m.categoryId}`));
    return;
  }

  console.log(`✅ 找到类目映射配置`);
  console.log(`   - ID: ${categoryMapping.id}`);
  console.log(`   - 类目ID: ${categoryMapping.categoryId}`);
  console.log(`   - 国家: ${categoryMapping.country}`);
  console.log(`   - 创建时间: ${categoryMapping.createdAt}`);
  console.log(`   - 更新时间: ${categoryMapping.updatedAt}`);

  // 解析映射规则
  const mappingRules = categoryMapping.mappingRules as any;
  const rules = mappingRules?.rules || [];
  console.log(`\n   映射规则数量: ${rules.length}`);

  // 检查图片相关字段
  console.log('\n   图片相关字段配置:');
  const imageFields = ['mainImageUrl', 'productSecondaryImageURL', 'additionalImageUrl'];
  for (const fieldId of imageFields) {
    const rule = rules.find((r: any) => r.attributeId === fieldId);
    if (rule) {
      console.log(`   ✅ ${fieldId}:`);
      console.log(`      - 映射类型: ${rule.mappingType}`);
      console.log(`      - 来源值: ${JSON.stringify(rule.value)}`);
    } else {
      console.log(`   ❌ ${fieldId}: 未配置`);
    }
  }

  // 输出所有规则
  console.log('\n   所有映射规则:');
  rules.forEach((rule: any, index: number) => {
    console.log(`   ${index + 1}. ${rule.attributeId} (${rule.attributeName})`);
    console.log(`      类型: ${rule.mappingType}, 值: ${JSON.stringify(rule.value)}`);
  });

  // 2. 检查商品数据
  console.log('\n' + '='.repeat(80));
  console.log('【2】检查商品 SKU: SJ000149AAK 的数据\n');

  const product = await prisma.listingProduct.findFirst({
    where: { sku: 'SJ000149AAK' },
    include: {
      shop: {
        include: { platform: true },
      },
    },
  });

  if (!product) {
    console.log('❌ 未找到商品 SJ000149AAK');
    return;
  }

  console.log(`✅ 找到商品`);
  console.log(`   - ID: ${product.id}`);
  console.log(`   - SKU: ${product.sku}`);
  console.log(`   - 标题: ${product.title}`);
  console.log(`   - 店铺: ${product.shop?.name}`);
  console.log(`   - 平台类目ID: ${product.platformCategoryId}`);
  console.log(`   - 刊登状态: ${product.listingStatus}`);

  // 检查 channelAttributes
  const channelAttrs = product.channelAttributes as any;
  console.log('\n   渠道属性 (channelAttributes):');
  
  if (!channelAttrs) {
    console.log('   ❌ channelAttributes 为空');
  } else {
    console.log(`   - mainImageUrl: ${channelAttrs.mainImageUrl || '❌ 未设置'}`);
    console.log(`   - imageUrls: ${channelAttrs.imageUrls ? `[${channelAttrs.imageUrls.length} 张图片]` : '❌ 未设置'}`);
    if (channelAttrs.imageUrls?.length > 0) {
      channelAttrs.imageUrls.slice(0, 3).forEach((url: string, i: number) => {
        console.log(`     [${i}]: ${url.substring(0, 80)}...`);
      });
    }
    console.log(`   - title: ${channelAttrs.title?.substring(0, 50) || '❌ 未设置'}...`);
    console.log(`   - brand: ${channelAttrs.brand || '❌ 未设置'}`);
    console.log(`   - color: ${channelAttrs.color || '❌ 未设置'}`);
    console.log(`   - material: ${channelAttrs.material || '❌ 未设置'}`);
    console.log(`   - price: ${channelAttrs.price || '❌ 未设置'}`);
    console.log(`   - stock: ${channelAttrs.stock || '❌ 未设置'}`);
  }

  // 3. 模拟属性解析
  console.log('\n' + '='.repeat(80));
  console.log('【3】模拟属性解析\n');

  if (categoryMapping && channelAttrs) {
    console.log('模拟解析映射规则...\n');
    
    for (const rule of rules.slice(0, 20)) { // 只显示前20条
      let resolvedValue: any = undefined;
      
      switch (rule.mappingType) {
        case 'default_value':
          resolvedValue = rule.value;
          break;
        case 'channel_data':
          // 简单的路径解析
          const path = rule.value as string;
          if (path) {
            const keys = path.split('.');
            let value = channelAttrs;
            for (const key of keys) {
              if (value === null || value === undefined) break;
              // 处理数组索引
              const match = key.match(/^(\w+)\[(\d+)\]$/);
              if (match) {
                value = value[match[1]]?.[parseInt(match[2])];
              } else {
                value = value[key];
              }
            }
            resolvedValue = value;
          }
          break;
        case 'enum_select':
          resolvedValue = rule.value;
          break;
        case 'auto_generate':
          resolvedValue = `[自动生成: ${(rule.value as any)?.ruleType || 'unknown'}]`;
          break;
      }
      
      const status = resolvedValue !== undefined && resolvedValue !== null && resolvedValue !== '' 
        ? '✅' : '❌';
      const displayValue = typeof resolvedValue === 'string' && resolvedValue.length > 50
        ? resolvedValue.substring(0, 50) + '...'
        : JSON.stringify(resolvedValue);
      
      console.log(`${status} ${rule.attributeId}: ${displayValue}`);
    }
  }

  console.log('\n' + '='.repeat(80));
  console.log('诊断完成');
  console.log('='.repeat(80));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
