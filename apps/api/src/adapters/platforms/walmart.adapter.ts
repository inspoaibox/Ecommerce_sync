import {
  BasePlatformAdapter,
  SyncProductData,
  SyncResult,
  BatchSyncResult,
} from './base.adapter';
import axios, { AxiosInstance } from 'axios';
import * as crypto from 'crypto';
import { WalmartRegionConfig, getWalmartRegionConfig } from './walmart.config';

// 加拿大市场 spec 文件（直接 import，避免运行时路径问题）
// eslint-disable-next-line @typescript-eslint/no-var-requires
let caSpecCache: any = null;
const loadCASpec = () => {
  if (!caSpecCache) {
    try {
      // 使用 require 动态加载，支持编译后的路径
      caSpecCache = require('./specs/CA_MP_ITEM_INTL_SPEC.json');
      console.log('[Walmart] CA spec file loaded successfully');
    } catch (e) {
      console.error('[Walmart] Failed to load CA spec file:', e);
    }
  }
  return caSpecCache;
};

export class WalmartAdapter extends BasePlatformAdapter {
  private client: AxiosInstance;
  private accessToken: string | null = null;
  private tokenExpiry: number = 0;
  private regionConfig: WalmartRegionConfig;
  
  /**
   * 认证模式：
   * - 'oauth': OAuth 2.0 (US 市场，使用 clientId + clientSecret)
   * - 'signature': 数字签名 (CA/国际市场，使用 consumerId + privateKey)
   */
  private authMode: 'oauth' | 'signature';

  constructor(credentials: Record<string, any>) {
    super(credentials);
    
    // 获取区域配置（从 credentials.region 或默认 US）
    this.regionConfig = getWalmartRegionConfig(credentials.region);
    
    // 优先使用 credentials 中指定的 apiBaseUrl，否则使用区域配置
    const baseURL = credentials.apiBaseUrl || this.regionConfig.apiBaseUrl;
    
    // 根据凭证类型和区域确定认证模式
    // CA/国际市场：如果有 consumerId + privateKey，使用数字签名认证
    // US 市场或有 clientId + clientSecret：使用 OAuth 2.0
    if (this.regionConfig.region !== 'US' && credentials.consumerId && credentials.privateKey) {
      this.authMode = 'signature';
      console.log(`[WalmartAdapter] Using signature auth for region: ${this.regionConfig.region}`);
    } else {
      this.authMode = 'oauth';
      console.log(`[WalmartAdapter] Using OAuth auth for region: ${this.regionConfig.region}`);
    }
    
    console.log(`[WalmartAdapter] Initialized for region: ${this.regionConfig.region}, baseURL: ${baseURL}, authMode: ${this.authMode}`);
    
    this.client = axios.create({
      baseURL,
      timeout: 60000, // 60秒超时
    });

    // 如果已有accessToken，直接使用（仅 OAuth 模式）
    if (this.authMode === 'oauth' && credentials.accessToken) {
      this.accessToken = credentials.accessToken;
      this.tokenExpiry = Date.now() + 14 * 60 * 1000; // 假设14分钟有效
    }
  }
  
  /**
   * 获取当前区域配置
   */
  getRegionConfig(): WalmartRegionConfig {
    return this.regionConfig;
  }

  // 生成关联ID
  private generateCorrelationId(): string {
    return crypto.randomUUID();
  }

  /**
   * 生成数字签名（用于 CA/国际市场）
   * 
   * 签名格式: consumerId + "\n" + url + "\n" + method + "\n" + timestamp + "\n"
   * 使用 SHA-256 with RSA 签名，然后 Base64 编码
   * 
   * @param url 完整的请求 URL
   * @param method HTTP 方法（大写）
   * @param timestamp Unix 时间戳（毫秒）
   */
  private generateSignature(url: string, method: string, timestamp: number): string {
    const { consumerId, privateKey } = this.credentials;
    
    if (!consumerId || !privateKey) {
      throw new Error('CA 市场需要 consumerId 和 privateKey 进行数字签名认证');
    }

    // 构建签名数据
    const stringToSign = `${consumerId}\n${url}\n${method}\n${timestamp}\n`;
    
    try {
      // 解码 Base64 编码的私钥
      const privateKeyPem = privateKey.includes('-----BEGIN')
        ? privateKey
        : `-----BEGIN PRIVATE KEY-----\n${privateKey}\n-----END PRIVATE KEY-----`;
      
      // 使用 SHA-256 with RSA 签名
      const sign = crypto.createSign('RSA-SHA256');
      sign.update(stringToSign);
      sign.end();
      
      const signature = sign.sign(privateKeyPem, 'base64');
      return signature;
    } catch (error: any) {
      console.error('[Walmart] Failed to generate signature:', error.message);
      throw new Error(`生成数字签名失败: ${error.message}`);
    }
  }

  // 获取访问令牌（仅 OAuth 模式）
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

      console.log(`[Walmart] Requesting token for market: ${this.regionConfig.marketCode}, region: ${this.regionConfig.region}`);
      
      // 构建请求头
      const tokenHeaders: Record<string, string> = {
        Authorization: `Basic ${authString}`,
        'Content-Type': 'application/x-www-form-urlencoded',
        Accept: 'application/json',
        'WM_MARKET': this.regionConfig.marketCode,  // 区分市场：us, ca, mx, cl
        'WM_SVC.NAME': 'Walmart Marketplace',
        'WM_QOS.CORRELATION_ID': this.generateCorrelationId(),
      };
      
      // 非美国市场需要额外的 headers（CA/MX/CL 等国际市场）
      if (this.regionConfig.region !== 'US') {
        tokenHeaders['WM_TENANT_ID'] = `WALMART.${this.regionConfig.region}`;  // 如：WALMART.CA
        tokenHeaders['WM_LOCALE_ID'] = `${this.regionConfig.locale}_${this.regionConfig.region}`;  // 如：en_CA
      }
      
      const response = await this.client.post('/v3/token', params.toString(), {
        headers: tokenHeaders,
      });

      console.log(`[Walmart] Token obtained successfully for market: ${this.regionConfig.marketCode}`);
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

  /**
   * 获取请求头
   * 
   * @param endpoint API 端点（用于数字签名模式）
   * @param method HTTP 方法（用于数字签名模式，默认 GET）
   */
  private async getHeaders(endpoint?: string, method: string = 'GET'): Promise<Record<string, string>> {
    const headers: Record<string, string> = {
      'WM_SVC.NAME': 'Walmart Marketplace',
      'WM_QOS.CORRELATION_ID': this.generateCorrelationId(),
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };

    if (this.authMode === 'signature') {
      // 数字签名认证模式（CA/国际市场）
      const timestamp = Date.now();
      const fullUrl = `${this.client.defaults.baseURL}${endpoint || '/v3/feeds'}`;
      const signature = this.generateSignature(fullUrl, method.toUpperCase(), timestamp);
      
      headers['WM_SEC.AUTH_SIGNATURE'] = signature;
      headers['WM_SEC.TIMESTAMP'] = String(timestamp);
      headers['WM_CONSUMER.ID'] = this.credentials.consumerId;
      headers['WM_CONSUMER.CHANNEL.TYPE'] = this.credentials.channelType;
      headers['WM_TENANT_ID'] = `WALMART.${this.regionConfig.region}`;
      headers['WM_LOCALE_ID'] = `${this.regionConfig.locale}_${this.regionConfig.region}`;
      
      console.log(`[Walmart] getHeaders (signature) - consumerId: ${this.credentials.consumerId?.substring(0, 10)}..., region: ${this.regionConfig.region}`);
    } else {
      // OAuth 2.0 认证模式（US 市场）
      const token = await this.getAccessToken();
      
      // WM_CONSUMER.CHANNEL.TYPE 优先级：
      // 1. credentials.channelType（用户在店铺配置中指定的 Channel Type Code）
      // 2. credentials.clientId（OAuth 2.0 模式使用 Client ID）
      // 3. regionConfig.businessUnit（降级使用业务单元名称）
      const channelType = this.credentials.channelType || this.credentials.clientId || this.regionConfig.businessUnit;
      
      headers['WM_SEC.ACCESS_TOKEN'] = token;
      headers['WM_MARKET'] = this.regionConfig.marketCode;
      headers['WM_CONSUMER.CHANNEL.TYPE'] = channelType;
      
      // 非美国市场需要额外的 headers（如果使用 OAuth 模式）
      if (this.regionConfig.region !== 'US') {
        headers['WM_TENANT_ID'] = `WALMART.${this.regionConfig.region}`;
        headers['WM_LOCALE_ID'] = `${this.regionConfig.locale}_${this.regionConfig.region}`;
      }
      
      console.log(`[Walmart] getHeaders (oauth) - channelType: ${channelType?.substring(0, 10)}..., region: ${this.regionConfig.region}`);
    }
    
    return headers;
  }

  /**
   * 构建带市场路径前缀的 API 端点
   * 美国市场: /v3/items/taxonomy -> /v3/items/taxonomy
   * 加拿大市场: /v3/items/taxonomy -> /v3/ca/items/taxonomy
   * 墨西哥市场: /v3/items/taxonomy -> /v3/mx/items/taxonomy
   */
  private buildEndpoint(endpoint: string): string {
    const prefix = this.regionConfig.apiPathPrefix;
    if (!prefix) {
      return endpoint;
    }
    // 将 /v3/xxx 转换为 /v3/{prefix}/xxx
    // 例如: /v3/items/taxonomy -> /v3/ca/items/taxonomy
    return endpoint.replace('/v3/', `/v3${prefix}/`);
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
              currency: product.currency || this.regionConfig.currency,
              amount: product.price,
            },
          },
        ],
      };

      const endpoint = this.buildEndpoint('/v3/feeds');
      const response = await this.client.post(endpoint, feedData, {
        headers,
        params: { feedType: this.regionConfig.feedType },
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

      const endpoint = this.buildEndpoint('/v3/inventory');
      await this.client.put(endpoint, inventoryData, {
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
              currency: this.regionConfig.currency,
              amount: price,
            },
          },
        ],
      };

      const endpoint = this.buildEndpoint('/v3/price');
      await this.client.put(endpoint, priceData, {
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

      // 构建Feed数据（使用区域配置）
      const feedData = {
        MPItemFeedHeader: {
          businessUnit: this.regionConfig.businessUnit,
          version: '2.0.20240126-12_25_52-api',
          locale: this.regionConfig.locale,
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

      const endpoint = this.buildEndpoint('/v3/feeds');
      const response = await this.client.post(endpoint, form, {
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

      const endpoint = this.buildEndpoint('/v3/feeds');
      const response = await this.client.post(endpoint, form, {
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
      const baseEndpoint = this.buildEndpoint(`/v3/feeds/${feedId}`);
      
      // 构建查询参数字符串
      const queryParams = `includeDetails=${includeDetails}&offset=${offset}&limit=${limit}`;
      const endpointWithParams = `${baseEndpoint}?${queryParams}`;
      
      // 获取请求头（传入完整 endpoint 用于数字签名）
      const headers = await this.getHeaders(endpointWithParams, 'GET');

      // 数字签名模式：使用完整 URL，不使用 params
      // OAuth 模式：可以使用 params
      if (this.authMode === 'signature') {
        const response = await this.client.get(endpointWithParams, { headers });
        return response.data;
      } else {
        const response = await this.client.get(baseEndpoint, {
          headers,
          params: { includeDetails, offset, limit },
        });
        return response.data;
      }
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

      const endpoint = this.buildEndpoint('/v3/items');
      const response = await this.client.get(endpoint, {
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
      const endpoint = this.buildEndpoint('/v3/items');
      const response = await this.client.get(endpoint, {
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
      currency: item.price?.currency || this.regionConfig.currency,
      extraFields: {
        wpid: item.wpid,
        upc: item.upc,
        gtin: item.gtin,
        productType: item.productType,
        lifecycleStatus: item.lifecycleStatus,
        publishedStatus: item.publishedStatus,
        unpublishedReasons: item.unpublishedReasons,
        region: this.regionConfig.region,  // 添加区域标识
      },
    };
  }

  // ==================== 类目管理 ====================

  /**
   * 获取 Walmart 类目列表
   * 美国市场: 使用 /v3/items/taxonomy?version=5.0 端点，三层结构（Category -> PTG -> PT）
   * 加拿大/墨西哥/智利市场: 使用 /v3/items/taxonomy 端点（无版本参数），一级扁平结构
   * 
   * 重要：所有市场都使用同一个端点，通过 WM_MARKET header 区分
   * 加拿大市场不需要 /ca/ 路径前缀（taxonomy API 特殊）
   */
  async getCategories(): Promise<Array<{
    categoryId: string;
    name: string;
    categoryPath: string;
    parentId: string | null;
    level: number;
    isLeaf: boolean;
    productTypeGroupId?: string;
    productTypeGroupName?: string;
    productTypeId?: string;
    productTypeName?: string;
  }>> {
    // 非美国市场使用不同的 API 调用方式
    if (this.regionConfig.region !== 'US') {
      return this.getInternationalCategories();
    }

    // 美国市场从 API 获取类目（带 version=5.0）
    return this.getUSCategories();
  }

  /**
   * 获取国际市场（CA/MX/CL）的类目列表
   * 这些市场使用 /v3/items/taxonomy 端点（不带版本参数），返回扁平的一级结构
   * 
   * 重要发现（通过 API 测试确认）：
   * - 端点: GET /v3/items/taxonomy（不带 /ca/ 前缀，不带 version 参数）
   * - 通过 WM_MARKET: ca header 区分市场
   * - 返回 79 个类目，ID 是下划线格式（如 alcoholic_beverages）
   */
  private async getInternationalCategories(): Promise<Array<{
    categoryId: string;
    name: string;
    categoryPath: string;
    parentId: string | null;
    level: number;
    isLeaf: boolean;
  }>> {
    try {
      const headers = await this.getHeaders();

      // 国际市场使用 /v3/items/taxonomy（不带版本参数，不带市场路径前缀）
      // 通过 WM_MARKET header 区分市场
      const endpoint = '/v3/items/taxonomy';
      
      console.log(`[Walmart] Fetching international categories from ${endpoint}, market: ${this.regionConfig.marketCode}, region: ${this.regionConfig.region}`);
      
      const response = await this.client.get(endpoint, { headers });
      
      // 解析响应：{ success: "SUCCESS", payload: [...] }
      const categoriesData = response.data?.payload || [];
      
      console.log(`[Walmart] Got ${categoriesData.length} categories for ${this.regionConfig.region} market`);

      return categoriesData.map((cat: any) => ({
        categoryId: cat.categoryId || cat.id || '',
        name: cat.categoryName || cat.name || '',
        categoryPath: cat.categoryName || cat.name || '',
        parentId: null,
        level: 0,
        isLeaf: true,  // 国际市场的类目都是叶子节点，可直接用于刊登
      }));
    } catch (error: any) {
      const errData = error.response?.data;
      console.error(`[Walmart] Get international categories failed:`, JSON.stringify(errData || error.message, null, 2));
      // 返回空数组，让前端显示错误
      return [];
    }
  }

  /**
   * 获取美国市场的类目列表（从 API）
   */
  private async getUSCategories(): Promise<Array<{
    categoryId: string;
    name: string;
    categoryPath: string;
    parentId: string | null;
    level: number;
    isLeaf: boolean;
    productTypeGroupId?: string;
    productTypeGroupName?: string;
    productTypeId?: string;
    productTypeName?: string;
  }>> {
    try {
      const headers = await this.getHeaders();

      const endpoint = '/v3/items/taxonomy';
      const version = '5.0';
      
      console.log(`[Walmart] Fetching categories from ${endpoint}?version=${version}, market: ${this.regionConfig.marketCode}, region: ${this.regionConfig.region}`);
      const response = await this.client.get(endpoint, {
        headers,
        params: { version },
      });
      
      // 调试：输出响应的前几个类目名称
      const firstCategories = response.data?.payload?.slice(0, 3) || response.data?.itemTaxonomy?.slice(0, 3) || [];
      console.log(`[Walmart] First categories:`, firstCategories.map((c: any) => c.category || c.name).join(', '));

      const categories: Array<{
        categoryId: string;
        name: string;
        categoryPath: string;
        parentId: string | null;
        level: number;
        isLeaf: boolean;
        productTypeGroupId?: string;
        productTypeGroupName?: string;
        productTypeId?: string;
        productTypeName?: string;
      }> = [];

      // 解析 API 响应数据
      let categoriesData = null;
      if (response.data?.payload) {
        categoriesData = response.data.payload;
      } else if (response.data?.itemTaxonomy) {
        categoriesData = response.data.itemTaxonomy;
      } else if (Array.isArray(response.data) && response.data[0]?.category) {
        categoriesData = response.data;
      }

      if (!categoriesData || !Array.isArray(categoriesData)) {
        console.log('[Walmart] No categories data found in response');
        console.log('[Walmart] Response keys:', Object.keys(response.data || {}));
        return [];
      }

      console.log(`[Walmart] Found ${categoriesData.length} top-level categories`);

      // 遍历分类数据（三层结构）
      for (const category of categoriesData) {
        const categoryId = category.category || category.id || '';
        const categoryName = category.category || category.name || '';

        if (!categoryId || !categoryName) continue;

        // Level 0: Category（顶级分类）
        categories.push({
          categoryId,
          name: categoryName,
          categoryPath: categoryName,
          parentId: null,
          level: 0,
          isLeaf: false,
        });

        // 处理 Product Type Groups (PTG)
        const ptgField = category.productTypeGroup || category.productTypeGroups || [];
        const ptgList = Array.isArray(ptgField) ? ptgField : [];

        for (const ptg of ptgList) {
          const ptgId = ptg.productTypeGroupName || ptg.productTypeGroup || ptg.id || ptg.name || '';
          const ptgName = ptg.productTypeGroupName || ptg.productTypeGroup || ptg.name || '';

          if (!ptgId || !ptgName) continue;

          // Level 1: Product Type Group
          categories.push({
            categoryId: ptgId,
            name: ptgName,
            categoryPath: `${categoryName} > ${ptgName}`,
            parentId: categoryId,
            level: 1,
            isLeaf: false,
            productTypeGroupId: ptgId,
            productTypeGroupName: ptgName,
          });

          // 处理 Product Types (PT)
          const ptField = ptg.productType || ptg.productTypes || [];
          const ptList = Array.isArray(ptField) ? ptField : [];

          for (const pt of ptList) {
            const ptId = pt.productTypeName || pt.productType || pt.id || pt.name || '';
            const ptName = pt.productTypeName || pt.productType || pt.name || '';

            if (!ptId || !ptName) continue;

            // Level 2: Product Type（叶子节点，用于实际刊登）
            categories.push({
              categoryId: ptId,
              name: ptName,
              categoryPath: `${categoryName} > ${ptgName} > ${ptName}`,
              parentId: ptgId,
              level: 2,
              isLeaf: true,
              productTypeGroupId: ptgId,
              productTypeGroupName: ptgName,
              productTypeId: ptId,
              productTypeName: ptName,
            });
          }
        }
      }

      console.log(`[Walmart] Total categories parsed: ${categories.length}`);
      return categories;
    } catch (error: any) {
      const status = error.response?.status;
      const errData = error.response?.data;
      console.error(`[Walmart] Get categories failed (HTTP ${status}):`, JSON.stringify(errData || error.message, null, 2));
      // 如果 API 不可用，返回空数组
      return [];
    }
  }

  /**
   * 获取类目属性原始响应（用于调试）
   * 返回 Item Spec API 的原始 JSON Schema 响应
   */
  async getCategoryAttributesRaw(categoryId: string): Promise<any> {
    try {
      const headers = await this.getHeaders();

      // 构建带市场前缀的端点
      const endpoint = this.buildEndpoint('/v3/items/spec');
      
      console.log(`[Walmart] Fetching raw spec for productType: ${categoryId}, endpoint: ${endpoint}, region: ${this.regionConfig.region}`);

      const requestBody = {
        feedType: this.regionConfig.feedType,
        version: this.regionConfig.specVersion,
        productTypes: [categoryId],
      };

      const response = await this.client.post(endpoint, requestBody, {
        headers,
      });

      return {
        requestBody,
        response: response.data,
        categoryId,
        region: this.regionConfig.region,
        timestamp: new Date().toISOString(),
      };
    } catch (error: any) {
      const errData = error.response?.data;
      const errMsg = errData?.error?.[0]?.description || errData?.message || error.message;
      return {
        error: errMsg,
        errorDetails: errData,
        categoryId,
        region: this.regionConfig.region,
        timestamp: new Date().toISOString(),
      };
    }
  }

  /**
   * 获取类目属性
   * 美国市场: 使用 Item Spec API: POST /v3/items/spec
   * 加拿大市场: 从本地 spec 文件解析（CA_MP_ITEM_INTL_SPEC.json）
   * 其他市场: 暂不支持
   */
  async getCategoryAttributes(categoryId: string): Promise<Array<{
    attributeId: string;
    name: string;
    description?: string;
    dataType: string;
    isRequired: boolean;
    isMultiSelect: boolean;
    maxLength?: number;
    enumValues?: string[];
    conditionalRequired?: Array<{
      dependsOn: string;
      dependsOnValue: string;
    }>;
  }>> {
    // 加拿大市场从本地 spec 文件解析属性
    if (this.regionConfig.region === 'CA') {
      return this.getCACategoryAttributes(categoryId);
    }

    // 美国市场从 API 获取属性
    return this.getUSCategoryAttributes(categoryId);
  }

  /**
   * 获取加拿大市场的类目属性
   * 从本地 CA_MP_ITEM_INTL_SPEC.json 文件解析
   */
  private async getCACategoryAttributes(categoryId: string): Promise<Array<{
    attributeId: string;
    name: string;
    description?: string;
    dataType: string;
    isRequired: boolean;
    isMultiSelect: boolean;
    maxLength?: number;
    enumValues?: string[];
  }>> {
    try {
      // 加载 spec 文件（带缓存）
      const spec = loadCASpec();
      if (!spec) {
        console.error('[Walmart] CA spec file not loaded');
        return [];
      }

      // 将 categoryId（下划线格式）转换为 categoryName（可读格式）
      const categoryName = this.getCategoryNameById(categoryId);
      if (!categoryName) {
        console.error(`[Walmart] Category name not found for ID: ${categoryId}`);
        return [];
      }

      console.log(`[Walmart] Getting attributes for category: ${categoryName} (ID: ${categoryId}), region: ${this.regionConfig.region}`);

      const attributes: Array<{
        attributeId: string;
        name: string;
        description?: string;
        dataType: string;
        isRequired: boolean;
        isMultiSelect: boolean;
        maxLength?: number;
        enumValues?: string[];
      }> = [];

      const mpItemProps = spec?.properties?.MPItem?.items?.properties;
      if (!mpItemProps) {
        console.error('[Walmart] Invalid spec file structure');
        return [];
      }

      // 1. 解析 Orderable 通用属性
      const orderable = mpItemProps.Orderable;
      if (orderable?.properties) {
        const orderableRequired = orderable.required || [];
        for (const [attrName, attrDef] of Object.entries(orderable.properties) as [string, any][]) {
          const attr = this.parseSpecAttribute(attrName, attrDef, orderableRequired.includes(attrName));
          if (attr) attributes.push(attr);
        }
      }

      // 2. 解析 Visible 类目特定属性
      const visible = mpItemProps.Visible;
      if (visible?.properties?.[categoryName]?.properties) {
        const categoryProps = visible.properties[categoryName].properties;
        const categoryRequired = visible.properties[categoryName].required || [];
        
        console.log(`[Walmart] Found ${Object.keys(categoryProps).length} category-specific attributes for ${categoryName}`);
        
        for (const [attrName, attrDef] of Object.entries(categoryProps) as [string, any][]) {
          const attr = this.parseSpecAttribute(attrName, attrDef, categoryRequired.includes(attrName));
          if (attr) attributes.push(attr);
        }
      } else {
        console.log(`[Walmart] No Visible properties found for category: ${categoryName}`);
      }

      console.log(`[Walmart] Total attributes for ${categoryName}: ${attributes.length}`);
      return attributes;
    } catch (error: any) {
      console.error('[Walmart] Get international category attributes failed:', error.message);
      return [];
    }
  }

  /**
   * 根据 categoryId 获取 categoryName
   * categoryId: 下划线格式（如 furniture_other）
   * categoryName: 可读格式（如 Furniture）
   * 
   * 使用静态映射，避免每次都调用 API
   */
  private getCategoryNameById(categoryId: string): string | null {
    // 静态映射表：categoryId -> categoryName（来自 taxonomy API 响应）
    const categoryIdToName: Record<string, string> = {
      'alcoholic_beverages': 'Alcoholic Beverages',
      'animal_accessories': 'Animal Accessories',
      'animal_food': 'Animal Food',
      'animal_health_and_grooming': 'Animal Health & Grooming',
      'animal_other': 'Animal Other',
      'art_and_craft_other': 'Art & Craft',
      'baby_clothing': 'Baby Clothing',
      'baby_other': 'Baby Diapering, Care, & Other',
      'baby_food': 'Baby Food',
      'baby_furniture': 'Baby Furniture',
      'baby_toys': 'Baby Toys',
      'child_car_seats': 'Baby Transport',
      'personal_care': 'Beauty, Personal Care, & Hygiene',
      'bedding': 'Bedding',
      'books_and_magazines': 'Books & Magazines',
      'building_supply': 'Building Supply',
      'cameras_and_lenses': 'Cameras & Lenses',
      'carriers_and_accessories_other': 'Carriers & Accessories',
      'cases_and_bags': 'Cases & Bags',
      'cell_phones': 'Cell Phones',
      'ceremonial_clothing_and_accessories': 'Ceremonial Clothing & Accessories',
      'clothing_other': 'Clothing',
      'computer_components': 'Computer Components',
      'computers': 'Computers',
      'costumes': 'Costumes',
      'cycling': 'Cycling',
      'decorations_and_favors': 'Decorations & Favors',
      'electrical': 'Electrical',
      'electronics_accessories': 'Electronics Accessories',
      'electronics_cables': 'Electronics Cables',
      'electronics_other': 'Electronics Other',
      'food_and_beverage_other': 'Food & Beverage',
      'footwear_other': 'Footwear',
      'fuels_and_lubricants': 'Fuels & Lubricants',
      'funeral': 'Funeral',
      'furniture_other': 'Furniture',
      'garden_and_patio_other': 'Garden & Patio',
      'gift_supply_and_awards': 'Gift Supply & Awards',
      'grills_and_outdoor_cooking': 'Grills & Outdoor Cooking',
      'hardware': 'Hardware',
      'health_and_beauty_electronics': 'Health & Beauty Electronics',
      'home_other': 'Home Decor, Kitchen, & Other',
      'cleaning_and_chemical': 'Household Cleaning Products & Supplies',
      'instrument_accessories': 'Instrument Accessories',
      'jewelry_other': 'Jewelry',
      'land_vehicles': 'Land Vehicles',
      'large_appliances': 'Large Appliances',
      'medical_aids': 'Medical Aids & Equipment',
      'medicine_and_supplements': 'Medicine & Supplements',
      'movies': 'Movies',
      'music_cases_and_bags': 'Music Cases & Bags',
      'music': 'Music',
      'musical_instruments': 'Musical Instruments',
      'office_other': 'Office',
      'optical': 'Optical',
      'optics': 'Optics',
      'other_other': 'Other',
      'photo_accessories': 'Photo Accessories',
      'plumbing_and_hvac': 'Plumbing & HVAC',
      'printers_scanners_and_imaging': 'Printers, Scanners, & Imaging',
      'safety_and_emergency': 'Safety & Emergency',
      'software': 'Software',
      'sound_and_recording': 'Sound & Recording',
      'sport_and_recreation_other': 'Sport & Recreation Other',
      'storage': 'Storage',
      'tv_shows': 'TV Shows',
      'tvs_and_video_displays': 'TVs & Video Displays',
      'tires': 'Tires',
      'tools_and_hardware_other': 'Tools & Hardware Other',
      'tools': 'Tools',
      'toys_other': 'Toys',
      'vehicle_other': 'Vehicle Other',
      'vehicle_parts_and_accessories': 'Vehicle Parts & Accessories',
      'video_games': 'Video Games',
      'video_projectors': 'Video Projectors',
      'watches_other': 'Watches',
      'watercraft': 'Watercraft',
      'weapons': 'Weapons',
      'wheels_and_wheel_components': 'Wheels & Wheel Components',
    };

    return categoryIdToName[categoryId] || null;
  }

  /**
   * 解析 spec 文件中的单个属性定义
   */
  private parseSpecAttribute(
    attrName: string,
    attrDef: any,
    isRequired: boolean
  ): {
    attributeId: string;
    name: string;
    description?: string;
    dataType: string;
    isRequired: boolean;
    isMultiSelect: boolean;
    maxLength?: number;
    enumValues?: string[];
  } | null {
    if (!attrDef) return null;

    // 处理嵌套对象（如 productName: { properties: { en: {...}, fr: {...} } }）
    let actualDef = attrDef;
    if (attrDef.properties?.en) {
      actualDef = attrDef.properties.en;
    }

    // 处理数组类型
    let isMultiSelect = false;
    if (attrDef.type === 'array' && attrDef.items) {
      isMultiSelect = true;
      if (attrDef.items.properties?.en) {
        actualDef = attrDef.items.properties.en;
      } else if (attrDef.items.enum) {
        actualDef = attrDef.items;
      } else {
        actualDef = attrDef.items;
      }
    }

    const dataType = this.mapSpecTypeToDataType(actualDef.type, actualDef.enum);

    return {
      attributeId: attrName,
      name: actualDef.title || attrDef.title || attrName,
      description: actualDef.description || attrDef.description,
      dataType,
      isRequired,
      isMultiSelect,
      maxLength: actualDef.maxLength,
      enumValues: actualDef.enum,
    };
  }

  /**
   * 将 JSON Schema 类型映射到我们的数据类型
   */
  private mapSpecTypeToDataType(type: string, hasEnum?: string[]): string {
    if (hasEnum && hasEnum.length > 0) {
      return 'enum';
    }
    switch (type) {
      case 'string':
        return 'string';
      case 'number':
      case 'integer':
        return 'number';
      case 'boolean':
        return 'boolean';
      case 'array':
        return 'array';
      case 'object':
        return 'object';
      default:
        return 'string';
    }
  }

  /**
   * 获取美国市场的类目属性（从 API）
   */
  private async getUSCategoryAttributes(categoryId: string): Promise<Array<{
    attributeId: string;
    name: string;
    description?: string;
    dataType: string;
    isRequired: boolean;
    isMultiSelect: boolean;
    maxLength?: number;
    enumValues?: string[];
    conditionalRequired?: Array<{
      dependsOn: string;
      dependsOnValue: string;
    }>;
  }>> {
    try {
      const headers = await this.getHeaders();

      // 构建带市场前缀的端点
      const endpoint = this.buildEndpoint('/v3/items/spec');

      console.log(`[Walmart] Fetching spec for productType: ${categoryId}, endpoint: ${endpoint}, region: ${this.regionConfig.region}`);

      // 使用区域配置的 feedType 和 specVersion
      const requestBody = {
        feedType: this.regionConfig.feedType,
        version: this.regionConfig.specVersion,
        productTypes: [categoryId], // Product Type 名称
      };

      console.log(`[Walmart] Request body:`, JSON.stringify(requestBody));

      const response = await this.client.post(endpoint, requestBody, {
        headers,
      });

      console.log(`[Walmart] Spec API response status: ${response.status}`);

      // 解析 V5.0 JSON Schema 格式的响应
      const attributes = this.parseV5SpecResponse(response.data, categoryId);

      console.log(`[Walmart] Parsed ${attributes.length} attributes`);
      return attributes;
    } catch (error: any) {
      const errData = error.response?.data;
      const errMsg = errData?.error?.[0]?.description || errData?.message || error.message;
      console.error('[Walmart] Get category attributes failed:', errMsg);
      console.error('[Walmart] Error details:', JSON.stringify(errData || {}, null, 2));
      // 返回空数组而不是抛出错误，让前端可以显示友好提示
      return [];
    }
  }

  /**
   * 解析 V5.0 Spec API 响应
   * 响应格式是 JSON Schema，结构为：
   * schema.properties.MPItem.items.properties 包含：
   * - Visible: 产品特定属性（按 productType 分组）
   * - Orderable: 通用订单属性
   * - 其他顶级属性
   */
  private parseV5SpecResponse(data: any, categoryId: string): Array<{
    attributeId: string;
    name: string;
    description?: string;
    dataType: string;
    isRequired: boolean;
    isMultiSelect: boolean;
    maxLength?: number;
    enumValues?: string[];
    conditionalRequired?: Array<{
      dependsOn: string;
      dependsOnValue: string;
    }>;
  }> {
    const attributes: Array<{
      attributeId: string;
      name: string;
      description?: string;
      dataType: string;
      isRequired: boolean;
      isMultiSelect: boolean;
      maxLength?: number;
      enumValues?: string[];
      conditionalRequired?: Array<{
        dependsOn: string;
        dependsOnValue: string;
      }>;
    }> = [];
    
    // 存储条件必填规则，稍后合并到属性中
    const conditionalRequiredMap = new Map<string, Array<{ dependsOn: string; dependsOnValue: string }>>();

    try {
      console.log('[Walmart] Parsing V5 spec response, keys:', Object.keys(data || {}));

      // V5.0 响应结构：schema.properties.MPItem.items.properties
      let mpItemProperties: any = null;
      let mpItemRequired: string[] = [];

      // 尝试多种可能的路径
      if (data?.schema?.properties?.MPItem?.items?.properties) {
        mpItemProperties = data.schema.properties.MPItem.items.properties;
        mpItemRequired = data.schema.properties.MPItem.items.required || [];
      } else if (data?.properties?.MPItem?.items?.properties) {
        mpItemProperties = data.properties.MPItem.items.properties;
        mpItemRequired = data.properties.MPItem.items.required || [];
      }

      if (!mpItemProperties) {
        console.log('[Walmart] No MPItem properties found');
        console.log('[Walmart] Response structure:', JSON.stringify(Object.keys(data || {})));
        if (data?.schema) {
          console.log('[Walmart] Schema keys:', Object.keys(data.schema));
        }
        return [];
      }

      console.log(`[Walmart] Found MPItem with ${Object.keys(mpItemProperties).length} properties`);
      console.log(`[Walmart] Required fields: ${mpItemRequired.length}`);

      // 遍历 MPItem 的属性
      for (const [propName, propDef] of Object.entries(mpItemProperties) as [string, any][]) {
        const isRequired = mpItemRequired.includes(propName);

        // 特殊处理 Visible 属性 - 包含产品特定的属性
        if (propName === 'Visible' && propDef.properties) {
          // 查找产品类型特定的属性
          if (propDef.properties[categoryId]?.properties) {
            const visibleProps = propDef.properties[categoryId].properties;
            const visibleRequired = propDef.properties[categoryId].required || [];
            const visibleAllOf = propDef.properties[categoryId].allOf || [];

            console.log(`[Walmart] Found Visible properties for ${categoryId}: ${Object.keys(visibleProps).length}`);
            console.log(`[Walmart] Found allOf conditions: ${visibleAllOf.length}`);
            if (visibleAllOf.length > 0) {
              console.log(`[Walmart] allOf sample:`, JSON.stringify(visibleAllOf[0], null, 2));
            }

            // 解析 allOf 条件必填规则
            // 格式: { if: { properties: { fieldA: { enum: ["value"] } } }, then: { required: ["fieldB"] } }
            for (const condition of visibleAllOf) {
              if (condition.if?.properties && condition.then?.required) {
                const ifProps = condition.if.properties;
                const thenRequired = condition.then.required;
                
                for (const [dependsOn, dependsDef] of Object.entries(ifProps) as [string, any][]) {
                  if (dependsDef.enum && Array.isArray(dependsDef.enum)) {
                    for (const dependsOnValue of dependsDef.enum) {
                      for (const requiredField of thenRequired) {
                        if (!conditionalRequiredMap.has(requiredField)) {
                          conditionalRequiredMap.set(requiredField, []);
                        }
                        conditionalRequiredMap.get(requiredField)!.push({
                          dependsOn,
                          dependsOnValue,
                        });
                      }
                    }
                  }
                }
              }
            }

            for (const [nestedName, nestedDef] of Object.entries(visibleProps) as [string, any][]) {
              const attr = this.extractAttributeFromSchema(
                nestedName,
                nestedDef,
                visibleRequired.includes(nestedName),
              );
              if (attr) attributes.push(attr);
            }
          }
          continue;
        }

        // 特殊处理 Orderable 属性 - 包含通用订单属性
        if (propName === 'Orderable' && propDef.properties) {
          const orderableProps = propDef.properties;
          const orderableRequired = propDef.required || [];

          console.log(`[Walmart] Found Orderable properties: ${Object.keys(orderableProps).length}`);

          for (const [nestedName, nestedDef] of Object.entries(orderableProps) as [string, any][]) {
            const attr = this.extractAttributeFromSchema(
              nestedName,
              nestedDef,
              orderableRequired.includes(nestedName),
            );
            if (attr) attributes.push(attr);
          }
          continue;
        }

        // 处理其他顶级属性
        const attr = this.extractAttributeFromSchema(propName, propDef, isRequired);
        if (attr) attributes.push(attr);
      }

      // 合并条件必填规则到属性中
      for (const attr of attributes) {
        const conditionalRules = conditionalRequiredMap.get(attr.attributeId);
        if (conditionalRules && conditionalRules.length > 0) {
          attr.conditionalRequired = conditionalRules;
        }
      }

      console.log(`[Walmart] Total attributes parsed: ${attributes.length}`);
      console.log(`[Walmart] Conditional required rules: ${conditionalRequiredMap.size}`);
      return attributes;
    } catch (error) {
      console.error('[Walmart] Error parsing V5 spec response:', error);
      return [];
    }
  }

  /**
   * 从 JSON Schema 属性定义中提取属性信息
   */
  private extractAttributeFromSchema(
    propertyName: string,
    propertyDef: any,
    isRequired: boolean,
  ): {
    attributeId: string;
    name: string;
    description?: string;
    dataType: string;
    isRequired: boolean;
    isMultiSelect: boolean;
    maxLength?: number;
    enumValues?: string[];
    conditionalRequired?: Array<{
      dependsOn: string;
      dependsOnValue: string;
    }>;
  } | null {
    if (!propertyName || !propertyDef) return null;

    let dataType = 'string';
    let enumValues: string[] | undefined;
    let isMultiSelect = false;

    // 处理 measurement 对象类型（如 measure + unit）
    if (propertyDef.type === 'object' && propertyDef.properties) {
      if (propertyDef.properties.measure && propertyDef.properties.unit) {
        dataType = 'measurement';
        // 提取单位枚举值
        if (propertyDef.properties.unit?.enum) {
          enumValues = propertyDef.properties.unit.enum;
        }
      }
    }

    // 根据类型确定字段类型
    if (propertyDef.type) {
      switch (propertyDef.type) {
        case 'string':
          dataType = 'string';
          break;
        case 'number':
        case 'integer':
          dataType = 'number';
          break;
        case 'boolean':
          dataType = 'boolean';
          break;
        case 'array':
          dataType = 'array';
          isMultiSelect = true;
          // 如果数组项有枚举值
          if (propertyDef.items?.enum) {
            enumValues = propertyDef.items.enum;
          }
          break;
      }
    }

    // 如果有枚举值
    if (propertyDef.enum && Array.isArray(propertyDef.enum)) {
      dataType = 'enum';
      enumValues = propertyDef.enum;
    }

    // 处理 oneOf 结构
    if (propertyDef.oneOf && Array.isArray(propertyDef.oneOf)) {
      const allEnums: string[] = [];
      for (const option of propertyDef.oneOf) {
        if (option.enum) {
          allEnums.push(...option.enum);
        }
      }
      if (allEnums.length > 0) {
        dataType = 'enum';
        enumValues = allEnums;
      }
    }

    return {
      attributeId: propertyName,
      name: propertyDef.title || propertyName,
      description: propertyDef.description,
      dataType,
      isRequired,
      isMultiSelect,
      maxLength: propertyDef.maxLength,
      enumValues,
    };
  }

  private mapWalmartDataType(type: string): string {
    const typeMap: Record<string, string> = {
      'STRING': 'string',
      'INTEGER': 'number',
      'DECIMAL': 'number',
      'BOOLEAN': 'boolean',
      'ENUM': 'enum',
      'DATE': 'string',
      'ARRAY': 'array',
    };
    return typeMap[type?.toUpperCase()] || 'string';
  }

  // ==================== 商品刊登 ====================

  /**
   * 创建商品（使用 Feed API）
   * 支持 V5.0 格式（Orderable + Visible 结构）
   * 
   * item 结构应为:
   * {
   *   Orderable: { sku, productIdentifiers, price, quantity, ... },
   *   Visible: { [categoryName]: { productName, mainImageUrl, ... } }
   * }
   * 
   * 或者平铺格式（向后兼容）:
   * { sku, productName, price, ... }
   */
  /**
   * 创建商品（使用 Feed API）
   * 
   * US 市场: MP_ITEM Feed，V5.0 格式
   * CA/国际市场: MP_ITEM_INTL Feed，V3.16 格式，需要额外的 Header 字段
   * 
   * @param item 商品数据（已转换为 Walmart 格式）
   * @param subCategory 类目ID（CA 市场需要在 Header 中指定）
   */
  async createItem(item: Record<string, any>, subCategory?: string): Promise<{ feedId: string; walmartResponse?: any; submittedFeedData?: any }> {
    try {
      const isInternational = this.regionConfig.region !== 'US';
      const baseEndpoint = this.buildEndpoint('/v3/feeds');
      
      // 对于数字签名认证，URL 必须包含查询参数
      // 签名时使用完整 URL（包含查询参数），请求时也使用完整 URL
      const endpointWithParams = `${baseEndpoint}?feedType=${this.regionConfig.feedType}`;
      
      // 获取请求头（传入完整 endpoint 用于数字签名）
      const headers = await this.getHeaders(endpointWithParams, 'POST');

      // 构建 Feed Header
      let feedHeader: Record<string, any>;
      
      if (isInternational) {
        // CA/国际市场: MP_ITEM_INTL Feed Header
        // 参考 CA_MP_ITEM_INTL_SPEC.json
        feedHeader = {
          version: this.regionConfig.specVersion,  // "3.16"
          processMode: 'REPLACE',
          subset: 'EXTERNAL',
          mart: this.regionConfig.businessUnit,    // "WALMART_CA"
          sellingChannel: 'marketplace',
          locale: ['en', 'fr'],  // CA 市场需要双语
        };
        // 如果提供了 subCategory，添加到 Header
        if (subCategory) {
          feedHeader.subCategory = subCategory;
        }
      } else {
        // US 市场: MP_ITEM Feed Header (V5.0)
        feedHeader = {
          businessUnit: this.regionConfig.businessUnit,
          locale: this.regionConfig.locale,
          version: this.regionConfig.specVersion,
        };
      }

      const feedData = {
        MPItemFeedHeader: feedHeader,
        MPItem: [item],
      };

      const FormData = require('form-data');
      const form = new FormData();
      form.append('file', JSON.stringify(feedData), {
        filename: 'item.json',
        contentType: 'application/json',
      });

      // 数字签名模式：使用完整 URL（包含查询参数），不使用 params
      // OAuth 模式：可以使用 params
      const requestConfig: any = {
        headers: {
          ...headers,
          ...form.getHeaders(),
        },
      };
      
      // OAuth 模式使用 params，数字签名模式查询参数已在 URL 中
      const requestUrl = this.authMode === 'signature' ? endpointWithParams : baseEndpoint;
      if (this.authMode !== 'signature') {
        requestConfig.params = { feedType: this.regionConfig.feedType };
      }

      const response = await this.client.post(requestUrl, form, requestConfig);

      return { 
        feedId: response.data.feedId,
        walmartResponse: response.data,
        submittedFeedData: feedData,
      };
    } catch (error: any) {
      const errMsg = error.response?.data?.error?.[0]?.description || error.message;
      throw new Error(`创建商品失败: ${errMsg}`);
    }
  }

  /**
   * 更新商品（使用 Feed API）
   */
  async updateItem(sku: string, updates: {
    title?: string;
    price?: number;
    description?: string;
    mainImageUrl?: string;
    attributes?: Record<string, any>;
  }): Promise<{ feedId: string }> {
    try {
      const headers = await this.getHeaders();

      const mpItem: any = { sku };
      if (updates.title) mpItem.productName = updates.title;
      if (updates.price) mpItem.price = { currency: this.regionConfig.currency, amount: updates.price };
      if (updates.description) mpItem.ShortDescription = updates.description;
      if (updates.mainImageUrl) mpItem.mainImageUrl = updates.mainImageUrl;
      if (updates.attributes) Object.assign(mpItem, updates.attributes);

      const feedData = {
        MPItemFeedHeader: {
          version: this.regionConfig.specVersion,
          requestId: this.generateCorrelationId(),
          requestBatchId: this.generateCorrelationId(),
        },
        MPItem: [mpItem],
      };

      const FormData = require('form-data');
      const form = new FormData();
      form.append('file', JSON.stringify(feedData), {
        filename: 'item.json',
        contentType: 'application/json',
      });

      const endpoint = this.buildEndpoint('/v3/feeds');
      const response = await this.client.post(endpoint, form, {
        headers: {
          ...headers,
          ...form.getHeaders(),
        },
        params: { feedType: this.regionConfig.feedType },
      });

      return { feedId: response.data.feedId };
    } catch (error: any) {
      const errMsg = error.response?.data?.error?.[0]?.description || error.message;
      throw new Error(`更新商品失败: ${errMsg}`);
    }
  }

  /**
   * 获取商品状态
   */
  async getItemStatus(sku: string): Promise<{
    sku: string;
    status: string;
    publishedStatus?: string;
    lifecycleStatus?: string;
    errors?: string[];
  }> {
    try {
      const item = await this.getItem(sku);
      if (!item) {
        return { sku, status: 'NOT_FOUND' };
      }

      return {
        sku,
        status: item.lifecycleStatus || 'UNKNOWN',
        publishedStatus: item.publishedStatus,
        lifecycleStatus: item.lifecycleStatus,
        errors: item.unpublishedReasons?.map((r: any) => r.reason || r),
      };
    } catch (error: any) {
      return { sku, status: 'ERROR', errors: [error.message] };
    }
  }
}
