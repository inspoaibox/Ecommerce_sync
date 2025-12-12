import { Controller, Get, Post, Put, Delete, Body, Param, Query, Res, UseInterceptors, UploadedFile } from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { Response } from 'express';
import { ProductPoolService } from './product-pool.service';

@Controller('product-pool')
export class ProductPoolController {
  constructor(private productPoolService: ProductPoolService) {}

  @Get('stats')
  getStats(@Query('channelId') channelId?: string) {
    return this.productPoolService.getStats(channelId);
  }

  @Get()
  list(
    @Query('page') page?: string,
    @Query('pageSize') pageSize?: string,
    @Query('channelId') channelId?: string,
    @Query('keyword') keyword?: string,
    @Query('sku') sku?: string,
  ) {
    return this.productPoolService.list({
      page: page ? parseInt(page, 10) : 1,
      pageSize: pageSize ? parseInt(pageSize, 10) : 20,
      channelId,
      keyword,
      sku,
    });
  }

  @Get(':id')
  get(@Param('id') id: string) {
    return this.productPoolService.get(id);
  }

  @Post('import')
  importProducts(@Body() body: {
    channelId: string;
    products: any[];
    duplicateAction?: 'skip' | 'update';
    platformCategoryId?: string;
  }) {
    return this.productPoolService.importProducts(body);
  }

  @Post('publish')
  publishToShop(@Body() body: {
    productPoolIds: string[];
    shopId: string;
    platformCategoryId?: string;
  }) {
    return this.productPoolService.publishToShop(body);
  }

  @Put(':id')
  update(@Param('id') id: string, @Body() body: any) {
    return this.productPoolService.update(id, body);
  }

  @Delete()
  delete(@Body() body: { ids: string[] }) {
    return this.productPoolService.delete(body.ids);
  }

  @Get(':id/listings')
  getListingStatus(@Param('id') id: string) {
    return this.productPoolService.getListingStatus(id);
  }

  /**
   * 下载导入模板
   * GET /product-pool/template/download
   */
  @Get('template/download')
  async downloadTemplate(@Res() res: Response) {
    const buffer = await this.productPoolService.generateImportTemplate();
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', 'attachment; filename=product_import_template.xlsx');
    res.send(buffer);
  }

  /**
   * 通过 Excel 文件导入商品
   * POST /product-pool/import-excel
   */
  @Post('import-excel')
  @UseInterceptors(FileInterceptor('file'))
  async importFromExcel(
    @UploadedFile() file: Express.Multer.File,
    @Body('channelId') channelId: string,
    @Body('duplicateAction') duplicateAction?: 'skip' | 'update',
    @Body('platformCategoryId') platformCategoryId?: string,
  ) {
    if (!file) {
      throw new Error('请上传文件');
    }
    return this.productPoolService.importFromExcel({
      fileBuffer: file.buffer,
      channelId,
      duplicateAction: duplicateAction || 'skip',
      platformCategoryId,
    });
  }
}
