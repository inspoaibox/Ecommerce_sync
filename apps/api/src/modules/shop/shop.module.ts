import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { ShopController } from './shop.controller';
import { ShopService } from './shop.service';
import { ShopSyncProcessor } from '@/queues/shop-sync.processor';
import { QUEUE_NAMES } from '@/queues/constants';

@Module({
  imports: [BullModule.registerQueue({ name: QUEUE_NAMES.SHOP_SYNC })],
  controllers: [ShopController],
  providers: [ShopService, ShopSyncProcessor],
  exports: [ShopService],
})
export class ShopModule {}
