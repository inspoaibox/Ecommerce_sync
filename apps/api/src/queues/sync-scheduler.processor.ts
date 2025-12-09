import { Processor, WorkerHost, InjectQueue } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import { Job, Queue } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { QUEUE_NAMES } from './constants';

@Processor(QUEUE_NAMES.SYNC_SCHEDULER)
export class SyncSchedulerProcessor extends WorkerHost {
  private readonly logger = new Logger(SyncSchedulerProcessor.name);

  constructor(
    private prisma: PrismaService,
    @InjectQueue(QUEUE_NAMES.FETCH) private fetchQueue: Queue,
  ) {
    super();
  }

  async process(job: Job): Promise<void> {
    if (job.name === 'check-scheduled') {
      await this.checkScheduledTasks();
    } else if (job.name === 'execute-sync') {
      await this.executeSyncTask(job.data.ruleId, job.data.triggerType);
    }
  }

  private async checkScheduledTasks() {
    const now = new Date();
    const rules = await this.prisma.syncRule.findMany({
      where: {
        status: 'active',
        nextSyncAt: { lte: now },
      },
    });

    this.logger.log(`Found ${rules.length} rules to sync`);

    for (const rule of rules) {
      await this.fetchQueue.add('fetch-products', {
        ruleId: rule.id,
        triggerType: 'scheduled',
      });
    }
  }

  private async executeSyncTask(ruleId: string, triggerType: string) {
    await this.fetchQueue.add('fetch-products', { ruleId, triggerType });
  }
}
