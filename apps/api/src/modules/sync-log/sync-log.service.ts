import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { SyncLogQueryDto } from './dto/sync-log.dto';
import { PaginatedResult } from '@/common/dto/pagination.dto';
import { SyncLog, Prisma } from '@prisma/client';

@Injectable()
export class SyncLogService {
  constructor(private prisma: PrismaService) {}

  async findAll(query: SyncLogQueryDto): Promise<PaginatedResult<SyncLog>> {
    const { page = 1, pageSize = 20, syncRuleId, status } = query;
    const skip = (page - 1) * pageSize;

    const where: Prisma.SyncLogWhereInput = {};
    if (syncRuleId) where.syncRuleId = syncRuleId;
    if (status) where.status = status;

    const [data, total] = await Promise.all([
      this.prisma.syncLog.findMany({
        where,
        skip,
        take: pageSize,
        include: { syncRule: { include: { channel: true, shop: true } } },
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.syncLog.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  async findOne(id: string): Promise<SyncLog> {
    const log = await this.prisma.syncLog.findUnique({
      where: { id },
      include: { syncRule: { include: { channel: true, shop: true } } },
    });
    if (!log) throw new NotFoundException('日志不存在');
    return log;
  }

  async getStats() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const [total, todayCount, successCount, failCount] = await Promise.all([
      this.prisma.syncLog.count(),
      this.prisma.syncLog.count({ where: { createdAt: { gte: today } } }),
      this.prisma.syncLog.count({ where: { status: 'success' } }),
      this.prisma.syncLog.count({ where: { status: 'failed' } }),
    ]);

    return { total, todayCount, successCount, failCount };
  }
}
