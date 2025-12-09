export interface ChannelProduct {
  channelProductId: string;
  sku: string;
  title: string;
  price: number;
  stock: number;
  currency?: string;
  extraFields?: Record<string, any>;
}

export interface FetchOptions {
  page?: number;
  pageSize?: number;
  updatedAfter?: Date; // 增量同步用
}

export interface FetchResult {
  products: ChannelProduct[];
  total: number;
  hasMore: boolean;
}

export abstract class BaseChannelAdapter {
  protected config: Record<string, any>;

  constructor(config: Record<string, any>) {
    this.config = config;
  }

  abstract testConnection(): Promise<boolean>;
  abstract fetchProducts(options: FetchOptions): Promise<FetchResult>;
  abstract fetchProduct(productId: string): Promise<ChannelProduct | null>;
}
