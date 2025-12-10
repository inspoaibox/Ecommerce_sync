import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreateProductDto, UpdateProductDto, ProductQueryDto, SyncFromChannelDto } from './dto/product.dto';
import { PaginatedResult } from '@/common/dto/pagination.dto';
import { Product, Prisma } from '@prisma/client';

@Injectable()
export class ProductService {
  constructor(private prisma: PrismaService) {}

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

  // 批量导入平台SKU映射
  async importPlatformSku(shopId: string, mappings: { sku: string; platformSku: string }[]): Promise<{ updated: number; notFound: string[] }> {
    let updated = 0;
    const notFound: string[] = [];

    for (const { sku, platformSku } of mappings) {
      const product = await this.prisma.product.findFirst({
        where: { shopId, sku },
      });

      if (product) {
        await this.prisma.product.update({
          where: { id: product.id },
          data: { platformSku },
        });
        updated++;
      } else {
        notFound.push(sku);
      }
    }

    return { updated, notFound };
  }

  // 导入平台产品（从沃尔玛表格导入）
  async importProducts(
    shopId: string,
    channelId: string,
    products: { sku: string; platformSku?: string }[],
  ): Promise<{ created: number; updated: number; message: string }> {
    // 验证渠道存在
    const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) throw new NotFoundException('渠道不存在');

    let created = 0;
    let updated = 0;

    for (const item of products) {
      // 查找是否已存在
      const existing = await this.prisma.product.findFirst({
        where: { shopId, sku: item.sku },
      });

      if (existing) {
        // 更新渠道ID和平台SKU
        await this.prisma.product.update({
          where: { id: existing.id },
          data: {
            channelId,
            platformSku: item.platformSku || existing.platformSku,
          },
        });
        updated++;
      } else {
        // 创建新商品
        await this.prisma.product.create({
          data: {
            sku: item.sku,
            platformSku: item.platformSku || null,
            channelId,
            shopId,
            channelProductId: item.sku,
            title: item.sku, // 暂用SKU作为标题
            originalPrice: 0,
            finalPrice: 0,
            originalStock: 0,
            finalStock: 0,
            sourceChannel: channel.name,
            syncStatus: 'pending',
          },
        });
        created++;
      }
    }

    return {
      created,
      updated,
      message: `导入完成：新增 ${created} 个，更新 ${updated} 个`,
    };
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
