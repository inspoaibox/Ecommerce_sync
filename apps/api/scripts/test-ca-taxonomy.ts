/**
 * 测试加拿大市场 Taxonomy API
 * 用于确认正确的类目数据和 ID 格式
 */

import axios from 'axios';

async function testCATaxonomy() {
  // 从环境变量或直接配置获取凭证
  const clientId = process.env.WALMART_CA_CLIENT_ID || '';
  const clientSecret = process.env.WALMART_CA_CLIENT_SECRET || '';
  const channelType = process.env.WALMART_CA_CHANNEL_TYPE || clientId;

  if (!clientId || !clientSecret) {
    console.error('请设置环境变量: WALMART_CA_CLIENT_ID, WALMART_CA_CLIENT_SECRET');
    console.log('示例: WALMART_CA_CLIENT_ID=xxx WALMART_CA_CLIENT_SECRET=xxx npx ts-node scripts/test-ca-taxonomy.ts');
    process.exit(1);
  }

  const baseURL = 'https://marketplace.walmartapis.com';

  console.log('=== 测试加拿大市场 Taxonomy API ===\n');
  console.log('Client ID:', clientId.substring(0, 8) + '...');
  console.log('Channel Type:', channelType.substring(0, 8) + '...');

  // Step 1: 获取 Access Token
  console.log('\n--- Step 1: 获取 Access Token ---');
  
  const authString = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');
  
  let accessToken: string;
  try {
    const tokenResponse = await axios.post(
      `${baseURL}/v3/token`,
      'grant_type=client_credentials',
      {
        headers: {
          'Authorization': `Basic ${authString}`,
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
          'WM_MARKET': 'ca',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': crypto.randomUUID(),
        },
      }
    );
    accessToken = tokenResponse.data.access_token;
    console.log('✅ Token 获取成功');
  } catch (error: any) {
    console.error('❌ Token 获取失败:', error.response?.data || error.message);
    process.exit(1);
  }

  // Step 2: 测试不同的 Taxonomy API 端点
  const endpoints = [
    { name: 'GET /v3/items/taxonomy (无前缀)', url: '/v3/items/taxonomy', method: 'GET' },
    { name: 'GET /v3/items/taxonomy?version=5.0', url: '/v3/items/taxonomy?version=5.0', method: 'GET' },
    { name: 'GET /v3/ca/items/taxonomy (带 ca 前缀)', url: '/v3/ca/items/taxonomy', method: 'GET' },
    { name: 'GET /v3/ca/items/taxonomy?version=5.0', url: '/v3/ca/items/taxonomy?version=5.0', method: 'GET' },
    { name: 'GET /v3/ca/items/taxonomy?version=3.16', url: '/v3/ca/items/taxonomy?version=3.16', method: 'GET' },
  ];

  const headers = {
    'WM_SEC.ACCESS_TOKEN': accessToken,
    'WM_MARKET': 'ca',
    'WM_SVC.NAME': 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID': crypto.randomUUID(),
    'WM_CONSUMER.CHANNEL.TYPE': channelType,
    'WM_TENANT_ID': 'WALMART.CA',
    'WM_LOCALE_ID': 'en_CA',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  console.log('\n--- Step 2: 测试 Taxonomy API 端点 ---');
  console.log('Headers:', JSON.stringify(headers, null, 2));

  for (const endpoint of endpoints) {
    console.log(`\n>>> 测试: ${endpoint.name}`);
    try {
      const response = await axios({
        method: endpoint.method,
        url: `${baseURL}${endpoint.url}`,
        headers,
      });

      console.log('✅ 成功!');
      console.log('Response keys:', Object.keys(response.data));
      
      // 解析类目数据
      let categories: any[] = [];
      if (response.data?.payload) {
        categories = response.data.payload;
      } else if (response.data?.itemTaxonomy) {
        categories = response.data.itemTaxonomy;
      } else if (Array.isArray(response.data)) {
        categories = response.data;
      }

      if (categories.length > 0) {
        console.log(`类目数量: ${categories.length}`);
        console.log('前5个类目:');
        categories.slice(0, 5).forEach((cat: any, i: number) => {
          console.log(`  ${i + 1}. ID: ${cat.id || cat.categoryId || cat.category}, Name: ${cat.name || cat.categoryName || cat.category}`);
          if (cat.productTypeGroup || cat.productTypeGroups) {
            const ptgs = cat.productTypeGroup || cat.productTypeGroups || [];
            console.log(`     PTG数量: ${ptgs.length}`);
          }
        });
        
        // 保存完整响应到文件
        const fs = require('fs');
        fs.writeFileSync(
          `ca-taxonomy-response-${endpoint.url.replace(/[^a-z0-9]/gi, '_')}.json`,
          JSON.stringify(response.data, null, 2)
        );
        console.log(`完整响应已保存到文件`);
      } else {
        console.log('响应数据:', JSON.stringify(response.data, null, 2).substring(0, 500));
      }
    } catch (error: any) {
      console.log('❌ 失败:', error.response?.data || error.message);
    }
  }

  // Step 3: 测试 POST 方式获取 taxonomy
  console.log('\n--- Step 3: 测试 POST /v3/items/taxonomy ---');
  
  const postEndpoints = [
    { name: 'POST /v3/items/taxonomy', url: '/v3/items/taxonomy' },
    { name: 'POST /v3/ca/items/taxonomy', url: '/v3/ca/items/taxonomy' },
  ];

  for (const endpoint of postEndpoints) {
    console.log(`\n>>> 测试: ${endpoint.name}`);
    try {
      const response = await axios.post(
        `${baseURL}${endpoint.url}`,
        {
          feedType: 'MP_ITEM_INTL',
          version: '3.16',
        },
        { headers }
      );

      console.log('✅ 成功!');
      console.log('Response keys:', Object.keys(response.data));
      
      const fs = require('fs');
      fs.writeFileSync(
        `ca-taxonomy-post-${endpoint.url.replace(/[^a-z0-9]/gi, '_')}.json`,
        JSON.stringify(response.data, null, 2)
      );
      console.log('完整响应已保存到文件');
    } catch (error: any) {
      console.log('❌ 失败:', error.response?.data || error.message);
    }
  }

  console.log('\n=== 测试完成 ===');
}

testCATaxonomy().catch(console.error);
