/**
 * AI 适配器基类和接口定义
 * 
 * 统一的 AI 模型调用接口，屏蔽不同 AI 服务商的差异
 */

/**
 * AI 生成选项
 */
export interface GenerateOptions {
  /** 最大 Token 数 */
  maxTokens?: number;
  /** 温度参数 0-2，越高越随机 */
  temperature?: number;
  /** Top-P 采样 */
  topP?: number;
  /** 停止序列 */
  stopSequences?: string[];
}

/**
 * Token 使用统计
 */
export interface TokenUsage {
  /** 输入 Token */
  promptTokens: number;
  /** 输出 Token */
  completionTokens: number;
  /** 总 Token */
  totalTokens: number;
}

/**
 * AI 生成结果
 */
export interface GenerateResult {
  /** 生成的内容 */
  content: string;
  /** Token 使用统计 */
  usage: TokenUsage;
  /** 完成原因: stop, length, content_filter */
  finishReason: string;
}

/**
 * AI 模型配置
 */
export interface AiModelConfig {
  /** API Key */
  apiKey: string;
  /** Base URL（OpenAI 兼容接口需要） */
  baseUrl?: string;
  /** 模型名称 */
  modelName: string;
  /** 默认最大 Token */
  maxTokens?: number;
  /** 默认温度 */
  temperature?: number;
}

/**
 * AI 适配器接口
 */
export interface IAiAdapter {
  /**
   * 测试连接
   */
  testConnection(): Promise<boolean>;

  /**
   * 生成内容
   * @param prompt 提示词
   * @param options 生成选项
   */
  generate(prompt: string, options?: GenerateOptions): Promise<GenerateResult>;
}

/**
 * AI 适配器基类
 */
export abstract class BaseAiAdapter implements IAiAdapter {
  protected config: AiModelConfig;

  constructor(config: AiModelConfig) {
    this.config = config;
  }

  /**
   * 测试连接
   */
  abstract testConnection(): Promise<boolean>;

  /**
   * 生成内容
   */
  abstract generate(prompt: string, options?: GenerateOptions): Promise<GenerateResult>;

  /**
   * 获取默认选项
   */
  protected getDefaultOptions(): GenerateOptions {
    return {
      maxTokens: this.config.maxTokens || 2000,
      temperature: this.config.temperature || 0.7,
    };
  }

  /**
   * 合并选项
   */
  protected mergeOptions(options?: GenerateOptions): GenerateOptions {
    const defaults = this.getDefaultOptions();
    return {
      ...defaults,
      ...options,
    };
  }
}

/**
 * AI 适配器工厂
 */
export class AiAdapterFactory {
  private static adapters: Map<string, new (config: AiModelConfig) => BaseAiAdapter> = new Map();

  /**
   * 注册适配器
   */
  static register(type: string, adapter: new (config: AiModelConfig) => BaseAiAdapter): void {
    this.adapters.set(type, adapter);
  }

  /**
   * 创建适配器实例
   */
  static create(type: string, config: AiModelConfig): BaseAiAdapter {
    const AdapterClass = this.adapters.get(type);
    if (!AdapterClass) {
      throw new Error(`Unsupported AI model type: ${type}`);
    }
    return new AdapterClass(config);
  }

  /**
   * 获取支持的类型列表
   */
  static getSupportedTypes(): string[] {
    return Array.from(this.adapters.keys());
  }
}
