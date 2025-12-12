import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { PrismaService } from '@/common/prisma/prisma.service';
import { AutoSyncService } from './auto-sync.service';

/**
 * 自动同步调度器
 * 
 * 定时检查 AutoSyncConfig 表中的 nextSyncAt，
 * 当到达同步时间时自动触发同步任务。
 */
@Injectable()
export class AutoSyncSchedulerService implements OnModuleInit {
  private readonly logger = new Logger(AutoSyncSchedulerService.name);
  private isRunning = false;

  constructor(
    private prisma: PrismaService,
    private autoSyncService: AutoSyncService,
  ) {}

  onModuleInit() {
    this.logger.log('AutoSyncScheduler initialized');
  }

  /**
   * 每分钟检查一次是否有需要执行的自动同步任务
   */
  @Cron(CronExpression.EVERY_MINUTE)
  async checkScheduledAutoSync() {
    // 防止重复执行
    if (this.isRunning) {
      return;
    }

    this.isRunning = true;
    try {
      await this.executeScheduledTasks();
    } catch (error: any) {
      this.logger.error(`AutoSync scheduler error: ${error.message}`);
    } finally {
      this.isRunning = false;
    }
  }

  /**
   * 执行到期的自动同步任务
   */
  private async executeScheduledTasks() {
    const now = new Date();

    // 查找所有已启用且到达同步时间的配置
    const configs = await this.prisma.autoSyncConfig.findMany({
      where: {
        enabled: true,
        nextSyncAt: { lte: now },
      },
      include: {
        shop: { select: { id: true, name: true } },
      },
    });

    if (configs.length === 0) {
      return;
    }

    this.logger.log(`Found ${configs.length} auto-sync configs to execute`);

    for (const config of configs) {
      try {
        // 检查是否已有正在运行的任务
        const runningTask = await this.prisma.autoSyncTask.findFirst({
          where: {
            shopId: config.shopId,
            stage: { in: ['fetch_channel', 'update_local', 'push_platform'] },
          },
        });

        if (runningTask) {
          this.logger.warn(`Shop ${config.shop.name} already has a running task, skipping`);
          continue;
        }

        // 触发同步任务
        this.logger.log(`Triggering auto-sync for shop: ${config.shop.name}`);
        await this.autoSyncService.triggerSync(config.shopId, config.syncType);

        // 计算下次同步时间
        const nextSyncAt = new Date();
        nextSyncAt.setDate(nextSyncAt.getDate() + config.intervalDays);
        nextSyncAt.setHours(config.syncHour ?? 8, 0, 0, 0);

        // 更新下次同步时间和最后同步时间
        await this.prisma.autoSyncConfig.update({
          where: { id: config.id },
          data: {
            nextSyncAt,
            lastSyncAt: now,
          },
        });

        this.logger.log(`Shop ${config.shop.name} next sync at: ${nextSyncAt.toISOString()}`);
      } catch (error: any) {
        this.logger.error(`Failed to trigger auto-sync for shop ${config.shop.name}: ${error.message}`);
      }
    }
  }

  /**
   * 手动触发检查（用于测试）
   */
  async manualCheck() {
    this.logger.log('Manual check triggered');
    await this.executeScheduledTasks();
    return { message: 'Check completed' };
  }
}
