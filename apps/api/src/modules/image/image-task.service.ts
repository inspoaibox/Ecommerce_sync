import { Injectable, Logger, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { ImageService } from './image.service';
import { ImageProcessOptions } from './dto/image.dto';

@Injectable()
export class ImageTaskService {
  private readonly logger = new Logger(ImageTaskService.name);
  private runningTasks = new Map<string, { paused: boolean; cancelled: boolean }>();

  constructor(
    private prisma: PrismaService,
    private imageService: ImageService,
  ) {}

  /**
   * 获取或创建默认配置
   */
  async getDefaultConfig() {
    let config = await this.prisma.imageProcessConfig.findFirst({
      where: { isDefault: true },
    });

    if (!config) {
      config = await this.prisma.imageProcessConfig.create({
        data: {
          name: '默认配置',
          maxSizeMB: 5,
          forceSquare: true,
          targetWidth: 2000,
          outputFormat: 'jpeg',
          quality: 85,
          isDefault: true,
        },
      });
    }

    return config;
  }

  /**
   * 获取配置列表
   */
  async listConfigs() {
    return this.prisma.imageProcessConfig.findMany({
      orderBy: { createdAt: 'desc' },
    });
  }

  /**
   * 创建或更新配置
   */
  async saveConfig(data: {
    id?: string;
    name: string;
    maxSizeMB: number;
    forceSquare: boolean;
    targetWidth: number;
    quality: number;
    isDefault?: boolean;
  }) {
    if (data.isDefault) {
      // 取消其他默认配置
      await this.prisma.imageProcessConfig.updateMany({
        where: { isDefault: true },
        data: { isDefault: false },
      });
    }

    if (data.id) {
      return this.prisma.imageProcessConfig.update({
        where: { id: data.id },
        data: {
          name: data.name,
          maxSizeMB: data.maxSizeMB,
          forceSquare: data.forceSquare,
          targetWidth: data.targetWidth,
          quality: data.quality,
          isDefault: data.isDefault,
        },
      });
    }

    return this.prisma.imageProcessConfig.create({
      data: {
        name: data.name,
        maxSizeMB: data.maxSizeMB,
        forceSquare: data.forceSquare,
        targetWidth: data.targetWidth,
        outputFormat: 'jpeg',
        quality: data.quality,
        isDefault: data.isDefault || false,
      },
    });
  }

  /**
   * 删除配置
   */
  async deleteConfig(id: string) {
    const config = await this.prisma.imageProcessConfig.findUnique({ where: { id } });
    if (!config) throw new NotFoundException('配置不存在');
    if (config.isDefault) throw new Error('不能删除默认配置');
    
    await this.prisma.imageProcessConfig.delete({ where: { id } });
    return { success: true };
  }

  /**
   * 创建处理任务
   */
  async createTask(data: {
    name: string;
    scope: 'all' | 'category' | 'sku_list';
    scopeValue?: string;
    configId?: string;
  }) {
    // 获取配置
    const config = data.configId
      ? await this.prisma.imageProcessConfig.findUnique({ where: { id: data.configId } })
      : await this.getDefaultConfig();

    if (!config) throw new NotFoundException('配置不存在');

    // 计算商品数量
    let totalCount = 0;
    if (data.scope === 'all') {
      totalCount = await this.prisma.productPool.count();
    } else if (data.scope === 'category' && data.scopeValue) {
      totalCount = await this.prisma.productPool.count({
        where: { platformCategoryId: data.scopeValue },
      });
    } else if (data.scope === 'sku_list' && data.scopeValue) {
      const skus = JSON.parse(data.scopeValue) as string[];
      totalCount = await this.prisma.productPool.count({
        where: { sku: { in: skus } },
      });
    }

    return this.prisma.imageProcessTask.create({
      data: {
        name: data.name,
        scope: data.scope,
        scopeValue: data.scopeValue,
        configId: config.id,
        totalCount,
        status: 'pending',
      },
      include: { config: true },
    });
  }

  /**
   * 获取任务列表
   */
  async listTasks(params: { page?: number; pageSize?: number; status?: string }) {
    const { page = 1, pageSize = 20, status } = params;
    const where: any = {};
    if (status) where.status = status;

    const [data, total] = await Promise.all([
      this.prisma.imageProcessTask.findMany({
        where,
        include: { config: true },
        orderBy: { createdAt: 'desc' },
        skip: (page - 1) * pageSize,
        take: pageSize,
      }),
      this.prisma.imageProcessTask.count({ where }),
    ]);

    return { data, total, page, pageSize };
  }

  /**
   * 获取任务详情
   */
  async getTask(id: string) {
    const task = await this.prisma.imageProcessTask.findUnique({
      where: { id },
      include: { config: true },
    });
    if (!task) throw new NotFoundException('任务不存在');
    return task;
  }

  /**
   * 获取任务日志
   */
  async getTaskLogs(taskId: string, params: { page?: number; pageSize?: number; status?: string }) {
    const { page = 1, pageSize = 50, status } = params;
    const where: any = { taskId };
    if (status) where.status = status;

    const [data, total] = await Promise.all([
      this.prisma.imageProcessLog.findMany({
        where,
        orderBy: { createdAt: 'desc' },
        skip: (page - 1) * pageSize,
        take: pageSize,
      }),
      this.prisma.imageProcessLog.count({ where }),
    ]);

    return { data, total, page, pageSize };
  }


  /**
   * 开始任务
   */
  async startTask(id: string) {
    const task = await this.getTask(id);
    if (task.status === 'running') {
      throw new Error('任务已在运行中');
    }

    // 初始化任务控制
    this.runningTasks.set(id, { paused: false, cancelled: false });

    // 更新状态
    await this.prisma.imageProcessTask.update({
      where: { id },
      data: { status: 'running', startedAt: new Date() },
    });

    // 异步执行任务
    this.executeTask(id).catch(err => {
      this.logger.error(`Task ${id} failed:`, err);
    });

    return { success: true, message: '任务已开始' };
  }

  /**
   * 暂停任务
   */
  async pauseTask(id: string) {
    const control = this.runningTasks.get(id);
    if (!control) throw new Error('任务未在运行');
    
    control.paused = true;
    await this.prisma.imageProcessTask.update({
      where: { id },
      data: { status: 'paused' },
    });

    return { success: true, message: '任务已暂停' };
  }

  /**
   * 继续任务
   */
  async resumeTask(id: string) {
    const task = await this.getTask(id);
    if (task.status !== 'paused') {
      throw new Error('任务未暂停');
    }

    const control = this.runningTasks.get(id);
    if (control) {
      control.paused = false;
    } else {
      // 重新开始
      return this.startTask(id);
    }

    await this.prisma.imageProcessTask.update({
      where: { id },
      data: { status: 'running' },
    });

    return { success: true, message: '任务已继续' };
  }

  /**
   * 取消任务
   */
  async cancelTask(id: string) {
    const control = this.runningTasks.get(id);
    if (control) {
      control.cancelled = true;
    }

    await this.prisma.imageProcessTask.update({
      where: { id },
      data: { status: 'cancelled', finishedAt: new Date() },
    });

    this.runningTasks.delete(id);
    return { success: true, message: '任务已取消' };
  }

  /**
   * 重试失败的图片
   */
  async retryFailed(id: string) {
    const task = await this.getTask(id);
    
    // 重置失败的日志状态
    await this.prisma.imageProcessLog.updateMany({
      where: { taskId: id, status: 'failed' },
      data: { status: 'pending' },
    });

    // 更新任务统计
    const failCount = await this.prisma.imageProcessLog.count({
      where: { taskId: id, status: 'pending' },
    });

    await this.prisma.imageProcessTask.update({
      where: { id },
      data: {
        status: 'pending',
        failCount: 0,
        processedCount: task.processedCount - failCount,
      },
    });

    return this.startTask(id);
  }

  /**
   * 执行任务（内部方法）
   */
  private async executeTask(taskId: string) {
    const task = await this.getTask(taskId);
    const config = task.config;
    if (!config) {
      await this.prisma.imageProcessTask.update({
        where: { id: taskId },
        data: { status: 'failed', errorMessage: '配置不存在' },
      });
      return;
    }

    // 获取商品列表
    let products: any[] = [];
    if (task.scope === 'all') {
      products = await this.prisma.productPool.findMany({
        select: { id: true, sku: true, mainImageUrl: true, imageUrls: true },
      });
    } else if (task.scope === 'category' && task.scopeValue) {
      products = await this.prisma.productPool.findMany({
        where: { platformCategoryId: task.scopeValue },
        select: { id: true, sku: true, mainImageUrl: true, imageUrls: true },
      });
    } else if (task.scope === 'sku_list' && task.scopeValue) {
      const skus = JSON.parse(task.scopeValue) as string[];
      products = await this.prisma.productPool.findMany({
        where: { sku: { in: skus } },
        select: { id: true, sku: true, mainImageUrl: true, imageUrls: true },
      });
    }

    const options: ImageProcessOptions = {
      maxSizeMB: Number(config.maxSizeMB),
      forceSquare: config.forceSquare,
      targetWidth: config.targetWidth || 2000,
      quality: config.quality,
      outputFormat: (config.outputFormat as any) || 'jpeg',
    };

    let processedCount = 0;
    let successCount = 0;
    let failCount = 0;
    let skippedCount = 0;

    for (const product of products) {
      const control = this.runningTasks.get(taskId);
      
      // 检查是否取消
      if (control?.cancelled) {
        break;
      }

      // 检查是否暂停
      while (control?.paused) {
        await new Promise(resolve => setTimeout(resolve, 1000));
        if (control.cancelled) break;
      }

      // 处理主图
      if (product.mainImageUrl) {
        const result = await this.processAndLogImage(
          taskId,
          product.id,
          product.sku,
          'main',
          0,
          product.mainImageUrl,
          options,
        );
        
        if (result.success) {
          if (result.wasProcessed) successCount++;
          else skippedCount++;
          
          // 更新商品主图
          if (result.wasProcessed) {
            await this.prisma.productPool.update({
              where: { id: product.id },
              data: { mainImageUrl: result.processedUrl },
            });
          }
        } else {
          failCount++;
        }
      }

      // 处理附图
      const imageUrls = (product.imageUrls as string[]) || [];
      const newImageUrls: string[] = [];
      
      for (let i = 0; i < imageUrls.length; i++) {
        const result = await this.processAndLogImage(
          taskId,
          product.id,
          product.sku,
          'additional',
          i,
          imageUrls[i],
          options,
        );

        if (result.success) {
          if (result.wasProcessed) successCount++;
          else skippedCount++;
          newImageUrls.push(result.processedUrl);
        } else {
          failCount++;
          newImageUrls.push(imageUrls[i]); // 保留原URL
        }
      }

      // 更新附图
      if (newImageUrls.length > 0) {
        await this.prisma.productPool.update({
          where: { id: product.id },
          data: { imageUrls: newImageUrls },
        });
      }

      processedCount++;

      // 更新任务进度
      await this.prisma.imageProcessTask.update({
        where: { id: taskId },
        data: { processedCount, successCount, failCount, skippedCount },
      });
    }

    // 完成任务
    const finalStatus = this.runningTasks.get(taskId)?.cancelled ? 'cancelled' : 'completed';
    await this.prisma.imageProcessTask.update({
      where: { id: taskId },
      data: {
        status: finalStatus,
        finishedAt: new Date(),
        processedCount,
        successCount,
        failCount,
        skippedCount,
      },
    });

    this.runningTasks.delete(taskId);
    this.logger.log(`Task ${taskId} ${finalStatus}: processed=${processedCount}, success=${successCount}, failed=${failCount}, skipped=${skippedCount}`);
  }

  /**
   * 处理单张图片并记录日志
   */
  private async processAndLogImage(
    taskId: string,
    productPoolId: string,
    productSku: string,
    imageType: string,
    imageIndex: number,
    originalUrl: string,
    options: ImageProcessOptions,
  ): Promise<{ success: boolean; wasProcessed: boolean; processedUrl: string }> {
    // 创建日志
    const log = await this.prisma.imageProcessLog.create({
      data: {
        taskId,
        productPoolId,
        productSku,
        imageType,
        imageIndex,
        originalUrl,
        status: 'processing',
      },
    });

    try {
      const result = await this.imageService.processImage(originalUrl, options);

      await this.prisma.imageProcessLog.update({
        where: { id: log.id },
        data: {
          originalSize: result.original.fileSize,
          originalWidth: result.original.width,
          originalHeight: result.original.height,
          processedUrl: result.processedUrl,
          processedSize: result.processed?.fileSize,
          processedWidth: result.processed?.width,
          processedHeight: result.processed?.height,
          operations: result.operations,
          wasProcessed: result.wasProcessed,
          status: result.success ? (result.wasProcessed ? 'success' : 'skipped') : 'failed',
          errorMessage: result.error,
        },
      });

      return {
        success: result.success,
        wasProcessed: result.wasProcessed,
        processedUrl: result.processedUrl,
      };
    } catch (error: any) {
      await this.prisma.imageProcessLog.update({
        where: { id: log.id },
        data: {
          status: 'failed',
          errorMessage: error.message,
        },
      });

      return {
        success: false,
        wasProcessed: false,
        processedUrl: originalUrl,
      };
    }
  }
}
