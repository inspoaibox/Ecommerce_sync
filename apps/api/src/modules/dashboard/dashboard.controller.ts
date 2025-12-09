import { Controller, Get, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { DashboardService } from './dashboard.service';

@ApiTags('仪表盘')
@Controller('dashboard')
export class DashboardController {
  constructor(private readonly dashboardService: DashboardService) {}

  @Get('overview')
  @ApiOperation({ summary: '获取总览数据' })
  getOverview() {
    return this.dashboardService.getOverview();
  }

  @Get('sync-stats')
  @ApiOperation({ summary: '获取同步统计' })
  getSyncStats() {
    return this.dashboardService.getSyncStats();
  }

  @Get('recent-logs')
  @ApiOperation({ summary: '获取最近同步记录' })
  getRecentLogs(@Query('limit') limit?: number) {
    return this.dashboardService.getRecentLogs(limit || 10);
  }
}
