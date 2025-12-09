import { Processor, WorkerHost, InjectQueue } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import { Job, Queue } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { ChannelAdapterFactory } from '@/adapters/channels';
import { QUEUE_NAMES } from './constants';
import { TriggerType, SyncType } from '@prisma/client';

@Processor(QUEUE_NAMES.FETCH)
export class FetchProcessor extends WorkerHost {
  private readonly logger = new Logger(FetchProcessor.name);

  constructor(
    private prisma: PrismaService,
    @InjectQueue(QUEUE_NAMES.TRANSFORM) private transformQueue: Queue,
  ) {
    super();
  }

  async process(job: Job): Promise<void> {
    const { ruleId, triggerType } = job.data;

    const rule = await this.prisma.syncRule.findUnique({
      where: { id: ruleId },
      include: { channel: true, shop: true },
    });

    if (!rule) {
      this.logger.error(`Rule ${ruleId} not found`);
      return;
    }

    // 创建同步日志
    const syncLog = await this.prisma.syncLog.create({
      data: {
        syncRuleId: ruleId,
        syncType: rule.syncType,
        triggerType: triggerType as TriggerType,
      },
    });

    try {
      const adapter = ChannelAdapterFactory.create(
        rule.channel.type,
        rule.channel.apiConfig as Record<string, any>,
      );

      const options = rule.syncType === SyncType.incremental && rule.lastSyncAt
        ? { updatedAfter: rule.lastSyncAt }
        : {};

      const result = await adapter.fetchProducts(options);

      this.logger.log(`Fetched ${result.products.length} products for rule ${ruleId}`);

      // 发送到转换队列
      await this.transformQueue.add('transform-products', {
        ruleId,
        syncLogId: syncLog.id,
        products: result.products,
      });
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : 'Unknown error';
      await this.prisma.syncLog.update({
        where: { id: syncLog.id },
        data: { status: 'failed', errorMessage: errorMsg, finishedAt: new Date() },
      });
      throw error;
    }
  }
}
