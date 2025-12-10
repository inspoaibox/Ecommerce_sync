import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ApiTags, ApiOperation } from '@nestjs/swagger';
import { ProductService } from './product.service';
import { UpdateProductDto, ProductQueryDto, BatchDeleteDto, AssignShopDto, SyncFromChannelDto } from './dto/product.dto';

@ApiTags('商品管理')
@Controller('products')
export class ProductController {
  constructor(private readonly productService: ProductService) {}

  @Get()
  @ApiOperation({ summary: '获取商品列表' })
  findAll(@Query() query: ProductQueryDto) {
    return this.productService.findAll(query);
  }

  @Get('export')
  @ApiOperation({ summary: '导出商品（不受分页限制）' })
  exportProducts(@Query() query: ProductQueryDto) {
    return this.productService.exportProducts(query);
  }

  @Get(':id')
  @ApiOperation({ summary: '获取商品详情' })
  findOne(@Param('id') id: string) {
    return this.productService.findOne(id);
  }

  @Put(':id')
  @ApiOperation({ summary: '更新商品' })
  update(@Param('id') id: string, @Body() dto: UpdateProductDto) {
    return this.productService.update(id, dto);
  }

  @Delete(':id')
  @ApiOperation({ summary: '删除商品' })
  remove(@Param('id') id: string) {
    return this.productService.remove(id);
  }

  @Post('batch-delete')
  @ApiOperation({ summary: '批量删除商品' })
  batchDelete(@Body() dto: BatchDeleteDto) {
    return this.productService.batchDelete(dto.ids);
  }

  @Post('assign-shop')
  @ApiOperation({ summary: '分配商品到店铺' })
  assignToShop(@Body() dto: AssignShopDto) {
    return this.productService.assignToShop(dto.ids, dto.shopId);
  }

  @Post('sync-from-channel')
  @ApiOperation({ summary: '从渠道同步商品到本地' })
  syncFromChannel(@Body() dto: SyncFromChannelDto) {
    return this.productService.syncFromChannel(dto);
  }

  @Put(':id/platform-sku')
  @ApiOperation({ summary: '更新商品平台SKU' })
  updatePlatformSku(@Param('id') id: string, @Body() body: { platformSku: string }) {
    return this.productService.updatePlatformSku(id, body.platformSku);
  }

  @Post('import-platform-sku/:shopId')
  @ApiOperation({ summary: '批量导入平台SKU映射' })
  importPlatformSku(
    @Param('shopId') shopId: string,
    @Body() body: { mappings: { sku: string; platformSku: string }[] },
  ) {
    return this.productService.importPlatformSku(shopId, body.mappings);
  }

  @Post('import-products/:shopId')
  @ApiOperation({ summary: '导入平台产品' })
  importProducts(
    @Param('shopId') shopId: string,
    @Body() body: { channelId: string; products: { sku: string; platformSku?: string }[] },
  ) {
    return this.productService.importProducts(shopId, body.channelId, body.products);
  }

  @Post('fetch-latest/:shopId')
  @ApiOperation({ summary: '从渠道获取最新价格库存' })
  fetchLatestFromChannel(
    @Param('shopId') shopId: string,
    @Body() body: { productIds?: string[]; fetchType: 'price' | 'inventory' | 'both' },
  ) {
    return this.productService.fetchLatestFromChannel(shopId, body.productIds, body.fetchType);
  }
}
