import { Processor, WorkerHost } from '@nestjs/bullmq';
import { Job } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { QUEUE_NAMES } from '@/queues/constants';
import { ChannelAdapterFactory } from '@/adapters/channels';
import { PlatformAdapterFactory } from '@/adapters/platforms';
import { ShopService } from '@/modules/shop/shop.service';

// 渠道速率限制配置
const CHANNEL_RATE_LIMITS: Record<string, { batchSize: number; batchDelay: number }> = {
  gigacloud: { batchSize: 200, batchDelay: 1500 },
  saleyee: { batchSize: 30, batchDelay: 1000 },
  default: { batchSize: 50, batchDelay: 1500 },
};

// 任务取消信号
export const autoSyncCancelSignals = new Map<string, boolean>();

@Processor(QUEUE_NAMES.AUTO_SYNC)
export class AutoSyncProcessor extends WorkerHost {
  constructor(
    private prisma: PrismaService,
    private shopService: ShopService,
  ) {
    super();
  }

  async process(job: Job<{ taskId: string }>) {
    const { taskId } = job.data;
    console.log(`[AutoSync] Starting task: ${taskId}`);

    try {
      // 阶段1: 从渠道获取价格库存
      await this.stageFetchChannel(taskId);
      if (autoSyncCancelSignals.get(taskId)) {
        await this.markCancelled(taskId);
        return;
      }

      // 阶段2: 更新本地商品
      await this.stageUpdateLocal(taskId);
      if (autoSyncCancelSignals.get(taskId)) {
        await this.markCancelled(taskId);
        return;
      }

      // 阶段3: 推送到平台
      await this.stagePushPlatform(taskId);

      // 完成
      await this.prisma.autoSyncTask.update({
        where: { id: taskId },
        data: { stage: 'completed', finishedAt: new Date() },
      });

      console.log(`[AutoSync] Task completed: ${taskId}`);
    } catch (error: any) {
      console.error(`[AutoSync] Task failed: ${taskId}`, error);
      await this.prisma.autoSyncTask.update({
        where: { id: taskId },
        data: {
          stage: 'failed',
          errorMessage: error.message,
          finishedAt: new Date(),
        },
      });
    } finally {
      autoSyncCancelSignals.delete(taskId);
    }
  }


  // 阶段1: 从渠道获取价格库存
  private async stageFetchChannel(taskId: string) {
    const task = await this.prisma.autoSyncTask.findUnique({
      where: { id: taskId },
      include: { shop: true },
    });
    if (!task) throw new Error('任务不存在');

    console.log(`[AutoSync] Stage 1: Fetch from channels for shop ${task.shopId}`);

    // 获取店铺商品按渠道分组
    const products = await this.prisma.product.findMany({
      where: { shopId: task.shopId },
      select: { id: true, sku: true, channelId: true },
    });

    // 按渠道分组
    const productsByChannel: Record<string, { id: string; sku: string }[]> = {};
    for (const p of products) {
      if (p.channelId) {
        if (!productsByChannel[p.channelId]) {
          productsByChannel[p.channelId] = [];
        }
        productsByChannel[p.channelId].push({ id: p.id, sku: p.sku });
      }
    }

    const channelStats = (task.channelStats as Record<string, any>) || {};

    // 对每个渠道获取数据
    for (const [channelId, channelProducts] of Object.entries(productsByChannel)) {
      if (autoSyncCancelSignals.get(taskId)) return;

      // 更新渠道状态为 running
      channelStats[channelId] = { ...channelStats[channelId], status: 'running' };
      await this.prisma.autoSyncTask.update({
        where: { id: taskId },
        data: { channelStats },
      });

      try {
        await this.fetchChannelData(taskId, channelId, channelProducts, channelStats);
        channelStats[channelId].status = 'completed';
      } catch (error: any) {
        console.error(`[AutoSync] Channel ${channelId} fetch failed:`, error.message);
        channelStats[channelId].status = 'failed';
        channelStats[channelId].error = error.message;
      }

      await this.prisma.autoSyncTask.update({
        where: { id: taskId },
        data: { channelStats },
      });
    }

    // 更新阶段
    await this.prisma.autoSyncTask.update({
      where: { id: taskId },
      data: { stage: 'update_local' },
    });
  }

  // 从单个渠道获取数据
  private async fetchChannelData(
    taskId: string,
    channelId: string,
    products: { id: string; sku: string }[],
    channelStats: Record<string, any>,
  ) {
    const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) {
      throw new Error(`渠道 ${channelId} 不存在`);
    }

    const rateLimit = CHANNEL_RATE_LIMITS[channel.type] || CHANNEL_RATE_LIMITS.default;
    const { batchSize, batchDelay } = rateLimit;

    // 创建渠道适配器
    const adapter = ChannelAdapterFactory.create(channel.type, channel.apiConfig as any) as any;

    // 检查适配器是否支持批量查询
    if (typeof adapter.fetchProductsBySkus !== 'function') {
      throw new Error(`渠道 ${channel.name} 不支持批量查询`);
    }

    // 分批查询
    const skus = products.map(p => p.sku);
    let fetched = 0;

    for (let i = 0; i < skus.length; i += batchSize) {
      if (autoSyncCancelSignals.get(taskId)) return;

      const batchSkus = skus.slice(i, i + batchSize);
      
      try {
        const result = await adapter.fetchProductsBySkus(batchSkus);
        
        // 更新商品的 extraFields.latestChannelData
        for (const item of result) {
          const product = products.find(p => p.sku === item.sku);
          if (product) {
            await this.prisma.product.update({
              where: { id: product.id },
              data: {
                extraFields: {
                  ...(await this.prisma.product.findUnique({ where: { id: product.id } }))?.extraFields as any,
                  latestChannelData: {
                    price: item.price,
                    stock: item.stock,
                    shippingFee: item.extraFields?.shippingFee,
                    fetchedAt: new Date().toISOString(),
                  },
                },
              },
            });
          }
        }

        fetched += batchSkus.length;
        channelStats[channelId].fetched = fetched;

        // 更新进度
        await this.prisma.autoSyncTask.update({
          where: { id: taskId },
          data: { channelStats },
        });

        console.log(`[AutoSync] Channel ${channel.name}: ${fetched}/${skus.length}`);
      } catch (error: any) {
        console.error(`[AutoSync] Batch fetch failed:`, error.message);
      }

      // 批次间延迟
      if (i + batchSize < skus.length) {
        await new Promise(resolve => setTimeout(resolve, batchDelay));
      }
    }
  }


  // 阶段2: 更新本地商品
  private async stageUpdateLocal(taskId: string) {
    const task = await this.prisma.autoSyncTask.findUnique({
      where: { id: taskId },
      include: { shop: true },
    });
    if (!task) throw new Error('任务不存在');

    console.log(`[AutoSync] Stage 2: Update local products for shop ${task.shopId}`);

    // 获取同步配置（包含 useDiscountedPrice）
    const syncConfig = await this.shopService.getSyncConfig(task.shopId);
    const useDiscountedPrice = syncConfig.price.useDiscountedPrice ?? false;

    // 获取所有有最新渠道数据的商品
    const products = await this.prisma.product.findMany({
      where: { shopId: task.shopId },
    });

    let updated = 0;
    for (const product of products) {
      if (autoSyncCancelSignals.get(taskId)) return;

      const extraFields = product.extraFields as any;
      const latestData = extraFields?.latestChannelData;

      if (latestData) {
        // 更新原始价格和库存
        const originalPrice = latestData.price ?? Number(product.originalPrice);
        const originalStock = latestData.stock ?? product.originalStock;

        // 计算本地价格（原价 + 运费）
        const shippingFee = latestData.shippingFee || 0;
        const localPrice = originalPrice + shippingFee;
        
        // 计算优惠总价（优惠价 + 运费）
        const discountedPrice = extraFields?.discountedPrice;
        const discountedTotalPrice = discountedPrice ? discountedPrice + shippingFee : null;

        // 确定用于同步的价格来源
        // 如果启用了优惠价优先，且有优惠总价，则使用优惠总价；否则使用总价(localPrice)
        let priceForSync: number;
        if (useDiscountedPrice && discountedTotalPrice && discountedTotalPrice > 0) {
          priceForSync = discountedTotalPrice;
        } else {
          priceForSync = syncConfig.price.source === 'local' ? localPrice : originalPrice;
        }

        // 应用同步规则计算最终价格和库存
        const finalPrice = this.shopService.calculateFinalPrice(priceForSync, syncConfig);
        const finalStock = this.shopService.calculateFinalStock(originalStock, syncConfig);

        await this.prisma.product.update({
          where: { id: product.id },
          data: {
            originalPrice,
            originalStock,
            localPrice,
            localStock: originalStock,
            finalPrice,
            finalStock,
            lastSyncAt: new Date(),
          },
        });

        updated++;
      }
    }

    await this.prisma.autoSyncTask.update({
      where: { id: taskId },
      data: { localUpdated: updated, stage: 'push_platform' },
    });

    console.log(`[AutoSync] Updated ${updated} products locally`);
  }

  // 阶段3: 推送到平台
  private async stagePushPlatform(taskId: string) {
    const task = await this.prisma.autoSyncTask.findUnique({
      where: { id: taskId },
      include: { shop: { include: { platform: true } } },
    });
    if (!task) throw new Error('任务不存在');

    console.log(`[AutoSync] Stage 3: Push to platform for shop ${task.shopId}`);

    const platformCode = task.shop.platform?.code;
    if (platformCode !== 'walmart') {
      console.log(`[AutoSync] Platform ${platformCode} not supported for auto push, skipping`);
      return;
    }

    // 获取所有商品
    const products = await this.prisma.product.findMany({
      where: { shopId: task.shopId },
    });

    if (products.length === 0) {
      console.log(`[AutoSync] No products to push`);
      return;
    }

    const adapter = PlatformAdapterFactory.create(
      platformCode,
      task.shop.apiCredentials as any,
    ) as any;

    const syncType = task.syncType as 'price' | 'inventory' | 'both';
    let successCount = 0;
    let failCount = 0;

    try {
      // 同步价格 - 跳过价格为空/0的商品
      if (syncType === 'price' || syncType === 'both') {
        const priceItems = products
          .filter(p => Number(p.finalPrice) > 0) // 跳过价格为空或0的商品
          .map(p => ({
            sku: (p as any).platformSku || p.sku, // 优先使用平台SKU
            price: Number(p.finalPrice),
            msrp: Number(p.originalPrice) || Number(p.finalPrice),
          }));

        if (priceItems.length > 0) {
          const priceResult = await adapter.batchUpdatePrices(priceItems);
          
          await this.prisma.autoSyncTask.update({
            where: { id: taskId },
            data: { platformFeedId: priceResult.feedId, platformStatus: 'RECEIVED' },
          });

          // 保存 Feed 记录，包含提交的数据
          const feedData: Record<string, { price: number }> = {};
          for (const item of priceItems) {
            feedData[item.sku] = { price: item.price };
          }
          
          await this.prisma.feedRecord.create({
            data: {
              shopId: task.shopId,
              feedId: priceResult.feedId,
              syncType: 'price',
              itemCount: priceItems.length,
              status: 'RECEIVED',
              feedDetail: { submittedData: feedData },
            },
          });

          successCount = priceItems.length;
          console.log(`[AutoSync] Price feed submitted: ${priceResult.feedId}, items: ${priceItems.length}`);
        } else {
          console.log(`[AutoSync] No valid price items to sync`);
        }
      }

      // 同步库存 - 库存为空时当作0处理
      if (syncType === 'inventory' || syncType === 'both') {
        const inventoryItems = products.map(p => ({
          sku: (p as any).platformSku || p.sku, // 优先使用平台SKU
          quantity: p.finalStock ?? 0, // 库存为空时当作0
        }));

        const inventoryResult = await adapter.batchUpdateInventory(inventoryItems);

        await this.prisma.autoSyncTask.update({
          where: { id: taskId },
          data: { platformFeedId: inventoryResult.feedId, platformStatus: 'RECEIVED' },
        });

        // 保存 Feed 记录，包含提交的数据
        const feedData: Record<string, { quantity: number }> = {};
        for (const item of inventoryItems) {
          feedData[item.sku] = { quantity: item.quantity };
        }
        
        await this.prisma.feedRecord.create({
          data: {
            shopId: task.shopId,
            feedId: inventoryResult.feedId,
            syncType: 'inventory',
            itemCount: inventoryItems.length,
            status: 'RECEIVED',
            feedDetail: { submittedData: feedData },
          },
        });

        successCount = inventoryItems.length;
      }
    } catch (error: any) {
      console.error(`[AutoSync] Push to platform failed:`, error.message);
      failCount = products.length;
    }

    await this.prisma.autoSyncTask.update({
      where: { id: taskId },
      data: { successCount, failCount },
    });
  }

  private async markCancelled(taskId: string) {
    await this.prisma.autoSyncTask.update({
      where: { id: taskId },
      data: {
        stage: 'cancelled',
        finishedAt: new Date(),
        errorMessage: '任务被手动取消',
      },
    });
  }
}
