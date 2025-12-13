/**
 * 全量字段测试脚本：测试所有属性字段库规则
 *
 * 使用真实的 AttributeResolverService 测试完整的属性映射流程
 *
 * 运行方式:
 *   cd apps/api
 *   pnpm exec ts-node -r tsconfig-paths/register scripts/test-all-fields.ts [sku]
 *
 * 示例:
 *   pnpm exec ts-node -r tsconfig-paths/register scripts/test-all-fields.ts
 *   pnpm exec ts-node -r tsconfig-paths/register scripts/test-all-fields.ts SJ000149AAK
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
  const testSku = process.argv[2] || DEFAULT_SKU;

  console.log('='.repeat(80));
  console.log('全量字段测试（使用真实的 AttributeResolverService）');
  console.log(`SKU: ${testSku}`);
  console.log('='.repeat(80));

  // 1. 启动 NestJS 应用获取真实服务
  console.log('\n【1】初始化 NestJS 应用...\n');
  const app = await NestFactory.createApplicationContext(AppModule, {
    logger: ['error', 'warn'],
  });

  const prisma = app.get(PrismaService);
  const attributeResolver = app.get(AttributeResolverService);

  // 2. 获取商品数据
  console.log('【2】获取商品数据\n');
  const product = await prisma.listingProduct.findFirst({
    where: { sku: testSku },
  });

  if (!product) {
    console.log(`❌ 未找到商品 ${testSku}`);
    await app.close();
    return;
  }

  console.log(`✅ 找到商品`);
  console.log(`   - SKU: ${product.sku}`);
  console.log(`   - 标题: ${product.title?.substring(0, 70)}...`);

  const channelAttrs = product.channelAttributes as Record<string, any>;
  if (!channelAttrs) {
    console.log('❌ channelAttributes 为空');
    await app.close();
    return;
  }

  // 显示原始数据
  console.log('\n【3】原始渠道数据\n');
  console.log(`   - title: ${channelAttrs.title?.substring(0, 60) || '(空)'}...`);
  console.log(`   - color: ${channelAttrs.color || '(空)'}`);
  console.log(`   - material: ${channelAttrs.material || '(空)'}`);
  console.log(`   - placeOfOrigin: ${channelAttrs.placeOfOrigin || '(空)'}`);
  console.log(`   - supplier: ${channelAttrs.supplier || '(空)'}`);
  const bulletPoints = channelAttrs.bulletPoints;
  if (Array.isArray(bulletPoints)) {
    console.log(`   - bulletPoints: ${bulletPoints.length} 条`);
  }

  // 3. 构建完整的映射规则配置
  console.log('\n【4】构建映射规则配置\n');

  const rules = WALMART_DEFAULT_MAPPING_RULES.map((config) => ({
    attributeId: config.attributeId,
    attributeName: config.attributeName,
    mappingType: config.mappingType,
    value: config.value,
    isRequired: false,
    dataType: 'string',
  })) as MappingRule[];

  const mappingRulesConfig: MappingRulesConfig = {
    rules,
    version: '1.0',
    updatedAt: new Date().toISOString(),
  };

  console.log(`   - 总规则数: ${rules.length}`);
  console.log(`   - auto_generate 规则: ${rules.filter(r => r.mappingType === 'auto_generate').length}`);
  console.log(`   - channel_data 规则: ${rules.filter(r => r.mappingType === 'channel_data').length}`);
  console.log(`   - default_value 规则: ${rules.filter(r => r.mappingType === 'default_value').length}`);
  console.log(`   - enum_select 规则: ${rules.filter(r => r.mappingType === 'enum_select').length}`);

  // 4. 调用真实的 AttributeResolverService
  console.log('\n【5】调用 AttributeResolverService.resolveAttributes()\n');
  console.log('-'.repeat(80));

  const startTime = Date.now();

  const result = await attributeResolver.resolveAttributes(
    mappingRulesConfig,
    channelAttrs,
    {
      productSku: product.sku,
      shopId: product.shopId,
    },
  );

  const totalTime = Date.now() - startTime;

  // 5. 显示结果
  console.log('\n【6】提取结果\n');

  if (!result.success) {
    console.log('❌ 提取失败');
    console.log('错误:', result.errors);
  }

  // 按类型分组显示
  const autoGenerateRules = rules.filter(r => r.mappingType === 'auto_generate');
  const channelDataRules = rules.filter(r => r.mappingType === 'channel_data');
  const defaultValueRules = rules.filter(r => r.mappingType === 'default_value');
  const enumSelectRules = rules.filter(r => r.mappingType === 'enum_select');

  console.log('\n--- auto_generate 规则结果 ---\n');
  for (const rule of autoGenerateRules) {
    const value = result.attributes[rule.attributeId];
    const status = value !== undefined && value !== null ? '✅' : '⚪';
    const valueStr = formatValue(value);
    const ruleConfig = rule.value as { ruleType?: string };
    console.log(`${status} ${rule.attributeId.padEnd(30)} [${ruleConfig.ruleType}]`);
    console.log(`   => ${valueStr}`);
  }

  console.log('\n--- channel_data 规则结果 ---\n');
  for (const rule of channelDataRules) {
    const value = result.attributes[rule.attributeId];
    const status = value !== undefined && value !== null ? '✅' : '⚪';
    const valueStr = formatValue(value);
    console.log(`${status} ${rule.attributeId.padEnd(30)} [${rule.value}]`);
    console.log(`   => ${valueStr}`);
  }

  console.log('\n--- default_value 规则结果 ---\n');
  for (const rule of defaultValueRules) {
    const value = result.attributes[rule.attributeId];
    const status = value !== undefined && value !== null ? '✅' : '⚪';
    const valueStr = formatValue(value);
    console.log(`${status} ${rule.attributeId.padEnd(30)} => ${valueStr}`);
  }

  console.log('\n--- enum_select 规则结果 ---\n');
  for (const rule of enumSelectRules) {
    const value = result.attributes[rule.attributeId];
    const status = value !== undefined && value !== null ? '✅' : '⚪';
    const valueStr = formatValue(value);
    console.log(`${status} ${rule.attributeId.padEnd(30)} => ${valueStr}`);
  }

  // 6. 统计
  console.log('\n' + '-'.repeat(80));
  console.log('\n【7】统计\n');

  const totalRules = rules.length;
  const resolvedCount = Object.keys(result.attributes).length;
  const autoGenResolved = autoGenerateRules.filter(r => result.attributes[r.attributeId] !== undefined).length;

  console.log(`📊 总规则数: ${totalRules}`);
  console.log(`✅ 成功解析: ${resolvedCount}`);
  console.log(`   - auto_generate: ${autoGenResolved}/${autoGenerateRules.length}`);
  console.log(`   - channel_data: ${channelDataRules.filter(r => result.attributes[r.attributeId] !== undefined).length}/${channelDataRules.length}`);
  console.log(`   - default_value: ${defaultValueRules.filter(r => result.attributes[r.attributeId] !== undefined).length}/${defaultValueRules.length}`);
  console.log(`   - enum_select: ${enumSelectRules.filter(r => result.attributes[r.attributeId] !== undefined).length}/${enumSelectRules.length}`);
  console.log(`⏱️  总耗时: ${totalTime}ms`);

  if (result.warnings.length > 0) {
    console.log(`\n⚠️  警告 (${result.warnings.length}):`);
    result.warnings.forEach(w => console.log(`   - ${w}`));
  }

  if (result.errors.length > 0) {
    console.log(`\n❌ 错误 (${result.errors.length}):`);
    result.errors.forEach(e => console.log(`   - ${e.attributeId}: ${e.message}`));
  }

  console.log('\n' + '='.repeat(80));
  console.log('测试完成');
  console.log('='.repeat(80));

  await app.close();
}

function formatValue(value: any): string {
  if (value === undefined) return '(undefined)';
  if (value === null) return '(null)';
  if (Array.isArray(value)) {
    const str = JSON.stringify(value);
    return str.length > 60 ? str.substring(0, 60) + '...' : str;
  }
  if (typeof value === 'object') {
    const str = JSON.stringify(value);
    return str.length > 60 ? str.substring(0, 60) + '...' : str;
  }
  const str = String(value);
  return str.length > 60 ? str.substring(0, 60) + '...' : str;
}

main().catch(console.error);
