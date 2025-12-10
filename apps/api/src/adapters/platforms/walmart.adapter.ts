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

  // ==================== 类目管理 ====================

  /**
   * 获取 Walmart 类目列表
   * 使用 /v3/items/taxonomy?version=5.0 端点获取完整的类目树
   * 数据结构：Category -> Product Type Group (PTG) -> Product Type (PT)
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
    try {
      const headers = await this.getHeaders();

      // 使用 5.0 版本的 taxonomy 端点
      console.log('[Walmart] Fetching categories from /v3/items/taxonomy?version=5.0');
      const response = await this.client.get('/v3/items/taxonomy', {
        headers,
        params: { version: '5.0' },
      });

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
      console.error('[Walmart] Get categories failed:', error.response?.data || error.message);
      // 如果 API 不可用，返回空数组
      return [];
    }
  }

  /**
   * 获取类目属性
   * 使用 V5.0 Item Spec API: POST /v3/items/spec
   * 参考 woo-walmart-sync 插件的实现
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
  }>> {
    try {
      const headers = await this.getHeaders();

      console.log(`[Walmart] Fetching V5.0 spec for productType: ${categoryId}`);

      // 使用 V5.0 Item Spec API（参考 woo-walmart-sync 插件）
      const requestBody = {
        feedType: 'MP_ITEM',
        version: '5.0.20241118-04_39_24-api',
        productTypes: [categoryId], // Product Type 名称
      };

      console.log(`[Walmart] Request body:`, JSON.stringify(requestBody));

      const response = await this.client.post('/v3/items/spec', requestBody, {
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
    }> = [];

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

            console.log(`[Walmart] Found Visible properties for ${categoryId}: ${Object.keys(visibleProps).length}`);

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

      console.log(`[Walmart] Total attributes parsed: ${attributes.length}`);
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
   */
  async createItem(item: {
    sku: string;
    productIdType: string;
    productId: string;
    title: string;
    brand: string;
    price: number;
    description?: string;
    mainImageUrl?: string;
    additionalImageUrls?: string[];
    category?: string;
    attributes?: Record<string, any>;
  }): Promise<{ feedId: string }> {
    try {
      const headers = await this.getHeaders();

      // 构建 MP_ITEM Feed 数据
      const feedData = {
        MPItemFeedHeader: {
          version: '4.2',
          requestId: this.generateCorrelationId(),
          requestBatchId: this.generateCorrelationId(),
        },
        MPItem: [
          {
            sku: item.sku,
            productIdentifiers: {
              productIdType: item.productIdType,
              productId: item.productId,
            },
            productName: item.title,
            brand: item.brand,
            price: {
              currency: 'USD',
              amount: item.price,
            },
            ShortDescription: item.description,
            mainImageUrl: item.mainImageUrl,
            additionalImageUrls: item.additionalImageUrls,
            ...item.attributes,
          },
        ],
      };

      const FormData = require('form-data');
      const form = new FormData();
      form.append('file', JSON.stringify(feedData), {
        filename: 'item.json',
        contentType: 'application/json',
      });

      const response = await this.client.post('/v3/feeds', form, {
        headers: {
          ...headers,
          ...form.getHeaders(),
        },
        params: { feedType: 'MP_ITEM' },
      });

      return { feedId: response.data.feedId };
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
      if (updates.price) mpItem.price = { currency: 'USD', amount: updates.price };
      if (updates.description) mpItem.ShortDescription = updates.description;
      if (updates.mainImageUrl) mpItem.mainImageUrl = updates.mainImageUrl;
      if (updates.attributes) Object.assign(mpItem, updates.attributes);

      const feedData = {
        MPItemFeedHeader: {
          version: '4.2',
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

      const response = await this.client.post('/v3/feeds', form, {
        headers: {
          ...headers,
          ...form.getHeaders(),
        },
        params: { feedType: 'MP_ITEM' },
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
