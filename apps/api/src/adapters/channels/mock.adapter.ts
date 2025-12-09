import { BaseChannelAdapter, ChannelProduct, FetchOptions, FetchResult } from './base.adapter';

// Mock适配器 - 用于测试
export class MockChannelAdapter extends BaseChannelAdapter {
  async testConnection(): Promise<boolean> {
    return true;
  }

  async fetchProducts(options: FetchOptions): Promise<FetchResult> {
    const { page = 1, pageSize = 20 } = options;
    const total = 100;
    const start = (page - 1) * pageSize;

    const products: ChannelProduct[] = [];
    for (let i = start; i < Math.min(start + pageSize, total); i++) {
      products.push({
        channelProductId: `MOCK-${i + 1}`,
        sku: `SKU-${String(i + 1).padStart(5, '0')}`,
        title: `Mock Product ${i + 1}`,
        price: Math.round(Math.random() * 10000) / 100,
        stock: Math.floor(Math.random() * 100),
        currency: 'USD',
        extraFields: { category: 'Electronics' },
      });
    }

    return {
      products,
      total,
      hasMore: start + pageSize < total,
    };
  }

  async fetchProduct(productId: string): Promise<ChannelProduct | null> {
    return {
      channelProductId: productId,
      sku: `SKU-${productId}`,
      title: `Mock Product ${productId}`,
      price: 99.99,
      stock: 50,
      currency: 'USD',
    };
  }
}
