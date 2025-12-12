import { Injectable } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { PaginationDto } from '@/common/dto/pagination.dto';

@Injectable()
export class ListingFeedService {
  constructor(private prisma: PrismaService) {}

  /**
   * 创建 Feed 记录（如果已存在则更新）
   */
  async create(data: {
    shopId: string;
    feedId: string;
    feedType?: string;
    itemCount: number;
    submittedData?: any;
    productIds?: string[];
  }) {
    return this.prisma.listingFeedRecord.upsert({
      where: {
        shopId_feedId: {
          shopId: data.shopId,
          feedId: data.feedId,
        },
      },
      create: {
        shopId: data.shopId,
        feedId: data.feedId,
        feedType: data.feedType || 'item',
        itemCount: data.itemCount,
        status: 'RECEIVED',
        submittedData: data.submittedData,
        productIds: data.productIds,
      },
      update: {
        feedType: data.feedType || 'item',
        itemCount: data.itemCount,
        submittedData: data.submittedData,
        productIds: data.productIds,
      },
    });
  }

  /**
   * 更新 Feed 状态
   */
  async updateStatus(shopId: string, feedId: string, data: {
    status: 'RECEIVED' | 'INPROGRESS' | 'PROCESSED' | 'ERROR';
    successCount?: number;
    failCount?: number;
    errorMessage?: string;
    feedDetail?: any;
  }) {
    const updateData: any = { status: data.status };
    if (data.successCount !== undefined) updateData.successCount = data.successCount;
    if (data.failCount !== undefined) updateData.failCount = data.failCount;
    if (data.errorMessage) updateData.errorMessage = data.errorMessage;
    if (data.feedDetail) updateData.feedDetail = data.feedDetail;
    if (data.status === 'PROCESSED' || data.status === 'ERROR') {
      updateData.completedAt = new Date();
    }

    return this.prisma.listingFeedRecord.update({
      where: { shopId_feedId: { shopId, feedId } },
      data: updateData,
    });
  }

  /**
   * 获取 Feed 列表
   */
  async findAll(query: PaginationDto & { shopId?: string; status?: string }) {
    const { shopId, status } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 20;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (shopId) where.shopId = shopId;
    if (status) where.status = status;

    const [data, total] = await Promise.all([
      this.prisma.listingFeedRecord.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { select: { id: true, name: true } } },
      }),
      this.prisma.listingFeedRecord.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  /**
   * 获取单个 Feed 详情
   */
  async findOne(id: string) {
    return this.prisma.listingFeedRecord.findUnique({
      where: { id },
      include: { shop: { select: { id: true, name: true } } },
    });
  }

  /**
   * 根据 feedId 获取 Feed
   */
  async findByFeedId(shopId: string, feedId: string) {
    return this.prisma.listingFeedRecord.findUnique({
      where: { shopId_feedId: { shopId, feedId } },
      include: { shop: { select: { id: true, name: true } } },
    });
  }

  /**
   * 删除 Feed 记录
   */
  async remove(id: string) {
    await this.prisma.listingFeedRecord.delete({ where: { id } });
    return { success: true };
  }

  /**
   * 批量删除
   */
  async removeMany(ids: string[]) {
    const result = await this.prisma.listingFeedRecord.deleteMany({
      where: { id: { in: ids } },
    });
    return { success: true, deleted: result.count };
  }

  /**
   * 清理旧记录
   */
  async cleanup(days: number = 30) {
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - days);

    const result = await this.prisma.listingFeedRecord.deleteMany({
      where: { createdAt: { lt: cutoffDate } },
    });
    return { deleted: result.count };
  }
}
