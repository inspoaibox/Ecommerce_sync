import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectQueue } from '@nestjs/bullmq';
import { Queue } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { QUEUE_NAMES } from '@/queues/constants';
import { PaginationDto } from '@/common/dto/pagination.dto';

@Injectable()
export class AutoSyncService {
  constructor(
    private prisma: PrismaService,
    @InjectQueue(QUEUE_NAMES.AUTO_SYNC) private autoSyncQueue: Queue,
  ) {}

  // 获取店铺的自动同步配置
  async getConfig(shopId: string) {
    const config = await this.prisma.autoSyncConfig.findUnique({
      where: { shopId },
      include: { shop: { select: { id: true, name: true } } },
    });
    return config;
  }

  // 获取所有自动同步配置列表
  async getConfigs() {
    const configs = await this.prisma.autoSyncConfig.findMany({
      include: { shop: { select: { id: true, name: true } } },
      orderBy: { createdAt: 'desc' },
    });
    return configs;
  }

  // 更新自动同步配置
  async updateConfig(shopId: string, data: {
    enabled?: boolean;
    intervalDays?: number;
    syncHour?: number;
    syncType?: string;
  }) {
    // 检查店铺是否存在
    const shop = await this.prisma.shop.findUnique({ where: { id: shopId } });
    if (!shop) throw new NotFoundException('店铺不存在');

    // 计算下次同步时间
    let nextSyncAt: Date | null = null;
    if (data.enabled) {
      const syncHour = data.syncHour ?? 8;
      nextSyncAt = new Date();
      // 设置到指定小时
      nextSyncAt.setHours(syncHour, 0, 0, 0);
      // 如果今天的时间已过，设置为明天
      if (nextSyncAt <= new Date()) {
        nextSyncAt.setDate(nextSyncAt.getDate() + 1);
      }
    }

    const config = await this.prisma.autoSyncConfig.upsert({
      where: { shopId },
      create: {
        shopId,
        enabled: data.enabled ?? false,
        intervalDays: data.intervalDays ?? 1,
        syncHour: data.syncHour ?? 8,
        syncType: data.syncType ?? 'both',
        nextSyncAt,
      },
      update: {
        enabled: data.enabled,
        intervalDays: data.intervalDays,
        syncHour: data.syncHour,
        syncType: data.syncType,
        nextSyncAt,
      },
      include: { shop: { select: { id: true, name: true } } },
    });

    return config;
  }


  // 立即触发同步任务
  async triggerSync(shopId: string, syncType?: string) {
    const shop = await this.prisma.shop.findUnique({
      where: { id: shopId },
      include: { platform: true },
    });
    if (!shop) throw new NotFoundException('店铺不存在');

    // 检查是否有正在运行的任务
    const runningTask = await this.prisma.autoSyncTask.findFirst({
      where: {
        shopId,
        stage: { in: ['fetch_channel', 'update_local', 'push_platform'] },
      },
    });
    if (runningTask) {
      throw new Error('该店铺已有正在运行的同步任务');
    }

    // 获取自动同步配置，如果没有传入 syncType 则使用配置的值
    const autoSyncConfig = await this.prisma.autoSyncConfig.findUnique({
      where: { shopId },
    });
    const finalSyncType = syncType || autoSyncConfig?.syncType || 'both';

    // 获取店铺商品按渠道分组统计
    const products = await this.prisma.product.findMany({
      where: { shopId },
      select: { channelId: true },
    });

    const channelCounts: Record<string, number> = {};
    for (const p of products) {
      if (p.channelId) {
        channelCounts[p.channelId] = (channelCounts[p.channelId] || 0) + 1;
      }
    }

    // 构建 channelStats
    const channelStats: Record<string, { total: number; fetched: number; status: string }> = {};
    for (const [channelId, count] of Object.entries(channelCounts)) {
      channelStats[channelId] = { total: count, fetched: 0, status: 'pending' };
    }

    // 创建任务
    const task = await this.prisma.autoSyncTask.create({
      data: {
        shopId,
        syncType: finalSyncType,
        stage: 'fetch_channel',
        channelStats,
        totalProducts: products.length,
      },
    });

    // 添加到队列
    await this.autoSyncQueue.add('auto-sync', { taskId: task.id });

    return task;
  }

  // 获取同步任务列表
  async getTasks(query: PaginationDto & { shopId?: string }) {
    const { shopId } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 20;
    const skip = (page - 1) * pageSize;
    const where = shopId ? { shopId } : {};

    const [data, total] = await Promise.all([
      this.prisma.autoSyncTask.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { select: { id: true, name: true } } },
      }),
      this.prisma.autoSyncTask.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  // 获取单个任务详情
  async getTask(taskId: string) {
    const task = await this.prisma.autoSyncTask.findUnique({
      where: { id: taskId },
      include: { shop: { select: { id: true, name: true } } },
    });
    if (!task) throw new NotFoundException('任务不存在');
    return task;
  }

  // 取消任务
  async cancelTask(taskId: string) {
    const task = await this.prisma.autoSyncTask.findUnique({ where: { id: taskId } });
    if (!task) throw new NotFoundException('任务不存在');
    
    if (!['fetch_channel', 'update_local', 'push_platform'].includes(task.stage)) {
      throw new Error('只能取消进行中的任务');
    }

    await this.prisma.autoSyncTask.update({
      where: { id: taskId },
      data: {
        stage: 'cancelled',
        finishedAt: new Date(),
        errorMessage: '任务被手动取消',
      },
    });

    return { success: true, message: '任务已取消' };
  }

  // 删除任务
  async deleteTask(taskId: string) {
    const task = await this.prisma.autoSyncTask.findUnique({ where: { id: taskId } });
    if (!task) throw new NotFoundException('任务不存在');
    
    if (['fetch_channel', 'update_local', 'push_platform'].includes(task.stage)) {
      throw new Error('不能删除进行中的任务');
    }

    await this.prisma.autoSyncTask.delete({ where: { id: taskId } });
    return { success: true, message: '删除成功' };
  }
}
