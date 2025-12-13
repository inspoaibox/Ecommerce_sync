/**
 * 测试 CA 市场 Feed 提交
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-feed-submit.ts
 */

import { PrismaClient } from '@prisma/client';
import axios from 'axios';

// eslint-disable-next-line @typescript-eslint/no-var-requires
const FormData = require('form-data');

const prisma = new PrismaClient();

async function main() {
  console.log('=== 测试 CA 市场 Feed 提交 ===\n');

  // 查询加拿大店铺
  const caShop = await prisma.shop.findFirst({
    where: { region: 'CA' },
  });

  if (!caShop) {
    console.log('没有找到加拿大店铺');
    return;
  }

  const credentials = caShop.apiCredentials as any;
  const clientId = credentials.clientId;
  const clientSecret = credentials.clientSecret;
  const channelType = credentials.channelType || clientId;

  console.log(`店铺: ${caShop.name}`);
  console.log(`Client ID: ${clientId.substring(0, 10)}...`);
  console.log(`Channel Type: ${channelType.substring(0, 10)}...`);
  console.log('');

  // 1. 获取 Token
  console.log('=== 步骤 1: 获取 Token ===');
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

  // 2. 构建测试 Feed 数据
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
          sku: 'TEST-SKU-DELETE-ME',
          productIdentifiers: {
            productIdType: 'GTIN',
            productId: '00000000000000',
          },
          productName: { en: 'Test Product - Please Delete' },
          brand: { en: 'Test Brand' },
          price: 99.99,
          ShippingWeight: { unit: 'lb', measure: 10 },
          mainImageUrl: 'https://example.com/image.jpg',
        },
        Visible: {
          Furniture: {},
        },
      },
    ],
  };

  console.log('\n=== 步骤 2: 测试不同的 Channel Type ===');

  // 测试不同的 Channel Type 值
  // 注意：CA 市场的 Channel Type 可能需要在 Walmart Developer Portal 单独申请
  const channelTypeOptions = [
    { value: channelType, desc: 'channelType from credentials' },
    { value: clientId, desc: 'clientId (CA)' },
    // 尝试使用 US 店铺的 clientId（如果 CA 和 US 共享同一个 Solution Provider 账号）
    { value: '5cb142fb-e985-4e0e-8e0e-8e0e8e0e8e0e', desc: 'US clientId (test)' },
  ];

  for (const ctOpt of channelTypeOptions) {
    console.log(`\n--- 测试 Channel Type: ${ctOpt.desc} ---`);

    const headers: Record<string, string> = {
      'WM_SEC.ACCESS_TOKEN': accessToken,
      'WM_MARKET': 'ca',
      'WM_SVC.NAME': 'Walmart Marketplace',
      'WM_QOS.CORRELATION_ID': `feed-${Date.now()}`,
      'WM_CONSUMER.CHANNEL.TYPE': ctOpt.value,
      'WM_TENANT_ID': 'WALMART.CA',
      'WM_LOCALE_ID': 'en_CA',
      Accept: 'application/json',
    };

    const form = new FormData();
    form.append('file', JSON.stringify(feedData), {
      filename: 'item.json',
      contentType: 'application/json',
    });

    try {
      const response = await axios.post(
        'https://marketplace.walmartapis.com/v3/ca/feeds',
        form,
        {
          headers: {
            ...headers,
            ...form.getHeaders(),
          },
          params: { feedType: 'MP_ITEM_INTL' },
        }
      );

      console.log('✅ 成功');
      console.log(`Feed ID: ${response.data.feedId}`);
      return;  // 成功就停止
    } catch (error: any) {
      console.log(`❌ 失败 - 状态码: ${error.response?.status}`);
      const errData = error.response?.data;
      if (errData && typeof errData === 'object' && errData.error) {
        console.log('错误详情:', JSON.stringify(errData.error, null, 2).substring(0, 500));
      }
    }
  }

  // 3. 尝试不带 WM_CONSUMER.CHANNEL.TYPE
  console.log('\n--- 测试: 不带 WM_CONSUMER.CHANNEL.TYPE ---');
  
  const headersNoChannel: Record<string, string> = {
    'WM_SEC.ACCESS_TOKEN': accessToken,
    'WM_MARKET': 'ca',
    'WM_SVC.NAME': 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID': `feed-${Date.now()}`,
    'WM_TENANT_ID': 'WALMART.CA',
    'WM_LOCALE_ID': 'en_CA',
    Accept: 'application/json',
  };

  const form2 = new FormData();
  form2.append('file', JSON.stringify(feedData), {
    filename: 'item.json',
    contentType: 'application/json',
  });

  try {
    const response = await axios.post(
      'https://marketplace.walmartapis.com/v3/ca/feeds',
      form2,
      {
        headers: {
          ...headersNoChannel,
          ...form2.getHeaders(),
        },
        params: { feedType: 'MP_ITEM_INTL' },
      }
    );

    console.log('✅ 成功');
    console.log(`Feed ID: ${response.data.feedId}`);
  } catch (error: any) {
    console.log(`❌ 失败 - 状态码: ${error.response?.status}`);
    const errData = error.response?.data;
    if (errData && typeof errData === 'object' && errData.error) {
      console.log('错误详情:', JSON.stringify(errData.error, null, 2).substring(0, 500));
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
