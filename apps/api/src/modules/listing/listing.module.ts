import { Module } from '@nestjs/common';
import { ListingController } from './listing.controller';
import { ListingService } from './listing.service';
import { ListingLogService } from './listing-log.service';
import { ListingFeedService } from './listing-feed.service';
import { ListingFeedSchedulerService } from './listing-feed-scheduler.service';
import { ChannelModule } from '@/modules/channel/channel.module';
import { UpcModule } from '@/modules/upc/upc.module';
import { AttributeMappingModule } from '@/modules/attribute-mapping/attribute-mapping.module';

@Module({
  imports: [ChannelModule, UpcModule, AttributeMappingModule],
  controllers: [ListingController],
  providers: [ListingService, ListingLogService, ListingFeedService, ListingFeedSchedulerService],
  exports: [ListingService, ListingLogService, ListingFeedService, ListingFeedSchedulerService],
})
export class ListingModule {}
