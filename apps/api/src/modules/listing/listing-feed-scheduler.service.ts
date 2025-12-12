import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { PrismaService } from '@/common/prisma/prisma.service';
import { ListingService } from './listing.service';

/**
 * Feed 状态轮询调度器
 * 自动检查未完成的 Feed 记录并更新状态
 */
@Injectable()
export class ListingFeedSchedulerService implements OnModuleInit {
  private readonly logger = new Logger(ListingFeedSchedulerService.name);
  private isProcessing = false;

  constructor(
    private prisma: PrismaService,
    private listingService: ListingService,
  ) {}

  onModuleInit() {
    this.logger.log('ListingFeedScheduler initialized');
  }

  /**
   * 每分钟检查一次待处理的 Feed
   */
  @Cron(CronExpression.EVERY_MINUTE)
  async checkPendingFeeds() {
    if (this.isProcessing) {
      this.logger.debug('Feed status check already in progress, skipping...');
      return;
    }

    this.isProcessing = true;
    try {
      await this.processPendingFeeds();
    } catch (error: any) {
      this.logger.error(`Feed status check failed: ${error.message}`);
    } finally {
      this.isProcessing = false;
    }
  }

  /**
   * 处理所有待处理的 Feed
   */
  private async processPendingFeeds() {
    // 查找所有未完成的 Feed 记录（状态为 RECEIVED 或 INPROGRESS）
    const pendingFeeds = await this.prisma.listingFeedRecord.findMany({
      where: {
        status: { in: ['RECEIVED', 'INPROGRESS'] },
        // 只处理最近 24 小时内的 Feed（避免处理过旧的记录）
        createdAt: { gte: new Date(Date.now() - 24 * 60 * 60 * 1000) },
      },
      include: {
        shop: { include: { platform: true } },
      },
      orderBy: { createdAt: 'asc' },
      take: 10, // 每次最多处理 10 个
    });

    if (pendingFeeds.length === 0) {
      return;
    }

    this.logger.log(`Found ${pendingFeeds.length} pending feeds to check`);

    for (const feed of pendingFeeds) {
      try {
        // 只处理 Walmart 平台的 Feed
        if (feed.shop.platform?.code !== 'walmart') {
          this.logger.debug(`Skipping non-Walmart feed: ${feed.feedId}`);
          continue;
        }

        this.logger.log(`Checking feed status: ${feed.feedId}`);
        
        // 调用 refreshFeedStatus 更新状态
        const result = await this.listingService.refreshFeedStatus(feed.id);
        
        this.logger.log(
          `Feed ${feed.feedId} status updated: ${result.status}, ` +
          `success: ${result.itemsSucceeded}, failed: ${result.itemsFailed}`
        );

        // 如果处理完成，记录日志
        if (result.status === 'PROCESSED' || result.status === 'ERROR') {
          this.logger.log(`Feed ${feed.feedId} processing completed`);
        }

        // 添加短暂延迟，避免 API 请求过于频繁
        await this.delay(1000);
      } catch (error: any) {
        this.logger.error(`Failed to check feed ${feed.feedId}: ${error.message}`);
        
        // 如果是 API 错误，可能需要增加重试间隔
        // 这里简单记录错误，下次调度时会重试
      }
    }
  }

  /**
   * 手动触发 Feed 状态检查（用于测试）
   */
  async manualCheck() {
    this.logger.log('Manual feed status check triggered');
    await this.processPendingFeeds();
    return { success: true, message: 'Feed status check completed' };
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}
