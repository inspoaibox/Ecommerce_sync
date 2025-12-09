import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreateChannelDto, UpdateChannelDto } from './dto/channel.dto';
import { PaginationDto, PaginatedResult } from '@/common/dto/pagination.dto';
import { Channel } from '@prisma/client';
import { ChannelAdapterFactory } from '@/adapters/channels';

@Injectable()
export class ChannelService {
  constructor(private prisma: PrismaService) {}

  async findAll(query: PaginationDto): Promise<PaginatedResult<Channel>> {
    const { page = 1, pageSize = 20 } = query;
    const skip = (page - 1) * pageSize;

    const [data, total] = await Promise.all([
      this.prisma.channel.findMany({
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.channel.count(),
    ]);

    return {
      data,
      total,
      page,
      pageSize,
      totalPages: Math.ceil(total / pageSize),
    };
  }

  async findOne(id: string): Promise<Channel> {
    const channel = await this.prisma.channel.findUnique({ where: { id } });
    if (!channel) throw new NotFoundException('渠道不存在');
    return channel;
  }

  async create(dto: CreateChannelDto): Promise<Channel> {
    return this.prisma.channel.create({ data: dto });
  }

  async update(id: string, dto: UpdateChannelDto): Promise<Channel> {
    await this.findOne(id);
    return this.prisma.channel.update({ where: { id }, data: dto });
  }

  async remove(id: string): Promise<void> {
    await this.findOne(id);
    await this.prisma.channel.delete({ where: { id } });
  }

  async testConnection(id: string): Promise<{ success: boolean; message: string }> {
    const channel = await this.findOne(id);
    try {
      const adapter = ChannelAdapterFactory.create(
        channel.type,
        channel.apiConfig as Record<string, any>,
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

  async queryProducts(
    id: string,
    skus: string[],
    options?: { warehouseCode?: string; priceType?: 'shipping' | 'pickup' }
  ) {
    const channel = await this.findOne(id);
    try {
      const adapter = ChannelAdapterFactory.create(
        channel.type,
        channel.apiConfig as Record<string, any>,
      );
      // 使用适配器的批量查询方法
      const products = await (adapter as any).fetchProductsBySkus(skus, undefined, options);
      return {
        success: true,
        data: products,
        total: products.length,
      };
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : '查询失败',
        data: [],
      };
    }
  }

  async testApi(
    id: string,
    params: { endpoint: string; skus?: string[]; page?: number; pageSize?: number },
  ) {
    const channel = await this.findOne(id);
    try {
      const adapter = ChannelAdapterFactory.create(
        channel.type,
        channel.apiConfig as Record<string, any>,
      );
      // 调用适配器的原始API测试方法
      const result = await (adapter as any).testRawApi(params);
      return result;
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : '请求失败',
        data: null,
      };
    }
  }

  async fetchWarehouses(id: string) {
    const channel = await this.findOne(id);
    try {
      const adapter = ChannelAdapterFactory.create(
        channel.type,
        channel.apiConfig as Record<string, any>,
      );

      // 只有赛盈云仓支持获取区域
      if (channel.type !== 'saleyee') {
        return {
          success: false,
          message: '该渠道不支持获取区域信息',
        };
      }

      // 调用适配器获取区域列表
      const warehouses = await (adapter as any).fetchWarehouses();

      // 删除旧的区域数据
      await this.prisma.channelWarehouse.deleteMany({
        where: { channelId: id },
      });

      // 保存新的区域数据
      await this.prisma.channelWarehouse.createMany({
        data: warehouses.map((w: any) => ({
          channelId: id,
          warehouseCode: w.code,
          warehouseName: w.name,
          region: w.region,
          country: w.country,
          extraData: w.extraData,
        })),
      });

      return {
        success: true,
        message: `成功获取并保存 ${warehouses.length} 个区域`,
        count: warehouses.length,
      };
    } catch (error) {
      return {
        success: false,
        message: error instanceof Error ? error.message : '获取区域失败',
      };
    }
  }

  async getWarehouses(id: string) {
    const warehouses = await this.prisma.channelWarehouse.findMany({
      where: { channelId: id },
      orderBy: { warehouseCode: 'asc' },
    });

    return {
      success: true,
      data: warehouses,
      total: warehouses.length,
    };
  }
}
