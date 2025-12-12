/**
 * OpenAI 兼容适配器
 * 
 * 支持第三方 OpenAI 兼容接口（如 Azure OpenAI、各种中转服务）
 * 与 OpenAI 适配器类似，但必须指定 baseUrl
 */

import { BaseAiAdapter, AiModelConfig, GenerateOptions, GenerateResult } from './base.adapter';

export class OpenAiCompatibleAdapter extends BaseAiAdapter {
  private baseUrl: string;

  constructor(config: AiModelConfig) {
    super(config);
    if (!config.baseUrl) {
      throw new Error('OpenAI Compatible adapter requires baseUrl');
    }
    this.baseUrl = config.baseUrl;
  }

  /**
   * 测试连接
   */
  async testConnection(): Promise<boolean> {
    try {
      // 尝试调用 models 接口，如果不支持则尝试简单的 chat 请求
      const response = await fetch(`${this.baseUrl}/models`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${this.config.apiKey}`,
        },
      });
      
      if (response.ok) {
        return true;
      }

      // 如果 models 接口不可用，尝试发送一个简单的请求
      const testResponse = await fetch(`${this.baseUrl}/chat/completions`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.config.apiKey}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: this.config.modelName,
          messages: [{ role: 'user', content: 'test' }],
          max_tokens: 5,
        }),
      });

      return testResponse.ok;
    } catch (error) {
      console.error('[OpenAI Compatible] Connection test failed:', error);
      return false;
    }
  }

  /**
   * 生成内容
   */
  async generate(prompt: string, options?: GenerateOptions): Promise<GenerateResult> {
    const mergedOptions = this.mergeOptions(options);

    const requestBody = {
      model: this.config.modelName,
      messages: [
        {
          role: 'user',
          content: prompt,
        },
      ],
      max_tokens: mergedOptions.maxTokens,
      temperature: mergedOptions.temperature,
      top_p: mergedOptions.topP,
      stop: mergedOptions.stopSequences,
    };

    const response = await fetch(`${this.baseUrl}/chat/completions`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.config.apiKey}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(requestBody),
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(`API error: ${response.status} - ${error.error?.message || error.message || 'Unknown error'}`);
    }

    const data = await response.json();
    const choice = data.choices?.[0];

    if (!choice) {
      throw new Error('API returned no choices');
    }

    return {
      content: choice.message?.content || '',
      usage: {
        promptTokens: data.usage?.prompt_tokens || 0,
        completionTokens: data.usage?.completion_tokens || 0,
        totalTokens: data.usage?.total_tokens || 0,
      },
      finishReason: choice.finish_reason || 'unknown',
    };
  }
}
