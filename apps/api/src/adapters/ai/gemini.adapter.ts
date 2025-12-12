/**
 * Google Gemini 适配器
 * 
 * 支持 gemini-pro、gemini-1.5-pro 等模型
 */

import { BaseAiAdapter, AiModelConfig, GenerateOptions, GenerateResult } from './base.adapter';

export class GeminiAdapter extends BaseAiAdapter {
  private baseUrl: string;

  constructor(config: AiModelConfig) {
    super(config);
    this.baseUrl = config.baseUrl || 'https://generativelanguage.googleapis.com/v1beta';
  }

  /**
   * 测试连接
   */
  async testConnection(): Promise<boolean> {
    try {
      const url = `${this.baseUrl}/models?key=${this.config.apiKey}`;
      const response = await fetch(url, {
        method: 'GET',
      });
      return response.ok;
    } catch (error) {
      console.error('[Gemini] Connection test failed:', error);
      return false;
    }
  }

  /**
   * 生成内容
   */
  async generate(prompt: string, options?: GenerateOptions): Promise<GenerateResult> {
    const mergedOptions = this.mergeOptions(options);

    const requestBody = {
      contents: [
        {
          parts: [
            {
              text: prompt,
            },
          ],
        },
      ],
      generationConfig: {
        maxOutputTokens: mergedOptions.maxTokens,
        temperature: mergedOptions.temperature,
        topP: mergedOptions.topP,
        stopSequences: mergedOptions.stopSequences,
      },
    };

    const url = `${this.baseUrl}/models/${this.config.modelName}:generateContent?key=${this.config.apiKey}`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(requestBody),
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(`Gemini API error: ${response.status} - ${error.error?.message || 'Unknown error'}`);
    }

    const data = await response.json();
    const candidate = data.candidates?.[0];

    if (!candidate) {
      throw new Error('Gemini API returned no candidates');
    }

    const content = candidate.content?.parts?.[0]?.text || '';
    const usageMetadata = data.usageMetadata || {};

    return {
      content,
      usage: {
        promptTokens: usageMetadata.promptTokenCount || 0,
        completionTokens: usageMetadata.candidatesTokenCount || 0,
        totalTokens: usageMetadata.totalTokenCount || 0,
      },
      finishReason: candidate.finishReason || 'unknown',
    };
  }
}
