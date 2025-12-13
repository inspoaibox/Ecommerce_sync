/**
 * 测试 CA 市场 POST Feed
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-feed-post.ts
 */

import { PrismaClient } from '@prisma/client';
import axios from 'axios';
import * as crypto from 'crypto';

// eslint-disable-next-line @typescript-eslint/no-var-requires
const FormData = require('form-data');

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
  console.log('=== 测试 CA 市场 POST Feed ===\n');

  const caShop = await prisma.shop.findFirst({ where: { region: 'CA' } });
  if (!caShop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  const credentials = caShop.apiCredentials as any;
  const { consumerId, privateKey, channelType } = credentials;

  console.log(`Consumer ID: ${consumerId.substring(0, 15)}...`);
  console.log(`Channel Type: ${channelType?.substring(0, 15)}...`);

  // 重要：URL 必须包含完整的查询参数
  const baseUrl = 'https://marketplace.walmartapis.com';
  const fullUrl = `${baseUrl}/v3/ca/feeds?feedType=MP_ITEM_INTL`;
  
  console.log(`\nURL: ${fullUrl}`);
  
  const timestamp = Date.now();
  const signature = generateSignature(consumerId, privateKey, fullUrl, 'POST', timestamp);
  
  console.log(`Timestamp: ${timestamp}`);
  console.log(`Signature: ${signature.substring(0, 40)}...`);

  const feedData = {
    MPItemFeedHeader: {
      version: '3.16',
      processMode: 'REPLACE',
      subset: 'EXTERNAL',
      mart: 'WALMART_CA',
      sellingChannel: 'marketplace',
      locale: ['en', 'fr'],
      subCategory: 'furniture_other',
    },
    MPItem: [
      {
        Orderable: {
          sku: 'TEST-CA-POST-DELETE',
          productIdentifiers: { productIdType: 'GTIN', productId: '00000000000000' },
          productName: { en: 'Test Product - CA POST' },
          brand: { en: 'Test Brand' },
          price: 99.99,
          ShippingWeight: { unit: 'lb', measure: 10 },
          mainImageUrl: 'https://example.com/image.jpg',
        },
        Visible: { Furniture: {} },
      },
    ],
  };

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

  const form = new FormData();
  form.append('file', JSON.stringify(feedData), {
    filename: 'item.json',
    contentType: 'application/json',
  });

  console.log('\n发送请求...');

  try {
    // 注意：不使用 params，因为查询参数已经在 URL 中（用于签名）
    const response = await axios.post(fullUrl, form, {
      headers: { ...headers, ...form.getHeaders() },
    });
    console.log('\n✅ Feed 提交成功！');
    console.log(`Feed ID: ${response.data.feedId}`);
    console.log('响应:', JSON.stringify(response.data, null, 2));
  } catch (error: any) {
    console.log(`\n❌ Feed 提交失败 - 状态码: ${error.response?.status}`);
    const errData = error.response?.data;
    if (errData) {
      console.log('错误详情:', JSON.stringify(errData, null, 2));
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
