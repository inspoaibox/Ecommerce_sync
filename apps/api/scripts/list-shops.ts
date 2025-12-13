/**
 * 列出所有店铺
 */
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const shops = await prisma.shop.findMany({
    include: { platform: { select: { name: true, code: true } } },
    orderBy: { region: 'asc' },
  });

  console.log(`共 ${shops.length} 个店铺:\n`);

  for (const shop of shops) {
    const creds = shop.apiCredentials as any;
    console.log(`${shop.name} (${shop.region})`);
    console.log(`  平台: ${shop.platform?.name}`);
    console.log(`  clientId: ${creds?.clientId?.substring(0, 15)}...`);
    console.log(`  channelType: ${creds?.channelType?.substring(0, 15) || '(未设置)'}...`);
    console.log('');
  }
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
