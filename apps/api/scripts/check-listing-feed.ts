/**
 * 检查最近提交的 Feed 数据格式
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/check-listing-feed.ts
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('=== 检查最近提交的 Feed 数据 ===\n');

  // 1. 查询最近的 ListingFeedRecord
  const feedRecords = await prisma.listingFeedRecord.findMany({
    orderBy: { createdAt: 'desc' },
    take: 5,
    include: {
      shop: {
        select: {
          name: true,
          region: true,
        },
      },
    },
  });

  console.log(`最近的 Feed 记录数: ${feedRecords.length}\n`);

  for (const record of feedRecords) {
    console.log('='.repeat(60));
    console.log(`Feed ID: ${record.feedId}`);
    console.log(`店铺: ${record.shop?.name} (${record.shop?.region})`);
    console.log(`状态: ${record.status}`);
    console.log(`创建时间: ${record.createdAt}`);
    
    // 检查 submittedData
    const submittedData = record.submittedData as any;
    if (submittedData) {
      console.log('\n--- MPItemFeedHeader ---');
      console.log(JSON.stringify(submittedData.MPItemFeedHeader, null, 2));
      
      console.log('\n--- MPItem (第一个商品) ---');
      const firstItem = submittedData.MPItem?.[0];
      if (firstItem) {
        console.log('Orderable keys:', Object.keys(firstItem.Orderable || {}));
        console.log('Visible keys:', Object.keys(firstItem.Visible || {}));
        
        // 检查 Visible 的 key 是否是类目名称
        const visibleKeys = Object.keys(firstItem.Visible || {});
        if (visibleKeys.length > 0) {
          console.log(`\nVisible 层级的 key: "${visibleKeys[0]}"`);
          
          // 检查这个 key 是 categoryId 还是 name
          const isLikelyCategoryId = visibleKeys[0].includes('_');
          const isLikelyCategoryName = !visibleKeys[0].includes('_') && visibleKeys[0].includes(' ');
          
          if (isLikelyCategoryId) {
            console.log('⚠️ 看起来是 categoryId 格式（包含下划线）');
          } else if (isLikelyCategoryName) {
            console.log('✅ 看起来是类目名称格式（包含空格）');
          } else {
            console.log('❓ 无法确定格式');
          }
        }
      }
    } else {
      console.log('没有 submittedData');
    }
    
    // 检查 feedDetail（Walmart 返回的错误信息）
    const feedDetail = record.feedDetail as any;
    if (feedDetail) {
      console.log('\n--- Feed Detail (Walmart 响应) ---');
      if (feedDetail.itemsReceived !== undefined) {
        console.log(`itemsReceived: ${feedDetail.itemsReceived}`);
        console.log(`itemsSucceeded: ${feedDetail.itemsSucceeded}`);
        console.log(`itemsFailed: ${feedDetail.itemsFailed}`);
      }
      
      // 检查错误信息
      if (feedDetail.itemDetails?.itemIngestionStatus) {
        const ingestionStatus = feedDetail.itemDetails.itemIngestionStatus;
        for (const item of ingestionStatus) {
          if (item.ingestionStatus === 'DATA_ERROR' || item.ingestionErrors) {
            console.log(`\n❌ SKU: ${item.sku}`);
            console.log(`   状态: ${item.ingestionStatus}`);
            if (item.ingestionErrors?.ingestionError) {
              for (const err of item.ingestionErrors.ingestionError) {
                console.log(`   错误: ${err.type} - ${err.description}`);
              }
            }
          }
        }
      }
    }
    
    console.log('\n');
  }

  // 2. 查询最近的 ListingLog（包含错误信息）
  console.log('=== 检查最近的 ListingLog ===\n');
  
  const listingLogs = await prisma.listingLog.findMany({
    where: {
      action: 'submit',
      status: 'failed',
    },
    orderBy: { createdAt: 'desc' },
    take: 5,
    include: {
      shop: {
        select: {
          name: true,
          region: true,
        },
      },
    },
  });

  console.log(`失败的提交日志数: ${listingLogs.length}\n`);

  for (const log of listingLogs) {
    console.log('='.repeat(60));
    console.log(`SKU: ${log.productSku}`);
    console.log(`店铺: ${log.shop?.name} (${log.shop?.region})`);
    console.log(`错误: ${log.errorMessage}`);
    console.log(`错误码: ${log.errorCode}`);
    console.log(`时间: ${log.createdAt}`);
    
    // 检查 requestData
    const requestData = log.requestData as any;
    if (requestData?.Visible) {
      console.log(`Visible keys: ${Object.keys(requestData.Visible)}`);
    }
    
    // 检查 responseData
    const responseData = log.responseData as any;
    if (responseData?.error) {
      console.log(`响应错误: ${responseData.error}`);
    }
    
    console.log('\n');
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
