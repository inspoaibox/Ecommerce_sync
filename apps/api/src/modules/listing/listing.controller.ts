import { Controller, Get, Post, Put, Delete, Body, Param, Query } from '@nestjs/common';
import { ListingService } from './listing.service';
import {
  QueryFromChannelDto,
  ListingQueryDto,
  ImportListingDto,
  UpdateListingDto,
  SubmitListingDto,
} from './dto';

@Controller('listing')
export class ListingController {
  constructor(private readonly listingService: ListingService) {}

  /**
   * 从渠道查询商品详情
   */
  @Post('query-channel')
  async queryFromChannel(@Body() dto: QueryFromChannelDto) {
    return this.listingService.queryFromChannel(dto);
  }

  /**
   * 导入商品到刊登店铺
   */
  @Post('import')
  async importProducts(@Body() dto: ImportListingDto) {
    return this.listingService.importProducts(dto);
  }

  /**
   * 获取刊登商品列表
   */
  @Get('products')
  async getListingProducts(@Query() query: ListingQueryDto) {
    return this.listingService.getListingProducts(query);
  }

  /**
   * 获取单个商品详情
   */
  @Get('products/:id')
  async getListingProduct(@Param('id') id: string) {
    return this.listingService.getListingProduct(id);
  }

  /**
   * 更新商品信息
   */
  @Put('products/:id')
  async updateListingProduct(@Param('id') id: string, @Body() dto: UpdateListingDto) {
    return this.listingService.updateListingProduct(id, dto);
  }

  /**
   * 删除商品（支持批量）
   */
  @Delete('products')
  async deleteListingProducts(@Body() body: { ids: string[] }) {
    return this.listingService.deleteListingProducts(body.ids);
  }

  /**
   * 删除单个商品
   */
  @Delete('products/:id')
  async deleteListingProduct(@Param('id') id: string) {
    return this.listingService.deleteListingProducts([id]);
  }

  /**
   * 验证商品刊登信息
   */
  @Post('validate')
  async validateListing(@Body() body: { productIds: string[] }) {
    return this.listingService.validateListing(body.productIds);
  }

  /**
   * 提交刊登
   */
  @Post('submit')
  async submitListing(@Body() dto: SubmitListingDto) {
    return this.listingService.submitListing(dto);
  }

  /**
   * 获取刊登任务列表
   */
  @Get('tasks')
  async getListingTasks(@Query() query: { shopId?: string; page?: number; pageSize?: number }) {
    return this.listingService.getListingTasks(query);
  }

  /**
   * 获取刊登任务详情
   */
  @Get('tasks/:id')
  async getListingTask(@Param('id') id: string) {
    return this.listingService.getListingTask(id);
  }
}
