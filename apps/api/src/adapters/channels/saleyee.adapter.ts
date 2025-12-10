import { BaseChannelAdapter, ChannelProduct, FetchOptions, FetchResult } from './base.adapter';
import * as crypto from 'crypto';
import * as CryptoJS from 'crypto-js';

interface SaleyeeConfig {
  token: string;
  key: string; // DES加密密钥
  baseUrl?: string; // 默认生产环境
  warehouseCode?: string; // 默认区域代码，如 SZ0001
  priceType?: 'shipping' | 'pickup'; // 价格类型：包邮或自提
}

interface RequestBase {
  Version: string;
  Token: string;
  Sign: string | null;
  RequestId: string;
  RequestTime: string;
  Message: string | null;
}

interface ResponseBase<T = any> {
  Version: string;
  RequestId: string;
  RequestTime: string;
  ResponseTime: string;
  ResponseId: string;
  Result: number; // 0-Failure，1-Success
  Error: ResponseError | null;
  RequestMessage: string | null;
  Message: string; // JSON字符串
}

interface ResponseError {
  Code: string;
  ShortMessage: string;
  LongMessage: string;
}

interface ProductDetailModel {
  Sku: string;
  CategoryFirstCode?: string;
  CategoryFirstName?: string;
  CategorySecondCode?: string;
  CategorySecondName?: string;
  CategoryThirdCode?: string;
  CategoryThirdName?: string;
  CnName?: string;
  EnName?: string;
  SpecLength?: number;
  SpecWidth?: number;
  SpecHeight?: number;
  SpecWeight?: number;
  Published: boolean;
  IsClear: boolean;
  CreateTime: string;
  UpdateTime?: string;
  IsProductAuth: boolean;
  Spu?: string;
  BrandName?: string;
}

interface ProductInventoryModel {
  StockCode: string;
  StockName: string;
  Qty: number;
  MakeStockQty?: number;
}

interface WarehousePrice {
  Store: string;
  StockCode: string;
  LogisticsProductCode: string;
  OriginalPrice: number;
  SellingPrice: number;
  CurrencyCode: string;
  LogisticsProductName: string;
}

export class SaleyeeAdapter extends BaseChannelAdapter {
  private baseUrl: string;

  constructor(config: Record<string, any>) {
    super(config);
    this.baseUrl = config.baseUrl || 'https://api.saleyee.com';
  }

  private get saleyeeConfig(): SaleyeeConfig {
    return this.config as SaleyeeConfig;
  }

  // DES加密（CBC模式，PKCS7填充）
  private desEncrypt(text: string, key: string): string {
    // 使用 crypto-js 实现 DES-CBC 加密
    // Key 和 IV 都使用相同的 key 值
    const keyHex = CryptoJS.enc.Utf8.parse(key);
    const ivHex = CryptoJS.enc.Utf8.parse(key);

    const encrypted = CryptoJS.DES.encrypt(text, keyHex, {
      iv: ivHex,
      mode: CryptoJS.mode.CBC,
      padding: CryptoJS.pad.Pkcs7,
    });

    return encrypted.toString();
  }

  // 构建请求
  private buildRequest(message: any = null): RequestBase {
    const requestId = crypto.randomUUID();
    const requestTime = new Date().toISOString();

    const request: RequestBase = {
      Version: '1.0.0.0',
      Token: this.saleyeeConfig.token,
      Sign: null,
      RequestId: requestId,
      RequestTime: requestTime,
      Message: message ? JSON.stringify(message) : null,
    };

    // 对整个请求对象进行DES加密生成Sign
    const requestJson = JSON.stringify(request);
    request.Sign = this.desEncrypt(requestJson, this.saleyeeConfig.key);

    return request;
  }

  // 发送请求
  private async request<T>(path: string, message: any = null): Promise<T> {
    const url = `${this.baseUrl}${path}`;
    const requestBody = this.buildRequest(message);

    console.log('[Saleyee] Request:', {
      url,
      path,
      hasMessage: !!message,
    });

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(requestBody),
    });

    console.log('[Saleyee] Response status:', response.status);

    if (!response.ok) {
      const errorText = await response.text();
      console.error('[Saleyee] Response error:', errorText);
      throw new Error(`Saleyee API error: ${response.status} - ${errorText}`);
    }

    const result: ResponseBase<T> = await response.json();
    console.log('[Saleyee] Response result:', {
      Result: result.Result,
      Error: result.Error,
      hasMessage: !!result.Message,
    });

    if (result.Result !== 1) {
      const errorMsg = result.Error?.LongMessage || result.Error?.Code || 'Unknown error';
      throw new Error(`Saleyee API failed: ${errorMsg}`);
    }

    // Message字段是JSON字符串，需要解析
    if (result.Message) {
      return JSON.parse(result.Message) as T;
    }

    return {} as T;
  }

  async testConnection(): Promise<boolean> {
    try {
      console.log('[Saleyee] Testing connection with config:', {
        baseUrl: this.baseUrl,
        token: this.saleyeeConfig.token ? '***' : 'missing',
        key: this.saleyeeConfig.key ? '***' : 'missing',
      });

      // 调用获取区域接口测试连接
      const result = await this.request('/api/Product/GetWarehouse');
      console.log('[Saleyee] Connection test successful:', result);
      return true;
    } catch (error) {
      console.error('[Saleyee] Connection test failed:', error);
      throw error; // 抛出错误以便上层捕获详细信息
    }
  }

  // 获取商品列表（分页查询）
  async fetchProducts(options: FetchOptions): Promise<FetchResult> {
    const { page = 1, pageSize = 50 } = options;

    // 构建查询参数
    const message: any = {
      PageIndex: page,
    };

    // 如果有更新时间，使用时间范围查询
    if (options.updatedAfter) {
      message.StartTime = options.updatedAfter.toISOString().split('T')[0];
      message.EndTime = new Date().toISOString().split('T')[0];
    }

    const result = await this.request<{
      ProductInfoList: ProductDetailModel[];
      PageIndex: number;
      TotalCount: number;
      PageTotal: number;
      PageSize: number;
    }>('/api/Product/QueryProductDetail', message);

    // 获取SKU列表
    const skus = result.ProductInfoList
      .filter(p => p.IsProductAuth) // 只获取有权限的商品
      .map(p => p.Sku);

    // 批量查询价格和库存
    const products = await this.fetchProductsBySkus(skus, result.ProductInfoList);

    return {
      products,
      total: result.TotalCount || 0,
      hasMore: result.PageIndex < result.PageTotal,
    };
  }

  // 批量查询SKU的价格和库存
  async fetchProductsBySkus(
    skus: string[],
    productDetails?: ProductDetailModel[],
    options?: { warehouseCode?: string; priceType?: 'shipping' | 'pickup' }
  ): Promise<ChannelProduct[]> {
    if (skus.length === 0) return [];

    // 优先使用传入参数，其次使用渠道配置的默认值，最后使用硬编码默认值
    const warehouseCode = options?.warehouseCode || this.saleyeeConfig.warehouseCode || 'SZ0001';
    const priceType = options?.priceType || this.saleyeeConfig.priceType || 'shipping';

    // API限制每次最多30个SKU
    const chunks = this.chunkArray(skus, 30);
    const allProducts: ChannelProduct[] = [];

    for (const chunk of chunks) {
      // 并行查询价格和库存
      const [priceResult, inventoryResult] = await Promise.all([
        this.fetchPrices(chunk).catch(() => ({ DataList: [] })),
        this.fetchInventory(chunk).catch(() => ({ DataList: [] })),
      ]);

      // 构建价格和库存映射
      const priceMap = new Map<string, WarehousePrice[]>();
      for (const item of priceResult.DataList || []) {
        if (item.WarehousePriceList && item.WarehousePriceList.length > 0) {
          priceMap.set(item.Sku, item.WarehousePriceList);
        }
      }

      const inventoryMap = new Map<string, ProductInventoryModel[]>();
      for (const item of inventoryResult.DataList || []) {
        if (item.ProductInventorySiteList && item.ProductInventorySiteList.length > 0) {
          const inventories: ProductInventoryModel[] = [];
          for (const site of item.ProductInventorySiteList) {
            if (site.ProductInventoryList) {
              inventories.push(...site.ProductInventoryList);
            }
          }
          inventoryMap.set(item.Sku, inventories);
        }
      }

      // 构建商品详情映射
      const detailMap = new Map<string, ProductDetailModel>();
      if (productDetails) {
        for (const detail of productDetails) {
          detailMap.set(detail.Sku, detail);
        }
      }

      for (const sku of chunk) {
        const prices = priceMap.get(sku) || [];
        const inventories = inventoryMap.get(sku) || [];
        const detail = detailMap.get(sku);

        // 根据区域和价格类型筛选价格
        let selectedPrice: WarehousePrice | undefined;
        const warehousePrices = prices.filter(p => p.StockCode === warehouseCode);

        if (priceType === 'shipping') {
          // 包邮价格：Standard Shipping
          selectedPrice = warehousePrices.find(p => p.LogisticsProductName === 'Standard Shipping');
        } else {
          // 自提价格：Self-Pick up
          selectedPrice = warehousePrices.find(p => p.LogisticsProductName === 'Self-Pick up');
        }

        // 如果没找到指定类型的价格，使用第一个匹配区域的价格
        if (!selectedPrice && warehousePrices.length > 0) {
          selectedPrice = warehousePrices[0];
        }

        // 获取对应区域的库存
        const selectedInventory = inventories.find(inv => inv.StockCode === warehouseCode) || inventories[0];

        // 判断可用性状态
        let availabilityStatus = 'available';
        if (!selectedPrice) {
          availabilityStatus = 'no_price_data';
        } else if (selectedPrice.SellingPrice == null || selectedPrice.SellingPrice === 0) {
          availabilityStatus = 'price_not_exist';
        } else if (!selectedInventory || selectedInventory.Qty === 0) {
          availabilityStatus = 'out_of_stock';
        }

        allProducts.push({
          channelProductId: sku,
          sku,
          title: detail?.CnName || detail?.EnName || '',
          price: selectedPrice?.OriginalPrice ?? null, // 原价
          stock: selectedInventory?.Qty || 0,
          currency: selectedPrice?.CurrencyCode || 'USD',
          extraFields: {
            discountedPrice: selectedPrice?.SellingPrice ?? null, // 优惠价（售价）
            warehouseCode: selectedPrice?.StockCode ?? null,
            warehouseName: selectedInventory?.StockName ?? null,
            logisticsProductCode: selectedPrice?.LogisticsProductCode ?? null,
            logisticsProductName: selectedPrice?.LogisticsProductName ?? null,
            makeStockQty: selectedInventory?.MakeStockQty ?? 0,
            spu: detail?.Spu ?? null,
            brandName: detail?.BrandName ?? null,
            published: detail?.Published ?? false,
            isClear: detail?.IsClear ?? false,
            availabilityStatus,
            priceType, // 添加价格类型标识
            allPrices: prices, // 保存所有价格供前端参考
          },
        });
      }
    }

    return allProducts;
  }

  // 查询价格（V2.0）
  private async fetchPrices(skus: string[]): Promise<{
    DataList: Array<{
      Sku: string;
      Spu: string;
      WarehousePriceList: WarehousePrice[];
    }>;
    NextToken?: string;
  }> {
    return this.request('/api/Product/QueryProductPriceV2', {
      SkuList: skus,
    });
  }

  // 查询库存（V2.0）
  private async fetchInventory(skus: string[]): Promise<{
    DataList: Array<{
      Sku: string;
      Spu: string;
      ProductInventorySiteList: Array<{
        SiteHosts: string;
        ProductInventoryList: ProductInventoryModel[];
      }>;
    }>;
    NextToken?: string;
  }> {
    return this.request('/api/Product/QueryProductInventoryV2', {
      SkuList: skus,
    });
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

  // 获取区域列表
  async fetchWarehouses(): Promise<Array<{
    code: string;
    name: string;
    region?: string;
    country?: string;
    extraData?: any;
  }>> {
    // 根据文档，GetWarehouse 接口的 Message 字段直接是一个数组
    const result = await this.request<any[]>('/api/Product/GetWarehouse');

    console.log('[Saleyee] Warehouse API response:', JSON.stringify(result, null, 2));

    // result 本身就应该是数组
    if (!Array.isArray(result)) {
      console.error('[Saleyee] Warehouse result is not an array:', result);
      return [];
    }

    console.log('[Saleyee] Parsed warehouse count:', result.length);

    return result.map((w: any) => ({
      code: w.StockCode || w.stockCode,
      name: w.StockName || w.stockName,
      region: w.StockAbbreviation || w.stockAbbreviation,
      country: w.CountryCode || w.countryCode,
      extraData: {
        street1: w.Street1,
        street2: w.Street2,
        city: w.City,
        stateOrProvince: w.StateOrProvince,
        postCode: w.PostCode,
        contactMan: w.ContactMan,
        tel: w.Tel,
        supportedReturnPlatformWarehouse: w.SupportedReturnPlatformWarehouse,
        original: w,
      },
    }));
  }

  // 原始API测试方法
  async testRawApi(params: {
    endpoint: string;
    skus?: string[];
    warehouseCode?: string;
  }): Promise<any> {
    const { endpoint, skus = [], warehouseCode } = params;

    switch (endpoint) {
      case 'warehouse':
        return this.request('/api/Product/GetWarehouse');

      case 'logistics':
        return this.request('/api/Product/GetLogisticsProductStandard');

      case 'productDetail':
        return this.request('/api/Product/QueryProductDetail', {
          Skus: skus,
          WarehouseCode: warehouseCode,
        });

      case 'price':
        return this.request('/api/Product/QueryProductPriceV2', {
          SkuList: skus,
        });

      case 'inventory':
        return this.request('/api/Product/QueryProductInventoryV2', {
          SkuList: skus,
        });

      default:
        throw new Error(`未知的接口类型: ${endpoint}`);
    }
  }
}
