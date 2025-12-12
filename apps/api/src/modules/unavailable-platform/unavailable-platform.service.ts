import { Injectable } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';

@Injectable()
export class UnavailablePlatformService {
  constructor(private prisma: PrismaService) {}

  /**
   * 获取所有不可售平台列表
   */
  async findAll(channelId?: string) {
    const where = channelId ? { channelId } : {};
    return this.prisma.unavailablePlatform.findMany({
      where,
      orderBy: { platformName: 'asc' },
    });
  }

  /**
   * 批量保存不可售平台（自动去重）
   */
  async saveMany(platforms: { platformId: string; platformName: string }[], channelId?: string) {
    const results = [];
    
    for (const platform of platforms) {
      const existing = await this.prisma.unavailablePlatform.findFirst({
        where: {
          platformId: platform.platformId,
          channelId: channelId || null,
        },
      });
      
      if (!existing) {
        const created = await this.prisma.unavailablePlatform.create({
          data: {
            platformId: platform.platformId,
            platformName: platform.platformName,
            channelId: channelId || null,
          },
        });
        results.push(created);
      }
    }
    
    return results;
  }

  /**
   * 从商品数据中提取并保存不可售平台
   */
  async extractAndSave(products: any[], channelId?: string) {
    const platformMap = new Map<string, { platformId: string; platformName: string }>();
    
    for (const product of products) {
      const unavailablePlatforms = product.channelSpecificFields?.unAvailablePlatform || 
                                   product.rawData?.detail?.unAvailablePlatform || [];
      
      for (const p of unavailablePlatforms) {
        if (p.id && p.name) {
          platformMap.set(p.id, { platformId: p.id, platformName: p.name });
        }
      }
    }
    
    if (platformMap.size > 0) {
      await this.saveMany(Array.from(platformMap.values()), channelId);
    }
    
    return platformMap.size;
  }
}
