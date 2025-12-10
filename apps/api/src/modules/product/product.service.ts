import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreateProductDto, UpdateProductDto, ProductQueryDto, SyncFromChannelDto } from './dto/product.dto';
import { PaginatedResult } from '@/common/dto/pagination.dto';
import { Product, Prisma } from '@prisma/client';
import { OperationLogService } from '@/modules/operation-log/operation-log.service';
import { ChannelAdapterFactory } from '@/adapters/channels';

@Injectable()
export class ProductService {
  constructor(
    private prisma: PrismaService,
    private operationLog: OperationLogService,
  ) {}

  async findAll(query: ProductQueryDto): Promise<PaginatedResult<Product>> {
    const { page = 1, pageSize = 20, shopId, syncRuleId, sku, skus, keyword, syncStatus } = query;
    const skip = (page - 1) * pageSize;

    const where: Prisma.ProductWhereInput = {};
    
    // 处理店铺筛选
    if (shopId === 'unassigned') {
      where.shopId = { equals: null };
    } else if (shopId) {
      where.shopId = shopId;
    }
    
    if (syncRuleId) where.syncRuleId = syncRuleId;
    
    // 批量 SKU 搜索（优先级高于单个 SKU）
    if (skus) {
      const skuList = skus.split(',').map(s => s.trim()).filter(s => s.length > 0);
      if (skuList.length > 0) {
        where.sku = { in: skuList };
      }
    } else if (sku) {
      where.sku = { contains: sku };
    }
    
    if (syncStatus) where.syncStatus = syncStatus;
    
    // 关键词搜索（SKU或标题）
    if (keyword) {
      where.OR = [
        { sku: { contains: keyword, mode: 'insensitive' } },
        { title: { contains: keyword, mode: 'insensitive' } },
      ];
    }

    const [data, total] = await Promise.all([
      this.prisma.product.findMany({
        where,
        skip,
        take: pageSize,
        include: { shop: true, syncRule: true },
        orderBy: { updatedAt: 'desc' },
      }),
      this.prisma.product.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  async findOne(id: string): Promise<Product> {
    const product = await this.prisma.product.findUnique({
      where: { id },
      include: { shop: true, syncRule: true },
    });
    if (!product) throw new NotFoundException('商品不存在');
    return product;
  }

  async update(id: string, dto: UpdateProductDto): Promise<Product> {
    await this.findOne(id);
    return this.prisma.product.update({ where: { id }, data: dto });
  }

  async remove(id: string): Promise<void> {
    await this.findOne(id);
    await this.prisma.product.delete({ where: { id } });
  }

  async batchDelete(ids: string[]): Promise<{ count: number }> {
    const result = await this.prisma.product.deleteMany({
      where: { id: { in: ids } },
    });
    return { count: result.count };
  }

  // 导出商品（不受分页限制）
  async exportProducts(query: ProductQueryDto): Promise<Product[]> {
    const { shopId, syncRuleId, sku, skus, keyword, syncStatus } = query;

    const where: Prisma.ProductWhereInput = {};
    
    if (shopId === 'unassigned') {
      where.shopId = { equals: null };
    } else if (shopId) {
      where.shopId = shopId;
    }
    
    if (syncRuleId) where.syncRuleId = syncRuleId;
    
    if (skus) {
      const skuList = skus.split(',').map(s => s.trim()).filter(s => s.length > 0);
      if (skuList.length > 0) {
        where.sku = { in: skuList };
      }
    } else if (sku) {
      where.sku = { contains: sku };
    }
    
    if (syncStatus) where.syncStatus = syncStatus;
    
    if (keyword) {
      where.OR = [
        { sku: { contains: keyword, mode: 'insensitive' } },
        { title: { contains: keyword, mode: 'insensitive' } },
      ];
    }

    return this.prisma.product.findMany({
      where,
      include: { shop: true },
      orderBy: { updatedAt: 'desc' },
    });
  }

  async assignToShop(ids: string[], shopId: string): Promise<{ count: number }> {
    const result = await this.prisma.product.updateMany({
      where: { id: { in: ids } },
      data: { shopId },
    });
    return { count: result.count };
  }

  // 更新单个商品的平台SKU
  async updatePlatformSku(id: string, platformSku: string): Promise<Product> {
    await this.findOne(id);
    return this.prisma.product.update({
      where: { id },
      data: { platformSku: platformSku || null },
    });
  }

  // 批量导入平台SKU映射 - 分批处理优化
  async importPlatformSku(shopId: string, mappings: { sku: string; platformSku: string }[]): Promise<{ updated: number; notFound: string[] }> {
    const BATCH_SIZE = 1000;
    const UPDATE_BATCH = 100;
    let totalUpdated = 0;
    const notFound: string[] = [];

    // 分批处理
    for (let i = 0; i < mappings.length; i += BATCH_SIZE) {
      const batch = mappings.slice(i, i + BATCH_SIZE);
      const inputSkus = batch.map(m => m.sku);
      
      // 批量查询已存在的商品
      const existingProducts = await this.prisma.product.findMany({
        where: { shopId, sku: { in: inputSkus } },
        select: { id: true, sku: true },
      });
      const existingSkuMap = new Map(existingProducts.map(p => [p.sku, p.id]));

      // 分离找到和未找到的
      const toUpdate: { id: string; platformSku: string }[] = [];

      for (const { sku, platformSku } of batch) {
        const productId = existingSkuMap.get(sku);
        if (productId) {
          toUpdate.push({ id: productId, platformSku });
        } else {
          notFound.push(sku);
        }
      }

      // 分小批更新
      for (let j = 0; j < toUpdate.length; j += UPDATE_BATCH) {
        const updateBatch = toUpdate.slice(j, j + UPDATE_BATCH);
        await this.prisma.$transaction(
          updateBatch.map(item =>
            this.prisma.product.update({
              where: { id: item.id },
              data: { platformSku: item.platformSku },
            })
          )
        );
      }
      totalUpdated += toUpdate.length;
    }

    return { updated: totalUpdated, notFound };
  }

  // 导入平台产品（从沃尔玛表格导入）- 分批处理优化
  async importProducts(
    shopId: string,
    channelId: string,
    products: { sku: string; platformSku?: string }[],
  ): Promise<{ created: number; updated: number; message: string; logId?: string }> {
    // 验证渠道存在
    const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) throw new NotFoundException('渠道不存在');

    // 创建操作日志
    const log = await this.operationLog.create({
      shopId,
      type: 'import_products',
      total: products.length,
      message: `导入平台产品，来源渠道: ${channel.name}`,
      detail: { channelId, channelName: channel.name },
    });

    const BATCH_SIZE = 1000; // 每批处理1000条
    let totalCreated = 0;
    let totalUpdated = 0;

    try {
    // 分批处理
    for (let i = 0; i < products.length; i += BATCH_SIZE) {
      const batch = products.slice(i, i + BATCH_SIZE);
      const inputSkus = batch.map(p => p.sku);
      
      // 批量查询已存在的商品
      const existingProducts = await this.prisma.product.findMany({
        where: { shopId, sku: { in: inputSkus } },
        select: { id: true, sku: true, platformSku: true },
      });
      const existingSkuMap = new Map(existingProducts.map(p => [p.sku, p]));

      // 分离需要创建和更新的数据
      const toCreate: any[] = [];
      const toUpdate: { id: string; channelId: string; platformSku: string | null }[] = [];

      for (const item of batch) {
        const existing = existingSkuMap.get(item.sku);
        if (existing) {
          toUpdate.push({
            id: existing.id,
            channelId,
            platformSku: item.platformSku || existing.platformSku,
          });
        } else {
          toCreate.push({
            sku: item.sku,
            platformSku: item.platformSku || null,
            channelId,
            shopId,
            channelProductId: item.sku,
            title: '',
            originalPrice: 0,
            finalPrice: 0,
            originalStock: 0,
            finalStock: 0,
            sourceChannel: channel.name,
            syncStatus: 'pending',
          });
        }
      }

      // 批量创建
      if (toCreate.length > 0) {
        const result = await this.prisma.product.createMany({
          data: toCreate,
          skipDuplicates: true,
        });
        totalCreated += result.count;
      }

      // 批量更新（分小批事务，每100条一个事务）
      const UPDATE_BATCH = 100;
      for (let j = 0; j < toUpdate.length; j += UPDATE_BATCH) {
        const updateBatch = toUpdate.slice(j, j + UPDATE_BATCH);
        await this.prisma.$transaction(
          updateBatch.map(item =>
            this.prisma.product.update({
              where: { id: item.id },
              data: { channelId: item.channelId, platformSku: item.platformSku },
            })
          )
        );
      }
      totalUpdated += toUpdate.length;

      // 更新日志进度
      await this.operationLog.updateProgress(log.id, i + batch.length, totalCreated, 0);
      console.log(`[ImportProducts] Batch ${Math.floor(i / BATCH_SIZE) + 1}: created ${toCreate.length}, updated ${toUpdate.length}`);
    }

    // 完成日志
    await this.operationLog.complete(log.id, {
      success: totalCreated + totalUpdated,
      failed: 0,
      message: `导入完成：新增 ${totalCreated} 个，更新 ${totalUpdated} 个`,
    });

    return {
      created: totalCreated,
      updated: totalUpdated,
      message: `导入完成：新增 ${totalCreated} 个，更新 ${totalUpdated} 个`,
      logId: log.id,
    };
    } catch (error: any) {
      // 记录失败
      await this.operationLog.fail(log.id, error.message);
      throw error;
    }
  }

  // 从渠道获取最新价格/库存
  async fetchLatestFromChannel(
    shopId: string,
    productIds: string[] | undefined,
    fetchType: 'price' | 'inventory' | 'both',
  ): Promise<{ updated: number; message: string; logId?: string }> {
    // 获取商品列表
    const where: any = { shopId };
    if (productIds && productIds.length > 0) {
      where.id = { in: productIds };
    }

    const products = await this.prisma.product.findMany({
      where,
      select: { id: true, sku: true, channelId: true, originalPrice: true, originalStock: true },
    });

    if (products.length === 0) {
      return { updated: 0, message: '没有找到商品' };
    }

    // 创建操作日志
    const log = await this.operationLog.create({
      shopId,
      type: 'fetch_latest',
      total: products.length,
      message: `获取最新${fetchType === 'price' ? '价格' : fetchType === 'inventory' ? '库存' : '价格+库存'}`,
      detail: { fetchType, productCount: products.length },
    });

    try {
      // 按渠道分组
      const productsByChannel: Record<string, { id: string; sku: string; originalPrice: any; originalStock: number }[]> = {};
      for (const p of products) {
        if (p.channelId) {
          if (!productsByChannel[p.channelId]) {
            productsByChannel[p.channelId] = [];
          }
          productsByChannel[p.channelId].push(p);
        }
      }

      let totalUpdated = 0;

      // 渠道速率限制配置
      const CHANNEL_RATE_LIMITS: Record<string, { batchSize: number; batchDelay: number }> = {
        gigacloud: { batchSize: 200, batchDelay: 1500 },
        saleyee: { batchSize: 30, batchDelay: 1000 },
        default: { batchSize: 50, batchDelay: 1500 },
      };

      // 对每个渠道获取数据
      for (const [channelId, channelProducts] of Object.entries(productsByChannel)) {
        const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
        if (!channel) continue;

        const rateLimit = CHANNEL_RATE_LIMITS[channel.type] || CHANNEL_RATE_LIMITS.default;
        const { batchSize, batchDelay } = rateLimit;

        // 创建渠道适配器
        const adapter = ChannelAdapterFactory.create(channel.type, channel.apiConfig as any) as any;
        if (typeof adapter.fetchProductsBySkus !== 'function') {
          console.log(`[FetchLatest] Channel ${channel.name} does not support fetchProductsBySkus`);
          continue;
        }

        // 分批查询
        const skus = channelProducts.map(p => p.sku);
        for (let i = 0; i < skus.length; i += batchSize) {
          const batchSkus = skus.slice(i, i + batchSize);

          try {
            const result = await adapter.fetchProductsBySkus(batchSkus);
            const resultMap = new Map(result.map((r: any) => [r.sku, r]));

            // 更新商品
            for (const product of channelProducts.filter(p => batchSkus.includes(p.sku))) {
              const channelData = resultMap.get(product.sku) as any;
              if (!channelData) continue;

              const updateData: any = {};
              if (fetchType === 'price' || fetchType === 'both') {
                if (channelData.price != null) {
                  updateData.originalPrice = channelData.price;
                  // 计算本地价格（原价 + 运费）
                  const shippingFee = channelData.extraFields?.shippingFee || 0;
                  updateData.localPrice = channelData.price + shippingFee;
                }
                // 保存额外字段（运费、优惠价等）
                if (channelData.extraFields) {
                  updateData.extraFields = channelData.extraFields;
                }
              }
              if (fetchType === 'inventory' || fetchType === 'both') {
                updateData.originalStock = channelData.stock ?? 0;
                updateData.localStock = channelData.stock ?? 0;
              }

              if (Object.keys(updateData).length > 0) {
                await this.prisma.product.update({
                  where: { id: product.id },
                  data: updateData,
                });
                totalUpdated++;
              }
            }

            console.log(`[FetchLatest] Channel ${channel.name}: batch ${Math.floor(i / batchSize) + 1}, updated ${batchSkus.length}`);
          } catch (error: any) {
            console.error(`[FetchLatest] Batch fetch failed:`, error.message);
          }

          // 批次间延迟
          if (i + batchSize < skus.length) {
            await new Promise(resolve => setTimeout(resolve, batchDelay));
          }
        }

        // 更新日志进度
        await this.operationLog.updateProgress(log.id, totalUpdated, totalUpdated, 0);
      }

      // 完成日志
      await this.operationLog.complete(log.id, {
        success: totalUpdated,
        failed: 0,
        message: `获取完成：更新 ${totalUpdated} 个商品`,
      });

      return {
        updated: totalUpdated,
        message: `获取完成：更新 ${totalUpdated} 个商品`,
        logId: log.id,
      };
    } catch (error: any) {
      await this.operationLog.fail(log.id, error.message);
      throw error;
    }
  }

  async syncFromChannel(dto: SyncFromChannelDto): Promise<{ created: number; updated: number }> {
    const { channelId, products, shopId } = dto;
    
    // 获取渠道信息
    const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) throw new NotFoundException('渠道不存在');

    let created = 0;
    let updated = 0;

    for (const product of products) {
      // 计算本地价格（总价 = 原价 + 运费）
      const shippingFee = product.extraFields?.shippingFee || 0;
      const localPrice = product.price != null ? product.price + shippingFee : null;

      const productData = {
        sku: product.sku,
        title: product.title || '',
        originalPrice: product.price || 0,
        finalPrice: 0, // 平台价格初始为0，需要通过同步规则计算
        originalStock: product.stock || 0,
        finalStock: 0, // 平台库存初始为0，需要通过同步规则计算
        localPrice: localPrice,
        localStock: product.stock || 0,
        currency: product.currency || 'USD',
        channelId: channelId,
        sourceChannel: channel.name,
        extraFields: product.extraFields || {},
        shopId: shopId || null,
      };

      // 先查找是否存在
      const existingProduct = await this.prisma.product.findFirst({
        where: {
          shopId: shopId || null,
          sourceChannel: channel.name,
          sku: product.sku,
        },
      });

      if (existingProduct) {
        // 更新现有商品
        await this.prisma.product.update({
          where: { id: existingProduct.id },
          data: productData,
        });
        updated++;
      } else {
        // 创建新商品
        await this.prisma.product.create({
          data: {
            ...productData,
            channelProductId: product.sku,
            syncStatus: 'pending',
          },
        });
        created++;
      }
    }

    return { created, updated };
  }
}
