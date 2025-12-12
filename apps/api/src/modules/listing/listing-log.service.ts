import { Injectable } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { PaginationDto } from '@/common/dto/pagination.dto';

@Injectable()
export class ListingLogService {
  constructor(private prisma: PrismaService) {}

  /**
   * 创建刊登日志
   */
  async create(data: {
    shopId: string;
    action: 'submit' | 'validate' | 'retry' | 'update';
    productId?: string;
    productSku?: string;
    requestData?: any;
    feedId?: string;
  }) {
    return this.prisma.listingLog.create({
      data: {
        shopId: data.shopId,
        action: data.action,
        productId: data.productId,
        productSku: data.productSku,
        requestData: data.requestData,
        feedId: data.feedId,
        status: 'pending',
      },
    });
  }

  /**
   * 更新日志状态为处理中
   */
  async setProcessing(id: string) {
    return this.prisma.listingLog.update({
      where: { id },
      data: { status: 'processing' },
    });
  }

  /**
   * 完成日志（成功）
   */
  async complete(id: string, data: {
    responseData?: any;
    feedId?: string;
    duration?: number;
  }) {
    return this.prisma.listingLog.update({
      where: { id },
      data: {
        status: 'success',
        responseData: data.responseData,
        feedId: data.feedId,
        duration: data.duration || 0,
      },
    });
  }

  /**
   * 完成日志（失败）
   */
  async fail(id: string, data: {
    errorMessage: string;
    errorCode?: string;
    responseData?: any;
    duration?: number;
  }) {
    return this.prisma.listingLog.update({
      where: { id },
      data: {
        status: 'failed',
        errorMessage: data.errorMessage,
        errorCode: data.errorCode,
        responseData: data.responseData,
        duration: data.duration || 0,
      },
    });
  }

  /**
   * 获取日志列表
   */
  async findAll(query: PaginationDto & {
    shopId?: string;
    action?: string;
    status?: string;
    productSku?: string;
  }) {
    const { shopId, action, status, productSku } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 20;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (shopId) where.shopId = shopId;
    if (action) where.action = action;
    if (status) where.status = status;
    if (productSku) where.productSku = { contains: productSku, mode: 'insensitive' };

    const [data, total] = await Promise.all([
      this.prisma.listingLog.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { select: { id: true, name: true } } },
      }),
      this.prisma.listingLog.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  /**
   * 获取单个日志详情
   */
  async findOne(id: string) {
    return this.prisma.listingLog.findUnique({
      where: { id },
      include: { shop: { select: { id: true, name: true } } },
    });
  }

  /**
   * 删除日志
   */
  async remove(id: string) {
    await this.prisma.listingLog.delete({ where: { id } });
    return { success: true };
  }

  /**
   * 批量删除日志
   */
  async removeMany(ids: string[]) {
    const result = await this.prisma.listingLog.deleteMany({
      where: { id: { in: ids } },
    });
    return { success: true, deleted: result.count };
  }

  /**
   * 清理旧日志（保留最近30天）
   */
  async cleanup(days: number = 30) {
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - days);

    const result = await this.prisma.listingLog.deleteMany({
      where: { createdAt: { lt: cutoffDate } },
    });
    return { deleted: result.count };
  }
}
