import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { ShopService } from './shop.service';
import { CreateShopDto, UpdateShopDto } from './dto/shop.dto';
import { PaginationDto } from '@/common/dto/pagination.dto';

@ApiTags('店铺管理')
@Controller('shops')
export class ShopController {
  constructor(private readonly shopService: ShopService) {}

  @Get()
  @ApiOperation({ summary: '获取店铺列表' })
  findAll(@Query() query: PaginationDto) {
    return this.shopService.findAll(query);
  }

  @Get(':id')
  @ApiOperation({ summary: '获取店铺详情' })
  findOne(@Param('id') id: string) {
    return this.shopService.findOne(id);
  }

  @Post()
  @ApiOperation({ summary: '创建店铺' })
  create(@Body() dto: CreateShopDto) {
    return this.shopService.create(dto);
  }

  @Put(':id')
  @ApiOperation({ summary: '更新店铺' })
  update(@Param('id') id: string, @Body() dto: UpdateShopDto) {
    return this.shopService.update(id, dto);
  }

  @Delete(':id')
  @ApiOperation({ summary: '删除店铺' })
  remove(@Param('id') id: string) {
    return this.shopService.remove(id);
  }

  @Post(':id/test')
  @ApiOperation({ summary: '测试店铺连接' })
  testConnection(@Param('id') id: string) {
    return this.shopService.testConnection(id);
  }
}
