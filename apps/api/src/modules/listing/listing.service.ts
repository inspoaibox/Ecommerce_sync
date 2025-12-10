import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { ChannelService } from '@/modules/channel/channel.service';
import { UpcService } from '@/modules/upc/upc.service';
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

@Injectable()
export class ListingService {
  constructor(
    private prisma: PrismaService,
    private channelService: ChannelService,
    private upcService: UpcService,
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
          platformAttributes = await this.generatePlatformAttributes(
            categoryMapping.mappingRules,
            product.channelAttributes || {},
            product.channelRawData || {},
            product.sku,
            shopId,
          );
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
   * 根据映射规则生成商品的平台属性
   */
  private async generatePlatformAttributes(
    mappingRules: any,
    channelAttributes: Record<string, any> = {},
    channelRawData: Record<string, any> = {},
    productSku?: string,
    shopId?: string,
  ): Promise<Record<string, any>> {
    const result: Record<string, any> = {};
    const rules = mappingRules?.rules || [];

    for (const rule of rules) {
      const { attributeId, mappingType, value } = rule;
      
      switch (mappingType) {
        case 'default_value':
          if (value !== undefined && value !== '') {
            result[attributeId] = value;
          }
          break;

        case 'channel_data':
          if (value) {
            const extractedValue = this.extractValueFromPath(value, channelAttributes, channelRawData);
            if (extractedValue !== undefined && extractedValue !== '') {
              result[attributeId] = extractedValue;
            }
          }
          break;

        case 'enum_select':
          if (value !== undefined && value !== '') {
            result[attributeId] = value;
          }
          break;

        case 'auto_generate':
          const generated = this.autoGenerateValue(attributeId, channelAttributes, channelRawData);
          if (generated !== undefined && generated !== '') {
            result[attributeId] = generated;
          }
          break;

        case 'upc_pool':
          // 从 UPC 池获取未使用的 UPC
          if (productSku) {
            const upcCode = await this.upcService.autoAssignUpc(productSku, shopId);
            if (upcCode) {
              result[attributeId] = upcCode;
            }
          }
          break;
      }
    }

    return result;
  }

  /**
   * 从路径提取值
   */
  private extractValueFromPath(
    path: string,
    channelAttributes: Record<string, any>,
    channelRawData: Record<string, any>,
  ): any {
    // 直接从 channelAttributes 提取（简化路径）
    if (channelAttributes && path in channelAttributes) {
      return channelAttributes[path];
    }

    // 支持点号分隔的路径
    const parts = path.split('.');
    let source: any = channelAttributes;

    if (parts[0] === 'channelAttributes') {
      source = channelAttributes;
      parts.shift();
    } else if (parts[0] === 'channelRawData') {
      source = channelRawData;
      parts.shift();
    }

    let value = source;
    for (const part of parts) {
      if (value && typeof value === 'object' && part in value) {
        value = value[part];
      } else {
        return undefined;
      }
    }

    return value;
  }

  /**
   * 自动生成属性值
   */
  private autoGenerateValue(
    attributeId: string,
    channelAttributes: Record<string, any>,
    channelRawData: Record<string, any>,
  ): any {
    switch (attributeId.toLowerCase()) {
      case 'brand':
        return channelAttributes.brand || 'Unbranded';
      case 'productname':
        return channelRawData.title || channelAttributes.title;
      case 'shortdescription':
        return channelRawData.description?.substring(0, 500);
      case 'keyfeatures':
        if (channelAttributes.characteristics?.length > 0) {
          return channelAttributes.characteristics.slice(0, 5);
        }
        return undefined;
      default:
        return undefined;
    }
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

    // 验证
    const validation = await this.validateListing(productIds);
    if (!validation.valid) {
      throw new BadRequestException({
        message: '商品信息不完整',
        errors: validation.errors,
      });
    }

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

    // TODO: 添加到队列处理实际刊登

    return {
      taskId: task.id,
      status: task.status,
      totalCount: task.totalCount,
    };
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
}
