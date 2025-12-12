import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { ChannelService } from '@/modules/channel/channel.service';
import { AttributeResolverService } from '@/modules/attribute-mapping/attribute-resolver.service';
import { ListingLogService } from './listing-log.service';
import { ListingFeedService } from './listing-feed.service';
import {
  QueryFromChannelDto,
  ListingQueryDto,
  ImportListingDto,
  ImportResultDto,
  UpdateListingDto,
  SubmitListingDto,
  ValidateListingResultDto,
} from './dto';
import { ChannelProductDetail } from '@/adapters/channels/gigacloud.adapter';
import { PlatformAdapterFactory, WalmartAdapter } from '@/adapters/platforms';

@Injectable()
export class ListingService {
  constructor(
    private prisma: PrismaService,
    private channelService: ChannelService,
    private attributeResolver: AttributeResolverService,
    private listingLogService: ListingLogService,
    private listingFeedService: ListingFeedService,
  ) {}

  /**
   * 从渠道查询商品完整详情
   */
  async queryFromChannel(dto: QueryFromChannelDto): Promise<ChannelProductDetail[]> {
    const channel = await this.prisma.channel.findUnique({
      where: { id: dto.channelId },
    });
    if (!channel) {
      throw new NotFoundException('渠道不存在');
    }

    const adapter = this.channelService.getAdapter(channel);
    
    // 检查适配器是否支持 fetchProductDetails 方法
    if (!('fetchProductDetails' in adapter)) {
      throw new BadRequestException('该渠道不支持获取商品详情');
    }

    return (adapter as any).fetchProductDetails(dto.skus);
  }

  /**
   * 导入商品到刊登店铺
   */
  async importProducts(dto: ImportListingDto): Promise<ImportResultDto> {
    const { shopId, channelId, products, duplicateAction = 'skip' } = dto;

    // 验证店铺和渠道
    const [shop, channel] = await Promise.all([
      this.prisma.shop.findUnique({ where: { id: shopId } }),
      this.prisma.channel.findUnique({ where: { id: channelId } }),
    ]);
    if (!shop) throw new NotFoundException('店铺不存在');
    if (!channel) throw new NotFoundException('渠道不存在');

    // 获取类目属性映射配置（如果有）
    let categoryMapping: any = null;
    const firstProduct = products[0];
    if (firstProduct?.platformCategoryId) {
      categoryMapping = await this.prisma.categoryAttributeMapping.findUnique({
        where: {
          platformId_country_categoryId: {
            platformId: shop.platformId,
            country: shop.region || 'US',
            categoryId: firstProduct.platformCategoryId,
          },
        },
      });
    }

    const result: ImportResultDto = {
      total: products.length,
      success: 0,
      failed: 0,
      skipped: 0,
      errors: [],
    };

    for (const product of products) {
      try {
        // 检查是否已存在
        const existing = await this.prisma.listingProduct.findUnique({
          where: { shopId_sku: { shopId, sku: product.sku } },
        });

        // 根据映射配置生成平台属性
        let platformAttributes: Record<string, any> | null = null;
        if (categoryMapping?.mappingRules) {
          const resolveResult = await this.attributeResolver.resolveAttributes(
            categoryMapping.mappingRules,
            product.channelAttributes || {},
            { productSku: product.sku, shopId },
          );
          platformAttributes = resolveResult.attributes;
        }

        if (existing) {
          if (duplicateAction === 'skip') {
            result.skipped++;
            continue;
          }
          // 更新 - 确保必填字段有默认值
          await this.prisma.listingProduct.update({
            where: { id: existing.id },
            data: {
              title: product.title || '',
              description: product.description || '',
              mainImageUrl: product.mainImageUrl || null,
              imageUrls: product.imageUrls || [],
              videoUrls: product.videoUrls || [],
              price: product.price ?? 0,
              stock: product.stock ?? 0,
              currency: product.currency || 'USD',
              channelRawData: product.channelRawData || null,
              channelAttributes: product.channelAttributes || null,
              platformCategoryId: product.platformCategoryId || existing.platformCategoryId,
              platformAttributes: platformAttributes ?? (existing.platformAttributes as any) ?? undefined,
            },
          });
        } else {
          // 创建 - 确保必填字段有默认值
          await this.prisma.listingProduct.create({
            data: {
              shopId,
              channelId,
              sku: product.sku,
              title: product.title || '',
              description: product.description || '',
              mainImageUrl: product.mainImageUrl || null,
              imageUrls: product.imageUrls || [],
              videoUrls: product.videoUrls || [],
              price: product.price ?? 0,
              stock: product.stock ?? 0,
              currency: product.currency || 'USD',
              channelRawData: product.channelRawData || null,
              channelAttributes: product.channelAttributes || null,
              platformCategoryId: product.platformCategoryId || null,
              platformAttributes: platformAttributes ?? undefined,
              listingStatus: 'draft',
            },
          });
        }
        result.success++;
      } catch (error: any) {
        result.failed++;
        result.errors?.push({ sku: product.sku, error: error.message });
      }
    }

    return result;
  }

  /**
   * 获取刊登商品列表
   */
  async getListingProducts(query: ListingQueryDto) {
    const { shopId, channelId, keyword, sku, listingStatus, platformCategoryId } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 20;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (shopId) where.shopId = shopId;
    if (channelId) where.channelId = channelId;
    if (listingStatus) where.listingStatus = listingStatus;
    if (platformCategoryId) where.platformCategoryId = platformCategoryId;
    if (sku) where.sku = { contains: sku, mode: 'insensitive' };
    if (keyword) {
      where.OR = [
        { sku: { contains: keyword, mode: 'insensitive' } },
        { title: { contains: keyword, mode: 'insensitive' } },
      ];
    }

    const [data, total] = await Promise.all([
      this.prisma.listingProduct.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: {
          shop: { select: { id: true, name: true } },
          channel: { select: { id: true, name: true } },
        },
      }),
      this.prisma.listingProduct.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  /**
   * 获取单个商品详情
   */
  async getListingProduct(id: string) {
    const product = await this.prisma.listingProduct.findUnique({
      where: { id },
      include: {
        shop: { select: { id: true, name: true } },
        channel: { select: { id: true, name: true } },
      },
    });
    if (!product) throw new NotFoundException('商品不存在');
    return product;
  }

  /**
   * 更新商品信息
   */
  async updateListingProduct(id: string, dto: UpdateListingDto) {
    const product = await this.prisma.listingProduct.findUnique({ where: { id } });
    if (!product) throw new NotFoundException('商品不存在');

    return this.prisma.listingProduct.update({
      where: { id },
      data: dto,
    });
  }

  /**
   * 删除商品
   */
  async deleteListingProducts(ids: string[]) {
    await this.prisma.listingProduct.deleteMany({
      where: { id: { in: ids } },
    });
    return { success: true, deleted: ids.length };
  }

  /**
   * 验证商品刊登信息
   */
  async validateListing(productIds: string[]): Promise<ValidateListingResultDto> {
    const products = await this.prisma.listingProduct.findMany({
      where: { id: { in: productIds } },
    });

    const errors: ValidateListingResultDto['errors'] = [];

    for (const product of products) {
      const missingFields: string[] = [];
      
      // 基本必填字段
      if (!product.title) missingFields.push('title');
      if (!product.price) missingFields.push('price');
      if (!product.platformCategoryId) missingFields.push('platformCategoryId');
      
      // TODO: 根据平台类目属性验证 platformAttributes

      if (missingFields.length > 0) {
        errors.push({
          productId: product.id,
          sku: product.sku,
          missingFields,
        });
      }
    }

    return {
      valid: errors.length === 0,
      errors,
    };
  }

  /**
   * 提交刊登
   */
  async submitListing(dto: SubmitListingDto) {
    const { shopId, productIds } = dto;
    const startTime = Date.now();

    // 获取店铺信息
    const shop = await this.prisma.shop.findUnique({
      where: { id: shopId },
      include: { platform: true },
    });
    if (!shop) throw new NotFoundException('店铺不存在');

    // 获取商品列表
    const products = await this.prisma.listingProduct.findMany({
      where: { id: { in: productIds } },
    });

    // 创建刊登日志
    const log = await this.listingLogService.create({
      shopId,
      action: 'submit',
      productSku: products.map(p => p.sku).join(','),
      requestData: {
        productIds,
        productCount: products.length,
        skus: products.map(p => p.sku),
      },
    });

    try {
      // 验证
      const validation = await this.validateListing(productIds);
      if (!validation.valid) {
        await this.listingLogService.fail(log.id, {
          errorMessage: '商品信息不完整',
          errorCode: 'VALIDATION_ERROR',
          responseData: validation.errors,
          duration: Date.now() - startTime,
        });
        throw new BadRequestException({
          message: '商品信息不完整',
          errors: validation.errors,
        });
      }

      // 更新日志状态为处理中
      await this.listingLogService.setProcessing(log.id);

      // 创建刊登任务
      const task = await this.prisma.listingTask.create({
        data: {
          shopId,
          status: 'pending',
          totalCount: productIds.length,
          productIds,
        },
      });

      // 更新商品状态
      await this.prisma.listingProduct.updateMany({
        where: { id: { in: productIds } },
        data: { listingStatus: 'submitting' },
      });

      // 调用平台 API 提交刊登
      let feedId: string | null = null;
      let successCount = 0;
      let failCount = 0;
      const errors: string[] = [];
      
      console.log(`[submitListing] Platform: ${shop.platform?.code}, Products: ${products.length}`);
      
      if (shop.platform?.code === 'walmart') {
        // Walmart 平台（传递 region 以支持多区域）
        const adapter = PlatformAdapterFactory.create('walmart', { ...(shop.apiCredentials as any), region: shop.region }) as WalmartAdapter;
        
        // 记录所有提交的数据
        const submittedItems: any[] = [];
        
        // 获取店铺的 Walmart 配置
        const shopCredentials = shop.apiCredentials as any || {};
        const walmartConfig = {
          fulfillmentLagTime: shopCredentials.fulfillmentLagTime || '1',
          fulfillmentMode: shopCredentials.fulfillmentMode || 'SELLER_FULFILLED',
          fulfillmentCenterId: shopCredentials.fulfillmentCenterId || '',
          shippingTemplate: shopCredentials.shippingTemplate || '',
          region: shop.region || 'US',
        };
        
        // 批量提交商品
        for (const product of products) {
          const channelAttrs = (product.channelAttributes as any) || {};
          
          // 重新解析属性（确保使用最新的映射规则和正确的数据格式）
          let platformAttrs: Record<string, any> = {};
          if (product.platformCategoryId && shop.platformId) {
            const categoryMapping = await this.prisma.categoryAttributeMapping.findUnique({
              where: {
                platformId_country_categoryId: {
                  platformId: shop.platformId,
                  country: shop.region || 'US',
                  categoryId: product.platformCategoryId,
                },
              },
            });
            
            if (categoryMapping?.mappingRules) {
              // 调试：输出渠道属性中的图片字段
              console.log(`[submitListing] channelAttrs.mainImageUrl:`, channelAttrs.mainImageUrl);
              console.log(`[submitListing] channelAttrs.imageUrls:`, channelAttrs.imageUrls);
              
              const resolveResult = await this.attributeResolver.resolveAttributes(
                categoryMapping.mappingRules as any,
                channelAttrs,
                { 
                  productSku: product.sku, 
                  shopId,
                  // 传递商品原价，用于价格计算（当 channelAttributes 中没有 price 时使用）
                  productPrice: Number(product.price) || 0,
                },
              );
              platformAttrs = resolveResult.attributes;
              
              // 调试：输出解析后的图片字段
              console.log(`[submitListing] platformAttrs.mainImageUrl:`, platformAttrs.mainImageUrl);
              console.log(`[submitListing] platformAttrs.productSecondaryImageURL:`, platformAttrs.productSecondaryImageURL);
            }
          }
          
          // 将平铺的 platformAttributes 转换为 Walmart V5.0 结构
          // V5.0 结构: { Orderable: {...}, Visible: { [categoryName]: {...} } }
          const itemData = this.convertToWalmartV5Format(platformAttrs, product.platformCategoryId, walmartConfig);
          
          console.log(`[submitListing] Submitting product: ${product.sku}`);
          
          // 为每个商品创建详细日志（只记录实际发送的数据）
          const productLog = await this.listingLogService.create({
            shopId,
            action: 'submit',
            productId: product.id,
            productSku: product.sku,
            requestData: itemData,  // 只记录实际发送给 Walmart 的数据
          });
          
          try {
            const result = await adapter.createItem(itemData);
            
            feedId = result.feedId;
            console.log(`[submitListing] Product ${product.sku} submitted, feedId: ${feedId}`);
            
            // 更新商品日志为成功，响应数据只记录 Walmart 真实反馈
            await this.listingLogService.complete(productLog.id, {
              feedId: result.feedId,
              responseData: result.walmartResponse,  // 只记录 Walmart API 真实响应
              duration: Date.now() - startTime,
            });
            
            // 创建 Feed 记录，submittedData 记录实际提交给 Walmart 的 Feed 数据
            await this.listingFeedService.create({
              shopId,
              feedId: result.feedId,
              feedType: 'MP_ITEM',
              itemCount: 1,
              productIds: [product.id],
              submittedData: result.submittedFeedData,  // 实际提交的 Feed 数据
            });
            console.log(`[submitListing] Feed record created for ${product.sku}`);
            
            // 更新商品状态和 feedId
            await this.prisma.listingProduct.update({
              where: { id: product.id },
              data: { listingStatus: 'pending' },
            });
            
            submittedItems.push({ sku: product.sku, feedId: result.feedId, status: 'success' });
            successCount++;
          } catch (err: any) {
            console.error(`[submitListing] Product ${product.sku} failed:`, err.message);
            
            // 更新商品日志为失败
            await this.listingLogService.fail(productLog.id, {
              errorMessage: err.message,
              errorCode: 'WALMART_API_ERROR',
              responseData: {
                error: err.message,
                submittedData: itemData,
              },
              duration: Date.now() - startTime,
            });
            
            failCount++;
            errors.push(`${product.sku}: ${err.message}`);
            submittedItems.push({ sku: product.sku, status: 'failed', error: err.message });
            
            // 单个商品失败，记录错误但继续处理其他商品
            await this.prisma.listingProduct.update({
              where: { id: product.id },
              data: { 
                listingStatus: 'failed',
                listingError: err.message,
              },
            });
          }
        }
        
        // 更新主日志，包含所有提交的数据汇总
        await this.prisma.listingLog.update({
          where: { id: log.id },
          data: {
            requestData: {
              productIds,
              productCount: products.length,
              skus: products.map(p => p.sku),
              submittedItems,
            },
          },
        });
        
        console.log(`[submitListing] Completed: success=${successCount}, fail=${failCount}`);
        
        // 如果所有商品都失败了，抛出错误
        if (successCount === 0 && failCount > 0) {
          throw new BadRequestException({
            message: `所有商品提交失败`,
            errors: errors,
          });
        }
      } else {
        // 其他平台暂不支持
        throw new BadRequestException(`平台 ${shop.platform?.code || '未知'} 暂不支持自动刊登`);
      }

      // 更新任务状态
      await this.prisma.listingTask.update({
        where: { id: task.id },
        data: { 
          status: successCount > 0 ? 'running' : 'failed',
        },
      });

      // 完成日志
      await this.listingLogService.complete(log.id, {
        responseData: { 
          taskId: task.id, 
          status: successCount > 0 ? 'running' : 'failed', 
          feedId,
          successCount,
          failCount,
          errors: errors.length > 0 ? errors : undefined,
        },
        duration: Date.now() - startTime,
      });

      return {
        taskId: task.id,
        status: successCount > 0 ? 'running' : 'failed',
        totalCount: task.totalCount,
        feedId,
        successCount,
        failCount,
        errors: errors.length > 0 ? errors : undefined,
      };
    } catch (error: any) {
      // 如果不是验证错误（已经记录过），则记录失败日志
      if (error.response?.message !== '商品信息不完整') {
        await this.listingLogService.fail(log.id, {
          errorMessage: error.message || '提交刊登失败',
          errorCode: error.code || 'SUBMIT_ERROR',
          responseData: error.response?.data,
          duration: Date.now() - startTime,
        });
      }
      throw error;
    }
  }

  /**
   * 获取刊登任务
   */
  async getListingTask(taskId: string) {
    const task = await this.prisma.listingTask.findUnique({
      where: { id: taskId },
      include: { shop: { select: { id: true, name: true } } },
    });
    if (!task) throw new NotFoundException('任务不存在');
    return task;
  }

  /**
   * 获取刊登任务列表
   */
  async getListingTasks(query: { shopId?: string; page?: number; pageSize?: number }) {
    const { shopId } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 20;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (shopId) where.shopId = shopId;

    const [data, total] = await Promise.all([
      this.prisma.listingTask.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: { shop: { select: { id: true, name: true } } },
      }),
      this.prisma.listingTask.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  /**
   * 将平铺的属性转换为 Walmart V5.0 格式
   * V5.0 结构: { Orderable: {...}, Visible: { [categoryName]: {...} } }
   * 
   * 参考 woo-walmart-sync 插件的实现:
   * - Orderable 部分: sku, productIdentifiers, price, quantity, ShippingWeight, fulfillmentLagTime 等
   * - Visible 部分: productName, mainImageUrl, productSecondaryImageURL, brand, shortDescription 等
   * 
   * @param platformAttrs 平铺的平台属性
   * @param categoryId 类目ID（用于 Visible 层级）
   * @param shopConfig 店铺配置（备货时间、履行中心等）
   */
  private convertToWalmartV5Format(
    platformAttrs: Record<string, any>,
    categoryId: string | null,
    shopConfig?: {
      fulfillmentLagTime?: string;
      fulfillmentMode?: string;
      fulfillmentCenterId?: string;
      shippingTemplate?: string;
      region?: string;
    },
  ): Record<string, any> {
    // 如果已经是 V5.0 格式（有 Orderable 或 Visible），直接返回
    if (platformAttrs.Orderable || platformAttrs.Visible) {
      return platformAttrs;
    }

    // Orderable 字段列表（参考 woo-walmart-sync）
    const orderableFields = [
      'sku',
      'productIdentifiers',
      'price',
      'quantity',
      'ShippingWeight',
      'shippingWeight',
      'fulfillmentLagTime',
      'stateRestrictions',
      'electronicsIndicator',
      'chemicalAerosolPesticide',
      'batteryTechnologyType',
      'shipsInOriginalPackaging',
      'MustShipAlone',
      'mustShipAlone',
      'IsPreorder',
      'isPreorder',
      'releaseDate',
      'startDate',
      'endDate',
      'fulfillmentCenterID',
      'inventoryAvailabilityDate',
      'ProductIdUpdate',
      'productIdUpdate',
      'SkuUpdate',
      'skuUpdate',
    ];

    const orderable: Record<string, any> = {};
    const visible: Record<string, any> = {};

    // 分离 Orderable 和 Visible 字段
    for (const [key, value] of Object.entries(platformAttrs)) {
      if (value === undefined || value === null || value === '') {
        continue;
      }

      // 检查是否为 Orderable 字段（不区分大小写）
      const isOrderable = orderableFields.some(
        (f) => f.toLowerCase() === key.toLowerCase(),
      );

      if (isOrderable) {
        orderable[key] = value;
      } else {
        visible[key] = value;
      }
    }

    // 应用店铺配置的默认值（只应用店铺设置中明确配置的字段）
    if (shopConfig) {
      // 备货时间 (fulfillmentLagTime) - 只有店铺设置中有值才应用
      if (!orderable.fulfillmentLagTime && shopConfig.fulfillmentLagTime) {
        orderable.fulfillmentLagTime = String(shopConfig.fulfillmentLagTime);
      }

      // 履行中心ID (fulfillmentCenterID) - 只有店铺设置中有值才应用
      if (!orderable.fulfillmentCenterID && shopConfig.fulfillmentCenterId) {
        orderable.fulfillmentCenterID = shopConfig.fulfillmentCenterId;
      }
      
      // 注意：startDate 和 releaseDate 不再自动生成
      // 如果需要这些字段，用户应该在类目属性映射中配置
    }

    // 构建 V5.0 结构
    const result: Record<string, any> = {};

    if (Object.keys(orderable).length > 0) {
      result.Orderable = orderable;
    }

    if (Object.keys(visible).length > 0) {
      // Visible 下需要有类目名称层级
      // 如果有 categoryId，使用它作为 key；否则使用 'Default'
      const categoryKey = categoryId || 'Default';
      result.Visible = {
        [categoryKey]: visible,
      };
    }

    return result;
  }

  /**
   * 刷新 Feed 状态（从 Walmart API 获取最新状态）
   */
  async refreshFeedStatus(feedRecordId: string) {
    // 获取 Feed 记录
    const feedRecord = await this.prisma.listingFeedRecord.findUnique({
      where: { id: feedRecordId },
      include: { shop: { include: { platform: true } } },
    });
    if (!feedRecord) throw new NotFoundException('Feed 记录不存在');

    // 只支持 Walmart 平台
    if (feedRecord.shop.platform?.code !== 'walmart') {
      throw new BadRequestException('只支持 Walmart 平台的 Feed 状态刷新');
    }

    // 创建 Walmart 适配器（传递 region 以支持多区域）
    const adapter = PlatformAdapterFactory.create(
      'walmart',
      { ...(feedRecord.shop.apiCredentials as any), region: feedRecord.shop.region },
    ) as WalmartAdapter;

    try {
      // 从 Walmart API 获取 Feed 状态
      console.log(`[refreshFeedStatus] Fetching feed status for ${feedRecord.feedId}`);
      const feedStatus = await adapter.getFeedStatus(feedRecord.feedId, true, 0, 50);

      // 解析状态
      const status = feedStatus.feedStatus || 'UNKNOWN';
      const itemsReceived = feedStatus.itemsReceived || 0;
      const itemsSucceeded = feedStatus.itemsSucceeded || 0;
      const itemsFailed = feedStatus.itemsFailed || 0;

      // 映射状态
      let mappedStatus: 'RECEIVED' | 'INPROGRESS' | 'PROCESSED' | 'ERROR' = 'RECEIVED';
      if (status === 'PROCESSED') {
        mappedStatus = itemsFailed > 0 && itemsSucceeded === 0 ? 'ERROR' : 'PROCESSED';
      } else if (status === 'INPROGRESS') {
        mappedStatus = 'INPROGRESS';
      } else if (status === 'ERROR') {
        mappedStatus = 'ERROR';
      }

      // 更新 Feed 记录
      const updatedRecord = await this.listingFeedService.updateStatus(
        feedRecord.shopId,
        feedRecord.feedId,
        {
          status: mappedStatus,
          successCount: itemsSucceeded,
          failCount: itemsFailed,
          feedDetail: feedStatus,
          errorMessage: itemsFailed > 0 ? `${itemsFailed} 个商品处理失败` : undefined,
        },
      );

      // 如果有关联的商品，更新商品状态并记录日志
      const productIds = feedRecord.productIds as string[] | null;
      const itemDetails = feedStatus.itemDetails?.itemIngestionStatus || [];
      
      // 为每个商品的处理结果创建日志
      for (const item of itemDetails) {
        const sku = item.sku;
        const ingestionStatus = item.ingestionStatus;
        const errors = item.ingestionErrors?.ingestionError || [];
        
        // 查找对应的商品
        const product = await this.prisma.listingProduct.findFirst({
          where: {
            shopId: feedRecord.shopId,
            sku,
          },
        });

        // 创建 Feed 处理结果日志
        const logData = {
          shopId: feedRecord.shopId,
          action: 'submit' as const,
          productId: product?.id,
          productSku: sku,
          feedId: feedRecord.feedId,
          requestData: { feedId: feedRecord.feedId, sku },
        };
        
        const log = await this.listingLogService.create(logData);
        
        if (ingestionStatus === 'SUCCESS') {
          // 成功日志
          await this.listingLogService.complete(log.id, {
            feedId: feedRecord.feedId,
            responseData: {
              ingestionStatus: 'SUCCESS',
              message: 'Walmart 处理成功',
            },
          });
          
          // 更新商品状态
          if (product) {
            await this.prisma.listingProduct.update({
              where: { id: product.id },
              data: {
                listingStatus: 'listed',
                listingError: null,
                lastListedAt: new Date(),
              },
            });
          }
        } else {
          // 失败日志
          const errorMsg = errors.map((e: any) => e.description || e.code).join('; ');
          await this.listingLogService.fail(log.id, {
            errorMessage: errorMsg || '处理失败',
            errorCode: errors[0]?.code || 'WALMART_ERROR',
            responseData: {
              ingestionStatus,
              errors: errors,
            },
          });
          
          // 更新商品状态
          if (product) {
            await this.prisma.listingProduct.update({
              where: { id: product.id },
              data: {
                listingStatus: 'failed',
                listingError: errorMsg || '处理失败',
              },
            });
          }
        }
      }

      console.log(`[refreshFeedStatus] Feed ${feedRecord.feedId} status: ${mappedStatus}, success: ${itemsSucceeded}, failed: ${itemsFailed}`);

      return {
        success: true,
        feedId: feedRecord.feedId,
        status: mappedStatus,
        itemsReceived,
        itemsSucceeded,
        itemsFailed,
        feedDetail: feedStatus,
      };
    } catch (error: any) {
      console.error(`[refreshFeedStatus] Failed to refresh feed status:`, error.message);
      throw new BadRequestException(`刷新 Feed 状态失败: ${error.message}`);
    }
  }
}
