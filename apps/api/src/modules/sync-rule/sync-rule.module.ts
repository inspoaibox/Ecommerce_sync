import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { SyncRuleController } from './sync-rule.controller';
import { SyncRuleService } from './sync-rule.service';
import { QUEUE_NAMES } from '@/queues/constants';

@Module({
  imports: [
    BullModule.registerQueue({ name: QUEUE_NAMES.SYNC_SCHEDULER }),
  ],
  controllers: [SyncRuleController],
  providers: [SyncRuleService],
  exports: [SyncRuleService],
})
export class SyncRuleModule {}
