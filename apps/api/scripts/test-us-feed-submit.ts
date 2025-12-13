/**
 * 测试 US 市场 Feed 提交（对比测试）
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-us-feed-submit.ts
 */

import { PrismaClient } from '@prisma/client';
import axios from 'axios';

// eslint-disable-next-line @typescript-eslint/no-var-requires
const FormData = require('form-data');

const prisma = new PrismaClient();

async function main() {
  console.log('=== 测试 US 市场 Feed 提交（对比测试） ===\n');

  // 查询美国店铺（使用美国11号店铺）
  const usShop = await prisma.shop.findFirst({
    where: { region: 'US', name: { contains: '11' } },
  });

  if (!usShop) {
    console.log('没有找到美国店铺');
    return;
  }

  const credentials = usShop.apiCredentials as any;
  const clientId = credentials.clientId;
  const clientSecret = credentials.clientSecret;
  const channelType = credentials.channelType || clientId;

  console.log(`店铺: ${usShop.name}`);
  console.log(`Client ID: ${clientId?.substring(0, 10)}...`);
  console.log(`Channel Type: ${channelType?.substring(0, 10)}...`);
  console.log('');

  if (!clientId || !clientSecret) {
    console.log('缺少 clientId 或 clientSecret');
    return;
  }

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
          'WM_MARKET': 'us',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': `token-${Date.now()}`,
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

  // 2. 测试 Feed API
  console.log('\n=== 步骤 2: 测试 Feed API ===');

  const feedData = {
    MPItemFeedHeader: {
      businessUnit: 'WALMART_US',
      locale: 'en',
      version: '5.0.20241118-04_39_24-api',
    },
    MPItem: [
      {
        Orderable: {
          sku: 'TEST-US-SKU-DELETE-ME',
          productIdentifiers: {
            productIdType: 'GTIN',
            productId: '00000000000000',
          },
          price: 99.99,
          ShippingWeight: { unit: 'lb', measure: 10 },
        },
        Visible: {
          'Living Room Furniture Sets': {
            productName: 'Test Product - Please Delete',
            brand: 'Test Brand',
            mainImageUrl: 'https://example.com/image.jpg',
          },
        },
      },
    ],
  };

  const headers: Record<string, string> = {
    'WM_SEC.ACCESS_TOKEN': accessToken,
    'WM_MARKET': 'us',
    'WM_SVC.NAME': 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID': `feed-${Date.now()}`,
    'WM_CONSUMER.CHANNEL.TYPE': channelType,
    Accept: 'application/json',
  };

  const form = new FormData();
  form.append('file', JSON.stringify(feedData), {
    filename: 'item.json',
    contentType: 'application/json',
  });

  try {
    const response = await axios.post(
      'https://marketplace.walmartapis.com/v3/feeds',
      form,
      {
        headers: {
          ...headers,
          ...form.getHeaders(),
        },
        params: { feedType: 'MP_ITEM' },
      }
    );

    console.log('✅ 成功');
    console.log(`Feed ID: ${response.data.feedId}`);
  } catch (error: any) {
    console.log(`❌ 失败 - 状态码: ${error.response?.status}`);
    const errData = error.response?.data;
    if (errData) {
      console.log('错误详情:', JSON.stringify(errData, null, 2).substring(0, 800));
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
