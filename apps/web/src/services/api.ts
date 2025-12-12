import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  timeout: 300000, // 5分钟超时（用于大数据量请求）
});

api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    const message = error.response?.data?.message || '请求失败';
    return Promise.reject(new Error(message));
  },
);

// 渠道
export const channelApi = {
  list: (params?: any) => api.get('/channels', { params }),
  get: (id: string) => api.get(`/channels/${id}`),
  create: (data: any) => api.post('/channels', data),
  update: (id: string, data: any) => api.put(`/channels/${id}`, data),
  delete: (id: string) => api.delete(`/channels/${id}`),
  test: (id: string) => api.post(`/channels/${id}/test`),
  queryProducts: (id: string, skus: string[], options?: { warehouseCode?: string; priceType?: 'shipping' | 'pickup' }) =>
    api.post(`/channels/${id}/query-products`, { skus, ...options }),
  testApi: (id: string, params: { endpoint: string; skus?: string[]; page?: number; pageSize?: number }) =>
    api.post(`/channels/${id}/test-api`, params),
  fetchWarehouses: (id: string) => api.post(`/channels/${id}/fetch-warehouses`),
  getWarehouses: (id: string) => api.get(`/channels/${id}/warehouses`),
};

// 平台
export const platformApi = {
  list: (params?: any) => api.get('/platforms', { params }),
  get: (id: string) => api.get(`/platforms/${id}`),
  create: (data: any) => api.post('/platforms', data),
  update: (id: string, data: any) => api.put(`/platforms/${id}`, data),
  delete: (id: string) => api.delete(`/platforms/${id}`),
};

// 店铺
export const shopApi = {
  list: (params?: any) => api.get('/shops', { params }),
  get: (id: string) => api.get(`/shops/${id}`),
  create: (data: any) => api.post('/shops', data),
  update: (id: string, data: any) => api.put(`/shops/${id}`, data),
  delete: (id: string) => api.delete(`/shops/${id}`),
  test: (id: string) => api.post(`/shops/${id}/test`),
  syncProducts: (id: string) => api.post(`/shops/${id}/sync-products`),
  getSyncTaskStatus: (taskId: string) => api.get(`/shops/sync-task/${taskId}`),
  deleteAllProducts: (id: string) => api.delete(`/shops/${id}/products`),
  syncMissingSkus: (id: string, skus: string[]) => api.post(`/shops/${id}/sync-missing-skus`, { skus }),
  syncToWalmart: (id: string, data: { productIds: string[]; syncType: 'price' | 'inventory' | 'both' }) =>
    api.post(`/shops/${id}/sync-to-walmart`, data),
  // Feed管理
  getFeeds: (id: string | undefined, params?: any) => 
    id ? api.get(`/shops/${id}/feeds`, { params }) : api.get('/shops/feeds', { params }),
  refreshFeedStatus: (id: string, feedId: string) => api.post(`/shops/${id}/feeds/${feedId}/refresh`),
  getFeedDetail: (id: string, feedId: string, status: 'failed' | 'success' | 'all' = 'failed') => 
    api.get(`/shops/${id}/feeds/${feedId}/detail`, { params: { status } }),
  refreshFeedDetail: (id: string, feedId: string, status: 'failed' | 'success' | 'all' = 'failed') => 
    api.post(`/shops/${id}/feeds/${feedId}/refresh-detail`, null, { params: { status } }),
  deleteFeed: (id: string, feedId: string) => api.delete(`/shops/${id}/feeds/${feedId}`),
  // 同步任务管理
  getSyncTasks: (params?: any) => api.get('/shops/sync-tasks', { params }),
  pauseSyncTask: (taskId: string) => api.post(`/shops/sync-task/${taskId}/pause`),
  resumeSyncTask: (taskId: string) => api.post(`/shops/sync-task/${taskId}/resume`),
  cancelSyncTask: (taskId: string, force?: boolean) => api.post(`/shops/sync-task/${taskId}/cancel`, null, { params: { force } }),
  deleteSyncTask: (taskId: string) => api.delete(`/shops/sync-task/${taskId}`),
  retrySyncTask: (taskId: string) => api.post(`/shops/sync-task/${taskId}/retry`),
  // 同步配置
  getSyncConfig: (id: string) => api.get(`/shops/${id}/sync-config`),
  updateSyncConfig: (id: string, config: any) => api.put(`/shops/${id}/sync-config`, config),
};

// 同步规则
export const syncRuleApi = {
  list: (params?: any) => api.get('/sync-rules', { params }),
  get: (id: string) => api.get(`/sync-rules/${id}`),
  create: (data: any) => api.post('/sync-rules', data),
  update: (id: string, data: any) => api.put(`/sync-rules/${id}`, data),
  delete: (id: string) => api.delete(`/sync-rules/${id}`),
  execute: (id: string) => api.post(`/sync-rules/${id}/execute`),
  pause: (id: string) => api.put(`/sync-rules/${id}/pause`),
  resume: (id: string) => api.put(`/sync-rules/${id}/resume`),
};

// 商品
export const productApi = {
  list: (params?: any) => {
    // 如果有 skus 数组，转换为逗号分隔的字符串
    if (params?.skus && Array.isArray(params.skus)) {
      params = { ...params, skus: params.skus.join(',') };
    }
    return api.get('/products', { params });
  },
  export: (params?: any) => {
    if (params?.skus && Array.isArray(params.skus)) {
      params = { ...params, skus: params.skus.join(',') };
    }
    return api.get('/products/export', { params });
  },
  get: (id: string) => api.get(`/products/${id}`),
  update: (id: string, data: any) => api.put(`/products/${id}`, data),
  delete: (id: string) => api.delete(`/products/${id}`),
  batchDelete: (ids: string[]) => api.post('/products/batch-delete', { ids }),
  assignToShop: (ids: string[], shopId: string) => api.post('/products/assign-shop', { ids, shopId }),
  syncFromChannel: (channelId: string, products: any[], shopId?: string) => 
    api.post('/products/sync-from-channel', { channelId, products, shopId }),
  updatePlatformSku: (id: string, platformSku: string) => api.put(`/products/${id}/platform-sku`, { platformSku }),
  importPlatformSku: (shopId: string, mappings: { sku: string; platformSku: string }[]) =>
    api.post(`/products/import-platform-sku/${shopId}`, { mappings }),
  importProducts: (shopId: string, data: { channelId: string; products: { sku: string; platformSku?: string }[] }) =>
    api.post(`/products/import-products/${shopId}`, data),
  fetchLatestFromChannel: (shopId: string, data: { productIds?: string[]; fetchType: string }) =>
    api.post(`/products/fetch-latest/${shopId}`, data),
};

// 同步日志
export const syncLogApi = {
  list: (params?: any) => api.get('/sync-logs', { params }),
  get: (id: string) => api.get(`/sync-logs/${id}`),
  stats: () => api.get('/sync-logs/stats'),
};

// 仪表盘
export const dashboardApi = {
  overview: () => api.get('/dashboard/overview'),
  syncStats: () => api.get('/dashboard/sync-stats'),
  recentLogs: (limit?: number) => api.get('/dashboard/recent-logs', { params: { limit } }),
};

// 操作日志
export const operationLogApi = {
  list: (params?: any) => api.get('/operation-logs', { params }),
  get: (id: string) => api.get(`/operation-logs/${id}`),
  delete: (id: string) => api.delete(`/operation-logs/${id}`),
};

// 自动同步
export const autoSyncApi = {
  getConfigs: () => api.get('/auto-sync/configs'),
  getConfig: (shopId: string) => api.get(`/auto-sync/config/${shopId}`),
  updateConfig: (shopId: string, data: any) => api.put(`/auto-sync/config/${shopId}`, data),
  triggerSync: (shopId: string, syncType?: string) => api.post(`/auto-sync/trigger/${shopId}`, { syncType }),
  getTasks: (params?: any) => api.get('/auto-sync/tasks', { params }),
  getTask: (taskId: string) => api.get(`/auto-sync/task/${taskId}`),
  cancelTask: (taskId: string) => api.post(`/auto-sync/task/${taskId}/cancel`),
  pauseTask: (taskId: string) => api.post(`/auto-sync/task/${taskId}/pause`),
  resumeTask: (taskId: string) => api.post(`/auto-sync/task/${taskId}/resume`),
  retryTask: (taskId: string) => api.post(`/auto-sync/task/${taskId}/retry`),
  deleteTask: (taskId: string) => api.delete(`/auto-sync/task/${taskId}`),
};

// 商品刊登
export const listingApi = {
  // 从渠道查询商品详情
  queryFromChannel: (channelId: string, skus: string[]) =>
    api.post('/listing/query-channel', { channelId, skus }),
  // 导入商品
  importProducts: (data: { shopId: string; channelId: string; products: any[]; duplicateAction?: 'skip' | 'update' }) =>
    api.post('/listing/import', data),
  // 商品列表
  getProducts: (params?: any) => api.get('/listing/products', { params }),
  // 商品详情
  getProduct: (id: string) => api.get(`/listing/products/${id}`),
  // 更新商品
  updateProduct: (id: string, data: any) => api.put(`/listing/products/${id}`, data),
  // 删除商品
  deleteProducts: (ids: string[]) => api.delete('/listing/products', { data: { ids } }),
  deleteProduct: (id: string) => api.delete(`/listing/products/${id}`),
  // 验证刊登
  validateListing: (productIds: string[]) => api.post('/listing/validate', { productIds }),
  // 提交刊登
  submitListing: (data: { shopId: string; productIds: string[]; categoryId?: string }) =>
    api.post('/listing/submit', data),
  // 刊登任务
  getTasks: (params?: any) => api.get('/listing/tasks', { params }),
  getTask: (taskId: string) => api.get(`/listing/tasks/${taskId}`),
  // 刊登日志
  getLogs: (params?: any) => api.get('/listing/logs', { params }),
  getLog: (id: string) => api.get(`/listing/logs/${id}`),
  deleteLog: (id: string) => api.delete(`/listing/logs/${id}`),
  deleteLogs: (ids: string[]) => api.delete('/listing/logs', { data: { ids } }),
  // 刊登 Feed
  getFeeds: (params?: any) => api.get('/listing/feeds', { params }),
  getFeed: (id: string) => api.get(`/listing/feeds/${id}`),
  deleteFeed: (id: string) => api.delete(`/listing/feeds/${id}`),
  deleteFeeds: (ids: string[]) => api.delete('/listing/feeds', { data: { ids } }),
  // 刷新 Feed 状态（从 Walmart API 获取最新状态）
  refreshFeedStatus: (id: string) => api.post(`/listing/feeds/${id}/refresh`),
};

// 平台类目
export const platformCategoryApi = {
  // 同步类目（支持指定国家）
  syncCategories: (platformId: string, country?: string, shopId?: string) => 
    api.post(`/platform-categories/sync/${platformId}`, null, { params: { country, shopId } }),
  // 清空类目（指定国家）
  clearCategories: (platformId: string, country: string) =>
    api.delete(`/platform-categories/clear/${platformId}`, { params: { country } }),
  // 获取类目列表
  getCategories: (params?: any) => api.get('/platform-categories', { params }),
  // 获取类目树（支持指定国家）
  getCategoryTree: (platformId: string, country?: string, parentId?: string) =>
    api.get(`/platform-categories/tree/${platformId}`, { params: { country, parentId } }),
  // 搜索类目（支持指定国家）
  searchCategories: (platformId: string, keyword: string, country?: string, limit?: number) =>
    api.get(`/platform-categories/search/${platformId}`, { params: { keyword, country, limit } }),
  // 获取类目详情
  getCategory: (id: string) => api.get(`/platform-categories/${id}`),
  // 获取类目属性（支持指定国家，forceRefresh=true 强制从平台重新获取）
  getCategoryAttributes: (platformId: string, categoryId: string, country?: string, forceRefresh?: boolean) =>
    api.get(`/platform-categories/${platformId}/attributes/${categoryId}`, { params: { country, forceRefresh } }),
  // 获取类目属性原始响应（用于调试）
  getCategoryAttributesRaw: (platformId: string, categoryId: string, country?: string) =>
    api.get(`/platform-categories/${platformId}/attributes-raw/${categoryId}`, { params: { country } }),
  // 获取平台已同步的国家列表
  getCountries: (platformId: string) => api.get(`/platform-categories/countries/${platformId}`),
  // 获取类目属性映射配置
  getCategoryAttributeMapping: (platformId: string, categoryId: string, country?: string) =>
    api.get(`/platform-categories/${platformId}/mapping/${categoryId}`, { params: { country } }),
  // 保存类目属性映射配置
  saveCategoryAttributeMapping: (platformId: string, categoryId: string, mappingRules: any, country?: string) =>
    api.post(`/platform-categories/${platformId}/mapping/${categoryId}`, { mappingRules }, { params: { country } }),
  // 删除类目属性映射配置
  deleteCategoryAttributeMapping: (platformId: string, categoryId: string, country?: string) =>
    api.delete(`/platform-categories/${platformId}/mapping/${categoryId}`, { params: { country } }),
  // 获取平台所有类目的映射配置列表
  getCategoryAttributeMappings: (platformId: string, country?: string) =>
    api.get(`/platform-categories/${platformId}/mappings`, { params: { country } }),
  // 获取常用类目
  getFrequentCategories: (platformId: string, country?: string, limit?: number) =>
    api.get(`/platform-categories/${platformId}/frequent`, { params: { country, limit } }),
  // 获取可用的映射配置列表（用于加载配置）
  getAvailableMappings: (platformId: string, country?: string) =>
    api.get(`/platform-categories/${platformId}/available-mappings`, { params: { country } }),
  // 获取默认属性映射配置
  getDefaultAttributeMapping: (platformId: string, country?: string) =>
    api.get(`/platform-categories/${platformId}/default-mapping`, { params: { country } }),
  // 保存默认属性映射配置
  saveDefaultAttributeMapping: (platformId: string, mappingRules: any, country?: string) =>
    api.post(`/platform-categories/${platformId}/default-mapping`, { mappingRules }, { params: { country } }),
  // 删除默认属性映射配置
  deleteDefaultAttributeMapping: (platformId: string, country?: string) =>
    api.delete(`/platform-categories/${platformId}/default-mapping`, { params: { country } }),
  // 应用默认配置到属性列表
  applyDefaultMappingToAttributes: (platformId: string, attributes: any[], country?: string) =>
    api.post(`/platform-categories/${platformId}/apply-default-mapping`, { attributes }, { params: { country } }),
};

// 商品池管理
export const productPoolApi = {
  // 获取统计信息
  getStats: (channelId?: string) => api.get('/product-pool/stats', { params: { channelId } }),
  // 获取列表
  list: (params?: {
    page?: number;
    pageSize?: number;
    channelId?: string;
    keyword?: string;
    sku?: string;
    platformCategoryId?: string;
  }) => api.get('/product-pool', { params }),
  // 获取详情
  get: (id: string) => api.get(`/product-pool/${id}`),
  // 导入商品
  import: (data: { channelId: string; products: any[]; duplicateAction?: 'skip' | 'update'; platformCategoryId?: string }) =>
    api.post('/product-pool/import', data),
  // 刊登到店铺
  publish: (data: { productPoolIds: string[]; shopId: string; platformCategoryId?: string }) =>
    api.post('/product-pool/publish', data),
  // 更新商品
  update: (id: string, data: any) => api.put(`/product-pool/${id}`, data),
  // 删除商品
  delete: (ids: string[]) => api.delete('/product-pool', { data: { ids } }),
  // 获取刊登状态
  getListingStatus: (id: string) => api.get(`/product-pool/${id}/listings`),
  // 下载导入模板
  downloadTemplate: () => api.get('/product-pool/template/download', { responseType: 'blob' }),
  // 从 Excel 导入
  importFromExcel: (formData: FormData) => api.post('/product-pool/import-excel', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
};

// UPC 池管理
export const upcApi = {
  // 获取统计信息
  getStats: () => api.get('/upc/stats'),
  // 获取列表
  list: (params?: { page?: number; pageSize?: number; search?: string; status?: 'all' | 'used' | 'available' }) =>
    api.get('/upc', { params }),
  // 批量导入
  import: (upcCodes: string[]) => api.post('/upc/import', { upcCodes }),
  // 自动分配
  autoAssign: (productSku: string, shopId?: string) => api.post('/upc/auto-assign', { productSku, shopId }),
  // 手动分配
  assign: (upcCode: string, productSku: string, shopId?: string) => api.post('/upc/assign', { upcCode, productSku, shopId }),
  // 释放
  release: (upcCode: string) => api.post(`/upc/release/${upcCode}`),
  // 批量释放
  batchRelease: (ids: string[]) => api.post('/upc/batch-release', { ids }),
  // 批量标记为已使用
  batchMarkUsed: (ids: string[]) => api.post('/upc/batch-mark-used', { ids }),
  // 删除
  delete: (id: string) => api.delete(`/upc/${id}`),
  // 批量删除
  batchDelete: (ids: string[]) => api.post('/upc/batch-delete', { ids }),
  // 导出
  export: (status?: 'all' | 'used' | 'available') => api.get('/upc/export', { params: { status } }),
};

export default api;


// AI 模型管理
export const aiModelApi = {
  // 获取渠道列表
  list: () => api.get('/ai/models'),
  // 获取所有模型（用于默认模型选择）
  getAllModels: () => api.get('/ai/models/all-models'),
  // 获取详情
  get: (id: string) => api.get(`/ai/models/${id}`),
  // 创建渠道
  create: (data: {
    name: string;
    type: 'openai' | 'gemini' | 'openai_compatible';
    apiKey: string;
    baseUrl?: string;
    modelList: { id: string; name: string; maxTokens?: number }[];
    maxTokens?: number;
    temperature?: number;
  }) => api.post('/ai/models', data),
  // 更新渠道
  update: (id: string, data: any) => api.put(`/ai/models/${id}`, data),
  // 删除渠道
  delete: (id: string) => api.delete(`/ai/models/${id}`),
  // 测试连接
  test: (id: string) => api.post(`/ai/models/${id}/test`),
  // 设置默认
  setDefault: (id: string, defaultModel?: string) => api.post(`/ai/models/${id}/default`, { defaultModel }),
  // 获取可用模型列表（从 API 动态获取）
  fetchModels: (data: { type: 'openai' | 'gemini' | 'openai_compatible'; apiKey: string; baseUrl?: string }) =>
    api.post('/ai/models/fetch-models', data),
};

// Prompt 模板管理
export const promptTemplateApi = {
  // 获取列表
  list: (type?: string) => api.get('/ai/templates', { params: { type } }),
  // 获取详情
  get: (id: string) => api.get(`/ai/templates/${id}`),
  // 创建
  create: (data: {
    name: string;
    type: 'title' | 'description' | 'bullet_points' | 'keywords' | 'general';
    content: string;
    description?: string;
    isDefault?: boolean;
  }) => api.post('/ai/templates', data),
  // 更新
  update: (id: string, data: any) => api.put(`/ai/templates/${id}`, data),
  // 删除
  delete: (id: string) => api.delete(`/ai/templates/${id}`),
  // 复制
  duplicate: (id: string) => api.post(`/ai/templates/${id}/duplicate`),
  // 设置默认
  setDefault: (id: string) => api.post(`/ai/templates/${id}/default`),
  // 预览
  preview: (templateId: string, variables: Record<string, any>) =>
    api.post('/ai/templates/preview', { templateId, variables }),
};

// 属性映射
export const attributeMappingApi = {
  // 预览映射结果
  preview: (data: { mappingRules: any; channelAttributes: Record<string, any>; context?: any }) =>
    api.post('/attribute-mapping/preview', data),
  // 获取标准字段列表
  getStandardFields: () => api.get('/attribute-mapping/standard-fields'),
  // 获取自动生成规则列表
  getAutoGenerateRules: () => api.get('/attribute-mapping/auto-generate-rules'),
};

// AI 优化
export const aiOptimizeApi = {
  // 优化单个商品
  optimize: (data: {
    productId: string;
    productType: 'pool' | 'listing';
    fields: ('title' | 'description' | 'bulletPoints' | 'keywords')[];
    modelId?: string;
    templateIds?: Record<string, string>;
  }) => api.post('/ai/optimize', data),
  // 批量优化
  batchOptimize: (data: {
    products: Array<{ id: string; type: 'pool' | 'listing' }>;
    fields: ('title' | 'description' | 'bulletPoints' | 'keywords')[];
    modelId?: string;
    templateIds?: Record<string, string>;
  }) => api.post('/ai/optimize/batch', data),
  // 应用优化结果
  apply: (logIds: string[]) => api.post('/ai/optimize/apply', { logIds }),
  // 获取优化日志
  getLogs: (params?: {
    page?: number;
    pageSize?: number;
    productSku?: string;
    field?: string;
    status?: string;
    startDate?: string;
    endDate?: string;
  }) => api.get('/ai/optimize/logs', { params }),
  // 获取日志详情
  getLogDetail: (id: string) => api.get(`/ai/optimize/logs/${id}`),
};

// 图片处理
export const imageApi = {
  // 分析单张图片
  analyze: (url: string, maxSizeMB?: number) =>
    api.get('/image/analyze', { params: { url, maxSizeMB } }),
  // 批量分析
  batchAnalyze: (urls: string[], maxSizeMB?: number) =>
    api.post('/image/analyze/batch', { urls, maxSizeMB }),
  // 处理单张图片
  process: (data: { url: string; maxSizeMB?: number; forceSquare?: boolean; targetWidth?: number; quality?: number }) =>
    api.post('/image/process', data),
  // 批量处理
  batchProcess: (data: { urls: string[]; maxSizeMB?: number; forceSquare?: boolean; targetWidth?: number; quality?: number }) =>
    api.post('/image/process/batch', data),
  // 处理商品图片
  processProduct: (data: { mainImageUrl: string; imageUrls: string[]; maxSizeMB?: number; forceSquare?: boolean; targetWidth?: number; quality?: number }) =>
    api.post('/image/process/product', data),

  // 配置管理
  listConfigs: () => api.get('/image/config'),
  getDefaultConfig: () => api.get('/image/config/default'),
  saveConfig: (data: { id?: string; name: string; maxSizeMB: number; forceSquare: boolean; targetWidth: number; quality: number; isDefault?: boolean }) =>
    api.post('/image/config', data),
  deleteConfig: (id: string) => api.delete(`/image/config/${id}`),

  // 任务管理
  listTasks: (params?: { page?: number; pageSize?: number; status?: string }) =>
    api.get('/image/task', { params }),
  getTask: (id: string) => api.get(`/image/task/${id}`),
  createTask: (data: { name: string; scope: 'all' | 'category' | 'sku_list'; scopeValue?: string; configId?: string }) =>
    api.post('/image/task', data),
  startTask: (id: string) => api.post(`/image/task/${id}/start`),
  pauseTask: (id: string) => api.post(`/image/task/${id}/pause`),
  resumeTask: (id: string) => api.post(`/image/task/${id}/resume`),
  cancelTask: (id: string) => api.post(`/image/task/${id}/cancel`),
  retryFailed: (id: string) => api.post(`/image/task/${id}/retry`),
  getTaskLogs: (id: string, params?: { page?: number; pageSize?: number; status?: string }) =>
    api.get(`/image/task/${id}/logs`, { params }),
};

// 不可售平台
export const unavailablePlatformApi = {
  // 获取列表
  list: (channelId?: string) => api.get('/unavailable-platforms', { params: { channelId } }),
  // 批量保存
  save: (platforms: { platformId: string; platformName: string }[], channelId?: string) =>
    api.post('/unavailable-platforms/save', { platforms, channelId }),
};
