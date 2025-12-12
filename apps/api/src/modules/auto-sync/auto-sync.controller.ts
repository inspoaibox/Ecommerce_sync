import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { AutoSyncService } from './auto-sync.service';
import { AutoSyncSchedulerService } from './auto-sync-scheduler.service';
import { PaginationDto } from '@/common/dto/pagination.dto';

@ApiTags('自动同步')
@Controller('auto-sync')
export class AutoSyncController {
  constructor(
    private readonly autoSyncService: AutoSyncService,
    private readonly schedulerService: AutoSyncSchedulerService,
  ) {}

  @Get('configs')
  @ApiOperation({ summary: '获取所有自动同步配置' })
  getConfigs() {
    return this.autoSyncService.getConfigs();
  }

  @Get('config/:shopId')
  @ApiOperation({ summary: '获取店铺自动同步配置' })
  getConfig(@Param('shopId') shopId: string) {
    return this.autoSyncService.getConfig(shopId);
  }

  @Put('config/:shopId')
  @ApiOperation({ summary: '更新店铺自动同步配置' })
  updateConfig(
    @Param('shopId') shopId: string,
    @Body() body: { enabled?: boolean; intervalDays?: number; syncType?: string },
  ) {
    return this.autoSyncService.updateConfig(shopId, body);
  }

  @Post('trigger/:shopId')
  @ApiOperation({ summary: '立即触发同步任务' })
  triggerSync(
    @Param('shopId') shopId: string,
    @Body() body: { syncType?: string },
  ) {
    return this.autoSyncService.triggerSync(shopId, body.syncType);
  }

  @Get('tasks')
  @ApiOperation({ summary: '获取同步任务列表' })
  getTasks(@Query() query: PaginationDto & { shopId?: string }) {
    return this.autoSyncService.getTasks(query);
  }

  @Get('task/:taskId')
  @ApiOperation({ summary: '获取任务详情' })
  getTask(@Param('taskId') taskId: string) {
    return this.autoSyncService.getTask(taskId);
  }

  @Post('task/:taskId/cancel')
  @ApiOperation({ summary: '取消任务' })
  cancelTask(@Param('taskId') taskId: string) {
    return this.autoSyncService.cancelTask(taskId);
  }

  @Post('task/:taskId/pause')
  @ApiOperation({ summary: '暂停任务' })
  pauseTask(@Param('taskId') taskId: string) {
    return this.autoSyncService.pauseTask(taskId);
  }

  @Post('task/:taskId/resume')
  @ApiOperation({ summary: '继续任务' })
  resumeTask(@Param('taskId') taskId: string) {
    return this.autoSyncService.resumeTask(taskId);
  }

  @Post('task/:taskId/retry')
  @ApiOperation({ summary: '重试任务' })
  retryTask(@Param('taskId') taskId: string) {
    return this.autoSyncService.retryTask(taskId);
  }

  @Delete('task/:taskId')
  @ApiOperation({ summary: '删除任务' })
  deleteTask(@Param('taskId') taskId: string) {
    return this.autoSyncService.deleteTask(taskId);
  }

  @Post('check-scheduled')
  @ApiOperation({ summary: '手动检查定时任务（测试用）' })
  checkScheduled() {
    return this.schedulerService.manualCheck();
  }
}
