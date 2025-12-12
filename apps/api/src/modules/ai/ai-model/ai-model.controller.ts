import { Controller, Get, Post, Put, Delete, Body, Param, HttpCode } from '@nestjs/common';
import { AiModelService } from './ai-model.service';

@Controller('ai/models')
export class AiModelController {
  constructor(private readonly aiModelService: AiModelService) {}

  @Get()
  async list() {
    return this.aiModelService.list();
  }

  @Get('all-models')
  async getAllModels() {
    return this.aiModelService.getAllModels();
  }

  @Post()
  async create(@Body() data: {
    name: string;
    type: 'openai' | 'gemini' | 'openai_compatible';
    apiKey: string;
    baseUrl?: string;
    modelList: { id: string; name: string; maxTokens?: number }[];
    defaultModel?: string;
    maxTokens?: number;
    temperature?: number;
  }) {
    return this.aiModelService.create(data);
  }

  @Post('fetch-models')
  async fetchAvailableModels(@Body() data: {
    type: 'openai' | 'gemini' | 'openai_compatible';
    apiKey: string;
    baseUrl?: string;
  }) {
    return this.aiModelService.fetchAvailableModels(data);
  }

  @Get(':id')
  async findById(@Param('id') id: string) {
    return this.aiModelService.findById(id);
  }

  @Put(':id')
  async update(
    @Param('id') id: string,
    @Body() data: {
      name?: string;
      apiKey?: string;
      baseUrl?: string;
      modelList?: { id: string; name: string; maxTokens?: number }[];
      defaultModel?: string;
      maxTokens?: number;
      temperature?: number;
      status?: 'active' | 'inactive';
    },
  ) {
    return this.aiModelService.update(id, data);
  }

  @Delete(':id')
  @HttpCode(204)
  async delete(@Param('id') id: string) {
    await this.aiModelService.delete(id);
  }

  @Post(':id/test')
  async testConnection(@Param('id') id: string) {
    return this.aiModelService.testConnection(id);
  }

  @Post(':id/default')
  async setDefault(@Param('id') id: string, @Body() data?: { defaultModel?: string }) {
    return this.aiModelService.setDefault(id, data?.defaultModel);
  }
}
