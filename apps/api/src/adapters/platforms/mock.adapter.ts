import { BasePlatformAdapter, SyncProductData, SyncResult, BatchSyncResult } from './base.adapter';

// Mock平台适配器 - 用于测试
export class MockPlatformAdapter extends BasePlatformAdapter {
  async testConnection(): Promise<boolean> {
    return true;
  }

  async syncProduct(product: SyncProductData): Promise<SyncResult> {
    // 模拟同步延迟
    await new Promise((resolve) => setTimeout(resolve, 100));
    return {
      success: true,
      platformProductId: `PLAT-${product.sku}`,
    };
  }

  async batchSyncProducts(products: SyncProductData[]): Promise<BatchSyncResult> {
    const results = await Promise.all(
      products.map(async (p) => {
        const result = await this.syncProduct(p);
        return { sku: p.sku, success: result.success, error: result.error };
      }),
    );

    return {
      total: products.length,
      successCount: results.filter((r) => r.success).length,
      failCount: results.filter((r) => !r.success).length,
      results,
    };
  }

  async updateStock(sku: string, stock: number): Promise<boolean> {
    return true;
  }

  async updatePrice(sku: string, price: number): Promise<boolean> {
    return true;
  }
}
