import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreateProductDto, UpdateProductDto, ProductQueryDto, SyncFromChannelDto } from './dto/product.dto';
import { PaginatedResult } from '@/common/dto/pagination.dto';
import { Product, Prisma } from '@prisma/client';

@Injectable()
export class ProductService {
  constructor(private prisma: PrismaService) {}

  async findAll(query: ProductQueryDto): Promise<PaginatedResult<Product>> {
    const { page = 1, pageSize = 20, shopId, syncRuleId, sku, keyword, syncStatus } = query;
    const skip = (page - 1) * pageSize;

    const where: Prisma.ProductWhereInput = {};
    
    // 处理店铺筛选
    if (shopId === 'unassigned') {
      where.shopId = { equals: null };
    } else if (shopId) {
      where.shopId = shopId;
    }
    
    if (syncRuleId) where.syncRuleId = syncRuleId;
    if (sku) where.sku = { contains: sku };
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

  async assignToShop(ids: string[], shopId: string): Promise<{ count: number }> {
    const result = await this.prisma.product.updateMany({
      where: { id: { in: ids } },
      data: { shopId },
    });
    return { count: result.count };
  }

  async syncFromChannel(dto: SyncFromChannelDto): Promise<{ created: number; updated: number }> {
    const { channelId, products, shopId } = dto;
    
    // 获取渠道信息
    const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) throw new NotFoundException('渠道不存在');

    let created = 0;
    let updated = 0;

    for (const product of products) {
      const existingProduct = await this.prisma.product.findFirst({
        where: { 
          sku: product.sku,
          sourceChannel: channel.name,
        },
      });

      const productData = {
        sku: product.sku,
        title: product.title || '',
        originalPrice: product.price || 0,
        finalPrice: product.price || 0,
        originalStock: product.stock || 0,
        finalStock: product.stock || 0,
        currency: product.currency || 'USD',
        sourceChannel: channel.name,
        extraFields: product.extraFields || {},
        shopId: shopId || null,
      };

      if (existingProduct) {
        await this.prisma.product.update({
          where: { id: existingProduct.id },
          data: {
            ...productData,
            shopId: shopId || undefined,
          },
        });
        updated++;
      } else {
        await this.prisma.product.create({
          data: {
            ...productData,
            channelProductId: product.sku,
            syncStatus: 'pending',
            shopId: shopId || undefined,
          },
        });
        created++;
      }
    }

    return { created, updated };
  }
}
