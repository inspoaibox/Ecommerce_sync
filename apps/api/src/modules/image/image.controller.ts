import { Controller, Post, Body, Get, Query, Param, Delete } from '@nestjs/common';
import { ImageService } from './image.service';
import { ImageTaskService } from './image-task.service';
import { BatchAnalyzeDto, BatchProcessDto, ProcessImageDto } from './dto/image.dto';

@Controller('image')
export class ImageController {
  constructor(
    private readonly imageService: ImageService,
    private readonly taskService: ImageTaskService,
  ) {}

  // ==================== 图片分析处理 ====================

  @Get('analyze')
  async analyzeImage(
    @Query('url') url: string,
    @Query('maxSizeMB') maxSizeMB?: string,
  ) {
    return this.imageService.analyzeImage(url, maxSizeMB ? parseFloat(maxSizeMB) : 5);
  }

  @Post('analyze/batch')
  async batchAnalyze(@Body() dto: BatchAnalyzeDto) {
    const results = await this.imageService.batchAnalyze(dto.urls, dto.maxSizeMB);
    const summary = {
      total: results.length,
      success: results.filter(r => r.success).length,
      failed: results.filter(r => !r.success).length,
      needsProcessing: results.filter(r => r.needsProcessing).length,
      oversized: results.filter(r => r.fileSizeMB > (dto.maxSizeMB || 5)).length,
      nonSquare: results.filter(r => !r.isSquare).length,
    };
    return { results, summary };
  }

  @Post('process')
  async processImage(@Body() dto: ProcessImageDto) {
    return this.imageService.processImage(dto.url, {
      maxSizeMB: dto.maxSizeMB,
      forceSquare: dto.forceSquare,
      targetWidth: dto.targetWidth,
      quality: dto.quality,
    });
  }

  @Post('process/batch')
  async batchProcess(@Body() dto: BatchProcessDto) {
    const results = await this.imageService.batchProcess(dto.urls, {
      maxSizeMB: dto.maxSizeMB,
      forceSquare: dto.forceSquare,
      targetWidth: dto.targetWidth,
      quality: dto.quality,
    });
    const summary = {
      total: results.length,
      processed: results.filter(r => r.wasProcessed).length,
      skipped: results.filter(r => r.success && !r.wasProcessed).length,
      failed: results.filter(r => !r.success).length,
    };
    return { results, summary };
  }

  @Post('process/product')
  async processProductImages(
    @Body() body: {
      mainImageUrl: string;
      imageUrls: string[];
      maxSizeMB?: number;
      forceSquare?: boolean;
      targetWidth?: number;
      quality?: number;
    },
  ) {
    return this.imageService.processProductImages(body.mainImageUrl, body.imageUrls, {
      maxSizeMB: body.maxSizeMB,
      forceSquare: body.forceSquare,
      targetWidth: body.targetWidth,
      quality: body.quality,
    });
  }

  // ==================== 配置管理 ====================

  @Get('config')
  async listConfigs() {
    return this.taskService.listConfigs();
  }

  @Get('config/default')
  async getDefaultConfig() {
    return this.taskService.getDefaultConfig();
  }

  @Post('config')
  async saveConfig(
    @Body() body: {
      id?: string;
      name: string;
      maxSizeMB: number;
      forceSquare: boolean;
      targetWidth: number;
      quality: number;
      isDefault?: boolean;
    },
  ) {
    return this.taskService.saveConfig(body);
  }

  @Delete('config/:id')
  async deleteConfig(@Param('id') id: string) {
    return this.taskService.deleteConfig(id);
  }

  // ==================== 任务管理 ====================

  @Get('task')
  async listTasks(
    @Query('page') page?: string,
    @Query('pageSize') pageSize?: string,
    @Query('status') status?: string,
  ) {
    return this.taskService.listTasks({
      page: page ? parseInt(page) : 1,
      pageSize: pageSize ? parseInt(pageSize) : 20,
      status,
    });
  }

  @Get('task/:id')
  async getTask(@Param('id') id: string) {
    return this.taskService.getTask(id);
  }

  @Post('task')
  async createTask(
    @Body() body: {
      name: string;
      scope: 'all' | 'category' | 'sku_list';
      scopeValue?: string;
      configId?: string;
    },
  ) {
    return this.taskService.createTask(body);
  }

  @Post('task/:id/start')
  async startTask(@Param('id') id: string) {
    return this.taskService.startTask(id);
  }

  @Post('task/:id/pause')
  async pauseTask(@Param('id') id: string) {
    return this.taskService.pauseTask(id);
  }

  @Post('task/:id/resume')
  async resumeTask(@Param('id') id: string) {
    return this.taskService.resumeTask(id);
  }

  @Post('task/:id/cancel')
  async cancelTask(@Param('id') id: string) {
    return this.taskService.cancelTask(id);
  }

  @Post('task/:id/retry')
  async retryFailed(@Param('id') id: string) {
    return this.taskService.retryFailed(id);
  }

  @Get('task/:id/logs')
  async getTaskLogs(
    @Param('id') id: string,
    @Query('page') page?: string,
    @Query('pageSize') pageSize?: string,
    @Query('status') status?: string,
  ) {
    return this.taskService.getTaskLogs(id, {
      page: page ? parseInt(page) : 1,
      pageSize: pageSize ? parseInt(pageSize) : 50,
      status,
    });
  }
}
