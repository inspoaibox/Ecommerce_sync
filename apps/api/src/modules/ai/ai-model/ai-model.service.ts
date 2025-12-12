import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '../../../common/prisma/prisma.service';
import { AiAdapterFactory, AiModelConfig } from '../../../adapters/ai';
import { encrypt, decrypt } from '../../../common/utils/crypto.util';

// 导入适配器注册
import '../../../adapters/ai';

export interface ModelInfo {
  id: string;
  name: string;
  maxTokens?: number;
}

@Injectable()
export class AiModelService {
  constructor(private prisma: PrismaService) {}

  /**
   * 获取渠道列表
   */
  async list() {
    const models = await this.prisma.aiModel.findMany({
      orderBy: [{ isDefault: 'desc' }, { createdAt: 'desc' }],
    });

    return models.map((model) => ({
      ...model,
      apiKey: decrypt(model.apiKey),
      modelList: model.modelList || [],
    }));
  }

  /**
   * 获取渠道详情
   */
  async findById(id: string) {
    const model = await this.prisma.aiModel.findUnique({ where: { id } });
    if (!model) {
      throw new NotFoundException('AI 渠道不存在');
    }
    return model;
  }

  /**
   * 获取默认渠道和模型
   */
  async getDefaultModel() {
    const model = await this.prisma.aiModel.findFirst({
      where: { isDefault: true, status: 'active' },
    });
    if (!model) {
      return this.prisma.aiModel.findFirst({
        where: { status: 'active' },
        orderBy: { createdAt: 'asc' },
      });
    }
    return model;
  }


  /**
   * 创建渠道（包含多个模型）
   */
  async create(data: {
    name: string;
    type: 'openai' | 'gemini' | 'openai_compatible';
    apiKey: string;
    baseUrl?: string;
    modelList: ModelInfo[];
    defaultModel?: string;
    maxTokens?: number;
    temperature?: number;
  }) {
    if (data.type === 'openai_compatible' && !data.baseUrl) {
      throw new BadRequestException('OpenAI 兼容接口必须提供 Base URL');
    }

    if (!data.modelList || data.modelList.length === 0) {
      throw new BadRequestException('请先获取模型列表');
    }

    const model = await this.prisma.aiModel.create({
      data: {
        name: data.name,
        type: data.type,
        apiKey: encrypt(data.apiKey),
        baseUrl: data.baseUrl,
        modelList: JSON.parse(JSON.stringify(data.modelList)),
        defaultModel: data.defaultModel || data.modelList[0]?.id,
        maxTokens: data.maxTokens || 4096,
        temperature: data.temperature || 0.7,
        isDefault: false,
      },
    });

    return {
      ...model,
      apiKey: data.apiKey,
    };
  }

  /**
   * 更新渠道
   */
  async update(
    id: string,
    data: {
      name?: string;
      apiKey?: string;
      baseUrl?: string;
      modelList?: ModelInfo[];
      defaultModel?: string;
      maxTokens?: number;
      temperature?: number;
      status?: 'active' | 'inactive';
    },
  ) {
    await this.findById(id);

    const updateData: any = {};
    if (data.name !== undefined) updateData.name = data.name;
    if (data.baseUrl !== undefined) updateData.baseUrl = data.baseUrl;
    if (data.defaultModel !== undefined) updateData.defaultModel = data.defaultModel;
    if (data.maxTokens !== undefined) updateData.maxTokens = data.maxTokens;
    if (data.temperature !== undefined) updateData.temperature = data.temperature;
    if (data.status !== undefined) updateData.status = data.status;
    if (data.apiKey) {
      updateData.apiKey = encrypt(data.apiKey);
    }
    if (data.modelList) {
      updateData.modelList = JSON.parse(JSON.stringify(data.modelList));
    }

    const model = await this.prisma.aiModel.update({
      where: { id },
      data: updateData,
    });

    return {
      ...model,
      apiKey: decrypt(model.apiKey),
    };
  }

  /**
   * 删除渠道
   */
  async delete(id: string) {
    await this.findById(id);
    
    const logsCount = await this.prisma.aiOptimizationLog.count({
      where: { modelId: id },
    });
    
    if (logsCount > 0) {
      throw new BadRequestException(`该渠道有 ${logsCount} 条优化记录，无法删除`);
    }

    return this.prisma.aiModel.delete({ where: { id } });
  }

  /**
   * 测试渠道连接
   */
  async testConnection(id: string) {
    const model = await this.findById(id);
    const modelList = (model.modelList as unknown as ModelInfo[]) || [];
    const testModel = model.defaultModel || modelList[0]?.id;

    if (!testModel) {
      return { success: false, message: '没有可用的模型' };
    }

    const config: AiModelConfig = {
      apiKey: decrypt(model.apiKey),
      baseUrl: model.baseUrl || undefined,
      modelName: testModel,
      maxTokens: model.maxTokens,
      temperature: Number(model.temperature),
    };

    try {
      const adapter = AiAdapterFactory.create(model.type, config);
      const success = await adapter.testConnection();
      return { success, message: success ? '连接成功' : '连接失败' };
    } catch (error) {
      return { success: false, message: error.message };
    }
  }

  /**
   * 设置为默认渠道
   */
  async setDefault(id: string, defaultModel?: string) {
    const model = await this.findById(id);

    await this.prisma.aiModel.updateMany({
      where: { isDefault: true },
      data: { isDefault: false },
    });

    return this.prisma.aiModel.update({
      where: { id },
      data: { 
        isDefault: true,
        defaultModel: defaultModel || model.defaultModel,
      },
    });
  }


  /**
   * 获取适配器实例
   */
  async getAdapter(channelId?: string, modelName?: string) {
    let channel;
    if (channelId) {
      channel = await this.findById(channelId);
    } else {
      channel = await this.getDefaultModel();
    }

    if (!channel) {
      throw new NotFoundException('没有可用的 AI 渠道，请先配置');
    }

    if (channel.status !== 'active') {
      throw new BadRequestException('该 AI 渠道已禁用');
    }

    const modelList = (channel.modelList as unknown as ModelInfo[]) || [];
    const useModel = modelName || channel.defaultModel || modelList[0]?.id;

    if (!useModel) {
      throw new BadRequestException('该渠道没有可用的模型');
    }

    const modelInfo = modelList.find(m => m.id === useModel);

    const config: AiModelConfig = {
      apiKey: decrypt(channel.apiKey),
      baseUrl: channel.baseUrl || undefined,
      modelName: useModel,
      maxTokens: modelInfo?.maxTokens || channel.maxTokens,
      temperature: Number(channel.temperature),
    };

    return {
      adapter: AiAdapterFactory.create(channel.type, config),
      model: channel,
      modelName: useModel,
    };
  }

  /**
   * 获取所有渠道的所有模型（用于默认模型选择）
   */
  async getAllModels() {
    const channels = await this.prisma.aiModel.findMany({
      where: { status: 'active' },
      orderBy: { createdAt: 'desc' },
    });

    const result: { channelId: string; channelName: string; modelId: string; modelName: string }[] = [];
    
    for (const channel of channels) {
      const modelList = (channel.modelList as unknown as ModelInfo[]) || [];
      for (const model of modelList) {
        result.push({
          channelId: channel.id,
          channelName: channel.name,
          modelId: model.id,
          modelName: model.name || model.id,
        });
      }
    }

    return result;
  }

  /**
   * 获取可用模型列表（从 API 动态获取）
   */
  async fetchAvailableModels(params: {
    type: 'openai' | 'gemini' | 'openai_compatible';
    apiKey: string;
    baseUrl?: string;
  }): Promise<ModelInfo[]> {
    const { type, apiKey, baseUrl } = params;

    try {
      if (type === 'openai') {
        return await this.fetchOpenAiModels(apiKey, 'https://api.openai.com/v1');
      } else if (type === 'openai_compatible') {
        if (!baseUrl) {
          throw new Error('OpenAI 兼容接口必须提供 Base URL');
        }
        return await this.fetchOpenAiModels(apiKey, baseUrl);
      } else if (type === 'gemini') {
        return await this.fetchGeminiModels(apiKey);
      }
    } catch (error) {
      console.error(`[AiModelService] Failed to fetch models:`, error.message);
      throw error;
    }

    return [];
  }

  private async fetchOpenAiModels(apiKey: string, baseUrl: string): Promise<ModelInfo[]> {
    const url = `${baseUrl}/models`;
    
    const response = await fetch(url, {
      method: 'GET',
      headers: { 'Authorization': `Bearer ${apiKey}` },
    });

    if (!response.ok) {
      const errorText = await response.text().catch(() => '');
      let errorMsg = `API 请求失败: ${response.status}`;
      try {
        const errorJson = JSON.parse(errorText);
        if (errorJson.error?.message) errorMsg = errorJson.error.message;
      } catch {}
      throw new Error(errorMsg);
    }

    const data = await response.json();
    let models = data.data || data.models || (Array.isArray(data) ? data : []);

    return models
      .map((m: any) => ({
        id: m.id || m.name || m.model,
        name: m.id || m.name || m.model,
        maxTokens: m.context_length || m.max_tokens || this.estimateMaxTokens(m.id || ''),
      }))
      .filter((m: any) => m.id)
      .sort((a: any, b: any) => a.id.localeCompare(b.id));
  }

  private async fetchGeminiModels(apiKey: string): Promise<ModelInfo[]> {
    const url = `https://generativelanguage.googleapis.com/v1beta/models?key=${apiKey}`;
    
    const response = await fetch(url, { method: 'GET' });

    if (!response.ok) {
      throw new Error(`Gemini API 请求失败: ${response.status}`);
    }

    const data = await response.json();
    const models = data.models || [];

    return models
      .filter((m: any) => m.supportedGenerationMethods?.includes('generateContent'))
      .map((m: any) => ({
        id: m.name?.replace('models/', '') || '',
        name: m.displayName || m.name?.replace('models/', '') || '',
        maxTokens: m.outputTokenLimit || 8192,
      }))
      .sort((a: any, b: any) => a.id.localeCompare(b.id));
  }

  private estimateMaxTokens(modelId: string): number {
    const id = modelId.toLowerCase();
    if (id.includes('gpt-4o') || id.includes('gpt-4-turbo')) return 128000;
    if (id.includes('gpt-4')) return 8192;
    if (id.includes('gpt-3.5')) return 16385;
    if (id.includes('claude-3')) return 200000;
    if (id.includes('deepseek')) return 64000;
    if (id.includes('qwen')) return 32000;
    if (id.includes('gemini-1.5')) return 1048576;
    if (id.includes('gemini')) return 32768;
    return 4096;
  }
}
