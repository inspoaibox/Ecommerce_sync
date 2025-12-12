/**
 * OpenAI 适配器
 * 
 * 支持 GPT-4、GPT-3.5-turbo 等模型
 */

import { BaseAiAdapter, AiModelConfig, GenerateOptions, GenerateResult } from './base.adapter';

export class OpenAiAdapter extends BaseAiAdapter {
  private baseUrl: string;

  constructor(config: AiModelConfig) {
    super(config);
    this.baseUrl = config.baseUrl || 'https://api.openai.com/v1';
  }

  /**
   * 测试连接
   */
  async testConnection(): Promise<boolean> {
    try {
      const response = await fetch(`${this.baseUrl}/models`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${this.config.apiKey}`,
        },
      });
      return response.ok;
    } catch (error) {
      console.error('[OpenAI] Connection test failed:', error);
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
      throw new Error(`OpenAI API error: ${response.status} - ${error.error?.message || 'Unknown error'}`);
    }

    const data = await response.json();
    const choice = data.choices?.[0];

    if (!choice) {
      throw new Error('OpenAI API returned no choices');
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
