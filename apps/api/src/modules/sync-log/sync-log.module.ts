import { Module } from '@nestjs/common';
import { SyncLogController } from './sync-log.controller';
import { SyncLogService } from './sync-log.service';

@Module({
  controllers: [SyncLogController],
  providers: [SyncLogService],
  exports: [SyncLogService],
})
export class SyncLogModule {}
