import { BaseChannelAdapter, ChannelProduct, FetchOptions, FetchResult } from './base.adapter';
import { ChannelAttributeDefinition } from './standard-product.interface';
import * as crypto from 'crypto';
import * as CryptoJS from 'crypto-js';

/**
 * 刊登模块：完整商品详情
 * 
 * 此接口用于 Saleyee 渠道商品详情的返回格式
 * standardAttributes 字段遵循 StandardProduct 接口的部分字段
 */
export interface SaleyeeProductDetail {
  sku: string;
  title: string;
  description: string;
  mainImageUrl: string;
  imageUrls: string[];
  videoUrls: string[];
  price: number;
  stock: number;
  currency: string;
  /** 标准化属性（遵循 StandardProduct 接口规范） */
  standardAttributes: {
    // 产品标识
    brand?: string;
    spu?: string;
    // 外观属性
    color?: string;
    // 包装尺寸重量
    weight?: number;
    weightUnit?: string;
    length?: number;
    width?: number;
    height?: number;
    lengthUnit?: string;
    // 分类
    categoryCode?: string;
    category?: string;
    categoryPath?: string;
    // 商品特点
    characteristics?: string[];
    keywords?: string[];
    // 货运属性
    freightAttrCode?: string;
    freightAttrName?: string;
    // 状态
    published?: boolean;
    isClear?: boolean;
  };
  /** 渠道特有字段 */
  channelSpecificFields: Record<string, any>;
  /** 原始数据 */
  rawData: Record<string, any>;
}

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
  // 扩展字段
  FreightAttrCode?: string;
  FreightAttrName?: string;
  PlatformCommodityCode?: string;
  CommodityWarehouseMode?: number;
  DistributionLimit?: number;
  TortInfo?: {
    TortReasons?: string;
    TortTypes?: string;
    TortStatus?: number;
  };
  GoodsImageList?: Array<{
    ImageUrl: string;
    ThumbnailUrl?: string;
    Sort?: number;
    IsMainImage?: boolean;
  }>;
  GoodsDescriptionList?: Array<{
    Title?: string;
    GoodsDescriptionKeywordList?: Array<{ KeyWord: string }>;
    GoodsDescriptionLabelList?: Array<{ LabelName: string }>;
    GoodsDescriptionParagraphList?: Array<{
      ParagraphName?: string;
      SortNo?: number;
      GoodsDescription?: string;
    }>;
  }>;
  GoodsAttachmentList?: Array<{
    AttachmentName?: string;
    AttachmentUrl?: string;
    AttachmentSize?: string;
  }>;
  ProductSiteModelList?: Array<{
    SiteHosts?: string;
    WarehouseCodeList?: string[];
  }>;
  ProductAttributeList?: Array<{
    AttributeCode?: string;
    AttributeName?: string;
    AttributeNameEn?: string;
    AttributeValue?: string;
  }>;
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

  // ==================== 刊登模块：获取完整商品详情 ====================

  /**
   * 获取商品完整详情（调用详情+价格+库存接口）
   * 用于商品刊登功能
   */
  async fetchProductDetails(skus: string[]): Promise<SaleyeeProductDetail[]> {
    if (skus.length === 0) return [];

    const warehouseCode = this.saleyeeConfig.warehouseCode || 'SZ0001';
    const priceType = this.saleyeeConfig.priceType || 'shipping';

    // API限制每次最多30个SKU
    const chunks = this.chunkArray(skus, 30);
    const allProducts: SaleyeeProductDetail[] = [];

    for (const chunk of chunks) {
      // 并行调用三个接口
      const [detailResult, priceResult, inventoryResult] = await Promise.all([
        this.fetchDetailInfo(chunk),
        this.fetchPrices(chunk).catch(() => ({ DataList: [] })),
        this.fetchInventory(chunk).catch(() => ({ DataList: [] })),
      ]);

      // 构建映射
      const detailMap = new Map(detailResult.map(d => [d.Sku, d]));

      const priceMap = new Map<string, WarehousePrice[]>();
      for (const item of priceResult.DataList || []) {
        if (item.WarehousePriceList?.length > 0) {
          priceMap.set(item.Sku, item.WarehousePriceList);
        }
      }

      const inventoryMap = new Map<string, ProductInventoryModel[]>();
      for (const item of inventoryResult.DataList || []) {
        if (item.ProductInventorySiteList?.length > 0) {
          const inventories: ProductInventoryModel[] = [];
          for (const site of item.ProductInventorySiteList) {
            if (site.ProductInventoryList) {
              inventories.push(...site.ProductInventoryList);
            }
          }
          inventoryMap.set(item.Sku, inventories);
        }
      }

      for (const sku of chunk) {
        const detail = detailMap.get(sku);
        const prices = priceMap.get(sku) || [];
        const inventories = inventoryMap.get(sku) || [];

        if (!detail) {
          console.log(`[Saleyee] SKU ${sku} 详情不存在，跳过`);
          continue;
        }

        // 选择价格
        const warehousePrices = prices.filter(p => p.StockCode === warehouseCode);
        let selectedPrice: WarehousePrice | undefined;
        if (priceType === 'shipping') {
          selectedPrice = warehousePrices.find(p => p.LogisticsProductName === 'Standard Shipping');
        } else {
          selectedPrice = warehousePrices.find(p => p.LogisticsProductName === 'Self-Pick up');
        }
        if (!selectedPrice && warehousePrices.length > 0) {
          selectedPrice = warehousePrices[0];
        }

        // 选择库存
        const selectedInventory = inventories.find(inv => inv.StockCode === warehouseCode) || inventories[0];

        allProducts.push({
          sku: detail.Sku,
          title: detail.CnName || detail.EnName || '',
          description: this.buildDescription(detail),
          mainImageUrl: this.getMainImageUrl(detail),
          imageUrls: this.getImageUrls(detail),
          videoUrls: [],
          price: selectedPrice?.SellingPrice ?? selectedPrice?.OriginalPrice ?? 0,
          stock: selectedInventory?.Qty || 0,
          currency: selectedPrice?.CurrencyCode || 'USD',
          standardAttributes: this.buildStandardAttributes(detail),
          channelSpecificFields: {
            // Saleyee 特有字段
            spu: detail.Spu,
            platformCommodityCode: detail.PlatformCommodityCode,
            commodityWarehouseMode: detail.CommodityWarehouseMode,
            freightAttrCode: detail.FreightAttrCode,
            freightAttrName: detail.FreightAttrName,
            tortInfo: detail.TortInfo,
            productSiteModelList: detail.ProductSiteModelList,
            goodsAttachmentList: detail.GoodsAttachmentList,
            goodsDescriptionList: detail.GoodsDescriptionList,
            productAttributeList: detail.ProductAttributeList,
            distributionLimit: detail.DistributionLimit,
            // 价格相关
            originalPrice: selectedPrice?.OriginalPrice,
            sellingPrice: selectedPrice?.SellingPrice,
            warehouseCode: selectedPrice?.StockCode,
            logisticsProductCode: selectedPrice?.LogisticsProductCode,
            logisticsProductName: selectedPrice?.LogisticsProductName,
            // 库存相关
            makeStockQty: selectedInventory?.MakeStockQty || 0,
          },
          rawData: {
            detail,
            price: selectedPrice,
            inventory: selectedInventory,
          },
        });
      }
    }

    return allProducts;
  }

  /**
   * 查询商品详情
   */
  private async fetchDetailInfo(skus: string[]): Promise<ProductDetailModel[]> {
    const result = await this.request<{
      ProductInfoList: ProductDetailModel[];
    }>('/api/Product/QueryProductDetail', { Skus: skus });
    return result.ProductInfoList || [];
  }

  /**
   * 构建标准化属性（遵循新版简化结构）
   */
  private buildStandardAttributes(detail: ProductDetailModel): Record<string, any> {
    // 从 ProductAttributeList 中提取颜色和材质
    const colorAttr = detail.ProductAttributeList?.find(
      a => a.AttributeName === '色彩' || a.AttributeNameEn?.toLowerCase() === 'color'
    );
    const materialAttr = detail.ProductAttributeList?.find(
      a => a.AttributeName === '材质' || a.AttributeNameEn?.toLowerCase() === 'material'
    );

    // 构建分类路径
    const categoryParts = [
      detail.CategoryFirstName,
      detail.CategorySecondName,
      detail.CategoryThirdName,
    ].filter(Boolean);

    return {
      // ==================== 核心字段（新版简化结构）====================
      // 外观属性（核心字段）
      color: colorAttr?.AttributeValue || null,
      material: materialAttr?.AttributeValue || null,

      // 包装尺寸（新字段名）
      packageWeight: detail.SpecWeight || null,
      packageLength: detail.SpecLength || null,
      packageWidth: detail.SpecWidth || null,
      packageHeight: detail.SpecHeight || null,
      weightUnit: 'g', // Saleyee 重量单位为克
      lengthUnit: 'cm', // Saleyee 尺寸单位为厘米

      // 商品特点（用于生成 bulletPoints）
      characteristics: this.extractCharacteristics(detail),

      // ==================== 兼容字段（供 attribute-resolver 使用）====================
      weight: detail.SpecWeight || null,
      length: detail.SpecLength || null,
      width: detail.SpecWidth || null,
      height: detail.SpecHeight || null,
      brand: detail.BrandName || null,
      spu: detail.Spu || null,
      categoryCode: detail.CategoryThirdCode || detail.CategorySecondCode || detail.CategoryFirstCode,
      category: detail.CategoryThirdName || detail.CategorySecondName || detail.CategoryFirstName,
      categoryPath: categoryParts.join(' > '),
      keywords: detail.GoodsDescriptionList?.[0]?.GoodsDescriptionKeywordList?.map(k => k.KeyWord) || [],

      // 货运属性
      freightAttrCode: detail.FreightAttrCode,
      freightAttrName: detail.FreightAttrName,

      // 状态
      published: detail.Published,
      isClear: detail.IsClear,
    };
  }

  /**
   * 从商品详情中提取特点描述
   */
  private extractCharacteristics(detail: ProductDetailModel): string[] {
    const characteristics: string[] = [];
    const descList = detail.GoodsDescriptionList?.[0]?.GoodsDescriptionParagraphList || [];
    
    for (const para of descList) {
      if (para.GoodsDescription) {
        // 移除 HTML 标签，提取纯文本
        const text = para.GoodsDescription.replace(/<[^>]*>/g, '').trim();
        if (text && text.length < 500) {
          characteristics.push(text);
        }
      }
    }
    
    return characteristics.slice(0, 5); // 最多5条
  }

  /**
   * 构建商品描述
   */
  private buildDescription(detail: ProductDetailModel): string {
    const descList = detail.GoodsDescriptionList?.[0]?.GoodsDescriptionParagraphList || [];
    return descList.map(p => p.GoodsDescription || '').join('\n');
  }

  /**
   * 获取主图URL
   */
  private getMainImageUrl(detail: ProductDetailModel): string {
    const mainImage = detail.GoodsImageList?.find(img => img.IsMainImage);
    return mainImage?.ImageUrl || detail.GoodsImageList?.[0]?.ImageUrl || '';
  }

  /**
   * 获取所有图片URL
   */
  private getImageUrls(detail: ProductDetailModel): string[] {
    return detail.GoodsImageList?.map(img => img.ImageUrl).filter(Boolean) || [];
  }

  /**
   * 获取渠道支持的属性字段定义
   */
  getAttributeDefinitions(): ChannelAttributeDefinition[] {
    return [
      // 基本信息
      { key: 'brand', label: '品牌', type: 'string', path: 'standardAttributes.brand' },
      { key: 'spu', label: 'SPU', type: 'string', path: 'standardAttributes.spu' },

      // 外观属性
      { key: 'color', label: '颜色', type: 'string', path: 'standardAttributes.color' },

      // 尺寸重量
      { key: 'weight', label: '重量(g)', type: 'number', path: 'standardAttributes.weight' },
      { key: 'weightUnit', label: '重量单位', type: 'string', path: 'standardAttributes.weightUnit' },
      { key: 'length', label: '长度(cm)', type: 'number', path: 'standardAttributes.length' },
      { key: 'width', label: '宽度(cm)', type: 'number', path: 'standardAttributes.width' },
      { key: 'height', label: '高度(cm)', type: 'number', path: 'standardAttributes.height' },
      { key: 'lengthUnit', label: '长度单位', type: 'string', path: 'standardAttributes.lengthUnit' },

      // 分类
      { key: 'categoryCode', label: '分类编码', type: 'string', path: 'standardAttributes.categoryCode' },
      { key: 'category', label: '分类名称', type: 'string', path: 'standardAttributes.category' },
      { key: 'categoryPath', label: '分类路径', type: 'string', path: 'standardAttributes.categoryPath' },

      // 特点
      { key: 'characteristics', label: '商品特点', type: 'array', path: 'standardAttributes.characteristics' },
      { key: 'keywords', label: '关键词', type: 'array', path: 'standardAttributes.keywords' },

      // Saleyee 特有字段
      { key: 'freightAttrCode', label: '货运属性编码', type: 'string', path: 'channelSpecificFields.freightAttrCode' },
      { key: 'freightAttrName', label: '货运属性名称', type: 'string', path: 'channelSpecificFields.freightAttrName' },
      { key: 'platformCommodityCode', label: '平台商品编码', type: 'string', path: 'channelSpecificFields.platformCommodityCode' },
      { key: 'published', label: '是否上架', type: 'boolean', path: 'standardAttributes.published' },
      { key: 'isClear', label: '是否清仓', type: 'boolean', path: 'standardAttributes.isClear' },
    ];
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
