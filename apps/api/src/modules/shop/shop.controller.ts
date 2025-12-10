import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { ShopService } from './shop.service';
import { CreateShopDto, UpdateShopDto } from './dto/shop.dto';
import { ShopSyncConfigDto } from './dto/sync-config.dto';
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

  @Get('sync-tasks')
  @ApiOperation({ summary: '获取所有同步任务列表' })
  getSyncTasks(@Query() query: PaginationDto & { shopId?: string }) {
    return this.shopService.getSyncTasks(query);
  }

  @Get('feeds')
  @ApiOperation({ summary: '获取所有店铺Feed记录列表' })
  getAllFeeds(@Query() query: PaginationDto) {
    return this.shopService.getAllFeeds(query);
  }

  @Get('sync-task/:taskId')
  @ApiOperation({ summary: '获取同步任务状态' })
  getSyncTaskStatus(@Param('taskId') taskId: string) {
    return this.shopService.getSyncTaskStatus(taskId);
  }

  @Post('sync-task/:taskId/pause')
  @ApiOperation({ summary: '暂停同步任务' })
  pauseSyncTask(@Param('taskId') taskId: string) {
    return this.shopService.pauseSyncTask(taskId);
  }

  @Post('sync-task/:taskId/resume')
  @ApiOperation({ summary: '继续同步任务' })
  resumeSyncTask(@Param('taskId') taskId: string) {
    return this.shopService.resumeSyncTask(taskId);
  }

  @Post('sync-task/:taskId/cancel')
  @ApiOperation({ summary: '取消同步任务' })
  cancelSyncTask(@Param('taskId') taskId: string, @Query('force') force?: string) {
    return this.shopService.cancelSyncTask(taskId, force === 'true');
  }

  @Delete('sync-task/:taskId')
  @ApiOperation({ summary: '删除同步任务记录' })
  deleteSyncTask(@Param('taskId') taskId: string) {
    return this.shopService.deleteSyncTask(taskId);
  }

  @Post('sync-task/:taskId/retry')
  @ApiOperation({ summary: '重试失败的同步任务' })
  retrySyncTask(@Param('taskId') taskId: string) {
    return this.shopService.retrySyncTask(taskId);
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

  @Post(':id/sync-products')
  @ApiOperation({ summary: '从平台同步商品到本地（异步）' })
  syncProducts(@Param('id') id: string) {
    return this.shopService.syncProducts(id);
  }

  @Delete(':id/products')
  @ApiOperation({ summary: '删除店铺所有商品' })
  deleteAllProducts(@Param('id') id: string) {
    return this.shopService.deleteAllProducts(id);
  }

  @Post(':id/sync-missing-skus')
  @ApiOperation({ summary: '补充同步缺失的SKU' })
  syncMissingSkus(@Param('id') id: string, @Body() body: { skus: string[] }) {
    return this.shopService.syncMissingSkus(id, body.skus);
  }

  @Post(':id/sync-to-walmart')
  @ApiOperation({ summary: '同步价格/库存到沃尔玛' })
  syncToWalmart(
    @Param('id') id: string,
    @Body() body: { productIds: string[]; syncType: 'price' | 'inventory' | 'both' }
  ) {
    return this.shopService.syncToWalmart(id, body.productIds, body.syncType);
  }

  @Get(':id/feeds')
  @ApiOperation({ summary: '获取店铺Feed记录列表' })
  getFeeds(@Param('id') id: string, @Query() query: PaginationDto) {
    return this.shopService.getFeeds(id, query);
  }

  @Post(':id/feeds/:feedId/refresh')
  @ApiOperation({ summary: '刷新Feed状态' })
  refreshFeedStatus(@Param('id') id: string, @Param('feedId') feedId: string) {
    return this.shopService.refreshFeedStatus(id, feedId);
  }

  @Get(':id/feeds/:feedId/detail')
  @ApiOperation({ summary: '获取Feed详情' })
  getFeedDetail(@Param('id') id: string, @Param('feedId') feedId: string) {
    return this.shopService.getFeedDetail(id, feedId);
  }

  @Get(':id/sync-config')
  @ApiOperation({ summary: '获取店铺同步配置' })
  getSyncConfig(@Param('id') id: string) {
    return this.shopService.getSyncConfig(id);
  }

  @Put(':id/sync-config')
  @ApiOperation({ summary: '更新店铺同步配置' })
  updateSyncConfig(@Param('id') id: string, @Body() config: any) {
    return this.shopService.updateSyncConfig(id, config);
  }
}
