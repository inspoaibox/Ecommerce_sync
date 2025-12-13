/**
 * 测试 CA 市场组合认证（OAuth + 数字签名）
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-combined-auth.ts
 */

import { PrismaClient } from '@prisma/client';
import axios from 'axios';
import * as crypto from 'crypto';

// eslint-disable-next-line @typescript-eslint/no-var-requires
const FormData = require('form-data');

const prisma = new PrismaClient();

/**
 * 生成数字签名
 */
function generateSignature(consumerId: string, privateKey: string, url: string, method: string, timestamp: number): string {
  const stringToSign = `${consumerId}\n${url}\n${method}\n${timestamp}\n`;
  
  const privateKeyPem = privateKey.includes('-----BEGIN')
    ? privateKey
    : `-----BEGIN PRIVATE KEY-----\n${privateKey}\n-----END PRIVATE KEY-----`;
  
  const sign = crypto.createSign('RSA-SHA256');
  sign.update(stringToSign);
  sign.end();
  
  return sign.sign(privateKeyPem, 'base64');
}

async function main() {
  console.log('=== 测试 CA 市场组合认证 ===\n');

  const caShop = await prisma.shop.findFirst({
    where: { region: 'CA' },
  });

  if (!caShop) {
    console.log('没有找到加拿大店铺');
    return;
  }

  const credentials = caShop.apiCredentials as any;
  const { clientId, clientSecret, consumerId, privateKey, channelType } = credentials;

  console.log(`店铺: ${caShop.name}`);
  console.log(`Client ID: ${clientId?.substring(0, 15)}...`);
  console.log(`Consumer ID: ${consumerId?.substring(0, 15)}...`);
  console.log('');

  // 1. 先获取 OAuth Token
  console.log('=== 步骤 1: 获取 OAuth Token ===');
  const authString = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  
  let accessToken: string;
  try {
    const tokenResponse = await axios.post(
      'https://marketplace.walmartapis.com/v3/token',
      'grant_type=client_credentials',
      {
        headers: {
          Authorization: `Basic ${authString}`,
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
          'WM_MARKET': 'ca',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': `token-${Date.now()}`,
          'WM_TENANT_ID': 'WALMART.CA',
          'WM_LOCALE_ID': 'en_CA',
        },
      }
    );
    accessToken = tokenResponse.data.access_token;
    console.log('✅ Token 获取成功');
  } catch (error: any) {
    console.log('❌ Token 获取失败');
    console.log(`错误: ${JSON.stringify(error.response?.data, null, 2)}`);
    return;
  }

  // 2. 测试不同的认证组合
  const baseUrl = 'https://marketplace.walmartapis.com';
  const endpoint = '/v3/ca/feeds';
  const fullUrl = `${baseUrl}${endpoint}`;
  const timestamp = Date.now();

  // 构建测试 Feed 数据
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
          sku: 'TEST-CA-COMBINED-DELETE',
          productIdentifiers: { productIdType: 'GTIN', productId: '00000000000000' },
          productName: { en: 'Test Product' },
          brand: { en: 'Test Brand' },
          price: 99.99,
          ShippingWeight: { unit: 'lb', measure: 10 },
          mainImageUrl: 'https://example.com/image.jpg',
        },
        Visible: { Furniture: {} },
      },
    ],
  };

  // 测试组合 1: OAuth Token + 数字签名
  console.log('\n=== 测试组合 1: OAuth Token + 数字签名 ===');
  if (consumerId && privateKey) {
    const signature = generateSignature(consumerId, privateKey, fullUrl, 'POST', timestamp);
    
    const headers1: Record<string, string> = {
      'WM_SEC.ACCESS_TOKEN': accessToken,
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

    const form1 = new FormData();
    form1.append('file', JSON.stringify(feedData), { filename: 'item.json', contentType: 'application/json' });

    try {
      const response = await axios.post(fullUrl, form1, {
        headers: { ...headers1, ...form1.getHeaders() },
        params: { feedType: 'MP_ITEM_INTL' },
      });
      console.log('✅ 成功');
      console.log(`Feed ID: ${response.data.feedId}`);
      return;
    } catch (error: any) {
      console.log(`❌ 失败 - 状态码: ${error.response?.status}`);
    }
  }

  // 测试组合 2: 仅 OAuth Token（使用 clientId 作为 Channel Type）
  console.log('\n=== 测试组合 2: 仅 OAuth Token (clientId as channelType) ===');
  const headers2: Record<string, string> = {
    'WM_SEC.ACCESS_TOKEN': accessToken,
    'WM_CONSUMER.CHANNEL.TYPE': clientId,  // 使用 clientId 作为 Channel Type
    'WM_SVC.NAME': 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID': `test-${Date.now()}`,
    'WM_TENANT_ID': 'WALMART.CA',
    'WM_LOCALE_ID': 'en_CA',
    'WM_MARKET': 'ca',
    Accept: 'application/json',
  };

  const form2 = new FormData();
  form2.append('file', JSON.stringify(feedData), { filename: 'item.json', contentType: 'application/json' });

  try {
    const response = await axios.post(fullUrl, form2, {
      headers: { ...headers2, ...form2.getHeaders() },
      params: { feedType: 'MP_ITEM_INTL' },
    });
    console.log('✅ 成功');
    console.log(`Feed ID: ${response.data.feedId}`);
    return;
  } catch (error: any) {
    console.log(`❌ 失败 - 状态码: ${error.response?.status}`);
    if (error.response?.data?.error) {
      console.log('错误:', JSON.stringify(error.response.data.error, null, 2).substring(0, 500));
    }
  }

  // 测试组合 3: OAuth Token + consumerId 作为 Channel Type
  console.log('\n=== 测试组合 3: OAuth Token (consumerId as channelType) ===');
  const headers3: Record<string, string> = {
    'WM_SEC.ACCESS_TOKEN': accessToken,
    'WM_CONSUMER.CHANNEL.TYPE': consumerId || channelType,
    'WM_SVC.NAME': 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID': `test-${Date.now()}`,
    'WM_TENANT_ID': 'WALMART.CA',
    'WM_LOCALE_ID': 'en_CA',
    'WM_MARKET': 'ca',
    Accept: 'application/json',
  };

  const form3 = new FormData();
  form3.append('file', JSON.stringify(feedData), { filename: 'item.json', contentType: 'application/json' });

  try {
    const response = await axios.post(fullUrl, form3, {
      headers: { ...headers3, ...form3.getHeaders() },
      params: { feedType: 'MP_ITEM_INTL' },
    });
    console.log('✅ 成功');
    console.log(`Feed ID: ${response.data.feedId}`);
  } catch (error: any) {
    console.log(`❌ 失败 - 状态码: ${error.response?.status}`);
    if (error.response?.data?.error) {
      console.log('错误:', JSON.stringify(error.response.data.error, null, 2).substring(0, 500));
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
