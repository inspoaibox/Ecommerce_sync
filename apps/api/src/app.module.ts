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
import { QueuesModule } from './queues/queues.module';

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
    ChannelModule,
    PlatformModule,
    ShopModule,
    SyncRuleModule,
    ProductModule,
    SyncLogModule,
    DashboardModule,
    AutoSyncModule,
    QueuesModule,
  ],
})
export class AppModule {}
