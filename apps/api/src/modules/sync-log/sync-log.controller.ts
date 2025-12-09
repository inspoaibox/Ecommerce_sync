import { Controller, Get, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { SyncLogService } from './sync-log.service';
import { SyncLogQueryDto } from './dto/sync-log.dto';

@ApiTags('同步日志')
@Controller('sync-logs')
export class SyncLogController {
  constructor(private readonly syncLogService: SyncLogService) {}

  @Get()
  @ApiOperation({ summary: '获取同步日志列表' })
  findAll(@Query() query: SyncLogQueryDto) {
    return this.syncLogService.findAll(query);
  }

  @Get('stats')
  @ApiOperation({ summary: '获取同步统计' })
  getStats() {
    return this.syncLogService.getStats();
  }

  @Get(':id')
  @ApiOperation({ summary: '获取日志详情' })
  findOne(@Param('id') id: string) {
    return this.syncLogService.findOne(id);
  }
}
