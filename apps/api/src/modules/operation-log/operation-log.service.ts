import { Injectable } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { PaginationDto } from '@/common/dto/pagination.dto';

@Injectable()
export class OperationLogService {
  constructor(private prisma: PrismaService) {}

  // 创建日志
  async create(data: {
    shopId?: string;
    type: string;
    total?: number;
    message?: string;
    detail?: any;
  }) {
    return this.prisma.operationLog.create({
      data: {
        shopId: data.shopId,
        type: data.type,
        total: data.total || 0,
        status: 'running',
        message: data.message,
        detail: data.detail,
      },
    });
  }

  // 更新进度
  async updateProgress(id: string, processed: number, success: number, failed: number) {
    return this.prisma.operationLog.update({
      where: { id },
      data: { processed, success, failed },
    });
  }

  // 完成日志
  async complete(id: string, data: { success: number; failed: number; message?: string }) {
    return this.prisma.operationLog.update({
      where: { id },
      data: {
        status: 'completed',
        success: data.success,
        failed: data.failed,
        message: data.message,
        finishedAt: new Date(),
      },
    });
  }

  // 失败日志
  async fail(id: string, message: string) {
    return this.prisma.operationLog.update({
      where: { id },
      data: {
        status: 'failed',
        message,
        finishedAt: new Date(),
      },
    });
  }


  // 获取日志列表
  async findAll(query: PaginationDto & { shopId?: string; type?: string }) {
    const { shopId, type } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 20;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (shopId) where.shopId = shopId;
    if (type) where.type = type;

    const [data, total] = await Promise.all([
      this.prisma.operationLog.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { select: { id: true, name: true } } },
      }),
      this.prisma.operationLog.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  // 获取单个日志
  async findOne(id: string) {
    return this.prisma.operationLog.findUnique({
      where: { id },
      include: { shop: { select: { id: true, name: true } } },
    });
  }

  // 删除日志
  async remove(id: string) {
    await this.prisma.operationLog.delete({ where: { id } });
    return { success: true };
  }

  // 清理旧日志（保留最近30天）
  async cleanup() {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    const result = await this.prisma.operationLog.deleteMany({
      where: { createdAt: { lt: thirtyDaysAgo } },
    });
    return { deleted: result.count };
  }
}
