import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { BullModule } from '@nestjs/bullmq';
import { PrismaModule } from './common/prisma/prisma.module';
import { ChannelModule } from './modules/channel/channel.module';
import { PlatformModule } from './modules/platform/platform.module';
import { ShopModule } from './modules/shop/shop.module';
import { SyncRuleModule } from './modules/sync-rule/sync-rule.module';
import { ProductModule } from './modules/product/product.module';
import { SyncLogModule } from './modules/sync-log/sync-log.module';
import { DashboardModule } from './modules/dashboard/dashboard.module';
import { AutoSyncModule } from './modules/auto-sync/auto-sync.module';
import { OperationLogModule } from './modules/operation-log/operation-log.module';
import { QueuesModule } from './queues/queues.module';
import { ListingModule } from './modules/listing/listing.module';
import { PlatformCategoryModule } from './modules/platform-category/platform-category.module';
import { UpcModule } from './modules/upc/upc.module';
import { ProductPoolModule } from './modules/product-pool/product-pool.module';
import { AiModule } from './modules/ai/ai.module';
import { AttributeMappingModule } from './modules/attribute-mapping/attribute-mapping.module';
import { ImageModule } from './modules/image/image.module';
import { UnavailablePlatformModule } from './modules/unavailable-platform/unavailable-platform.module';

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true }),
    BullModule.forRoot({
      connection: {
        host: process.env.REDIS_HOST || 'localhost',
        port: parseInt(process.env.REDIS_PORT || '6379'),
        password: process.env.REDIS_PASSWORD || undefined,
      },
    }),
    PrismaModule,
    OperationLogModule,
    ChannelModule,
    PlatformModule,
    ShopModule,
    SyncRuleModule,
    ProductModule,
    SyncLogModule,
    DashboardModule,
    AutoSyncModule,
    QueuesModule,
    ListingModule,
    PlatformCategoryModule,
    UpcModule,
    ProductPoolModule,
    AiModule,
    AttributeMappingModule,
    ImageModule,
    UnavailablePlatformModule,
  ],
})
export class AppModule {}
