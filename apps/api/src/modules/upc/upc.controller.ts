import {
  Controller,
  Get,
  Post,
  Delete,
  Body,
  Param,
  Query,
} from '@nestjs/common';
import { UpcService } from './upc.service';

@Controller('upc')
export class UpcController {
  constructor(private readonly upcService: UpcService) {}

  /**
   * 获取 UPC 池统计信息
   */
  @Get('stats')
  async getStats() {
    return this.upcService.getStats();
  }

  /**
   * 获取 UPC 列表
   */
  @Get()
  async list(
    @Query('page') page?: string,
    @Query('pageSize') pageSize?: string,
    @Query('search') search?: string,
    @Query('status') status?: 'all' | 'used' | 'available',
  ) {
    return this.upcService.list({
      page: page ? parseInt(page, 10) : 1,
      pageSize: pageSize ? parseInt(pageSize, 10) : 50,
      search,
      status,
    });
  }

  /**
   * 批量导入 UPC
   */
  @Post('import')
  async import(@Body() body: { upcCodes: string[] }) {
    return this.upcService.import(body.upcCodes);
  }

  /**
   * 自动分配 UPC
   */
  @Post('auto-assign')
  async autoAssign(@Body() body: { productSku: string; shopId?: string }) {
    const upcCode = await this.upcService.autoAssignUpc(body.productSku, body.shopId);
    if (!upcCode) {
      return { success: false, message: 'UPC 池已用尽' };
    }
    return { success: true, upcCode };
  }

  /**
   * 手动分配 UPC
   */
  @Post('assign')
  async assign(@Body() body: { upcCode: string; productSku: string; shopId?: string }) {
    return this.upcService.assignUpc(body.upcCode, body.productSku, body.shopId);
  }

  /**
   * 释放 UPC
   */
  @Post('release/:upcCode')
  async release(@Param('upcCode') upcCode: string) {
    return this.upcService.releaseUpc(upcCode);
  }

  /**
   * 批量释放 UPC
   */
  @Post('batch-release')
  async batchRelease(@Body() body: { ids: string[] }) {
    return this.upcService.batchRelease(body.ids);
  }

  /**
   * 批量标记为已使用
   */
  @Post('batch-mark-used')
  async batchMarkUsed(@Body() body: { ids: string[] }) {
    return this.upcService.batchMarkUsed(body.ids);
  }

  /**
   * 删除 UPC
   */
  @Delete(':id')
  async delete(@Param('id') id: string) {
    return this.upcService.delete(id);
  }

  /**
   * 批量删除 UPC
   */
  @Post('batch-delete')
  async batchDelete(@Body() body: { ids: string[] }) {
    return this.upcService.batchDelete(body.ids);
  }

  /**
   * 导出 UPC
   */
  @Get('export')
  async export(@Query('status') status?: 'all' | 'used' | 'available') {
    return this.upcService.export(status);
  }
}
