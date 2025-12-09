import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
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
  queryProducts: (id: string, skus: string[]) => api.post(`/channels/${id}/query-products`, { skus }),
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
  list: (params?: any) => api.get('/products', { params }),
  get: (id: string) => api.get(`/products/${id}`),
  update: (id: string, data: any) => api.put(`/products/${id}`, data),
  delete: (id: string) => api.delete(`/products/${id}`),
  batchDelete: (ids: string[]) => api.post('/products/batch-delete', { ids }),
  assignToShop: (ids: string[], shopId: string) => api.post('/products/assign-shop', { ids, shopId }),
  syncFromChannel: (channelId: string, products: any[], shopId?: string) => 
    api.post('/products/sync-from-channel', { channelId, products, shopId }),
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

export default api;
