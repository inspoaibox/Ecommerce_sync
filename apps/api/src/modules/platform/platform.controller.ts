import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { PlatformService } from './platform.service';
import { CreatePlatformDto, UpdatePlatformDto } from './dto/platform.dto';
import { PaginationDto } from '@/common/dto/pagination.dto';

@ApiTags('平台管理')
@Controller('platforms')
export class PlatformController {
  constructor(private readonly platformService: PlatformService) {}

  @Get()
  @ApiOperation({ summary: '获取平台列表' })
  findAll(@Query() query: PaginationDto) {
    return this.platformService.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: '获取平台详情' })
  findOne(@Param('id') id: string) {
    return this.platformService.findOne(id);
  }

  @Post()
  @ApiOperation({ summary: '创建平台' })
  create(@Body() dto: CreatePlatformDto) {
    return this.platformService.create(dto);
  }

  @Put(':id')
  @ApiOperation({ summary: '更新平台' })
  update(@Param('id') id: string, @Body() dto: UpdatePlatformDto) {
    return this.platformService.update(id, dto);
  }

  @Delete(':id')
  @ApiOperation({ summary: '删除平台' })
  remove(@Param('id') id: string) {
    return this.platformService.remove(id);
  }
}
