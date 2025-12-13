/**
 * 测试 CA 市场 Feed 状态查询
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-feed-status.ts
 */

import { PrismaClient } from '@prisma/client';
import axios from 'axios';
import * as crypto from 'crypto';

const prisma = new PrismaClient();

function generateSignature(consumerId: string, privateKey: string, url: string, method: string, timestamp: number): string {
  const stringToSign = `${consumerId}\n${url}\n${method}\n${timestamp}\n`;
  
  const formattedKey = privateKey.match(/.{1,64}/g)?.join('\n') || privateKey;
  const privateKeyPem = `-----BEGIN PRIVATE KEY-----\n${formattedKey}\n-----END PRIVATE KEY-----`;
  
  const sign = crypto.createSign('RSA-SHA256');
  sign.update(stringToSign);
  sign.end();
  
  return sign.sign(privateKeyPem, 'base64');
}

async function main() {
  console.log('=== 测试 CA 市场 Feed 状态查询 ===\n');

  const caShop = await prisma.shop.findFirst({ where: { region: 'CA' } });
  if (!caShop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  const credentials = caShop.apiCredentials as any;
  const { consumerId, privateKey, channelType } = credentials;

  // 查询最近的 Feed 记录
  const feedRecord = await prisma.listingFeedRecord.findFirst({
    where: { shopId: caShop.id },
    orderBy: { createdAt: 'desc' },
  });

  if (!feedRecord) {
    console.log('没有找到 Feed 记录');
    return;
  }

  console.log(`Feed ID: ${feedRecord.feedId}`);
  console.log(`Consumer ID: ${consumerId.substring(0, 15)}...`);

  const baseUrl = 'https://marketplace.walmartapis.com';
  const feedId = feedRecord.feedId;
  
  // 构建完整 URL（包含查询参数）
  const fullUrl = `${baseUrl}/v3/ca/feeds/${feedId}?includeDetails=true&offset=0&limit=50`;
  
  console.log(`\nURL: ${fullUrl}`);
  
  const timestamp = Date.now();
  const signature = generateSignature(consumerId, privateKey, fullUrl, 'GET', timestamp);

  const headers: Record<string, string> = {
    'WM_SEC.AUTH_SIGNATURE': signature,
    'WM_SEC.TIMESTAMP': String(timestamp),
    'WM_CONSUMER.ID': consumerId,
    'WM_CONSUMER.CHANNEL.TYPE': channelType,
    'WM_SVC.NAME': 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID': `test-${Date.now()}`,
    'WM_TENANT_ID': 'WALMART.CA',
    'WM_LOCALE_ID': 'en_CA',
    Accept: 'application/json',
  };

  try {
    const response = await axios.get(fullUrl, { headers });
    console.log('\n✅ Feed 状态查询成功！');
    console.log(`Feed Status: ${response.data.feedStatus}`);
    console.log(`Items Received: ${response.data.itemsReceived}`);
    console.log(`Items Succeeded: ${response.data.itemsSucceeded}`);
    console.log(`Items Failed: ${response.data.itemsFailed}`);
    
    if (response.data.itemDetails?.itemIngestionStatus) {
      console.log('\nItem Details:');
      response.data.itemDetails.itemIngestionStatus.forEach((item: any, i: number) => {
        console.log(`  ${i + 1}. SKU: ${item.sku}, Status: ${item.ingestionStatus}`);
        if (item.ingestionErrors?.ingestionError) {
          item.ingestionErrors.ingestionError.forEach((err: any) => {
            console.log(`     Error: ${err.description}`);
          });
        }
      });
    }
  } catch (error: any) {
    console.log(`\n❌ Feed 状态查询失败 - 状态码: ${error.response?.status}`);
    const errData = error.response?.data;
    if (errData) {
      console.log('错误详情:', JSON.stringify(errData, null, 2));
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
