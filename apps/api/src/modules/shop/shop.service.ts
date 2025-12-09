import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreateShopDto, UpdateShopDto } from './dto/shop.dto';
import { PaginationDto, PaginatedResult } from '@/common/dto/pagination.dto';
import { Shop } from '@prisma/client';
import { PlatformAdapterFactory, PLATFORM_CONFIGS } from '@/adapters/platforms';

@Injectable()
export class ShopService {
  constructor(private prisma: PrismaService) {}

  async findAll(query: PaginationDto): Promise<PaginatedResult<Shop>> {
    const { page = 1, pageSize = 20 } = query;
    const skip = (page - 1) * pageSize;

    const [data, total] = await Promise.all([
      this.prisma.shop.findMany({
        skip,
        take: pageSize,
        include: { platform: true },
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.shop.count(),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  async findOne(id: string): Promise<Shop> {
    const shop = await this.prisma.shop.findUnique({
      where: { id },
      include: { platform: true },
    });
    if (!shop) throw new NotFoundException('店铺不存在');
    return shop;
  }

  // 获取或创建平台
  private async getOrCreatePlatform(platformCode: string): Promise<string> {
    let platform = await this.prisma.platform.findUnique({
      where: { code: platformCode },
    });

    if (!platform) {
      const config = PLATFORM_CONFIGS[platformCode];
      platform = await this.prisma.platform.create({
        data: {
          code: platformCode,
          name: config?.name || platformCode,
          apiBaseUrl: config?.apiBaseUrl || '',
          status: 'active',
        },
      });
    }

    return platform.id;
  }

  async create(dto: CreateShopDto): Promise<Shop> {
    const { platformCode, ...rest } = dto;
    const platformId = await this.getOrCreatePlatform(platformCode);
    
    return this.prisma.shop.create({
      data: {
        ...rest,
        platformId,
      },
      include: { platform: true },
    });
  }

  async update(id: string, dto: UpdateShopDto): Promise<Shop> {
    await this.findOne(id);
    const { platformCode, ...rest } = dto;
    
    const updateData: any = { ...rest };
    if (platformCode) {
      updateData.platformId = await this.getOrCreatePlatform(platformCode);
    }
    
    return this.prisma.shop.update({
      where: { id },
      data: updateData,
      include: { platform: true },
    });
  }

  async remove(id: string): Promise<void> {
    await this.findOne(id);
    await this.prisma.shop.delete({ where: { id } });
  }

  async testConnection(id: string): Promise<{ success: boolean; message: string }> {
    const shop = await this.findOne(id);
    try {
      const platformCode = (shop as any).platform?.code || shop.platformId;
      const adapter = PlatformAdapterFactory.create(
        platformCode,
        shop.apiCredentials as Record<string, any>,
      );
      const result = await adapter.testConnection();
      return {
        success: result,
        message: result ? '连接成功' : '连接失败',
      };
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : '连接失败',
      };
    }
  }
}
