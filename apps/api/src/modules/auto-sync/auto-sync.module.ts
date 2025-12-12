import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { ScheduleModule } from '@nestjs/schedule';
import { AutoSyncController } from './auto-sync.controller';
import { AutoSyncService } from './auto-sync.service';
import { AutoSyncProcessor } from './auto-sync.processor';
import { AutoSyncSchedulerService } from './auto-sync-scheduler.service';
import { ShopModule } from '@/modules/shop/shop.module';
import { QUEUE_NAMES } from '@/queues/constants';

@Module({
  imports: [
    ScheduleModule.forRoot(),
    BullModule.registerQueue({
      name: QUEUE_NAMES.AUTO_SYNC,
    }),
    ShopModule,
  ],
  controllers: [AutoSyncController],
  providers: [AutoSyncService, AutoSyncProcessor, AutoSyncSchedulerService],
  exports: [AutoSyncService],
})
export class AutoSyncModule {}
