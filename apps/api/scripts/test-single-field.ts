/**
 * 单字段测试脚本：测试单个属性字段的提取规则
 *
 * 用于新增字段后快速验证规则是否正确
 *
 * 使用方式:
 *   cd apps/api
 *   pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts <ruleType> [sku]
 *
 * 示例:
 *   pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts country_of_origin_textiles_extract
 *   pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts color_extract SJ000149AAK
 *
 * 支持的规则类型:
 *   - 所有 auto_generate 规则（如 color_extract, material_extract 等）
 *   - channel_data 规则（如 title, description 等）
 */

import { NestFactory } from '@nestjs/core';
import { AppModule } from '../src/app.module';
import { PrismaService } from '@/common/prisma/prisma.service';
import { AttributeResolverService } from '@/modules/attribute-mapping/attribute-resolver.service';
import { WALMART_DEFAULT_MAPPING_RULES } from '@/modules/platform-category/default-mapping-rules';
import { MappingRulesConfig, MappingRule } from '@/modules/attribute-mapping/interfaces/mapping-rule.interface';

// 默认测试 SKU
const DEFAULT_SKU = 'SJ000149AAK';

async function main() {
  const args = process.argv.slice(2);
  
  if (args.length === 0) {
    console.log('用法: pnpm exec ts-node -r tsconfig-paths/register scripts/test-single-field.ts <ruleType> [sku]');
    console.log('');
    console.log('示例:');
    console.log('  test-single-field.ts country_of_origin_textiles_extract');
    console.log('  test-single-field.ts color_extract SJ000149AAK');
    console.log('');
    console.log('可用的 auto_generate 规则:');
    const autoGenRules = WALMART_DEFAULT_MAPPING_RULES.filter(r => r.mappingType === 'auto_generate');
    autoGenRules.forEach(r => {
      const config = r.value as { ruleType?: string };
      console.log(`  - ${config.ruleType} (${r.attributeId})`);
    });
    console.log('');
    console.log('可用的 channel_data 字段:');
    const channelRules = WALMART_DEFAULT_MAPPING_RULES.filter(r => r.mappingType === 'channel_data');
    channelRules.forEach(r => {
      console.log(`  - ${r.value} (${r.attributeId})`);
    });
    return;
  }

  const ruleType = args[0];
  const testSku = args[1] || DEFAULT_SKU;

  console.log('='.repeat(60));
  console.log(`单字段测试: ${ruleType}`);
  console.log(`测试 SKU: ${testSku}`);
  console.log('='.repeat(60));

  // 1. 初始化 NestJS 应用
  console.log('\n初始化应用...');
  const app = await NestFactory.createApplicationContext(AppModule, {
    logger: ['error'],
  });

  const prisma = app.get(PrismaService);
  const attributeResolver = app.get(AttributeResolverService);

  // 2. 获取商品数据
  const product = await prisma.listingProduct.findFirst({
    where: { sku: testSku },
  });

  if (!product) {
    console.log(`❌ 未找到商品 ${testSku}`);
    await app.close();
    return;
  }

  console.log(`\n✅ 找到商品: ${product.title?.substring(0, 50)}...`);

  const channelAttrs = product.channelAttributes as Record<string, any>;

  // 3. 查找匹配的规则
  let targetRule = WALMART_DEFAULT_MAPPING_RULES.find(r => {
    if (r.mappingType === 'auto_generate') {
      const config = r.value as { ruleType?: string };
      return config.ruleType === ruleType;
    }
    if (r.mappingType === 'channel_data') {
      return r.value === ruleType;
    }
    return r.attributeId === ruleType;
  });

  if (!targetRule) {
    console.log(`\n❌ 未找到规则: ${ruleType}`);
    console.log('请检查规则类型是否正确');
    await app.close();
    return;
  }

  console.log(`\n📋 规则信息:`);
  console.log(`   - attributeId: ${targetRule.attributeId}`);
  console.log(`   - attributeName: ${targetRule.attributeName}`);
  console.log(`   - mappingType: ${targetRule.mappingType}`);
  console.log(`   - value: ${JSON.stringify(targetRule.value)}`);

  // 4. 显示相关渠道数据
  console.log(`\n📦 相关渠道数据:`);
  console.log(`   - title: ${channelAttrs.title?.substring(0, 50) || '(空)'}...`);
  console.log(`   - color: ${channelAttrs.color || '(空)'}`);
  console.log(`   - material: ${channelAttrs.material || '(空)'}`);
  console.log(`   - placeOfOrigin: ${channelAttrs.placeOfOrigin || '(空)'}`);
  console.log(`   - supplier: ${channelAttrs.supplier || '(空)'}`);
  if (Array.isArray(channelAttrs.bulletPoints)) {
    console.log(`   - bulletPoints: ${channelAttrs.bulletPoints.length} 条`);
  }

  // 5. 执行提取
  console.log(`\n🔄 执行提取...`);
  const startTime = Date.now();

  const rule = {
    ...targetRule,
    isRequired: false,
    dataType: 'string',
  } as MappingRule;

  const mappingConfig: MappingRulesConfig = {
    rules: [rule],
    version: '1.0',
    updatedAt: new Date().toISOString(),
  };

  const result = await attributeResolver.resolveAttributes(
    mappingConfig,
    channelAttrs,
    {
      productSku: product.sku,
      shopId: product.shopId,
    },
  );

  const elapsed = Date.now() - startTime;

  // 6. 显示结果
  console.log(`\n${'='.repeat(60)}`);
  console.log('📊 提取结果');
  console.log('='.repeat(60));

  const value = result.attributes[targetRule.attributeId];
  
  if (value !== undefined && value !== null) {
    console.log(`\n✅ 成功提取`);
    console.log(`   值: ${JSON.stringify(value, null, 2)}`);
    console.log(`   类型: ${typeof value}${Array.isArray(value) ? ' (array)' : ''}`);
  } else {
    console.log(`\n⚪ 返回空值 (undefined)`);
    console.log(`   这可能是正常的，表示产品数据中没有相关信息`);
  }

  console.log(`\n⏱️  耗时: ${elapsed}ms`);

  if (result.errors.length > 0) {
    console.log(`\n❌ 错误:`);
    result.errors.forEach(e => console.log(`   - ${e.message}`));
  }

  if (result.warnings.length > 0) {
    console.log(`\n⚠️  警告:`);
    result.warnings.forEach(w => console.log(`   - ${w}`));
  }

  console.log('\n' + '='.repeat(60));

  await app.close();
}

main().catch(console.error);
