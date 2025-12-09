export interface SyncProductData {
  sku: string;
  title: string;
  price: number;
  stock: number;
  currency?: string;
  extraFields?: Record<string, any>;
}

export interface SyncResult {
  success: boolean;
  platformProductId?: string;
  error?: string;
}

export interface BatchSyncResult {
  total: number;
  successCount: number;
  failCount: number;
  results: Array<{ sku: string; success: boolean; error?: string }>;
}

export abstract class BasePlatformAdapter {
  protected credentials: Record<string, any>;

  constructor(credentials: Record<string, any>) {
    this.credentials = credentials;
  }

  abstract testConnection(): Promise<boolean>;
  abstract syncProduct(product: SyncProductData): Promise<SyncResult>;
  abstract batchSyncProducts(products: SyncProductData[]): Promise<BatchSyncResult>;
  abstract updateStock(sku: string, stock: number): Promise<boolean>;
  abstract updatePrice(sku: string, price: number): Promise<boolean>;
}
