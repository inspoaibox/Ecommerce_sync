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

  // 同步价格/库存到沃尔玛（自动分批，每批最多9800条）
  async syncToWalmart(
    id: string,
    productIds: string[],
    syncType: 'price' | 'inventory' | 'both'
  ): Promise<{ success: boolean; message: string; feedId?: string; feedIds?: string[] }> {
    const BATCH_SIZE = 9800; // 每批最多9800条，确保Feed详情可完整查看
    
    const shop = await this.findOne(id);
    const platformCode = (shop as any).platform?.code || shop.platformId;

    if (platformCode !== 'walmart') {
      throw new Error('此功能仅支持沃尔玛平台');
    }

    const products = await this.prisma.product.findMany({
      where: { id: { in: productIds }, shopId: id },
    });

    if (products.length === 0) {
      throw new Error('未找到要同步的商品');
    }

    const adapter = PlatformAdapterFactory.create(
      platformCode,
      shop.apiCredentials as Record<string, any>,
    ) as any;

    if ((syncType === 'price' || syncType === 'both') && typeof adapter.batchUpdatePrices !== 'function') {
      throw new Error('沃尔玛适配器不支持批量价格更新');
    }
    if ((syncType === 'inventory' || syncType === 'both') && typeof adapter.batchUpdateInventory !== 'function') {
      throw new Error('沃尔玛适配器不支持批量库存更新');
    }

    try {
      const syncConfig = await this.getSyncConfig(id);
      const feedIds: string[] = [];
      const useDiscountedPrice = syncConfig.price.useDiscountedPrice ?? false;

      // 同步价格
      if (syncType === 'price' || syncType === 'both') {
        const priceItems = products
          .filter(p => {
            const extraFields = p.extraFields as any;
            const discountedPrice = extraFields?.discountedPrice;
            const shippingFee = extraFields?.shippingFee || 0;
            if (useDiscountedPrice && discountedPrice && discountedPrice > 0) {
              return (discountedPrice + shippingFee) > 0;
            }
            const sourcePrice = syncConfig.price.source === 'local' && p.localPrice !== null
              ? Number(p.localPrice) : Number(p.originalPrice);
            return sourcePrice > 0;
          })
          .map(p => {
            const extraFields = p.extraFields as any;
            const discountedPrice = extraFields?.discountedPrice;
            const shippingFee = extraFields?.shippingFee || 0;
            let sourcePrice: number;
            if (useDiscountedPrice && discountedPrice && discountedPrice > 0) {
              sourcePrice = discountedPrice + shippingFee;
            } else if (syncConfig.price.source === 'local' && p.localPrice !== null) {
              sourcePrice = Number(p.localPrice);
            } else {
              sourcePrice = Number(p.originalPrice);
            }
            return {
              sku: p.platformSku || p.sku,
              price: this.calculateFinalPrice(sourcePrice, syncConfig),
              msrp: Number(p.originalPrice) || this.calculateFinalPrice(sourcePrice, syncConfig),
            };
          });

        if (priceItems.length === 0) {
          console.log(`[Shop ${id}] No valid price items to sync`);
        } else {
          // 分批提交价格
          for (let i = 0; i < priceItems.length; i += BATCH_SIZE) {
            const batch = priceItems.slice(i, i + BATCH_SIZE);
            const batchNum = Math.floor(i / BATCH_SIZE) + 1;
            const totalBatches = Math.ceil(priceItems.length / BATCH_SIZE);
            
            const priceResult = await adapter.batchUpdatePrices(batch);
            feedIds.push(priceResult.feedId);
            console.log(`[Shop ${id}] Price feed ${batchNum}/${totalBatches} submitted: ${priceResult.feedId}, items: ${batch.length}`);

            const feedData: Record<string, { price: number }> = {};
            for (const item of batch) {
              feedData[item.sku] = { price: item.price };
            }
            await this.prisma.feedRecord.create({
              data: {
                shopId: id,
                feedId: priceResult.feedId,
                syncType: 'price',
                itemCount: batch.length,
                status: 'RECEIVED',
                feedDetail: { submittedData: feedData, batchInfo: { batch: batchNum, total: totalBatches } },
              },
            });

            // 批次间延迟
            if (i + BATCH_SIZE < priceItems.length) {
              await new Promise(resolve => setTimeout(resolve, 1000));
            }
          }
        }
      }

      // 同步库存
      if (syncType === 'inventory' || syncType === 'both') {
        const inventoryItems = products.map(p => ({
          sku: p.platformSku || p.sku,
          quantity: this.calculateFinalStock(p.originalStock ?? 0, syncConfig),
        }));

        // 分批提交库存
        for (let i = 0; i < inventoryItems.length; i += BATCH_SIZE) {
          const batch = inventoryItems.slice(i, i + BATCH_SIZE);
          const batchNum = Math.floor(i / BATCH_SIZE) + 1;
          const totalBatches = Math.ceil(inventoryItems.length / BATCH_SIZE);

          const inventoryResult = await adapter.batchUpdateInventory(batch);
          feedIds.push(inventoryResult.feedId);
          console.log(`[Shop ${id}] Inventory feed ${batchNum}/${totalBatches} submitted: ${inventoryResult.feedId}, items: ${batch.length}`);

          const feedData: Record<string, { quantity: number }> = {};
          for (const item of batch) {
            feedData[item.sku] = { quantity: item.quantity };
          }
          await this.prisma.feedRecord.create({
            data: {
              shopId: id,
              feedId: inventoryResult.feedId,
              syncType: 'inventory',
              itemCount: batch.length,
              status: 'RECEIVED',
              feedDetail: { submittedData: feedData, batchInfo: { batch: batchNum, total: totalBatches } },
            },
          });

          // 批次间延迟
          if (i + BATCH_SIZE < inventoryItems.length) {
            await new Promise(resolve => setTimeout(resolve, 1000));
          }
        }
      }

      // 更新商品同步状态
      await this.prisma.product.updateMany({
        where: { id: { in: productIds } },
        data: { syncStatus: 'pending', updatedAt: new Date() },
      });

      const typeText = syncType === 'price' ? '价格' : syncType === 'inventory' ? '库存' : '价格和库存';
      const batchInfo = feedIds.length > 1 ? `（分 ${feedIds.length} 批提交）` : '';
      return {
        success: true,
        message: `成功提交${typeText}同步任务，共 ${products.length} 个商品${batchInfo}`,
        feedId: feedIds[0],
        feedIds,
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

  // 删除Feed记录
  async deleteFeed(shopId: string, feedId: string) {
    const feedRecord = await this.prisma.feedRecord.findUnique({
      where: { shopId_feedId: { shopId, feedId } },
    });
    
    if (!feedRecord) {
      throw new Error('Feed记录不存在');
    }

    await this.prisma.feedRecord.delete({
      where: { shopId_feedId: { shopId, feedId } },
    });

    return { success: true, message: 'Feed记录已删除' };
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

  // 获取Feed详情（优先读取缓存，默认只获取失败数据）
  // statusFilter: 'failed' | 'success' | 'all'
  async getFeedDetail(shopId: string, feedId: string, statusFilter: 'failed' | 'success' | 'all' = 'failed') {
    // 获取本地保存的 Feed 记录
    const feedRecord = await this.prisma.feedRecord.findUnique({
      where: { shopId_feedId: { shopId, feedId } },
    });
    
    if (!feedRecord) {
      throw new Error('Feed记录不存在');
    }

    const feedDetail = feedRecord.feedDetail as any || {};
    const submittedData = feedDetail.submittedData || {};
    
    // 检查是否有对应状态的缓存数据
    const cacheKey = `itemDetails_${statusFilter}`;
    if (feedDetail[cacheKey]) {
      return {
        feedId: feedDetail.feedId || feedId,
        feedStatus: feedDetail.feedStatus || feedRecord.status,
        itemsReceived: feedDetail.itemsReceived,
        itemsSucceeded: feedDetail.itemsSucceeded,
        itemsFailed: feedDetail.itemsFailed,
        itemDetails: feedDetail[cacheKey],
        submittedData,
        statusFilter,
        cached: true,
        cachedAt: feedDetail[`cachedAt_${statusFilter}`],
      };
    }

    // 没有缓存，从 API 获取
    return this.refreshFeedDetail(shopId, feedId, statusFilter);
  }

  // 强制刷新Feed详情（从API获取并缓存）
  // statusFilter: 'failed' | 'success' | 'all'
  async refreshFeedDetail(shopId: string, feedId: string, statusFilter: 'failed' | 'success' | 'all' = 'failed') {
    const shop = await this.findOne(shopId);
    const platformCode = (shop as any).platform?.code || shop.platformId;

    if (platformCode !== 'walmart') {
      throw new Error('此功能仅支持沃尔玛平台');
    }

    // 获取本地保存的 Feed 记录
    const feedRecord = await this.prisma.feedRecord.findUnique({
      where: { shopId_feedId: { shopId, feedId } },
    });
    
    if (!feedRecord) {
      throw new Error('Feed记录不存在');
    }

    const existingDetail = feedRecord.feedDetail as any || {};
    const submittedData = existingDetail.submittedData || {};

    const adapter = PlatformAdapterFactory.create(
      platformCode,
      shop.apiCredentials as Record<string, any>,
    ) as any;

    if (typeof adapter.getFeedStatusAll !== 'function') {
      throw new Error('沃尔玛适配器不支持Feed状态查询');
    }

    try {
      // 使用 getFeedStatusAll 获取明细（按状态筛选）
      const feedStatus = await adapter.getFeedStatusAll(feedId, statusFilter);

      // 缓存到数据库（按状态分别缓存）
      const cacheKey = `itemDetails_${statusFilter}`;
      const cachedDetail = {
        ...existingDetail,
        submittedData,
        feedId: feedStatus.feedId,
        feedStatus: feedStatus.feedStatus,
        itemsReceived: feedStatus.itemsReceived,
        itemsSucceeded: feedStatus.itemsSucceeded,
        itemsFailed: feedStatus.itemsFailed,
        [cacheKey]: feedStatus.itemDetails,
        [`cachedAt_${statusFilter}`]: new Date().toISOString(),
      };

      await this.prisma.feedRecord.update({
        where: { shopId_feedId: { shopId, feedId } },
        data: { feedDetail: cachedDetail },
      });

      console.log(`[Shop ${shopId}] Feed detail (${statusFilter}) cached: ${feedId}, items: ${feedStatus.itemDetails?.itemIngestionStatus?.length || 0}`);

      return { 
        ...feedStatus, 
        submittedData, 
        statusFilter,
        cached: false,
      };
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
