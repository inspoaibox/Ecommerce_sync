import { BasePlatformAdapter, SyncProductData, SyncResult, BatchSyncResult } from './base.adapter';
import axios, { AxiosInstance } from 'axios';
import * as crypto from 'crypto';

export class WalmartAdapter extends BasePlatformAdapter {
  private client: AxiosInstance;
  private accessToken: string | null = null;
  private tokenExpiry: number = 0;

  constructor(credentials: Record<string, any>) {
    super(credentials);
    const baseURL = credentials.apiBaseUrl || 'https://marketplace.walmartapis.com';
    this.client = axios.create({
      baseURL,
      timeout: 30000,
    });
  }

  // 获取访问令牌
  private async getAccessToken(): Promise<string> {
    if (this.accessToken && Date.now() < this.tokenExpiry) {
      return this.accessToken;
    }

    const { clientId, clientSecret } = this.credentials;
    const authString = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');

    const response = await this.client.post(
      '/v3/token',
      'grant_type=client_credentials',
      {
        headers: {
          'Authorization': `Basic ${authString}`,
          'Content-Type': 'application/x-www-form-urlencoded',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': this.generateCorrelationId(),
        },
      }
    );

    this.accessToken = response.data.access_token;
    // Token有效期通常是15分钟，提前1分钟刷新
    this.tokenExpiry = Date.now() + (response.data.expires_in - 60) * 1000;
    return this.accessToken!;
  }

  // 生成关联ID
  private generateCorrelationId(): string {
    return crypto.randomUUID();
  }

  // 获取请求头
  private async getHeaders(): Promise<Record<string, string>> {
    const token = await this.getAccessToken();
    return {
      'Authorization': `Bearer ${token}`,
      'WM_SVC.NAME': 'Walmart Marketplace',
      'WM_QOS.CORRELATION_ID': this.generateCorrelationId(),
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
  }

  async testConnection(): Promise<boolean> {
    try {
      await this.getAccessToken();
      // 尝试获取商品列表来验证连接
      const headers = await this.getHeaders();
      await this.client.get('/v3/items', {
        headers,
        params: { limit: 1 },
      });
      return true;
    } catch (error) {
      console.error('Walmart connection test failed:', error);
      return false;
    }
  }

  async syncProduct(product: SyncProductData): Promise<SyncResult> {
    try {
      const headers = await this.getHeaders();
      
      // Walmart使用Feed API来更新商品
      const feedData = {
        MPItemFeedHeader: {
          version: '4.2',
          requestId: this.generateCorrelationId(),
          requestBatchId: this.generateCorrelationId(),
        },
        MPItem: [{
          sku: product.sku,
          productIdentifiers: {
            productIdType: 'SKU',
            productId: product.sku,
          },
          price: {
            currency: product.currency || 'USD',
            amount: product.price,
          },
        }],
      };

      const response = await this.client.post('/v3/feeds', feedData, {
        headers,
        params: { feedType: 'MP_ITEM' },
      });

      return {
        success: true,
        platformProductId: response.data.feedId,
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.response?.data?.message || error.message,
      };
    }
  }

  async batchSyncProducts(products: SyncProductData[]): Promise<BatchSyncResult> {
    const results: Array<{ sku: string; success: boolean; error?: string }> = [];
    let successCount = 0;
    let failCount = 0;

    // Walmart支持批量Feed，但这里简化处理
    for (const product of products) {
      const result = await this.syncProduct(product);
      results.push({
        sku: product.sku,
        success: result.success,
        error: result.error,
      });
      if (result.success) successCount++;
      else failCount++;
    }

    return {
      total: products.length,
      successCount,
      failCount,
      results,
    };
  }

  async updateStock(sku: string, stock: number): Promise<boolean> {
    try {
      const headers = await this.getHeaders();
      
      const inventoryData = {
        sku,
        quantity: {
          unit: 'EACH',
          amount: stock,
        },
      };

      await this.client.put(`/v3/inventory`, inventoryData, {
        headers,
        params: { sku },
      });

      return true;
    } catch (error) {
      console.error('Walmart update stock failed:', error);
      return false;
    }
  }

  async updatePrice(sku: string, price: number): Promise<boolean> {
    try {
      const headers = await this.getHeaders();
      
      const priceData = {
        pricing: [{
          currentPrice: {
            currency: 'USD',
            amount: price,
          },
        }],
      };

      await this.client.put(`/v3/price`, priceData, {
        headers,
        params: { sku },
      });

      return true;
    } catch (error) {
      console.error('Walmart update price failed:', error);
      return false;
    }
  }

  // 获取商品列表
  async getItems(limit = 20, offset = 0): Promise<any[]> {
    try {
      const headers = await this.getHeaders();
      const response = await this.client.get('/v3/items', {
        headers,
        params: { limit, offset },
      });
      return response.data.ItemResponse || [];
    } catch (error) {
      console.error('Walmart get items failed:', error);
      return [];
    }
  }

  // 获取单个商品
  async getItem(sku: string): Promise<any | null> {
    try {
      const headers = await this.getHeaders();
      const response = await this.client.get(`/v3/items/${sku}`, { headers });
      return response.data;
    } catch (error) {
      console.error('Walmart get item failed:', error);
      return null;
    }
  }
}
