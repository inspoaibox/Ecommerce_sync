import { Module } from '@nestjs/common';
import { UpcController } from './upc.controller';
import { UpcService } from './upc.service';
import { PrismaModule } from '../../common/prisma/prisma.module';

@Module({
  imports: [PrismaModule],
  controllers: [UpcController],
  providers: [UpcService],
  exports: [UpcService],
})
export class UpcModule {}
