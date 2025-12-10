import { Module, Global } from '@nestjs/common';
import { OperationLogController } from './operation-log.controller';
import { OperationLogService } from './operation-log.service';

@Global()
@Module({
  controllers: [OperationLogController],
  providers: [OperationLogService],
  exports: [OperationLogService],
})
export class OperationLogModule {}
