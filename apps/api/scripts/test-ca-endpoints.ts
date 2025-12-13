/**
 * 测试 CA 市场不同端点
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-endpoints.ts
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

async function testEndpoint(
  consumerId: string, 
  privateKey: string, 
  channelType: string,
  url: string, 
  method: string,
  description: string
) {
  console.log(`\n--- ${description} ---`);
  console.log(`URL: ${url}`);
  
  const timestamp = Date.now();
  const signature = generateSignature(consumerId, privateKey, url, method, timestamp);
  
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
    const response = method === 'GET' 
      ? await axios.get(url, { headers })
      : await axios.post(url, {}, { headers });
    console.log(`✅ 成功 - 状态码: ${response.status}`);
    return true;
  } catch (error: any) {
    const status = error.response?.status;
    const errCode = error.response?.data?.error?.[0]?.code || '';
    console.log(`❌ 失败 - 状态码: ${status}, 错误: ${errCode}`);
    return false;
  }
}

async function main() {
  console.log('=== 测试 CA 市场不同端点 ===\n');

  const caShop = await prisma.shop.findFirst({ where: { region: 'CA' } });
  if (!caShop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  const credentials = caShop.apiCredentials as any;
  const { consumerId, privateKey, channelType } = credentials;

  if (!consumerId || !privateKey) {
    console.log('缺少 consumerId 或 privateKey');
    return;
  }

  console.log(`Consumer ID: ${consumerId.substring(0, 15)}...`);
  console.log(`Channel Type: ${channelType?.substring(0, 15)}...`);

  const baseUrl = 'https://marketplace.walmartapis.com';
  
  // 测试不同的端点组合
  const endpoints = [
    { url: `${baseUrl}/v3/feeds`, method: 'GET', desc: 'GET /v3/feeds' },
    { url: `${baseUrl}/v3/ca/feeds`, method: 'GET', desc: 'GET /v3/ca/feeds' },
    { url: `${baseUrl}/v3/items`, method: 'GET', desc: 'GET /v3/items' },
    { url: `${baseUrl}/v3/ca/items`, method: 'GET', desc: 'GET /v3/ca/items' },
    { url: `${baseUrl}/v3/items/taxonomy`, method: 'GET', desc: 'GET /v3/items/taxonomy' },
    { url: `${baseUrl}/v3/ca/items/taxonomy`, method: 'GET', desc: 'GET /v3/ca/items/taxonomy' },
  ];

  for (const ep of endpoints) {
    await testEndpoint(consumerId, privateKey, channelType, ep.url, ep.method, ep.desc);
    // 添加延迟避免请求过快
    await new Promise(resolve => setTimeout(resolve, 500));
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
