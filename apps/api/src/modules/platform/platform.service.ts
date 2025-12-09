import { Injectable, NotFoundException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreatePlatformDto, UpdatePlatformDto } from './dto/platform.dto';
import { PaginationDto, PaginatedResult } from '@/common/dto/pagination.dto';
import { Platform } from '@prisma/client';

@Injectable()
export class PlatformService {
  constructor(private prisma: PrismaService) {}

  async findAll(query: PaginationDto): Promise<PaginatedResult<Platform>> {
    const { page = 1, pageSize = 20 } = query;
    const skip = (page - 1) * pageSize;

    const [data, total] = await Promise.all([
      this.prisma.platform.findMany({
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.platform.count(),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  async findOne(id: string): Promise<Platform> {
    const platform = await this.prisma.platform.findUnique({ where: { id } });
    if (!platform) throw new NotFoundException('平台不存在');
    return platform;
  }

  async create(dto: CreatePlatformDto): Promise<Platform> {
    return this.prisma.platform.create({ data: dto });
  }

  async update(id: string, dto: UpdatePlatformDto): Promise<Platform> {
    await this.findOne(id);
    return this.prisma.platform.update({ where: { id }, data: dto });
  }

  async remove(id: string): Promise<void> {
    await this.findOne(id);
    await this.prisma.platform.delete({ where: { id } });
  }
}
