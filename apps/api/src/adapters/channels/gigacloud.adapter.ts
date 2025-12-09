import { BaseChannelAdapter, ChannelProduct, FetchOptions, FetchResult } from './base.adapter';

interface GigaCloudConfig {
  clientId: string;
  clientSecret: string;
  baseUrl?: string; // 默认生产环境
}

interface TokenResponse {
  access_token: string;
  token_type: string;
  expires_in: number;
}

interface PriceData {
  sku: string;
  currency: string;
  price: number;
  shippingFee: number;
  discountedPrice?: number;
  mapPrice?: number;
  skuAvailable: boolean;
}

interface InventoryData {
  sku: string;
  buyerInventoryInfo?: {
    totalBuyerAvailableInventory: number;
  };
  sellerInventoryInfo?: {
    sellerAvailableInventory: number;
    discountAvailableInventory: number;
  };
}

export class GigaCloudAdapter extends BaseChannelAdapter {
  private accessToken: string | null = null;
  private tokenExpiry: number = 0;
  private baseUrl: string;

  constructor(config: Record<string, any>) {
    super(config);
    this.baseUrl = config.baseUrl || 'https://api.gigacloudlogistics.com';
  }

  private get gigaConfig(): GigaCloudConfig {
    return this.config as GigaCloudConfig;
  }

  private async getAccessToken(): Promise<string> {
    const now = Date.now();
    if (this.accessToken && now < this.tokenExpiry) {
      return this.accessToken;
    }

    const url = `${this.baseUrl}/api-auth-v1/oauth/token`;
    const params = new URLSearchParams({
      grant_type: 'client_credentials',
      client_id: this.gigaConfig.clientId,
      client_secret: this.gigaConfig.clientSecret,
    });

    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString(),
    });

    if (!response.ok) {
      throw new Error(`GigaCloud auth failed: ${response.status}`);
    }

    const data: TokenResponse = await response.json();
    this.accessToken = data.access_token;
    this.tokenExpiry = now + (data.expires_in - 60) * 1000; // 提前60秒刷新
    return this.accessToken;
  }

  private async request<T>(method: string, path: string, body?: any): Promise<T> {
    const token = await this.getAccessToken();
    const url = `${this.baseUrl}${path}`;

    const response = await fetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
      throw new Error(`GigaCloud API error: ${response.status}`);
    }

    return response.json();
  }

  async testConnection(): Promise<boolean> {
    try {
      await this.getAccessToken();
      return true;
    } catch {
      return false;
    }
  }

  // 获取商品列表
  async fetchProducts(options: FetchOptions): Promise<FetchResult> {
    const { page = 1, pageSize = 100 } = options;
    
    const params = new URLSearchParams({
      page: page.toString(),
      limit: pageSize.toString(),
      sort: '2', // updatTime:desc
    });

    if (options.updatedAfter) {
      params.append('lastUpdatedAfter', options.updatedAfter.toISOString().replace('T', ' ').slice(0, 19));
    }

    const token = await this.getAccessToken();
    const url = `${this.baseUrl}/api-b2b-v1/product/skus?${params}`;
    
    const response = await fetch(url, {
      headers: { 'Authorization': `Bearer ${token}` },
    });

    const result = await response.json();
    
    if (!result.success) {
      throw new Error('Failed to fetch product list');
    }

    // 获取SKU列表后，批量查询价格和库存
    const skus = result.data.map((item: any) => item.sku);
    const products = await this.fetchProductsBySkus(skus);

    return {
      products,
      total: result.pageMeta?.total || 0,
      hasMore: !!result.pageMeta?.next,
    };
  }

  // 批量查询SKU的价格和库存（不调用商品详情接口）
  async fetchProductsBySkus(skus: string[]): Promise<ChannelProduct[]> {
    if (skus.length === 0) return [];

    // API限制每次最多200个SKU
    const chunks = this.chunkArray(skus, 200);
    const allProducts: ChannelProduct[] = [];

    for (const chunk of chunks) {
      // 只调用价格和库存接口
      const [priceResult, inventoryResult] = await Promise.all([
        this.fetchPrices(chunk),
        this.fetchInventory(chunk),
      ]);

      // 同一个SKU可能返回多条记录（不同卖家），只保留skuAvailable=true的记录
      const priceMap = new Map<string, PriceData>();
      for (const p of priceResult) {
        // 优先保留 skuAvailable=true 的记录
        const existing = priceMap.get(p.sku);
        if (!existing || (p.skuAvailable && !existing.skuAvailable)) {
          priceMap.set(p.sku, p);
        }
      }

      const inventoryMap = new Map(inventoryResult.map(i => [i.sku, i]));

      for (const sku of chunk) {
        const price = priceMap.get(sku);
        const inventory = inventoryMap.get(sku);

        // 调试日志：记录每个SKU的查询结果
        console.log(`[GigaCloud] SKU: ${sku}, hasPrice: ${!!price}, skuAvailable: ${price?.skuAvailable}`);

        // 返回所有查询的SKU，包括价格不存在或不可用的商品
        let availabilityStatus = 'available';
        if (!price) {
          availabilityStatus = 'no_price_data';
        } else if (!price.skuAvailable) {
          availabilityStatus = 'unavailable';
        } else if (price.price == null) {
          availabilityStatus = 'price_not_exist';
        }

        allProducts.push({
          channelProductId: sku,
          sku,
          title: sku, // 不调用详情接口，使用SKU作为标题
          price: price?.price ?? null, // 原价，可能为null
          stock: inventory?.sellerInventoryInfo?.sellerAvailableInventory || 0,
          currency: price?.currency || 'USD',
          extraFields: {
            shippingFee: price?.shippingFee ?? null,
            mapPrice: price?.mapPrice ?? null,
            discountedPrice: price?.discountedPrice ?? null, // 优惠价格
            buyerStock: inventory?.buyerInventoryInfo?.totalBuyerAvailableInventory || 0,
            skuAvailable: price?.skuAvailable ?? false,
            availabilityStatus, // 新增状态字段
          },
        });
      }
    }

    return allProducts;
  }

  // 查询价格
  private async fetchPrices(skus: string[]): Promise<PriceData[]> {
    const result = await this.request<{ success: boolean; data: PriceData[] }>(
      'POST',
      '/api-b2b-v1/product/price',
      { skus }
    );
    return result.success ? result.data : [];
  }

  // 查询库存
  private async fetchInventory(skus: string[]): Promise<InventoryData[]> {
    const result = await this.request<{ success: boolean; data: InventoryData[] }>(
      'POST',
      '/api-b2b-v1/inventory/quantity-query',
      { skus }
    );
    return result.success ? result.data : [];
  }

  // 查询商品详情
  private async fetchDetails(skus: string[]): Promise<any[]> {
    const result = await this.request<{ success: boolean; data: any[] }>(
      'POST',
      '/api-b2b-v1/product/detailInfo',
      { skus }
    );
    return result.success ? result.data : [];
  }

  async fetchProduct(productId: string): Promise<ChannelProduct | null> {
    const products = await this.fetchProductsBySkus([productId]);
    return products[0] || null;
  }

  private chunkArray<T>(array: T[], size: number): T[][] {
    const chunks: T[][] = [];
    for (let i = 0; i < array.length; i += size) {
      chunks.push(array.slice(i, i + size));
    }
    return chunks;
  }

  // 原始API测试方法 - 返回API原始响应
  async testRawApi(params: {
    endpoint: string;
    skus?: string[];
    page?: number;
    pageSize?: number;
  }): Promise<any> {
    const { endpoint, skus = [], page = 1, pageSize = 20 } = params;

    switch (endpoint) {
      case 'price':
        return this.request<any>('POST', '/api-b2b-v1/product/price', { skus });

      case 'inventory':
        return this.request<any>('POST', '/api-b2b-v1/inventory/quantity-query', { skus });

      case 'detail':
        return this.request<any>('POST', '/api-b2b-v1/product/detailInfo', { skus });

      case 'skuList': {
        const token = await this.getAccessToken();
        const queryParams = new URLSearchParams({
          page: page.toString(),
          limit: pageSize.toString(),
          sort: '2',
        });
        const url = `${this.baseUrl}/api-b2b-v1/product/skus?${queryParams}`;
        const response = await fetch(url, {
          headers: { Authorization: `Bearer ${token}` },
        });
        return response.json();
      }

      default:
        throw new Error(`未知的接口类型: ${endpoint}`);
    }
  }
}
