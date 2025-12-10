import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '../../common/prisma/prisma.service';

@Injectable()
export class UpcService {
  constructor(private prisma: PrismaService) {}

  /**
   * 获取 UPC 池统计信息
   */
  async getStats() {
    const [total, used, available] = await Promise.all([
      this.prisma.upcPool.count(),
      this.prisma.upcPool.count({ where: { isUsed: true } }),
      this.prisma.upcPool.count({ where: { isUsed: false } }),
    ]);
    return { total, used, available };
  }

  /**
   * 获取 UPC 列表（分页）
   */
  async list(params: {
    page?: number;
    pageSize?: number;
    search?: string;
    status?: 'all' | 'used' | 'available';
  }) {
    const { page = 1, pageSize = 50, search, status = 'all' } = params;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (search) {
      where.OR = [
        { upcCode: { contains: search, mode: 'insensitive' } },
        { productSku: { contains: search, mode: 'insensitive' } },
      ];
    }
    if (status === 'used') {
      where.isUsed = true;
    } else if (status === 'available') {
      where.isUsed = false;
    }

    const [data, total] = await Promise.all([
      this.prisma.upcPool.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.upcPool.count({ where }),
    ]);

    return { data, total, page, pageSize };
  }


  /**
   * 批量导入 UPC
   */
  async import(upcCodes: string[]) {
    const validCodes = upcCodes
      .map(code => code.trim())
      .filter(code => code && /^\d{12,14}$/.test(code)); // UPC-A 12位，EAN-13 13位，GTIN-14 14位

    if (validCodes.length === 0) {
      throw new BadRequestException('没有有效的 UPC 码');
    }

    // 过滤已存在的
    const existing = await this.prisma.upcPool.findMany({
      where: { upcCode: { in: validCodes } },
      select: { upcCode: true },
    });
    const existingSet = new Set(existing.map(e => e.upcCode));
    const newCodes = validCodes.filter(code => !existingSet.has(code));

    if (newCodes.length === 0) {
      return { imported: 0, skipped: validCodes.length, message: '所有 UPC 码已存在' };
    }

    // 批量插入
    await this.prisma.upcPool.createMany({
      data: newCodes.map(code => ({ upcCode: code })),
      skipDuplicates: true,
    });

    return {
      imported: newCodes.length,
      skipped: validCodes.length - newCodes.length,
      message: `成功导入 ${newCodes.length} 个 UPC 码`,
    };
  }

  /**
   * 获取一个可用的 UPC（用于自动分配）
   */
  async getAvailableUpc(shopId?: string): Promise<string | null> {
    const upc = await this.prisma.upcPool.findFirst({
      where: { isUsed: false },
      orderBy: { createdAt: 'asc' },
    });
    return upc?.upcCode || null;
  }

  /**
   * 分配 UPC 给商品
   */
  async assignUpc(upcCode: string, productSku: string, shopId?: string) {
    const upc = await this.prisma.upcPool.findUnique({
      where: { upcCode },
    });

    if (!upc) {
      throw new NotFoundException('UPC 不存在');
    }

    if (upc.isUsed) {
      throw new BadRequestException('UPC 已被使用');
    }

    return this.prisma.upcPool.update({
      where: { upcCode },
      data: {
        isUsed: true,
        productSku,
        shopId,
        usedAt: new Date(),
      },
    });
  }

  /**
   * 自动分配一个可用的 UPC
   */
  async autoAssignUpc(productSku: string, shopId?: string): Promise<string | null> {
    // 先检查是否已有分配
    const existing = await this.prisma.upcPool.findFirst({
      where: { productSku, isUsed: true },
    });
    if (existing) {
      return existing.upcCode;
    }

    // 获取一个可用的 UPC
    const available = await this.prisma.upcPool.findFirst({
      where: { isUsed: false },
      orderBy: { createdAt: 'asc' },
    });

    if (!available) {
      return null; // UPC 池已用尽
    }

    // 分配
    await this.prisma.upcPool.update({
      where: { id: available.id },
      data: {
        isUsed: true,
        productSku,
        shopId,
        usedAt: new Date(),
      },
    });

    return available.upcCode;
  }

  /**
   * 释放 UPC（标记为未使用）
   */
  async releaseUpc(upcCode: string) {
    return this.prisma.upcPool.update({
      where: { upcCode },
      data: {
        isUsed: false,
        productSku: null,
        shopId: null,
        usedAt: null,
      },
    });
  }

  /**
   * 批量释放 UPC
   */
  async batchRelease(ids: string[]) {
    return this.prisma.upcPool.updateMany({
      where: { id: { in: ids } },
      data: {
        isUsed: false,
        productSku: null,
        shopId: null,
        usedAt: null,
      },
    });
  }

  /**
   * 批量标记为已使用
   */
  async batchMarkUsed(ids: string[]) {
    return this.prisma.upcPool.updateMany({
      where: { id: { in: ids } },
      data: {
        isUsed: true,
        usedAt: new Date(),
      },
    });
  }

  /**
   * 删除 UPC
   */
  async delete(id: string) {
    return this.prisma.upcPool.delete({ where: { id } });
  }

  /**
   * 批量删除 UPC
   */
  async batchDelete(ids: string[]) {
    return this.prisma.upcPool.deleteMany({
      where: { id: { in: ids } },
    });
  }

  /**
   * 导出 UPC 列表
   */
  async export(status?: 'all' | 'used' | 'available') {
    const where: any = {};
    if (status === 'used') {
      where.isUsed = true;
    } else if (status === 'available') {
      where.isUsed = false;
    }

    return this.prisma.upcPool.findMany({
      where,
      orderBy: { createdAt: 'desc' },
    });
  }
}
