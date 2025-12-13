/**
 * 查询 Feed 状态
 */
import { PrismaClient } from '@prisma/client';
import { NestFactory } from '@nestjs/core';
import { AppModule } from '../src/app.module';
import { ListingService } from '../src/modules/listing/listing.service';

const prisma = new PrismaClient();

async function main() {
  const feedId = process.argv[2] || '1880C5B019175718887A1EFA2C83EE80@Ae0BCgA';
  
  console.log(`查询 Feed 状态: ${feedId}`);

  // 查询数据库中的 Feed 记录
  const feedRecord = await prisma.listingFeedRecord.findFirst({
    where: { feedId },
    include: { shop: true },
  });

  if (!feedRecord) {
    console.log('未找到 Feed 记录');
    return;
  }

  console.log('\n=== 数据库 Feed 记录 ===');
  console.log('状态:', feedRecord.status);
  console.log('成功数:', feedRecord.successCount);
  console.log('失败数:', feedRecord.failCount);
  console.log('错误信息:', feedRecord.errorMessage || '(无)');

  // 调用 API 刷新状态
  const app = await NestFactory.createApplicationContext(AppModule, { logger: ['error', 'warn'] });
  const listingService = app.get(ListingService);

  console.log('\n=== 从 Walmart API 刷新状态 ===');
  try {
    const result = await listingService.refreshFeedStatus(feedRecord.id);
    console.log('刷新后状态:', result.status);
    console.log('成功数:', result.itemsSucceeded);
    console.log('失败数:', result.itemsFailed);
    console.log('接收数:', result.itemsReceived);
    
    if (result.feedDetail) {
      const detail = result.feedDetail as any;
      console.log('\n=== Feed 详情 ===');
      console.log('feedStatus:', detail.feedStatus);
      console.log('itemsReceived:', detail.itemsReceived);
      console.log('itemsSucceeded:', detail.itemsSucceeded);
      console.log('itemsFailed:', detail.itemsFailed);
      
      if (detail.itemDetails?.itemIngestionStatus) {
        console.log('\n=== 商品处理详情 ===');
        for (const item of detail.itemDetails.itemIngestionStatus) {
          console.log(`\nSKU: ${item.sku}`);
          console.log('  状态:', item.ingestionStatus);
          if (item.ingestionErrors?.ingestionError) {
            console.log('  错误:');
            for (const err of item.ingestionErrors.ingestionError) {
              console.log(`    - [${err.type}] ${err.code}: ${err.description}`);
              if (err.field) console.log(`      字段: ${err.field}`);
            }
          }
        }
      }
    }
  } catch (error: any) {
    console.error('刷新失败:', error.message);
  }

  await app.close();
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
