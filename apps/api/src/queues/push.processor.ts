import { Processor, WorkerHost } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import { Job } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { PlatformAdapterFactory } from '@/adapters/platforms';
import { QUEUE_NAMES } from './constants';

@Processor(QUEUE_NAMES.PUSH)
export class PushProcessor extends WorkerHost {
  private readonly logger = new Logger(PushProcessor.name);

  constructor(private prisma: PrismaService) {
    super();
  }

  async process(job: Job): Promise<void> {
    const { ruleId, syncLogId, productIds } = job.data;

    const rule = await this.prisma.syncRule.findUnique({
      where: { id: ruleId },
      include: { shop: { include: { platform: true } } },
    });
    if (!rule) return;

    const products = await this.prisma.product.findMany({
      where: { id: { in: productIds } },
    });

    // 传递 region 以支持多区域
    const adapter = PlatformAdapterFactory.create(
      rule.shop.platform.code,
      { ...(rule.shop.apiCredentials as Record<string, any>), region: rule.shop.region },
    );

    let successCount = 0;
    let failCount = 0;

    for (const product of products) {
      try {
        const result = await adapter.syncProduct({
          sku: product.sku,
          title: product.title,
          price: Number(product.finalPrice),
          stock: product.finalStock,
          currency: product.currency,
          extraFields: product.extraFields as Record<string, any>,
        });

        if (result.success) {
          await this.prisma.product.update({
            where: { id: product.id },
            data: {
              syncStatus: 'synced',
              platformProductId: result.platformProductId,
              lastSyncAt: new Date(),
            },
          });
          successCount++;
        } else {
          await this.prisma.product.update({
            where: { id: product.id },
            data: { syncStatus: 'failed' },
          });
          failCount++;
        }
      } catch (error) {
        failCount++;
        this.logger.error(`Failed to sync product ${product.sku}`, error);
      }
    }

    // 更新同步日志
    const status = failCount === 0 ? 'success' : successCount === 0 ? 'failed' : 'partial';
    await this.prisma.syncLog.update({
      where: { id: syncLogId },
      data: {
        status,
        totalCount: products.length,
        successCount,
        failCount,
        finishedAt: new Date(),
      },
    });

    // 更新规则的下次同步时间
    const nextSyncAt = new Date();
    nextSyncAt.setDate(nextSyncAt.getDate() + rule.intervalDays);
    await this.prisma.syncRule.update({
      where: { id: ruleId },
      data: { lastSyncAt: new Date(), nextSyncAt },
    });

    this.logger.log(`Sync completed: ${successCount} success, ${failCount} failed`);
  }
}
