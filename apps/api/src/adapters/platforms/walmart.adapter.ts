import {
  BasePlatformAdapter,
  SyncProductData,
  SyncResult,
  BatchSyncResult,
} from './base.adapter';
import axios, { AxiosInstance } from 'axios';
import * as crypto from 'crypto';

export class WalmartAdapter extends BasePlatformAdapter {
  private client: AxiosInstance;
  private accessToken: string | null = null;
  private tokenExpiry: number = 0;

  constructor(credentials: Record<string, any>) {
    super(credentials);
    const baseURL =
      credentials.apiBaseUrl || 'https://marketplace.walmartapis.com';
    this.client = axios.create({
      baseURL,
      timeout: 60000, // 60秒超时
    });

    // 如果已有accessToken，直接使用
    if (credentials.accessToken) {
      this.accessToken = credentials.accessToken;
      this.tokenExpiry = Date.now() + 14 * 60 * 1000; // 假设14分钟有效
    }
  }

  // 生成关联ID
  private generateCorrelationId(): string {
    return crypto.randomUUID();
  }

  // 获取访问令牌
  private async getAccessToken(): Promise<string> {
    // 如果有有效的token，直接返回
    if (this.accessToken && Date.now() < this.tokenExpiry) {
      return this.accessToken;
    }

    const { clientId, clientSecret, refreshToken } = this.credentials;
    if (!clientId || !clientSecret) {
      throw new Error('缺少 Client ID 或 Client Secret');
    }

    const authString = Buffer.from(`${clientId}:${clientSecret}`).toString(
      'base64',
    );

    try {
      // 构建请求数据
      const params = new URLSearchParams();
      params.append('grant_type', 'client_credentials');

      // 如果有refresh_token，添加到请求中
      if (refreshToken) {
        params.append('refresh_token', refreshToken);
      }

      const response = await this.client.post('/v3/token', params.toString(), {
        headers: {
          Authorization: `Basic ${authString}`,
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
          'WM_SVC.NAME': 'Walmart Marketplace',
          'WM_QOS.CORRELATION_ID': this.generateCorrelationId(),
        },
      });

      this.accessToken = response.data.access_token;
      // Token有效期通常是15分钟，提前1分钟刷新
      const expiresIn = response.data.expires_in || 900;
      this.tokenExpiry = Date.now() + (expiresIn - 60) * 1000;
      return this.accessToken!;
    } catch (error: any) {
      const errData = error.response?.data;
      const errMsg =
        errData?.error_description ||
        errData?.error?.description ||
        errData?.message ||
        error.message;
      throw new Error(`Walmart认证失败: ${errMsg}`);
    }
  }

  // 获取请求头
  private async getHeaders(): Promise<Record<string, string>> {
    const token = await this.getAccessToken();
    return {
      'WM_SEC.ACCESS_TOKEN': token,
      'WM_SVC.NAME': 'Walmart Marketplace',
      'WM_QOS.CORRELATION_ID': this.generateCorrelationId(),
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
  }

  async testConnection(): Promise<boolean> {
    try {
      await this.getAccessToken();
      return true;
    } catch (error: any) {
      const errData = error.response?.data;
      let errMsg = error.message;
      if (errData) {
        if (errData.error) {
          errMsg = Array.isArray(errData.error) 
            ? errData.error.map((e: any) => e.description || e.message || JSON.stringify(e)).join('; ')
            : errData.error.description || errData.error.message || JSON.stringify(errData.error);
        } else if (errData.errors) {
          errMsg = errData.errors.map((e: any) => e.description || e.message || JSON.stringify(e)).join('; ');
        } else if (errData.message) {
          errMsg = errData.message;
        }
      }
      console.error('Walmart connection test failed:', errMsg);
      throw new Error(errMsg);
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
        MPItem: [
          {
            sku: product.sku,
            productIdentifiers: {
              productIdType: 'SKU',
              productId: product.sku,
            },
            price: {
              currency: product.currency || 'USD',
              amount: product.price,
            },
          },
        ],
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
        pricing: [
          {
            currentPrice: {
              currency: 'USD',
              amount: price,
            },
          },
        ],
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

  // 批量更新价格（使用Feed API）
  async batchUpdatePrices(items: Array<{ sku: string; price: number; msrp?: number }>): Promise<{ feedId: string }> {
    try {
      const headers = await this.getHeaders();

      // 构建Feed数据
      const feedData = {
        MPItemFeedHeader: {
          businessUnit: 'WALMART_US',
          version: '2.0.20240126-12_25_52-api',
          locale: 'en',
        },
        MPItem: items.map(item => ({
          'Promo&Discount': {
            sku: item.sku,
            price: item.price,
            msrp: item.msrp || item.price, // 如果没有MSRP，使用price
          },
        })),
      };

      console.log(`[Walmart] Submitting price feed with ${items.length} items`);

      // 使用 multipart/form-data 上传（与库存更新保持一致）
      const FormData = require('form-data');
      const form = new FormData();
      form.append('file', JSON.stringify(feedData), {
        filename: 'price.json',
        contentType: 'application/json',
      });

      const response = await this.client.post('/v3/feeds', form, {
        headers: {
          ...headers,
          ...form.getHeaders(),
        },
        params: { feedType: 'PRICE_AND_PROMOTION' },
      });

      console.log(`[Walmart] Price feed submitted, feedId: ${response.data.feedId}`);

      return {
        feedId: response.data.feedId,
      };
    } catch (error: any) {
      const errData = error.response?.data;
      const errMsg = errData?.error?.[0]?.description || errData?.message || error.message;
      console.error('[Walmart] Batch update prices failed:', errMsg);
      throw new Error(`批量更新价格失败: ${errMsg}`);
    }
  }

  // 批量更新库存（使用Feed API）
  async batchUpdateInventory(items: Array<{ sku: string; quantity: number }>): Promise<{ feedId: string }> {
    try {
      const headers = await this.getHeaders();

      // 构建Feed数据（Spec 1.4 - 单个ship node）
      const feedData = {
        InventoryHeader: {
          version: '1.4',
        },
        Inventory: items.map(item => ({
          sku: item.sku,
          quantity: {
            unit: 'EACH',
            amount: item.quantity,
          },
        })),
      };

      console.log(`[Walmart] Submitting inventory feed with ${items.length} items`);

      // 使用 multipart/form-data 上传
      const FormData = require('form-data');
      const form = new FormData();
      form.append('file', JSON.stringify(feedData), {
        filename: 'inventory.json',
        contentType: 'application/json',
      });

      const response = await this.client.post('/v3/feeds', form, {
        headers: {
          ...headers,
          ...form.getHeaders(),
        },
        params: { feedType: 'inventory' },
      });

      console.log(`[Walmart] Inventory feed submitted, feedId: ${response.data.feedId}`);

      return {
        feedId: response.data.feedId,
      };
    } catch (error: any) {
      const errData = error.response?.data;
      const errMsg = errData?.error?.[0]?.description || errData?.message || error.message;
      console.error('[Walmart] Batch update inventory failed:', errMsg);
      throw new Error(`批量更新库存失败: ${errMsg}`);
    }
  }

  // 查询Feed状态（单页）
  async getFeedStatus(feedId: string, includeDetails: boolean = true, offset: number = 0, limit: number = 50): Promise<any> {
    try {
      const headers = await this.getHeaders();

      const response = await this.client.get(`/v3/feeds/${feedId}`, {
        headers,
        params: { includeDetails, offset, limit },
      });

      return response.data;
    } catch (error: any) {
      const errData = error.response?.data;
      const errMsg = errData?.error?.[0]?.description || errData?.message || error.message;
      console.error('[Walmart] Get feed status failed:', errMsg);
      throw new Error(`查询Feed状态失败: ${errMsg}`);
    }
  }

  // 获取所有 Feed 明细（自动分页获取全部）
  // statusFilter: 'all' | 'failed' | 'success' - 筛选状态
  // 注意：Walmart API 有 offset 上限 10000 的限制
  async getFeedStatusAll(feedId: string, statusFilter: 'all' | 'failed' | 'success' = 'all'): Promise<any> {
    const limit = 50;
    const maxOffset = 10000; // Walmart API 的硬性限制
    let offset = 0;
    let allItems: any[] = [];
    let baseData: any = null;
    let pageCount = 0;
    let reachedLimit = false;

    console.log(`[Walmart] Starting to fetch feed details for ${feedId}, filter: ${statusFilter}`);

    while (true) {
      // 检查是否达到 Walmart API 的 offset 上限
      if (offset >= maxOffset) {
        console.log(`[Walmart] Feed ${feedId}: reached Walmart API max offset limit (10000), stopping`);
        reachedLimit = true;
        break;
      }

      pageCount++;
      const data = await this.getFeedStatus(feedId, true, offset, limit);
      
      if (!baseData) {
        baseData = { ...data };
        console.log(`[Walmart] Feed ${feedId}: itemsReceived=${data.itemsReceived}, itemsSucceeded=${data.itemsSucceeded}, itemsFailed=${data.itemsFailed}`);
      }

      const items = data.itemDetails?.itemIngestionStatus || [];
      
      // 根据筛选条件过滤
      const filteredItems = statusFilter === 'all' 
        ? items 
        : statusFilter === 'failed'
          ? items.filter((item: any) => item.ingestionStatus !== 'SUCCESS')
          : items.filter((item: any) => item.ingestionStatus === 'SUCCESS');
      
      allItems = allItems.concat(filteredItems);

      // 如果返回的数量小于 limit，说明已经是最后一页
      if (items.length < limit) {
        break;
      }

      offset += limit;

      // 每50页打印一次进度
      if (pageCount % 50 === 0) {
        console.log(`[Walmart] Feed ${feedId}: fetched ${allItems.length} ${statusFilter} items (page ${pageCount})`);
      }

      // 添加延迟避免请求过快
      await new Promise(resolve => setTimeout(resolve, 100));
    }

    // 合并所有结果
    baseData.itemDetails = { itemIngestionStatus: allItems };
    baseData.totalFetched = allItems.length;
    baseData.statusFilter = statusFilter;
    baseData.reachedApiLimit = reachedLimit; // 标记是否达到 API 限制
    
    console.log(`[Walmart] Feed ${feedId}: completed, total ${allItems.length} ${statusFilter} items in ${pageCount} pages${reachedLimit ? ' (reached API limit)' : ''}`);
    
    return baseData;
  }

  // 获取商品列表（使用offset+limit分页，每页最多200条）
  async getItems(
    offset: number = 0,
    limit: number = 200,
  ): Promise<{ items: any[]; totalItems: number }> {
    try {
      const headers = await this.getHeaders();

      const response = await this.client.get('/v3/items', {
        headers,
        params: { offset, limit },
      });

      return {
        items: response.data.ItemResponse || [],
        totalItems: response.data.totalItems || 0,
      };
    } catch (error: any) {
      console.error(
        'Walmart get items failed:',
        error.response?.data || error.message,
      );
      throw new Error(error.response?.data?.message || error.message);
    }
  }

  // 获取所有商品（使用offset+limit分页，过滤isDuplicate商品）
  // startOffset: 断点续传时从指定位置开始
  async *getItemsBatched(startOffset: number = 0): AsyncGenerator<{
    items: any[];
    fetched: number;
    total: number;
    isLast: boolean;
  }> {
    const RATE_LIMIT_DELAY = 1200; // 1.2秒间隔
    const PAGE_SIZE = 200;
    let pageCount = 0;
    let offset = startOffset;
    let fetched = startOffset;
    let totalItems = 0;

    console.log(`Starting from offset: ${startOffset}`);

    while (true) {
      if (pageCount > 0) {
        console.log(`Waiting ${RATE_LIMIT_DELAY}ms before next request...`);
        await new Promise((resolve) => setTimeout(resolve, RATE_LIMIT_DELAY));
      }

      pageCount++;
      console.log(`Fetching page ${pageCount}, offset: ${offset}, limit: ${PAGE_SIZE}`);

      const result = await this.getItems(offset, PAGE_SIZE);
      
      if (pageCount === 1 || totalItems === 0) {
        totalItems = result.totalItems;
      }

      // 不再过滤 isDuplicate，因为这些商品在 Walmart 是有效的
      // isDuplicate 只是标记该商品在 Walmart 系统中有重复记录，但仍然是有效商品
      const items = result.items;
      
      fetched += items.length;
      offset += items.length;

      const hasMore = items.length === PAGE_SIZE && fetched < totalItems;

      console.log(`Page ${pageCount}: got ${items.length} items, fetched: ${fetched}/${totalItems}, hasMore: ${hasMore}`);

      yield {
        items,
        fetched,
        total: totalItems,
        isLast: !hasMore,
      };

      if (!hasMore) {
        break;
      }
    }

    console.log(`Total pages: ${pageCount}, total items fetched: ${fetched}`);
  }

  // 获取单个商品（通过 SKU 参数查询）
  async getItem(sku: string): Promise<any | null> {
    try {
      const headers = await this.getHeaders();
      const response = await this.client.get('/v3/items', {
        headers,
        params: { sku },
      });
      const items = response.data.ItemResponse || [];
      return items.length > 0 ? items[0] : null;
    } catch (error: any) {
      console.error('Walmart get item failed:', error.response?.data || error.message);
      return null;
    }
  }

  // 批量查询 SKU（用于补充同步缺失的商品）
  async getItemsBySkus(skus: string[]): Promise<any[]> {
    const results: any[] = [];
    const RATE_LIMIT_DELAY = 500; // 0.5秒间隔

    for (let i = 0; i < skus.length; i++) {
      const sku = skus[i];
      if (i > 0) {
        await new Promise(resolve => setTimeout(resolve, RATE_LIMIT_DELAY));
      }

      try {
        const item = await this.getItem(sku);
        if (item) {
          results.push(item);
          console.log(`[${i + 1}/${skus.length}] Found: ${sku}`);
        } else {
          console.log(`[${i + 1}/${skus.length}] Not found: ${sku}`);
        }
      } catch (error: any) {
        console.error(`[${i + 1}/${skus.length}] Error querying ${sku}:`, error.message);
      }
    }

    return results;
  }

  // 将Walmart商品数据转换为标准格式
  transformItem(item: any): {
    sku: string;
    title: string;
    price: number;
    stock: number;
    currency: string;
    extraFields: Record<string, any>;
  } {
    return {
      sku: item.sku || '',
      title: item.productName || '',
      price: item.price?.amount || 0,
      stock: item.inventory?.quantity || 0,
      currency: item.price?.currency || 'USD',
      extraFields: {
        wpid: item.wpid,
        upc: item.upc,
        gtin: item.gtin,
        productType: item.productType,
        lifecycleStatus: item.lifecycleStatus,
        publishedStatus: item.publishedStatus,
        unpublishedReasons: item.unpublishedReasons,
      },
    };
  }
}
