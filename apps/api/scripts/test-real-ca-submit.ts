/**
 * 真实 CA 市场提交测试
 * 调用 ListingService.submitListing 方法提交商品到 Walmart CA
 * 
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-real-ca-submit.ts
 */

import { NestFactory } from '@nestjs/core';
import { AppModule } from '../src/app.module';
import { ListingService } from '../src/modules/listing/listing.service';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const SKU = 'N891Q372281A';
  
  console.log('='.repeat(80));
  console.log(`CA 市场真实提交测试: ${SKU}`);
  console.log('='.repeat(80));

  // 创建 NestJS 应用上下文
  const app = await NestFactory.createApplicationContext(AppModule, { logger: ['error', 'warn'] });
  const listingService = app.get(ListingService);

  // 获取商品信息
  const product = await prisma.listingProduct.findFirst({
    where: { sku: SKU },
    include: { shop: true },
  });

  if (!product) {
    console.log('商品不存在');
    await app.close();
    return;
  }

  console.log(`\n[商品] SKU: ${product.sku}`);
  console.log(`[商品] 店铺: ${product.shop.name} (${product.shop.region})`);
  console.log(`[商品] 店铺ID: ${product.shopId}`);
  console.log(`[商品] 商品ID: ${product.id}`);

  // 调用提交方法
  console.log('\n[提交] 开始提交...');
  
  try {
    const result = await listingService.submitListing({
      shopId: product.shopId,
      productIds: [product.id],
    });

    console.log('\n[结果] 提交完成');
    console.log('  feedId:', result.feedId);
    console.log('  successCount:', result.successCount);
    console.log('  failCount:', result.failCount);
    
    if (result.errors && result.errors.length > 0) {
      console.log('\n[错误]');
      for (const err of result.errors) {
        console.log(`  - ${err}`);
      }
    }

    // 等待几秒后查询 Feed 状态
    if (result.feedId) {
      console.log('\n[状态] 等待 5 秒后查询 Feed 状态...');
      await new Promise(resolve => setTimeout(resolve, 5000));
      
      // 查询 Feed 记录
      const feedRecord = await prisma.listingFeedRecord.findFirst({
        where: { feedId: result.feedId },
      });
      
      if (feedRecord) {
        console.log('\n[Feed 记录]');
        console.log('  状态:', feedRecord.status);
        console.log('  成功数:', feedRecord.successCount);
        console.log('  失败数:', feedRecord.failCount);
        console.log('  错误信息:', feedRecord.errorMessage || '(无)');
      }
    }
  } catch (error: any) {
    console.error('\n[错误] 提交失败:', error.message);
    if (error.response?.data) {
      console.error('  API 响应:', JSON.stringify(error.response.data, null, 2));
    }
  }

  await app.close();
  console.log('\n' + '='.repeat(80));
  console.log('测试完成');
  console.log('='.repeat(80));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
