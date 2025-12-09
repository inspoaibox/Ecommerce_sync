import { Injectable } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';

@Injectable()
export class DashboardService {
  constructor(private prisma: PrismaService) {}

  async getOverview() {
    const [channelCount, platformCount, shopCount, productCount, syncRuleCount] =
      await Promise.all([
        this.prisma.channel.count({ where: { status: 'active' } }),
        this.prisma.platform.count({ where: { status: 'active' } }),
        this.prisma.shop.count({ where: { status: 'active' } }),
        this.prisma.product.count(),
        this.prisma.syncRule.count({ where: { status: 'active' } }),
      ]);

    return { channelCount, platformCount, shopCount, productCount, syncRuleCount };
  }

  async getSyncStats() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const [todaySyncCount, successRate] = await Promise.all([
      this.prisma.syncLog.count({ where: { createdAt: { gte: today } } }),
      this.calculateSuccessRate(),
    ]);

    return { todaySyncCount, successRate };
  }

  private async calculateSuccessRate(): Promise<number> {
    const total = await this.prisma.syncLog.count();
    if (total === 0) return 100;
    const success = await this.prisma.syncLog.count({ where: { status: 'success' } });
    return Math.round((success / total) * 100 * 10) / 10;
  }

  async getRecentLogs(limit = 10) {
    return this.prisma.syncLog.findMany({
      take: limit,
      include: { syncRule: { include: { channel: true, shop: true } } },
      orderBy: { createdAt: 'desc' },
    });
  }
}
