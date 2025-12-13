/**
 * 测试 CA 市场数字签名认证
 * 运行: pnpm exec ts-node -r tsconfig-paths/register scripts/test-ca-signature-auth.ts
 * 
 * 参考文档: https://developer.walmart.com/ca-marketplace/docs/authentication
 */

import { PrismaClient } from '@prisma/client';
import axios from 'axios';
import * as crypto from 'crypto';

// eslint-disable-next-line @typescript-eslint/no-var-requires
const FormData = require('form-data');

const prisma = new PrismaClient();

/**
 * 生成数字签名（严格按照 Walmart CA 文档）
 * 
 * 签名格式: consumerId + "\n" + url + "\n" + method + "\n" + timestamp + "\n"
 * 
 * 注意：
 * 1. URL 必须包含完整的查询参数
 * 2. method 必须大写
 * 3. timestamp 是毫秒级 Unix 时间戳
 */
function generateSignature(consumerId: string, privateKey: string, url: string, method: string, timestamp: number): string {
  // 构建签名字符串（注意每个部分后面都有 \n）
  const stringToSign = `${consumerId}\n${url}\n${method}\n${timestamp}\n`;
  
  console.log('签名字符串 (调试):');
  console.log(`  consumerId: ${consumerId}`);
  console.log(`  url: ${url}`);
  console.log(`  method: ${method}`);
  console.log(`  timestamp: ${timestamp}`);
  
  // 处理私钥格式 - 确保是 PKCS#8 格式
  let privateKeyPem = privateKey;
  if (!privateKey.includes('-----BEGIN')) {
    // 如果是纯 Base64 编码的私钥，添加 PEM 头尾
    // 注意：需要每 64 字符换行
    const formattedKey = privateKey.match(/.{1,64}/g)?.join('\n') || privateKey;
    privateKeyPem = `-----BEGIN PRIVATE KEY-----\n${formattedKey}\n-----END PRIVATE KEY-----`;
  }
  
  const sign = crypto.createSign('RSA-SHA256');
  sign.update(stringToSign);
  sign.end();
  
  return sign.sign(privateKeyPem, 'base64');
}

async function main() {
  console.log('=== 测试 CA 市场数字签名认证 ===\n');

  // 查询加拿大店铺
  const caShop = await prisma.shop.findFirst({
    where: { region: 'CA' },
  });

  if (!caShop) {
    console.log('没有找到加拿大店铺');
    return;
  }

  const credentials = caShop.apiCredentials as any;
  const { consumerId, privateKey, channelType } = credentials;

  if (!consumerId || !privateKey) {
    console.log('❌ 缺少 consumerId 或 privateKey');
    console.log(`consumerId: ${consumerId ? '已设置' : '未设置'}`);
    console.log(`privateKey: ${privateKey ? '已设置' : '未设置'}`);
    return;
  }

  console.log(`店铺: ${caShop.name}`);
  console.log(`Consumer ID: ${consumerId.substring(0, 15)}...`);
  console.log(`Channel Type: ${channelType?.substring(0, 15)}...`);
  console.log(`Private Key 长度: ${privateKey.length}`);
  console.log('');

  // 按照文档建议，先用简单的 GET 请求测试（Get All Feed Statuses）
  const baseUrl = 'https://marketplace.walmartapis.com';
  
  // 测试 1: GET /v3/feeds（获取 Feed 状态列表）
  console.log('=== 测试 1: GET /v3/feeds（简单 GET 请求）===');
  const getUrl = `${baseUrl}/v3/feeds`;
  const getTimestamp = Date.now();
  
  let getSignature: string;
  try {
    getSignature = generateSignature(consumerId, privateKey, getUrl, 'GET', getTimestamp);
    console.log(`✅ 签名生成成功: ${getSignature.substring(0, 40)}...`);
  } catch (error: any) {
    console.log(`❌ 签名生成失败: ${error.message}`);
    console.log('错误详情:', error);
    return;
  }

  const getHeaders: Record<string, string> = {
    'WM_SEC.AUTH_SIGNATURE': getSignature,
    'WM_SEC.TIMESTAMP': String(getTimestamp),
    'WM_CONSUMER.ID': consumerId,
    'WM_CONSUMER.CHANNEL.TYPE': channelType,
    'WM_SVC.NAME': 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID': `test-${Date.now()}`,
    'WM_TENANT_ID': 'WALMART.CA',
    'WM_LOCALE_ID': 'en_CA',
    Accept: 'application/json',
  };

  console.log('\n请求头:');
  const headersForLog = { ...getHeaders };
  headersForLog['WM_SEC.AUTH_SIGNATURE'] = headersForLog['WM_SEC.AUTH_SIGNATURE'].substring(0, 30) + '...';
  console.log(JSON.stringify(headersForLog, null, 2));

  try {
    const response = await axios.get(getUrl, { headers: getHeaders });
    console.log('\n✅ GET 请求成功！');
    console.log('响应:', JSON.stringify(response.data, null, 2).substring(0, 500));
  } catch (error: any) {
    console.log(`\n❌ GET 请求失败 - 状态码: ${error.response?.status}`);
    const errData = error.response?.data;
    if (errData) {
      console.log('错误详情:', JSON.stringify(errData, null, 2).substring(0, 800));
    }
  }

  // 测试 2: POST /v3/feeds（提交 Feed）
  console.log('\n\n=== 测试 2: POST /v3/feeds?feedType=MP_ITEM_INTL ===');
  // 注意：URL 必须包含查询参数
  const postUrl = `${baseUrl}/v3/feeds?feedType=MP_ITEM_INTL`;
  const postTimestamp = Date.now();
  
  let postSignature: string;
  try {
    postSignature = generateSignature(consumerId, privateKey, postUrl, 'POST', postTimestamp);
    console.log(`✅ 签名生成成功: ${postSignature.substring(0, 40)}...`);
  } catch (error: any) {
    console.log(`❌ 签名生成失败: ${error.message}`);
    return;
  }

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
          sku: 'TEST-CA-SIG-DELETE',
          productIdentifiers: { productIdType: 'GTIN', productId: '00000000000000' },
          productName: { en: 'Test Product' },
          brand: { en: 'Test' },
          price: 99.99,
          ShippingWeight: { unit: 'lb', measure: 10 },
          mainImageUrl: 'https://example.com/image.jpg',
        },
        Visible: { Furniture: {} },
      },
    ],
  };

  const postHeaders: Record<string, string> = {
    'WM_SEC.AUTH_SIGNATURE': postSignature,
    'WM_SEC.TIMESTAMP': String(postTimestamp),
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

  try {
    // 注意：不使用 params，因为查询参数已经在 URL 中
    const response = await axios.post(postUrl, form, {
      headers: { ...postHeaders, ...form.getHeaders() },
    });
    console.log('\n✅ POST 请求成功！');
    console.log(`Feed ID: ${response.data.feedId}`);
  } catch (error: any) {
    console.log(`\n❌ POST 请求失败 - 状态码: ${error.response?.status}`);
    const errData = error.response?.data;
    if (errData) {
      console.log('错误详情:', JSON.stringify(errData, null, 2).substring(0, 800));
    }
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
