import { Controller, Get, Delete, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { OperationLogService } from './operation-log.service';
import { PaginationDto } from '@/common/dto/pagination.dto';

@ApiTags('操作日志')
@Controller('operation-logs')
export class OperationLogController {
  constructor(private readonly operationLogService: OperationLogService) {}

  @Get()
  @ApiOperation({ summary: '获取操作日志列表' })
  findAll(@Query() query: PaginationDto & { shopId?: string; type?: string }) {
    return this.operationLogService.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: '获取操作日志详情' })
  findOne(@Param('id') id: string) {
    return this.operationLogService.findOne(id);
  }

  @Delete(':id')
  @ApiOperation({ summary: '删除操作日志' })
  remove(@Param('id') id: string) {
    return this.operationLogService.remove(id);
  }
}
