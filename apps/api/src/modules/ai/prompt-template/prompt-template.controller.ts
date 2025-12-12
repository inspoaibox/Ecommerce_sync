import { Controller, Get, Post, Put, Delete, Body, Param, Query, HttpCode } from '@nestjs/common';
import { PromptTemplateService, PromptType } from './prompt-template.service';

@Controller('ai/templates')
export class PromptTemplateController {
  constructor(private readonly promptTemplateService: PromptTemplateService) {}

  @Get()
  async list(@Query('type') type?: PromptType) {
    return this.promptTemplateService.list(type);
  }

  @Get(':id')
  async findById(@Param('id') id: string) {
    return this.promptTemplateService.findById(id);
  }

  @Post()
  async create(@Body() data: {
    name: string;
    type: PromptType;
    content: string;
    description?: string;
    isDefault?: boolean;
  }) {
    return this.promptTemplateService.create(data);
  }

  @Put(':id')
  async update(
    @Param('id') id: string,
    @Body() data: {
      name?: string;
      content?: string;
      description?: string;
      status?: 'active' | 'inactive';
    },
  ) {
    return this.promptTemplateService.update(id, data);
  }

  @Delete(':id')
  @HttpCode(204)
  async delete(@Param('id') id: string) {
    await this.promptTemplateService.delete(id);
  }

  @Post(':id/duplicate')
  async duplicate(@Param('id') id: string) {
    return this.promptTemplateService.duplicate(id);
  }

  @Post(':id/default')
  async setDefault(@Param('id') id: string) {
    return this.promptTemplateService.setDefault(id);
  }

  @Post('preview')
  async preview(@Body() data: { templateId: string; variables: Record<string, any> }) {
    return this.promptTemplateService.preview(data.templateId, data.variables);
  }
}
