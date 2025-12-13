/**
 * 调试 CA 店铺私钥格式
 */
import { PrismaClient } from '@prisma/client';
import * as crypto from 'crypto';

const prisma = new PrismaClient();

async function main() {
  const caShop = await prisma.shop.findFirst({ where: { region: 'CA' } });
  if (!caShop) {
    console.log('没有找到 CA 店铺');
    return;
  }

  const credentials = caShop.apiCredentials as any;
  const { privateKey } = credentials;

  console.log('=== 私钥分析 ===');
  console.log(`长度: ${privateKey?.length}`);
  console.log(`包含 BEGIN: ${privateKey?.includes('-----BEGIN')}`);
  console.log(`包含换行: ${privateKey?.includes('\n')}`);
  console.log('');
  console.log('前 100 字符:');
  console.log(privateKey?.substring(0, 100));
  console.log('');
  console.log('后 100 字符:');
  console.log(privateKey?.substring(privateKey.length - 100));

  // 尝试解析私钥
  console.log('\n=== 尝试解析私钥 ===');
  try {
    // 方式 1: 直接使用
    if (privateKey.includes('-----BEGIN')) {
      const key1 = crypto.createPrivateKey(privateKey);
      console.log('✅ 方式 1 (直接使用) 成功');
      console.log(`  类型: ${key1.asymmetricKeyType}`);
    }
  } catch (e: any) {
    console.log(`❌ 方式 1 失败: ${e.message}`);
  }

  try {
    // 方式 2: 添加 PEM 头尾
    const formattedKey = privateKey.match(/.{1,64}/g)?.join('\n') || privateKey;
    const pem = `-----BEGIN PRIVATE KEY-----\n${formattedKey}\n-----END PRIVATE KEY-----`;
    const key2 = crypto.createPrivateKey(pem);
    console.log('✅ 方式 2 (添加 PEM 头尾) 成功');
    console.log(`  类型: ${key2.asymmetricKeyType}`);
  } catch (e: any) {
    console.log(`❌ 方式 2 失败: ${e.message}`);
  }

  try {
    // 方式 3: RSA PRIVATE KEY 格式
    const formattedKey = privateKey.match(/.{1,64}/g)?.join('\n') || privateKey;
    const pem = `-----BEGIN RSA PRIVATE KEY-----\n${formattedKey}\n-----END RSA PRIVATE KEY-----`;
    const key3 = crypto.createPrivateKey(pem);
    console.log('✅ 方式 3 (RSA PRIVATE KEY) 成功');
    console.log(`  类型: ${key3.asymmetricKeyType}`);
  } catch (e: any) {
    console.log(`❌ 方式 3 失败: ${e.message}`);
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
