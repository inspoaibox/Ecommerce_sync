/**
 * 检查 CA 店铺凭证
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const shop = await prisma.shop.findFirst({ where: { region: 'CA' } });
  
  if (!shop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  console.log('CA Shop:');
  console.log(`  Name: ${shop.name}`);
  console.log(`  Region: ${shop.region}`);
  console.log('');
  console.log('API Credentials:');
  const creds = shop.apiCredentials as any;
  console.log(`  clientId: ${creds?.clientId}`);
  console.log(`  clientSecret: ${creds?.clientSecret?.substring(0, 10)}...`);
  console.log(`  channelType: ${creds?.channelType}`);
  console.log(`  consumerId: ${creds?.consumerId || '(未设置)'}`);
  console.log(`  privateKey: ${creds?.privateKey ? '(已设置, 长度: ' + creds.privateKey.length + ')' : '(未设置)'}`);
  console.log(`  所有字段: ${Object.keys(creds || {}).join(', ')}`);
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
