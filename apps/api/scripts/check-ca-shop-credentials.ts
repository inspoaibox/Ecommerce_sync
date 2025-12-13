/**
 * 检查加拿大店铺的 API 凭证配置
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/check-ca-shop-credentials.ts
 */

import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  console.log('=== 检查加拿大店铺的 API 凭证配置 ===\n');

  // 查询加拿大店铺
  const caShops = await prisma.shop.findMany({
    where: { region: 'CA' },
    include: {
      platform: {
        select: {
          name: true,
          code: true,
        },
      },
    },
  });

  console.log(`加拿大店铺数量: ${caShops.length}\n`);

  for (const shop of caShops) {
    console.log('='.repeat(60));
    console.log(`店铺名称: ${shop.name}`);
    console.log(`店铺ID: ${shop.id}`);
    console.log(`平台: ${shop.platform?.name} (${shop.platform?.code})`);
    console.log(`区域: ${shop.region}`);
    console.log(`状态: ${shop.status}`);
    
    const credentials = shop.apiCredentials as any;
    if (credentials) {
      console.log('\n--- API 凭证 ---');
      console.log(`clientId: ${credentials.clientId ? '已配置 (' + credentials.clientId.substring(0, 10) + '...)' : '未配置'}`);
      console.log(`clientSecret: ${credentials.clientSecret ? '已配置 (长度: ' + credentials.clientSecret.length + ')' : '未配置'}`);
      
      // 检查是否有其他必要的配置
      console.log(`fulfillmentLagTime: ${credentials.fulfillmentLagTime || '未配置'}`);
      console.log(`fulfillmentMode: ${credentials.fulfillmentMode || '未配置'}`);
      console.log(`fulfillmentCenterId: ${credentials.fulfillmentCenterId || '未配置'}`);
    } else {
      console.log('\n⚠️ 没有 API 凭证配置');
    }
    
    console.log('\n');
  }

  // 对比美国店铺的配置
  console.log('=== 对比美国店铺的 API 凭证配置 ===\n');
  
  const usShops = await prisma.shop.findMany({
    where: { region: 'US' },
    take: 1,
    include: {
      platform: {
        select: {
          name: true,
          code: true,
        },
      },
    },
  });

  for (const shop of usShops) {
    console.log('='.repeat(60));
    console.log(`店铺名称: ${shop.name}`);
    console.log(`区域: ${shop.region}`);
    
    const credentials = shop.apiCredentials as any;
    if (credentials) {
      console.log('\n--- API 凭证 ---');
      console.log(`clientId: ${credentials.clientId ? '已配置 (' + credentials.clientId.substring(0, 10) + '...)' : '未配置'}`);
      console.log(`clientSecret: ${credentials.clientSecret ? '已配置 (长度: ' + credentials.clientSecret.length + ')' : '未配置'}`);
    }
    
    console.log('\n');
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
