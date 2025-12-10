import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { AutoSyncController } from './auto-sync.controller';
import { AutoSyncService } from './auto-sync.service';
import { AutoSyncProcessor } from './auto-sync.processor';
import { ShopModule } from '@/modules/shop/shop.module';
import { QUEUE_NAMES } from '@/queues/constants';

@Module({
  imports: [
    BullModule.registerQueue({
      name: QUEUE_NAMES.AUTO_SYNC,
    }),
    ShopModule,
  ],
  controllers: [AutoSyncController],
  providers: [AutoSyncService, AutoSyncProcessor],
  exports: [AutoSyncService],
})
export class AutoSyncModule {}
