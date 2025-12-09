import { BasePlatformAdapter } from './base.adapter';
import { MockPlatformAdapter } from './mock.adapter';
import { WalmartAdapter } from './walmart.adapter';

export * from './base.adapter';
export * from './mock.adapter';
export * from './walmart.adapter';

// 平台配置
export const PLATFORM_CONFIGS: Record<string, { name: string; apiBaseUrl: string }> = {
  walmart: {
    name: 'Walmart',
    apiBaseUrl: 'https://marketplace.walmartapis.com',
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
  static create(platformCode: string, credentials: Record<string, any>): BasePlatformAdapter {
    switch (platformCode.toLowerCase()) {
      case 'mock':
        return new MockPlatformAdapter(credentials);
      case 'walmart':
        return new WalmartAdapter(credentials);
      // 后续添加其他平台适配器
      // case 'amazon':
      //   return new AmazonAdapter(credentials);
      default:
        throw new Error(`Unknown platform: ${platformCode}`);
    }
  }
}
