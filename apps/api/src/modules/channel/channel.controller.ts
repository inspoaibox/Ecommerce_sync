import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { ChannelService } from './channel.service';
import { CreateChannelDto, UpdateChannelDto } from './dto/channel.dto';
import { PaginationDto } from '@/common/dto/pagination.dto';

@ApiTags('渠道管理')
@Controller('channels')
export class ChannelController {
  constructor(private readonly channelService: ChannelService) {}

  @Get()
  @ApiOperation({ summary: '获取渠道列表' })
  findAll(@Query() query: PaginationDto) {
    return this.channelService.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: '获取渠道详情' })
  findOne(@Param('id') id: string) {
    return this.channelService.findOne(id);
  }

  @Post()
  @ApiOperation({ summary: '创建渠道' })
  create(@Body() dto: CreateChannelDto) {
    return this.channelService.create(dto);
  }

  @Put(':id')
  @ApiOperation({ summary: '更新渠道' })
  update(@Param('id') id: string, @Body() dto: UpdateChannelDto) {
    return this.channelService.update(id, dto);
  }

  @Delete(':id')
  @ApiOperation({ summary: '删除渠道' })
  remove(@Param('id') id: string) {
    return this.channelService.remove(id);
  }

  @Post(':id/test')
  @ApiOperation({ summary: '测试渠道连接' })
  testConnection(@Param('id') id: string) {
    return this.channelService.testConnection(id);
  }

  @Post(':id/query-products')
  @ApiOperation({ summary: '批量查询商品' })
  queryProducts(@Param('id') id: string, @Body() body: { skus: string[] }) {
    return this.channelService.queryProducts(id, body.skus);
  }
}
