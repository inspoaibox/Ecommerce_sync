/**
 * AI 适配器模块导出
 */

export * from './base.adapter';
export * from './openai.adapter';
export * from './gemini.adapter';
export * from './openai-compatible.adapter';

// 注册适配器
import { AiAdapterFactory } from './base.adapter';
import { OpenAiAdapter } from './openai.adapter';
import { GeminiAdapter } from './gemini.adapter';
import { OpenAiCompatibleAdapter } from './openai-compatible.adapter';

// 注册所有适配器
AiAdapterFactory.register('openai', OpenAiAdapter);
AiAdapterFactory.register('gemini', GeminiAdapter);
AiAdapterFactory.register('openai_compatible', OpenAiCompatibleAdapter);
