import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { QUEUE_NAMES } from './constants';
import { SyncSchedulerProcessor } from './sync-scheduler.processor';
import { FetchProcessor } from './fetch.processor';
import { TransformProcessor } from './transform.processor';
import { PushProcessor } from './push.processor';

@Module({
  imports: [
    BullModule.registerQueue(
      { name: QUEUE_NAMES.SYNC_SCHEDULER },
      { name: QUEUE_NAMES.FETCH },
      { name: QUEUE_NAMES.TRANSFORM },
      { name: QUEUE_NAMES.PUSH },
    ),
  ],
  providers: [
    SyncSchedulerProcessor,
    FetchProcessor,
    TransformProcessor,
    PushProcessor,
  ],
})
export class QueuesModule {}
