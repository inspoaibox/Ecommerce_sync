import { Controller, Get, Post, Body, Param, Query } from '@nestjs/common';
import { OptimizationService, OptimizeField } from './optimization.service';

@Controller('ai/optimize')
export class OptimizationController {
  constructor(private readonly optimizationService: OptimizationService) {}

  @Post()
  async optimize(@Body() data: {
    productId: string;
    productType: 'pool' | 'listing';
    fields: OptimizeField[];
    modelId?: string;
    templateIds?: Partial<Record<OptimizeField, string>>;
  }) {
    return this.optimizationService.optimizeProduct(data);
  }

  @Post('batch')
  async batchOptimize(@Body() data: {
    products: Array<{ id: string; type: 'pool' | 'listing' }>;
    fields: OptimizeField[];
    modelId?: string;
    templateIds?: Partial<Record<OptimizeField, string>>;
  }) {
    return this.optimizationService.batchOptimize(data);
  }

  @Post('apply')
  async applyOptimization(@Body() data: { logIds: string[] }) {
    return this.optimizationService.applyOptimization(data.logIds);
  }

  @Get('logs')
  async listLogs(@Query() query: {
    page?: number;
    pageSize?: number;
    productSku?: string;
    field?: string;
    status?: string;
    startDate?: string;
    endDate?: string;
  }) {
    return this.optimizationService.listLogs(query);
  }

  @Get('logs/:id')
  async getLogDetail(@Param('id') id: string) {
    return this.optimizationService.getLogDetail(id);
  }
}
