import { Module } from '@nestjs/common';
import { ListingController } from './listing.controller';
import { ListingService } from './listing.service';
import { ChannelModule } from '@/modules/channel/channel.module';
import { UpcModule } from '@/modules/upc/upc.module';

@Module({
  imports: [ChannelModule, UpcModule],
  controllers: [ListingController],
  providers: [ListingService],
  exports: [ListingService],
})
export class ListingModule {}
