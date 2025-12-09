import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectQueue } from '@nestjs/bullmq';
import { Queue } from 'bullmq';
import { PrismaService } from '@/common/prisma/prisma.service';
import { CreateSyncRuleDto, UpdateSyncRuleDto } from './dto/sync-rule.dto';
import { PaginationDto, PaginatedResult } from '@/common/dto/pagination.dto';
import { SyncRule, SyncRuleStatus } from '@prisma/client';
import { QUEUE_NAMES } from '@/queues/constants';

@Injectable()
export class SyncRuleService {
  constructor(
    private prisma: PrismaService,
    @InjectQueue(QUEUE_NAMES.SYNC_SCHEDULER) private syncQueue: Queue,
  ) {}

  async findAll(query: PaginationDto): Promise<PaginatedResult<SyncRule>> {
    const { page = 1, pageSize = 20 } = query;
    const skip = (page - 1) * pageSize;

    const [data, total] = await Promise.all([
      this.prisma.syncRule.findMany({
        skip,
        take: pageSize,
        include: { channel: true, shop: { include: { platform: true } } },
        orderBy: { createdAt: 'desc' },
      }),
      this.prisma.syncRule.count(),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  async findOne(id: string): Promise<SyncRule> {
    const rule = await this.prisma.syncRule.findUnique({
      where: { id },
      include: { channel: true, shop: { include: { platform: true } } },
    });
    if (!rule) throw new NotFoundException('同步规则不存在');
    return rule;
  }

  async create(dto: CreateSyncRuleDto): Promise<SyncRule> {
    const nextSyncAt = new Date();
    return this.prisma.syncRule.create({
      data: { ...dto, nextSyncAt },
    });
  }

  async update(id: string, dto: UpdateSyncRuleDto): Promise<SyncRule> {
    await this.findOne(id);
    return this.prisma.syncRule.update({ where: { id }, data: dto });
  }

  async remove(id: string): Promise<void> {
    await this.findOne(id);
    await this.prisma.syncRule.delete({ where: { id } });
  }


  async execute(id: string): Promise<void> {
    const rule = await this.findOne(id);
    await this.syncQueue.add('execute-sync', { ruleId: rule.id, triggerType: 'manual' });
  }

  async pause(id: string): Promise<SyncRule> {
    await this.findOne(id);
    return this.prisma.syncRule.update({
      where: { id },
      data: { status: SyncRuleStatus.paused },
    });
  }

  async resume(id: string): Promise<SyncRule> {
    await this.findOne(id);
    return this.prisma.syncRule.update({
      where: { id },
      data: { status: SyncRuleStatus.active, nextSyncAt: new Date() },
    });
  }
}
