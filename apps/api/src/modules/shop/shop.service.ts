import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectQueue } from '@nestjs/bullmq';
import { Queue } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreateShopDto, UpdateShopDto } from './dto/shop.dto';
import { ShopSyncConfigDto, DEFAULT_SYNC_CONFIG, PriceTierDto } from './dto/sync-config.dto';
import { PaginationDto, PaginatedResult } from '@/common/dto/pagination.dto';
import { Shop } from '@prisma/client';
import { PlatformAdapterFactory, PLATFORM_CONFIGS } from '@/adapters/platforms';
import { QUEUE_NAMES } from '@/queues/constants';
import { taskControlSignals } from '@/queues/shop-sync.processor';

@Injectable()
export class ShopService {
  constructor(
    private prisma: PrismaService,
    @InjectQueue(QUEUE_NAMES.SHOP_SYNC) private shopSyncQueue: Queue,
  ) {}

  async findAll(query: PaginationDto): Promise<PaginatedResult<Shop>> {
    const { page = 1, pageSize = 20 } = query;
    const skip = (page - 1) * pageSize;

    const [data, total] = await Promise.all([
      this.prisma.shop.findMany({
        skip,
        take: pageSize,
        include: { platform: true },
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.shop.count(),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  async findOne(id: string): Promise<Shop> {
    const shop = await this.prisma.shop.findUnique({
      where: { id },
      include: { platform: true },
    });
    if (!shop) throw new NotFoundException('店铺不存在');
    return shop;
  }

  private async getOrCreatePlatform(platformCode: string): Promise<string> {
    let platform = await this.prisma.platform.findUnique({
      where: { code: platformCode },
    });

    if (!platform) {
      const config = PLATFORM_CONFIGS[platformCode];
      platform = await this.prisma.platform.create({
        data: {
          code: platformCode,
          name: config?.name || platformCode,
          apiBaseUrl: config?.apiBaseUrl || '',
          status: 'active',
        },
      });
    }

    return platform.id;
  }

  async create(dto: CreateShopDto): Promise<Shop> {
    const { platformCode, ...rest } = dto;
    const platformId = await this.getOrCreatePlatform(platformCode);
    
    return this.prisma.shop.create({
      data: { ...rest, platformId },
      include: { platform: true },
    });
  }

  async update(id: string, dto: UpdateShopDto): Promise<Shop> {
    await this.findOne(id);
    const { platformCode, ...rest } = dto;
    
    const updateData: any = { ...rest };
    if (platformCode) {
      updateData.platformId = await this.getOrCreatePlatform(platformCode);
    }
    
    return this.prisma.shop.update({
      where: { id },
      data: updateData,
      include: { platform: true },
    });
  }

  async remove(id: string): Promise<void> {
    await this.findOne(id);
    await this.prisma.shop.delete({ where: { id } });
  }

  async testConnection(id: string): Promise<{ success: boolean; message: string }> {
    const shop = await this.findOne(id);
    try {
      const platformCode = (shop as any).platform?.code || shop.platformId;
      const adapter = PlatformAdapterFactory.create(
        platformCode,
        shop.apiCredentials as Record<string, any>,
      );
      const result = await adapter.testConnection();
      return { success: result, message: result ? '连接成功' : '连接失败' };
    } catch (error) {
      const errMsg = error instanceof Error ? error.message : '连接失败';
      return { success: false, message: errMsg };
    }
  }

  // 从平台同步商品到本地（异步）
  async syncProducts(id: string): Promise<{ success: boolean; taskId: string; message: string }> {
    const shop = await this.findOne(id);

    // 创建数据库任务记录
    const task = await this.prisma.shopSyncTask.create({
      data: {
        shopId: id,
        shopName: shop.name,
        status: 'pending',
      },
    });

    // 添加到队列
    await this.shopSyncQueue.add('sync-products', { shopId: id, taskId: task.id });

    return {
      success: true,
      taskId: task.id,
      message: '同步任务已创建，正在后台处理',
    };
  }

  // 获取同步任务状态（从数据库）
  async getSyncTaskStatus(taskId: string) {
    const task = await this.prisma.shopSyncTask.findUnique({
      where: { id: taskId },
    });
    if (!task) {
      return { success: false, message: '任务不存在' };
    }
    return { success: true, ...task };
  }

  // 获取所有同步任务列表
  async getSyncTasks(query: PaginationDto & { shopId?: string }) {
    const { shopId } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 20;
    const skip = (page - 1) * pageSize;
    const where = shopId ? { shopId } : {};

    const [data, total] = await Promise.all([
      this.prisma.shopSyncTask.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { include: { platform: true } } },
      }),
      this.prisma.shopSyncTask.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  // 暂停同步任务
  async pauseSyncTask(taskId: string) {
    const task = await this.prisma.shopSyncTask.findUnique({ where: { id: taskId } });
    if (!task) throw new NotFoundException('任务不存在');
    if (task.status !== 'running') {
      return { success: false, message: '只能暂停运行中的任务' };
    }
    taskControlSignals.set(taskId, 'pause');
    return { success: true, message: '暂停信号已发送' };
  }

  // 继续同步任务
  async resumeSyncTask(taskId: string) {
    const task = await this.prisma.shopSyncTask.findUnique({ where: { id: taskId } });
    if (!task) throw new NotFoundException('任务不存在');
    if (task.status !== 'paused') {
      return { success: false, message: '只能继续已暂停的任务' };
    }
    taskControlSignals.delete(taskId);
    return { success: true, message: '继续信号已发送' };
  }

  // 取消同步任务
  async cancelSyncTask(taskId: string, force: boolean = false) {
    const task = await this.prisma.shopSyncTask.findUnique({ where: { id: taskId } });
    if (!task) throw new NotFoundException('任务不存在');
    if (!['running', 'paused', 'pending'].includes(task.status)) {
      return { success: false, message: '只能取消进行中的任务' };
    }
    
    // 发送取消信号
    taskControlSignals.set(taskId, 'cancel');
    
    // 如果是强制取消，直接更新数据库状态（用于任务卡死的情况）
    if (force) {
      await this.prisma.shopSyncTask.update({
        where: { id: taskId },
        data: {
          status: 'cancelled',
          finishedAt: new Date(),
          errorMessage: '任务被强制取消',
        },
      });
      taskControlSignals.delete(taskId);
      return { success: true, message: '任务已强制取消' };
    }
    
    return { success: true, message: '取消信号已发送' };
  }

  // 删除同步任务记录
  async deleteSyncTask(taskId: string) {
    const task = await this.prisma.shopSyncTask.findUnique({ where: { id: taskId } });
    if (!task) throw new NotFoundException('任务不存在');
    if (['running', 'paused'].includes(task.status)) {
      return { success: false, message: '不能删除进行中的任务' };
    }
    await this.prisma.shopSyncTask.delete({ where: { id: taskId } });
    return { success: true, message: '删除成功' };
  }

  // 重试失败的同步任务
  async retrySyncTask(taskId: string) {
    const task = await this.prisma.shopSyncTask.findUnique({ where: { id: taskId } });
    if (!task) throw new NotFoundException('任务不存在');
    if (!['failed', 'cancelled'].includes(task.status)) {
      return { success: false, message: '只能重试失败或已取消的任务' };
    }

    // 重置任务状态，保留已处理的进度用于断点续传
    await this.prisma.shopSyncTask.update({
      where: { id: taskId },
      data: {
        status: 'pending',
        errorMessage: null,
        finishedAt: null,
      },
    });

    // 重新添加到队列，传递 resumeFrom 参数
    await this.shopSyncQueue.add('sync-products', {
      shopId: task.shopId,
      taskId: task.id,
      resumeFrom: task.progress, // 从上次进度继续
    });

    return { success: true, message: '重试任务已创建' };
  }

  // 删除店铺所有商品
  async deleteAllProducts(id: string): Promise<{ success: boolean; message: string; count: number }> {
    await this.findOne(id);
    const result = await this.prisma.product.deleteMany({ where: { shopId: id } });
    return { success: true, message: `成功删除 ${result.count} 个商品`, count: result.count };
  }

  // 补充同步缺失的 SKU
  async syncMissingSkus(id: string, skus: string[]): Promise<{ success: boolean; message: string; created: number; notFound: number }> {
    const shop = await this.findOne(id);
    const platformCode = (shop as any).platform?.code || shop.platformId;
    const platformName = (shop as any).platform?.name || platformCode;

    const adapter = PlatformAdapterFactory.create(
      platformCode,
      shop.apiCredentials as Record<string, any>,
    ) as any;

    if (typeof adapter.getItemsBySkus !== 'function') {
      throw new Error(`${platformName} 平台暂不支持补充同步`);
    }

    // 过滤掉已存在的 SKU
    const existingProducts = await this.prisma.product.findMany({
      where: { shopId: id, sku: { in: skus } },
      select: { sku: true },
    });
    const existingSkuSet = new Set(existingProducts.map(p => p.sku));
    const missingSkus = skus.filter(sku => !existingSkuSet.has(sku));

    if (missingSkus.length === 0) {
      return { success: true, message: '所有 SKU 已存在，无需补充同步', created: 0, notFound: 0 };
    }

    // 查询缺失的 SKU
    const items = await adapter.getItemsBySkus(missingSkus);

    let created = 0;
    let notFound = missingSkus.length - items.length;

    for (const item of items) {
      const transformed = adapter.transformItem(item);

      await this.prisma.product.create({
        data: {
          sku: transformed.sku,
          title: transformed.title,
          originalPrice: transformed.price,
          finalPrice: transformed.price,
          originalStock: transformed.stock,
          finalStock: transformed.stock,
          currency: transformed.currency,
          extraFields: transformed.extraFields,
          sourceChannel: platformName,
          shopId: id,
          channelProductId: transformed.sku,
          syncStatus: 'pending',
        },
      });
      created++;
    }

    return {
      success: true,
      message: `补充同步完成：新增 ${created} 个，未找到 ${notFound} 个`,
      created,
      notFound,
    };
  }

  // 同步价格/库存到沃尔玛
  async syncToWalmart(
    id: string,
    productIds: string[],
    syncType: 'price' | 'inventory' | 'both'
  ): Promise<{ success: boolean; message: string; feedId?: string }> {
    const shop = await this.findOne(id);
    const platformCode = (shop as any).platform?.code || shop.platformId;

    // 验证是否为沃尔玛平台
    if (platformCode !== 'walmart') {
      throw new Error('此功能仅支持沃尔玛平台');
    }

    // 获取要同步的商品
    const products = await this.prisma.product.findMany({
      where: {
        id: { in: productIds },
        shopId: id,
      },
    });

    if (products.length === 0) {
      throw new Error('未找到要同步的商品');
    }

    const adapter = PlatformAdapterFactory.create(
      platformCode,
      shop.apiCredentials as Record<string, any>,
    ) as any;

    // 验证适配器是否支持批量更新
    if (syncType === 'price' || syncType === 'both') {
      if (typeof adapter.batchUpdatePrices !== 'function') {
        throw new Error('沃尔玛适配器不支持批量价格更新');
      }
    }
    if (syncType === 'inventory' || syncType === 'both') {
      if (typeof adapter.batchUpdateInventory !== 'function') {
        throw new Error('沃尔玛适配器不支持批量库存更新');
      }
    }

    try {
      // 获取店铺同步配置
      const syncConfig = await this.getSyncConfig(id);
      
      let feedId: string | undefined;
      const feedIds: string[] = [];

      // 同步价格 - 使用 platformSku 或 sku
      // 跳过价格为空/null/0的商品
      if (syncType === 'price' || syncType === 'both') {
        const priceItems = products
          .filter(p => {
            // 检查价格来源是否有效
            const sourcePrice = syncConfig.price.source === 'local' && p.localPrice !== null
              ? Number(p.localPrice)
              : Number(p.originalPrice);
            return sourcePrice > 0; // 跳过价格为空或0的商品
          })
          .map(p => {
            // 根据配置选择价格来源
            let sourcePrice: number;
            if (syncConfig.price.source === 'local' && p.localPrice !== null) {
              sourcePrice = Number(p.localPrice);
            } else {
              sourcePrice = Number(p.originalPrice);
            }
            
            // 应用价格计算规则
            const calculatedPrice = this.calculateFinalPrice(sourcePrice, syncConfig);
            
            return {
              sku: p.platformSku || p.sku, // 优先使用平台SKU
              price: calculatedPrice,
              msrp: Number(p.originalPrice) || calculatedPrice, // 使用原价作为MSRP，为空则用计算价格
            };
          });

        if (priceItems.length === 0) {
          console.log(`[Shop ${id}] No valid price items to sync (all prices are empty or 0)`);
        } else {
          const priceResult = await adapter.batchUpdatePrices(priceItems);
          feedId = priceResult.feedId;
          feedIds.push(priceResult.feedId);

          console.log(`[Shop ${id}] Price feed submitted: ${priceResult.feedId}, items: ${priceItems.length}`);

          // 保存价格Feed记录
          await this.prisma.feedRecord.create({
            data: {
              shopId: id,
              feedId: priceResult.feedId,
              syncType: 'price',
              itemCount: priceItems.length,
              status: 'RECEIVED',
            },
          });
        }
      }

      // 同步库存 - 使用 platformSku 或 sku
      // 库存为空/null时当作0处理
      if (syncType === 'inventory' || syncType === 'both') {
        const inventoryItems = products.map(p => {
          // 库存为空时当作0处理
          const originalStock = p.originalStock ?? 0;
          const calculatedStock = this.calculateFinalStock(originalStock, syncConfig);
          
          return {
            sku: p.platformSku || p.sku, // 优先使用平台SKU
            quantity: calculatedStock,
          };
        });

        const inventoryResult = await adapter.batchUpdateInventory(inventoryItems);
        feedId = inventoryResult.feedId;
        feedIds.push(inventoryResult.feedId);

        console.log(`[Shop ${id}] Inventory feed submitted: ${inventoryResult.feedId}`);

        // 保存库存Feed记录
        await this.prisma.feedRecord.create({
          data: {
            shopId: id,
            feedId: inventoryResult.feedId,
            syncType: 'inventory',
            itemCount: products.length,
            status: 'RECEIVED',
          },
        });
      }

      // 更新商品同步状态
      await this.prisma.product.updateMany({
        where: { id: { in: productIds } },
        data: {
          syncStatus: 'pending',
          updatedAt: new Date(),
        },
      });

      const typeText = syncType === 'price' ? '价格' : syncType === 'inventory' ? '库存' : '价格和库存';
      const feedIdsText = feedIds.length > 1 ? `Feed IDs: ${feedIds.join(', ')}` : `Feed ID: ${feedId}`;
      return {
        success: true,
        message: `成功提交${typeText}同步任务，共 ${products.length} 个商品。${feedIdsText}`,
        feedId,
      };
    } catch (error: any) {
      console.error(`[Shop ${id}] Sync to Walmart failed:`, error);
      throw new Error(error.message || '同步失败');
    }
  }

  // 获取店铺的Feed记录列表
  async getFeeds(shopId: string, query: PaginationDto) {
    const { page = 1, pageSize = 20 } = query;
    const skip = (page - 1) * pageSize;

    const [data, total] = await Promise.all([
      this.prisma.feedRecord.findMany({
        where: { shopId },
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { select: { id: true, name: true } } },
      }),
      this.prisma.feedRecord.count({ where: { shopId } }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  // 获取所有店铺的Feed记录列表
  async getAllFeeds(query: PaginationDto) {
    const { page = 1, pageSize = 20 } = query;
    const skip = (page - 1) * pageSize;

    const [data, total] = await Promise.all([
      this.prisma.feedRecord.findMany({
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { select: { id: true, name: true } } },
      }),
      this.prisma.feedRecord.count(),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  // 刷新Feed状态
  async refreshFeedStatus(shopId: string, feedId: string) {
    const shop = await this.findOne(shopId);
    const platformCode = (shop as any).platform?.code || shop.platformId;

    if (platformCode !== 'walmart') {
      throw new Error('此功能仅支持沃尔玛平台');
    }

    const adapter = PlatformAdapterFactory.create(
      platformCode,
      shop.apiCredentials as Record<string, any>,
    ) as any;

    if (typeof adapter.getFeedStatus !== 'function') {
      throw new Error('沃尔玛适配器不支持Feed状态查询');
    }

    try {
      const feedStatus = await adapter.getFeedStatus(feedId);

      // 更新数据库中的Feed记录
      const updateData: any = {
        status: feedStatus.feedStatus || 'RECEIVED',
        updatedAt: new Date(),
      };

      if (feedStatus.feedStatus === 'PROCESSED') {
        updateData.completedAt = new Date();
        updateData.successCount = feedStatus.itemsSucceeded || 0;
        updateData.failCount = feedStatus.itemsFailed || 0;
      } else if (feedStatus.feedStatus === 'ERROR') {
        updateData.errorMessage = feedStatus.feedStatusDescription || '处理失败';
      }

      await this.prisma.feedRecord.update({
        where: {
          shopId_feedId: {
            shopId,
            feedId,
          },
        },
        data: updateData,
      });

      return { success: true, message: 'Feed状态已更新' };
    } catch (error: any) {
      console.error(`[Shop ${shopId}] Refresh feed status failed:`, error);
      throw new Error(error.message || '刷新Feed状态失败');
    }
  }

  // 获取Feed详情
  async getFeedDetail(shopId: string, feedId: string) {
    const shop = await this.findOne(shopId);
    const platformCode = (shop as any).platform?.code || shop.platformId;

    if (platformCode !== 'walmart') {
      throw new Error('此功能仅支持沃尔玛平台');
    }

    const adapter = PlatformAdapterFactory.create(
      platformCode,
      shop.apiCredentials as Record<string, any>,
    ) as any;

    if (typeof adapter.getFeedStatus !== 'function') {
      throw new Error('沃尔玛适配器不支持Feed状态查询');
    }

    try {
      const feedStatus = await adapter.getFeedStatus(feedId);
      return feedStatus;
    } catch (error: any) {
      console.error(`[Shop ${shopId}] Get feed detail failed:`, error);
      throw new Error(error.message || '获取Feed详情失败');
    }
  }

  // 获取店铺同步配置
  async getSyncConfig(shopId: string): Promise<ShopSyncConfigDto> {
    const shop = await this.findOne(shopId);
    const config = shop.syncConfig as ShopSyncConfigDto | null;
    return config || DEFAULT_SYNC_CONFIG;
  }

  // 更新店铺同步配置
  async updateSyncConfig(shopId: string, config: ShopSyncConfigDto): Promise<ShopSyncConfigDto> {
    await this.findOne(shopId);
    
    // 对价格区间按 minPrice 升序排序
    if (config.price?.tiers) {
      config.price.tiers.sort((a, b) => a.minPrice - b.minPrice);
    }

    await this.prisma.shop.update({
      where: { id: shopId },
      data: { syncConfig: config as any },
    });

    return config;
  }

  // 根据同步配置计算最终价格
  calculateFinalPrice(originalPrice: number, config: ShopSyncConfigDto): number {
    if (!config.price?.enabled) {
      return originalPrice;
    }

    const tiers = config.price.tiers || [];
    
    // 查找匹配的区间：价格 >= minPrice 且 (maxPrice 为空 或 价格 < maxPrice)
    for (const tier of tiers) {
      const inRange = originalPrice >= tier.minPrice && 
        (tier.maxPrice === null || originalPrice < tier.maxPrice);
      
      if (inRange) {
        const result = originalPrice * tier.multiplier + tier.adjustment;
        return Math.round(result * 100) / 100; // 保留两位小数
      }
    }

    // 无匹配区间，使用默认值
    const result = originalPrice * config.price.defaultMultiplier + config.price.defaultAdjustment;
    return Math.round(result * 100) / 100;
  }

  // 根据同步配置计算最终库存
  calculateFinalStock(originalStock: number, config: ShopSyncConfigDto): number {
    if (!config.inventory?.enabled) {
      return originalStock;
    }

    let finalStock = Math.floor(originalStock * config.inventory.multiplier + config.inventory.adjustment);

    // 应用最小库存限制
    if (finalStock < config.inventory.minStock) {
      finalStock = 0;
    }

    // 应用最大库存限制
    if (config.inventory.maxStock !== null && finalStock > config.inventory.maxStock) {
      finalStock = config.inventory.maxStock;
    }

    return Math.max(0, finalStock);
  }
}
