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
};

// 平台类目
export const platformCategoryApi = {
  // 同步类目（支持指定国家）
  syncCategories: (platformId: string, country?: string, shopId?: string) => 
    api.post(`/platform-categories/sync/${platformId}`, null, { params: { country, shopId } }),
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
  // 获取类目属性（支持指定国家）
  getCategoryAttributes: (platformId: string, categoryId: string, country?: string) =>
    api.get(`/platform-categories/${platformId}/attributes/${categoryId}`, { params: { country } }),
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
