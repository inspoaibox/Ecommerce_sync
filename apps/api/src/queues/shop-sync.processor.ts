import { Processor, WorkerHost } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import { Job } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { PlatformAdapterFactory } from '@/adapters/platforms';
import { QUEUE_NAMES } from './constants';

// 内存中的任务控制信号
export const taskControlSignals = new Map<string, 'pause' | 'cancel'>();

@Processor(QUEUE_NAMES.SHOP_SYNC)
export class ShopSyncProcessor extends WorkerHost {
  private readonly logger = new Logger(ShopSyncProcessor.name);

  constructor(private prisma: PrismaService) {
    super();
  }

  async process(job: Job): Promise<any> {
    const { shopId, taskId, resumeFrom = 0 } = job.data;

    try {
      // 获取店铺信息
      const shop = await this.prisma.shop.findUnique({
        where: { id: shopId },
        include: { platform: true },
      });

      if (!shop) {
        throw new Error('店铺不存在');
      }

      const platformCode = shop.platform?.code || shop.platformId;
      const platformName = shop.platform?.name || platformCode;

      // 获取任务当前状态（用于断点续传）
      const existingTask = await this.prisma.shopSyncTask.findUnique({
        where: { id: taskId },
      });

      // 更新任务状态为运行中
      await this.prisma.shopSyncTask.update({
        where: { id: taskId },
        data: { status: 'running', startedAt: existingTask?.startedAt || new Date() },
      });

      this.logger.log(`Starting product sync for shop: ${shop.name}, resumeFrom: ${resumeFrom}`);

      // 创建适配器（传递 region 以支持多区域）
      const adapter = PlatformAdapterFactory.create(
        platformCode,
        { ...(shop.apiCredentials as Record<string, any>), region: shop.region },
      ) as any;

      if (typeof adapter.getItemsBatched !== 'function') {
        throw new Error(`${platformName} 平台暂不支持商品同步`);
      }

      // 断点续传：从上次进度继续
      let created = existingTask?.created || 0;
      let updated = existingTask?.updated || 0;
      let skipped = existingTask?.skipped || 0;
      let processed = resumeFrom;
      const createdSkus: string[] = existingTask?.createdSkus as string[] || [];
      const updatedSkus: string[] = existingTask?.updatedSkus as string[] || [];
      const skippedSkus: string[] = existingTask?.skippedSkus as string[] || [];
      
      // 用于跟踪本次同步已处理的 SKU（避免 API 返回重复数据时重复处理）
      const processedInThisSync = new Set<string>(createdSkus);
      updatedSkus.forEach(sku => processedInThisSync.add(sku));
      skippedSkus.forEach(sku => processedInThisSync.add(sku));

      // 边获取边保存（支持断点续传）
      for await (const batch of adapter.getItemsBatched(resumeFrom)) {
        // 检查控制信号
        const signal = taskControlSignals.get(taskId);
        if (signal === 'cancel') {
          taskControlSignals.delete(taskId);
          await this.prisma.shopSyncTask.update({
            where: { id: taskId },
            data: {
              status: 'cancelled',
              finishedAt: new Date(),
              progress: processed,
              created,
              updated,
            },
          });
          this.logger.log(`Task ${taskId} cancelled`);
          return { success: false, cancelled: true, created, updated };
        }

        if (signal === 'pause') {
          await this.prisma.shopSyncTask.update({
            where: { id: taskId },
            data: {
              status: 'paused',
              progress: processed,
              created,
              updated,
            },
          });
          this.logger.log(`Task ${taskId} paused at ${processed}`);
          // 等待继续信号
          while (taskControlSignals.get(taskId) === 'pause') {
            await new Promise((resolve) => setTimeout(resolve, 1000));
            // 检查是否变成取消
            if (taskControlSignals.get(taskId) === 'cancel') {
              taskControlSignals.delete(taskId);
              await this.prisma.shopSyncTask.update({
                where: { id: taskId },
                data: { status: 'cancelled', finishedAt: new Date() },
              });
              return { success: false, cancelled: true };
            }
          }
          // 继续执行
          taskControlSignals.delete(taskId);
          await this.prisma.shopSyncTask.update({
            where: { id: taskId },
            data: { status: 'running' },
          });
        }

        const { items, total, isLast } = batch;

        // 更新数据库中的进度
        await this.prisma.shopSyncTask.update({
          where: { id: taskId },
          data: { total, progress: processed },
        });

        this.logger.log(`Processing batch: ${items.length} items, progress: ${processed}/${total}`);

        // 立即保存这批数据
        for (const item of items) {
          // 在处理每个商品时也检查取消信号，响应更快
          if (taskControlSignals.get(taskId) === 'cancel') {
            taskControlSignals.delete(taskId);
            await this.prisma.shopSyncTask.update({
              where: { id: taskId },
              data: {
                status: 'cancelled',
                finishedAt: new Date(),
                progress: processed,
                created,
                updated,
                skipped,
                createdSkus,
                updatedSkus,
                skippedSkus,
              },
            });
            this.logger.log(`Task ${taskId} cancelled during batch processing`);
            return { success: false, cancelled: true, created, updated };
          }

          const transformed = adapter.transformItem(item);

          // 检查本次同步是否已处理过该 SKU（API 可能返回重复数据）
          if (processedInThisSync.has(transformed.sku)) {
            skipped++;
            skippedSkus.push(transformed.sku);
            processed++;
            this.logger.debug(`Skipped duplicate SKU from API: ${transformed.sku}`);
            continue;
          }
          processedInThisSync.add(transformed.sku);

          // 检查数据库中是否已存在该 SKU（用于断点续传场景）
          const existingProduct = await this.prisma.product.findFirst({
            where: { sku: transformed.sku, shopId: shop.id },
          });

          const productData = {
            sku: transformed.sku,
            title: transformed.title,
            originalPrice: transformed.price,
            finalPrice: transformed.price,
            originalStock: transformed.stock,
            finalStock: transformed.stock,
            currency: transformed.currency,
            extraFields: transformed.extraFields,
            sourceChannel: platformName,
            shopId: shop.id,
          };

          if (existingProduct) {
            await this.prisma.product.update({
              where: { id: existingProduct.id },
              data: productData,
            });
            updated++;
            updatedSkus.push(transformed.sku);
          } else {
            await this.prisma.product.create({
              data: { ...productData, channelProductId: transformed.sku, syncStatus: 'pending' },
            });
            created++;
            createdSkus.push(transformed.sku);
          }
          processed++;
        }

        // 更新进度和 SKU 列表（实时更新，支持查看）
        await this.prisma.shopSyncTask.update({
          where: { id: taskId },
          data: {
            progress: processed,
            created,
            updated,
            skipped,
            createdSkus,
            updatedSkus,
            skippedSkus,
          },
        });

        this.logger.log(`Saved batch: ${processed}/${total}, created: ${created}, updated: ${updated}`);
        if (isLast) break;
      }

      // 完成
      await this.prisma.shopSyncTask.update({
        where: { id: taskId },
        data: {
          status: 'completed',
          finishedAt: new Date(),
          progress: processed,
          created,
          updated,
          skipped,
          createdSkus,
          updatedSkus,
          skippedSkus,
        },
      });

      this.logger.log(`Sync completed: created ${created}, updated ${updated}, skipped (API duplicates) ${skipped}`);
      return { success: true, created, updated };
    } catch (error) {
      const errMsg = error instanceof Error ? error.message : '同步失败';
      this.logger.error(`Sync failed: ${errMsg}`);

      await this.prisma.shopSyncTask.update({
        where: { id: taskId },
        data: {
          status: 'failed',
          errorMessage: errMsg,
          finishedAt: new Date(),
        },
      });

      throw error;
    }
  }
}
