/**
 * 测试加拿大店铺的 Token 获取
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-token.ts
 */

import { PrismaClient } from '@prisma/client';
import axios from 'axios';

const prisma = new PrismaClient();

async function main() {
  console.log('=== 测试加拿大店铺的 Token 获取 ===\n');

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

  console.log(`店铺: ${caShop.name}`);
  console.log(`Client ID: ${clientId.substring(0, 10)}...`);
  console.log('');

  const authString = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  const correlationId = `test-${Date.now()}`;

  // 测试 1: 使用 /v3/token（当前代码的方式）
  console.log('=== 测试 1: /v3/token + WM_MARKET=ca ===');
  try {
    const response1 = await axios.post(
      'https://marketplace.walmartapis.com/v3/token',
      'grant_type=client_credentials',
      {
        headers: {
          Authorization: `Basic ${authString}`,
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
          'WM_MARKET': 'ca',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': correlationId,
        },
      }
    );
    console.log('✅ 成功获取 Token');
    console.log(`Token: ${response1.data.access_token.substring(0, 20)}...`);
    console.log(`Expires in: ${response1.data.expires_in} seconds`);
  } catch (error: any) {
    console.log('❌ 获取 Token 失败');
    console.log(`状态码: ${error.response?.status}`);
    console.log(`错误: ${JSON.stringify(error.response?.data, null, 2)}`);
  }

  console.log('');

  // 测试 2: 使用 /v3/ca/token（带路径前缀）
  console.log('=== 测试 2: /v3/ca/token ===');
  try {
    const response2 = await axios.post(
      'https://marketplace.walmartapis.com/v3/ca/token',
      'grant_type=client_credentials',
      {
        headers: {
          Authorization: `Basic ${authString}`,
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
          'WM_MARKET': 'ca',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': correlationId,
        },
      }
    );
    console.log('✅ 成功获取 Token');
    console.log(`Token: ${response2.data.access_token.substring(0, 20)}...`);
    console.log(`Expires in: ${response2.data.expires_in} seconds`);
  } catch (error: any) {
    console.log('❌ 获取 Token 失败');
    console.log(`状态码: ${error.response?.status}`);
    console.log(`错误: ${JSON.stringify(error.response?.data, null, 2)}`);
  }

  console.log('');

  // 测试 3: 使用 /v3/token 但添加 WM_TENANT_ID 和 WM_LOCALE_ID
  console.log('=== 测试 3: /v3/token + WM_TENANT_ID + WM_LOCALE_ID ===');
  try {
    const response3 = await axios.post(
      'https://marketplace.walmartapis.com/v3/token',
      'grant_type=client_credentials',
      {
        headers: {
          Authorization: `Basic ${authString}`,
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
          'WM_MARKET': 'ca',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': correlationId,
          'WM_TENANT_ID': 'WALMART.CA',
          'WM_LOCALE_ID': 'en_CA',
        },
      }
    );
    console.log('✅ 成功获取 Token');
    console.log(`Token: ${response3.data.access_token.substring(0, 20)}...`);
    console.log(`Expires in: ${response3.data.expires_in} seconds`);
  } catch (error: any) {
    console.log('❌ 获取 Token 失败');
    console.log(`状态码: ${error.response?.status}`);
    console.log(`错误: ${JSON.stringify(error.response?.data, null, 2)}`);
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
