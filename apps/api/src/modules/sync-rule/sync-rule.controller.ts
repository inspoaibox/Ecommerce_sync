import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { SyncRuleService } from './sync-rule.service';
import { CreateSyncRuleDto, UpdateSyncRuleDto } from './dto/sync-rule.dto';
import { PaginationDto } from '@/common/dto/pagination.dto';

@ApiTags('同步规则')
@Controller('sync-rules')
export class SyncRuleController {
  constructor(private readonly syncRuleService: SyncRuleService) {}

  @Get()
  @ApiOperation({ summary: '获取同步规则列表' })
  findAll(@Query() query: PaginationDto) {
    return this.syncRuleService.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: '获取同步规则详情' })
  findOne(@Param('id') id: string) {
    return this.syncRuleService.findOne(id);
  }

  @Post()
  @ApiOperation({ summary: '创建同步规则' })
  create(@Body() dto: CreateSyncRuleDto) {
    return this.syncRuleService.create(dto);
  }

  @Put(':id')
  @ApiOperation({ summary: '更新同步规则' })
  update(@Param('id') id: string, @Body() dto: UpdateSyncRuleDto) {
    return this.syncRuleService.update(id, dto);
  }

  @Delete(':id')
  @ApiOperation({ summary: '删除同步规则' })
  remove(@Param('id') id: string) {
    return this.syncRuleService.remove(id);
  }

  @Post(':id/execute')
  @ApiOperation({ summary: '手动执行同步' })
  execute(@Param('id') id: string) {
    return this.syncRuleService.execute(id);
  }

  @Put(':id/pause')
  @ApiOperation({ summary: '暂停同步规则' })
  pause(@Param('id') id: string) {
    return this.syncRuleService.pause(id);
  }

  @Put(':id/resume')
  @ApiOperation({ summary: '恢复同步规则' })
  resume(@Param('id') id: string) {
    return this.syncRuleService.resume(id);
  }
}
