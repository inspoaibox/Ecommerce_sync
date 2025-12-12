import { BasePlatformAdapter } from './base.adapter';
import { MockPlatformAdapter } from './mock.adapter';
import { WalmartAdapter } from './walmart.adapter';

export * from './base.adapter';
export * from './mock.adapter';
export * from './walmart.adapter';
export * from './walmart.config';

// 平台配置（包含各区域）
export const PLATFORM_CONFIGS: Record<string, { name: string; apiBaseUrl: string; regions?: string[] }> = {
  walmart: {
    name: 'Walmart',
    apiBaseUrl: 'https://marketplace.walmartapis.com',
    regions: ['US', 'CA', 'MX'],  // 支持的区域
  },
  amazon: {
    name: 'Amazon',
    apiBaseUrl: 'https://sellingpartnerapi.amazon.com',
  },
  ebay: {
    name: 'eBay',
    apiBaseUrl: 'https://api.ebay.com',
  },
  temu: {
    name: 'Temu',
    apiBaseUrl: 'https://openapi.temupro.com',
  },
  tiktok: {
    name: 'TikTok Shop',
    apiBaseUrl: 'https://open-api.tiktokglobalshop.com',
  },
};

export class PlatformAdapterFactory {
  /**
   * 创建平台适配器
   * @param platformCode 平台代码（如 walmart, amazon）
   * @param credentials API 凭证，可包含 region 字段指定区域
   */
  static create(platformCode: string, credentials: Record<string, any>): BasePlatformAdapter {
    switch (platformCode.toLowerCase()) {
      case 'mock':
        return new MockPlatformAdapter(credentials);
      case 'walmart':
        // WalmartAdapter 会根据 credentials.region 自动选择区域配置
        return new WalmartAdapter(credentials);
      // 后续添加其他平台适配器
      // case 'amazon':
      //   return new AmazonAdapter(credentials);
      default:
        throw new Error(`Unknown platform: ${platformCode}`);
    }
  }
}
