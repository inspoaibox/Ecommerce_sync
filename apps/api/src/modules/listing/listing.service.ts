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
import * as path from 'path';
import * as fs from 'fs';

/**
 * CA 市场 spec 文件解析结果缓存
 * 包含：多语言字段列表、数组多语言字段列表、特殊格式字段信息
 */
interface CASpecInfo {
  // Orderable 层级字段类型
  multiLangFields: Set<string>;      // 需要 { en: "..." } 格式的字段（对象类型）
  arrayMultiLangFields: Set<string>; // 数组类型，每个元素需要 { en: "..." } 格式
  weightFields: Set<string>;         // 需要 { unit: "lb", measure: number } 格式
  arrayFields: Set<string>;          // 普通字符串数组字段（如 productSecondaryImageURL）
  enumArrayFields: Set<string>;      // 枚举数组字段（如 countryOfOriginAssembly）
  validOrderableFields: Set<string>; // Orderable 层级的所有有效字段
  // Visible 层级字段类型（按类目）
  visibleMultiLangFields: Map<string, Set<string>>;      // 类目 -> 多语言对象字段
  visibleArrayMultiLangFields: Map<string, Set<string>>; // 类目 -> 数组多语言字段
  visibleMeasureFields: Map<string, Set<string>>;        // 类目 -> 度量对象字段
  visibleEnumArrayFields: Map<string, Set<string>>;      // 类目 -> 枚举数组字段
}

let caSpecCache: CASpecInfo | null = null;

/**
 * 从 CA spec 文件动态解析字段类型信息
 * 根据 JSON Schema 结构判断哪些字段需要特殊格式转换
 */
const parseCASpecFields = (): CASpecInfo => {
  if (caSpecCache) return caSpecCache;

  const multiLangFields = new Set<string>();
  const arrayMultiLangFields = new Set<string>();
  const weightFields = new Set<string>();
  const arrayFields = new Set<string>();
  const enumArrayFields = new Set<string>();
  const visibleMultiLangFields = new Map<string, Set<string>>();
  const visibleArrayMultiLangFields = new Map<string, Set<string>>();
  const visibleMeasureFields = new Map<string, Set<string>>();
  const visibleEnumArrayFields = new Map<string, Set<string>>();

  try {
    // 尝试多个可能的路径（包括编译后的 dist 目录）
    const possiblePaths = [
      // 编译后的路径（从 dist/src/modules/listing/ 到 dist/src/adapters/platforms/specs/）
      path.join(__dirname, '../../adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
      // 源码路径
      path.join(process.cwd(), 'src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
      path.join(process.cwd(), 'apps/api/src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
      // dist 目录的绝对路径
      path.join(process.cwd(), 'dist/src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
      path.join(process.cwd(), 'apps/api/dist/src/adapters/platforms/specs/CA_MP_ITEM_INTL_SPEC.json'),
      // API-doc 目录
      path.join(process.cwd(), 'API-doc/CA_MP_ITEM_INTL_SPEC.json'),
    ];

    let spec: any = null;
    for (const specPath of possiblePaths) {
      try {
        if (fs.existsSync(specPath)) {
          spec = JSON.parse(fs.readFileSync(specPath, 'utf-8'));
          console.log(`[ListingService] CA spec loaded from: ${specPath}`);
          break;
        }
      } catch (e) {
        // 继续尝试下一个路径
      }
    }

    if (!spec) {
      console.warn('[ListingService] CA spec file not found, using fallback field list');
      // 降级使用硬编码列表
      return getFallbackCASpecInfo();
    }

    // 解析 Orderable 层级的字段定义
    const orderableProps = spec?.properties?.MPItem?.items?.properties?.Orderable?.properties || {};
    
    // 收集 Orderable 层级的所有有效字段
    const validOrderableFields = new Set<string>(Object.keys(orderableProps));
    
    for (const [fieldName, fieldDef] of Object.entries(orderableProps) as [string, any][]) {
      // 检查是否为多语言对象字段：type: "object" 且有 en 属性
      if (fieldDef.type === 'object' && fieldDef.properties?.en) {
        multiLangFields.add(fieldName);
      }
      // 检查是否为数组多语言字段：type: "array" 且 items 是多语言对象
      else if (fieldDef.type === 'array' && fieldDef.items?.type === 'object' && fieldDef.items?.properties?.en) {
        arrayMultiLangFields.add(fieldName);
      }
      // 检查是否为重量/度量字段：type: "object" 且有 unit 和 measure 属性
      else if (fieldDef.type === 'object' && fieldDef.properties?.unit && fieldDef.properties?.measure) {
        weightFields.add(fieldName);
      }
      // 检查是否为枚举数组字段：type: "array" 且 items 有 enum
      else if (fieldDef.type === 'array' && fieldDef.items?.enum) {
        enumArrayFields.add(fieldName);
      }
      // 检查是否为普通字符串数组字段
      else if (fieldDef.type === 'array' && fieldDef.items?.type === 'string') {
        arrayFields.add(fieldName);
      }
    }

    // 解析 Visible 层级下各类目的字段类型
    const visibleProps = spec?.properties?.MPItem?.items?.properties?.Visible?.properties || {};
    for (const [categoryName, categoryDef] of Object.entries(visibleProps) as [string, any][]) {
      const categoryProps = categoryDef?.properties || {};
      const catMultiLang = new Set<string>();
      const catArrayMultiLang = new Set<string>();
      const catMeasure = new Set<string>();
      const catEnumArray = new Set<string>();
      
      for (const [fieldName, fieldDef] of Object.entries(categoryProps) as [string, any][]) {
        // 多语言对象字段: { en: "...", fr: "..." }
        if (fieldDef.type === 'object' && fieldDef.properties?.en) {
          catMultiLang.add(fieldName);
        }
        // 数组多语言字段: [{ en: "...", fr: "..." }]
        else if (fieldDef.type === 'array' && fieldDef.items?.type === 'object' && fieldDef.items?.properties?.en) {
          catArrayMultiLang.add(fieldName);
        }
        // 度量对象字段: { unit: "...", measure: number }
        else if (fieldDef.type === 'object' && fieldDef.properties?.unit && fieldDef.properties?.measure) {
          catMeasure.add(fieldName);
        }
        // 枚举数组字段: ["value1", "value2"]
        else if (fieldDef.type === 'array' && fieldDef.items?.enum) {
          catEnumArray.add(fieldName);
        }
      }
      
      if (catMultiLang.size > 0) {
        visibleMultiLangFields.set(categoryName, catMultiLang);
      }
      if (catArrayMultiLang.size > 0) {
        visibleArrayMultiLangFields.set(categoryName, catArrayMultiLang);
      }
      if (catMeasure.size > 0) {
        visibleMeasureFields.set(categoryName, catMeasure);
      }
      if (catEnumArray.size > 0) {
        visibleEnumArrayFields.set(categoryName, catEnumArray);
      }
    }

    console.log(`[ListingService] CA spec parsed: ${multiLangFields.size} multi-lang fields, ${arrayMultiLangFields.size} array multi-lang fields, ${weightFields.size} weight fields`);
    console.log(`[ListingService] Multi-lang fields: ${Array.from(multiLangFields).join(', ')}`);
    console.log(`[ListingService] Array multi-lang fields: ${Array.from(arrayMultiLangFields).join(', ')}`);
    console.log(`[ListingService] Valid Orderable fields: ${validOrderableFields.size} fields`);
    console.log(`[ListingService] Visible categories with multi-lang fields: ${visibleMultiLangFields.size}`);

    caSpecCache = { 
      multiLangFields, arrayMultiLangFields, weightFields, arrayFields, enumArrayFields,
      validOrderableFields, visibleMultiLangFields, visibleArrayMultiLangFields,
      visibleMeasureFields, visibleEnumArrayFields,
    };
    return caSpecCache;
  } catch (error: any) {
    console.error('[ListingService] Failed to parse CA spec:', error.message);
    return getFallbackCASpecInfo();
  }
};

/**
 * 降级方案：硬编码的字段列表
 */
const getFallbackCASpecInfo = (): CASpecInfo => {
  // Orderable 层级的核心字段（从 CA spec required 字段 + 常用字段）
  const validOrderableFields = new Set([
    'sku', 'productIdentifiers', 'productName', 'brand', 'shortDescription',
    'price', 'ShippingWeight', 'countryOfOriginAssembly', 'mainImageUrl',
    'productTaxCode', 'keyFeatures', 'features', 'manufacturer', 'warrantyText',
    'keywords', 'electronicsIndicator', 'isChemical', 'isPesticide', 'isAerosol',
    'batteryTechnologyType', 'isTemperatureSensitive', 'shipsInOriginalPackaging',
    'MustShipAlone', 'startDate', 'endDate', 'productSecondaryImageURL',
    'manufacturerPartNumber', 'smallPartsWarnings', 'inflexKitComponent',
    'SkuUpdate', 'fulfillmentLagTime',
  ]);
  
  // Visible 层级常见的多语言字段（Furniture 类目）
  const furnitureMultiLang = new Set([
    'finish', 'bedStyle', 'configuration', 'bedSize', 'pattern', 
    'homeDecorStyle', 'topMaterial', 'baseMaterial', 'topFinish',
    'collection', 'theme', 'shape', 'mountType', 'fabricCareInstructions',
  ]);
  const furnitureArrayMultiLang = new Set([
    'color', 'material', 'recommendedUses', 'recommendedRooms', 
    'recommendedLocations', 'fillMaterial', 'frameMaterial',
  ]);
  
  const visibleMultiLangFields = new Map<string, Set<string>>();
  const visibleArrayMultiLangFields = new Map<string, Set<string>>();
  visibleMultiLangFields.set('Furniture', furnitureMultiLang);
  visibleArrayMultiLangFields.set('Furniture', furnitureArrayMultiLang);
  
  // Visible 层级度量字段（Furniture 类目）
  const furnitureMeasure = new Set([
    'diameter', 'mattressThickness', 'seatHeight', 'seatBackHeight',
    'tableHeight', 'slatWidth', 'assembledProductLength', 'assembledProductWidth',
    'assembledProductHeight', 'assembledProductWeight',
  ]);
  
  // Visible 层级枚举数组字段（Furniture 类目）
  const furnitureEnumArray = new Set([
    'colorCategory', 'ageGroup', 'variantAttributeNames',
  ]);
  
  const visibleMeasureFields = new Map<string, Set<string>>();
  const visibleEnumArrayFields = new Map<string, Set<string>>();
  visibleMeasureFields.set('Furniture', furnitureMeasure);
  visibleEnumArrayFields.set('Furniture', furnitureEnumArray);
  
  return {
    multiLangFields: new Set([
      'productName', 'brand', 'shortDescription', 'manufacturer',
      'warrantyText', 'keywords',
    ]),
    arrayMultiLangFields: new Set(['keyFeatures', 'features']),
    weightFields: new Set(['ShippingWeight']),
    arrayFields: new Set(['productSecondaryImageURL']),
    enumArrayFields: new Set(['countryOfOriginAssembly', 'smallPartsWarnings']),
    validOrderableFields,
    visibleMultiLangFields,
    visibleArrayMultiLangFields,
    visibleMeasureFields,
    visibleEnumArrayFields,
  };
};

// 获取多语言字段列表（兼容旧代码）
const getMultiLangFieldsFromSpec = (): Set<string> => {
  const specInfo = parseCASpecFields();
  // 合并普通多语言字段和数组多语言字段
  return new Set([...specInfo.multiLangFields, ...specInfo.arrayMultiLangFields]);
};

/**
 * 转换 CA 市场特殊字段格式（基于 spec 动态解析）
 * - 重量字段: number -> { unit: "lb", measure: number }
 * - 数组字段: string -> [string]
 * - 删除无效字段
 */
const convertCASpecialFields = (attrs: Record<string, any>): Record<string, any> => {
  const result = { ...attrs };
  const specInfo = parseCASpecFields();
  
  // 转换重量字段
  for (const weightField of specInfo.weightFields) {
    const lowerField = weightField.charAt(0).toLowerCase() + weightField.slice(1);
    
    // 处理大写开头的字段名
    if (result[weightField] !== undefined && typeof result[weightField] === 'number') {
      result[weightField] = { unit: 'lb', measure: result[weightField] };
    }
    // 处理小写开头的字段名（如 shippingWeight -> ShippingWeight）
    if (result[lowerField] !== undefined && typeof result[lowerField] === 'number') {
      result[weightField] = { unit: 'lb', measure: result[lowerField] };
      delete result[lowerField];
    }
  }
  
  // 转换普通数组字段（string -> [string]）
  for (const arrayField of specInfo.arrayFields) {
    if (result[arrayField] !== undefined && typeof result[arrayField] === 'string') {
      result[arrayField] = [result[arrayField]];
    }
  }
  
  // 转换枚举数组字段（string -> [string]）
  for (const enumArrayField of specInfo.enumArrayFields) {
    if (result[enumArrayField] !== undefined && !Array.isArray(result[enumArrayField])) {
      result[enumArrayField] = [result[enumArrayField]];
    }
  }
  
  // 删除 CA 市场不支持的字段
  delete result.countryOfOriginTextiles;
  
  return result;
};

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
          
          // 查询类目名称（CA 市场 Visible 层级需要使用类目名称而非 categoryId）
          let categoryName: string | null = null;
          if (product.platformCategoryId && shop.platformId) {
            const platformCategory = await this.prisma.platformCategory.findFirst({
              where: {
                platformId: shop.platformId,
                country: shop.region || 'US',
                categoryId: product.platformCategoryId,
              },
              select: { name: true },
            });
            categoryName = platformCategory?.name || null;
          }
          
          // 将平铺的 platformAttributes 转换为 Walmart V5.0 结构
          // V5.0 结构: { Orderable: {...}, Visible: { [categoryName]: {...} } }
          const itemData = this.convertToWalmartV5Format(platformAttrs, product.platformCategoryId, walmartConfig, categoryName);
          
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
            // CA 市场需要传递 subCategory（类目ID）
            const result = await adapter.createItem(itemData, product.platformCategoryId || undefined);
            
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
   * 将平铺的属性转换为 Walmart Feed 格式
   * 
   * US 市场 (V5.0 结构):
   * - Orderable: sku, productIdentifiers, price, quantity, ShippingWeight 等
   * - Visible: { [categoryName]: { productName, mainImageUrl, brand, ... } }
   * 
   * CA 市场 (V3.16 INTL 结构):
   * - Orderable: 所有字段都在这里，包括 productName, brand 等
   * - 多语言字段需要 { en: "...", fr: "..." } 格式（从 spec 动态解析）
   * - Visible: { [categoryName]: {} } (空对象)
   * 
   * @param platformAttrs 平铺的平台属性
   * @param categoryId 类目ID（用于 Feed Header 的 subCategory）
   * @param shopConfig 店铺配置（备货时间、履行中心、区域等）
   * @param categoryName 类目名称（用于 Visible 层级的 key，CA 市场必须使用类目名称）
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
    categoryName?: string | null,
  ): Record<string, any> {
    // 如果已经是 V5.0 格式（有 Orderable 或 Visible），直接返回
    if (platformAttrs.Orderable || platformAttrs.Visible) {
      return platformAttrs;
    }

    const region = shopConfig?.region || 'US';
    const isInternational = region !== 'US';  // CA, MX, CL 等国际市场

    console.log(`[convertToWalmartV5Format] region=${region}, isInternational=${isInternational}`);
    console.log(`[convertToWalmartV5Format] Input fields: ${Object.keys(platformAttrs).join(', ')}`);

    // CA/国际市场：从 spec 动态解析字段类型信息
    const specInfo = parseCASpecFields();
    
    if (isInternational) {
      console.log(`[convertToWalmartV5Format] Multi-lang fields from spec: ${Array.from(specInfo.multiLangFields).join(', ')}`);
      console.log(`[convertToWalmartV5Format] Array multi-lang fields from spec: ${Array.from(specInfo.arrayMultiLangFields).join(', ')}`);
    }

    // US 市场的 Orderable 字段列表
    const usOrderableFields = [
      'sku',
      'productIdentifiers',
      'price',
      'msrp',
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

      let processedValue = value;

      if (isInternational) {
        // CA/国际市场：根据 spec 区分 Orderable 和 Visible 字段
        // Orderable 层级有 additionalProperties: false，只能包含 spec 定义的字段
        
        // 过滤空数组（如 keywords: []）
        if (Array.isArray(value) && value.length === 0) {
          continue;
        }
        
        // 检查是否为多语言对象字段（如 productName, brand, keywords）
        if (specInfo.multiLangFields.has(key)) {
          processedValue = this.convertToMultiLangFormat(key, value, false);
          console.log(`[convertToWalmartV5Format] Converted multi-lang field: ${key}`);
        }
        // 检查是否为数组多语言字段（如 keyFeatures, features）
        else if (specInfo.arrayMultiLangFields.has(key)) {
          processedValue = this.convertToMultiLangFormat(key, value, true);
          console.log(`[convertToWalmartV5Format] Converted array multi-lang field: ${key}`);
        }
        
        // 根据 spec 判断字段应该放在 Orderable 还是 Visible
        if (specInfo.validOrderableFields.has(key)) {
          orderable[key] = processedValue;
        } else {
          // 不在 Orderable 层级的字段放到 Visible
          // 需要根据类目的 Visible 字段定义进行格式转换
          visible[key] = processedValue;
        }
      } else {
        // US 市场：检查是否为 Orderable 字段（不区分大小写）
        const isOrderable = usOrderableFields.some(
          (f) => f.toLowerCase() === key.toLowerCase(),
        );

        if (isOrderable) {
          orderable[key] = processedValue;
        } else {
          visible[key] = processedValue;
        }
      }
    }

    // 国际市场：转换特殊字段格式（重量、数组等）
    if (isInternational && Object.keys(orderable).length > 0) {
      const convertedOrderable = convertCASpecialFields(orderable);
      // 使用 Object.keys 遍历并更新，避免覆盖已转换的字段
      for (const [k, v] of Object.entries(convertedOrderable)) {
        orderable[k] = v;
      }
    }

    // 应用店铺配置的默认值（仅 US 市场）
    // CA 市场不支持 fulfillmentLagTime 和 fulfillmentCenterID 字段
    if (shopConfig && !isInternational) {
      if (!orderable.fulfillmentLagTime && shopConfig.fulfillmentLagTime) {
        orderable.fulfillmentLagTime = String(shopConfig.fulfillmentLagTime);
      }
      if (!orderable.fulfillmentCenterID && shopConfig.fulfillmentCenterId) {
        orderable.fulfillmentCenterID = shopConfig.fulfillmentCenterId;
      }
    }

    // 构建最终结构
    const result: Record<string, any> = {};

    if (Object.keys(orderable).length > 0) {
      result.Orderable = orderable;
    }

    // Visible 层级处理
    // CA/国际市场：Visible 的 key 必须是类目名称（如 "Furniture"），而非 categoryId（如 "furniture_other"）
    // US 市场：Visible 的 key 使用 categoryId（Product Type Name）
    const categoryKey = isInternational 
      ? (categoryName || categoryId || 'Default')  // CA 市场优先使用类目名称
      : (categoryId || 'Default');  // US 市场使用 categoryId
    
    if (isInternational) {
      // 国际市场：Visible 下的类目对象包含不在 Orderable 层级的字段
      // 如 seatingCapacity, color, finish 等类目特定字段
      // 需要根据类目的 Visible 字段定义进行格式转换
      const convertedVisible: Record<string, any> = {};
      const catMultiLang = specInfo.visibleMultiLangFields.get(categoryKey) || new Set();
      const catArrayMultiLang = specInfo.visibleArrayMultiLangFields.get(categoryKey) || new Set();
      const catMeasure = specInfo.visibleMeasureFields.get(categoryKey) || new Set();
      const catEnumArray = specInfo.visibleEnumArrayFields.get(categoryKey) || new Set();
      
      for (const [key, value] of Object.entries(visible)) {
        if (catMultiLang.has(key)) {
          // 多语言对象字段 { en: "..." }
          convertedVisible[key] = this.convertToMultiLangFormat(key, value, false);
          console.log(`[convertToWalmartV5Format] Visible field ${key} converted to multi-lang object`);
        } else if (catArrayMultiLang.has(key)) {
          // 数组多语言字段 [{ en: "..." }]
          convertedVisible[key] = this.convertToMultiLangFormat(key, value, true);
          console.log(`[convertToWalmartV5Format] Visible field ${key} converted to array multi-lang`);
        } else if (catMeasure.has(key)) {
          // 度量对象字段 { unit: "...", measure: number }
          convertedVisible[key] = this.convertToMeasureFormat(key, value);
          console.log(`[convertToWalmartV5Format] Visible field ${key} converted to measure object`);
        } else if (catEnumArray.has(key)) {
          // 枚举数组字段 ["value1", "value2"]
          convertedVisible[key] = Array.isArray(value) ? value : [value];
          console.log(`[convertToWalmartV5Format] Visible field ${key} converted to enum array`);
        } else {
          convertedVisible[key] = value;
        }
      }
      
      result.Visible = { [categoryKey]: convertedVisible };
      if (Object.keys(convertedVisible).length > 0) {
        console.log(`[convertToWalmartV5Format] CA Visible fields: ${Object.keys(convertedVisible).join(', ')}`);
      }
    } else if (Object.keys(visible).length > 0) {
      // US 市场：Visible 下包含非 Orderable 字段
      result.Visible = { [categoryKey]: visible };
    }

    return result;
  }

  /**
   * 将字段值转换为多语言格式
   * 用于 CA 等国际市场
   * 
   * @param fieldName 字段名
   * @param value 原始值（字符串或数组）
   * @param isArrayType 是否为数组类型字段（如 color, material 需要 [{ en: "..." }] 格式）
   */
  private convertToMultiLangFormat(fieldName: string, value: any, isArrayType: boolean = false): any {
    // 如果已经是多语言格式，直接返回
    if (value && typeof value === 'object' && !Array.isArray(value) && ('en' in value || 'fr' in value)) {
      // 如果需要数组格式但当前是对象，包装成数组
      return isArrayType ? [value] : value;
    }

    // 数组类型字段（如 keyFeatures, features, color, material）
    if (Array.isArray(value)) {
      // 如果不需要数组格式，但值是数组，取第一个元素转换为对象
      if (!isArrayType) {
        const firstItem = value[0];
        if (firstItem && typeof firstItem === 'object' && ('en' in firstItem || 'fr' in firstItem)) {
          return firstItem;
        }
        if (typeof firstItem === 'string') {
          return { en: firstItem };
        }
        return firstItem !== undefined ? { en: String(firstItem) } : value;
      }
      
      // 需要数组格式，检查数组元素是否已经是多语言格式
      if (value.length > 0 && typeof value[0] === 'object' && ('en' in value[0] || 'fr' in value[0])) {
        return value;
      }
      return value.map(item => {
        if (typeof item === 'string') {
          return { en: item };
        }
        if (item && typeof item === 'object' && ('en' in item || 'fr' in item)) {
          return item;
        }
        return { en: String(item) };
      });
    }

    // 字符串类型字段
    if (typeof value === 'string') {
      const multiLangObj = { en: value };
      // 如果需要数组格式，包装成数组
      return isArrayType ? [multiLangObj] : multiLangObj;
    }

    // 其他类型，如果需要数组格式，包装成数组
    if (isArrayType && value !== undefined && value !== null) {
      return [{ en: String(value) }];
    }

    return value;
  }

  /**
   * 将字段值转换为度量格式 { unit: "...", measure: number }
   * 用于 CA 等国际市场的尺寸/重量字段
   */
  private convertToMeasureFormat(fieldName: string, value: any): any {
    // 如果已经是度量格式，直接返回
    if (value && typeof value === 'object' && 'unit' in value && 'measure' in value) {
      return value;
    }

    // 数字类型
    if (typeof value === 'number') {
      // 根据字段名确定单位
      const unit = fieldName.toLowerCase().includes('weight') ? 'lb' : 'in';
      return { unit, measure: value };
    }

    // 字符串类型（尝试解析为数字）
    if (typeof value === 'string') {
      const num = parseFloat(value);
      if (!isNaN(num)) {
        const unit = fieldName.toLowerCase().includes('weight') ? 'lb' : 'in';
        return { unit, measure: num };
      }
    }

    return value;
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
