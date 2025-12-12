import { Module } from '@nestjs/common';
import { UnavailablePlatformController } from './unavailable-platform.controller';
import { UnavailablePlatformService } from './unavailable-platform.service';
import { PrismaModule } from '@/common/prisma/prisma.module';

@Module({
  imports: [PrismaModule],
  controllers: [UnavailablePlatformController],
  providers: [UnavailablePlatformService],
  exports: [UnavailablePlatformService],
})
export class UnavailablePlatformModule {}
