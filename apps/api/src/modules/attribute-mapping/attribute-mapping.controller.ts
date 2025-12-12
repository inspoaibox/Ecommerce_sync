import { Controller, Post, Get, Body, Query } from '@nestjs/common';
import { AttributeResolverService } from './attribute-resolver.service';
import {
  MappingRulesConfig,
  ResolveContext,
  ResolveResult,
} from './interfaces/mapping-rule.interface';
import { STANDARD_FIELD_PATHS } from '@/adapters/channels/standard-product.interface';
import { PrismaService } from '@/common/prisma/prisma.service';

/**
 * 预览映射结果请求
 */
interface PreviewRequest {
  mappingRules: MappingRulesConfig;
  channelAttributes: Record<string, any>;
  context?: ResolveContext;
}

/**
 * 标准字段信息
 */
interface StandardFieldInfo {
  key: string;
  path: string;
  category: string;
}

@Controller('attribute-mapping')
export class AttributeMappingController {
  constructor(
    private attributeResolver: AttributeResolverService,
    private prisma: PrismaService,
  ) {}

  /**
   * 预览映射结果
   * POST /attribute-mapping/preview
   */
  @Post('preview')
  async previewMapping(@Body() body: PreviewRequest): Promise<ResolveResult> {
    return this.attributeResolver.resolveAttributes(
      body.mappingRules,
      body.channelAttributes,
      body.context || {},
    );
  }

  /**
   * 获取标准字段列表（新版简化结构）
   * GET /attribute-mapping/standard-fields
   */
  @Get('standard-fields')
  getStandardFields(): StandardFieldInfo[] {
    const fields: StandardFieldInfo[] = [];

    // 基础信息（6个核心字段）
    fields.push(
      { key: 'TITLE', path: STANDARD_FIELD_PATHS.TITLE, category: '基础信息' },
      { key: 'SKU', path: STANDARD_FIELD_PATHS.SKU, category: '基础信息' },
      { key: 'COLOR', path: STANDARD_FIELD_PATHS.COLOR, category: '基础信息' },
      { key: 'MATERIAL', path: STANDARD_FIELD_PATHS.MATERIAL, category: '基础信息' },
      { key: 'DESCRIPTION', path: STANDARD_FIELD_PATHS.DESCRIPTION, category: '基础信息' },
      { key: 'BULLET_POINTS', path: STANDARD_FIELD_PATHS.BULLET_POINTS, category: '基础信息' },
    );

    // 价格信息（5个存储字段）
    fields.push(
      { key: 'PRICE', path: STANDARD_FIELD_PATHS.PRICE, category: '价格信息' },
      { key: 'SALE_PRICE', path: STANDARD_FIELD_PATHS.SALE_PRICE, category: '价格信息' },
      { key: 'SHIPPING_FEE', path: STANDARD_FIELD_PATHS.SHIPPING_FEE, category: '价格信息' },
      { key: 'PLATFORM_PRICE', path: STANDARD_FIELD_PATHS.PLATFORM_PRICE, category: '价格信息' },
      { key: 'CURRENCY', path: STANDARD_FIELD_PATHS.CURRENCY, category: '价格信息' },
    );

    // 库存信息（1个字段）
    fields.push(
      { key: 'STOCK', path: STANDARD_FIELD_PATHS.STOCK, category: '库存信息' },
    );

    // 图片媒体
    fields.push(
      { key: 'MAIN_IMAGE', path: STANDARD_FIELD_PATHS.MAIN_IMAGE, category: '图片媒体' },
      { key: 'IMAGE_URLS', path: STANDARD_FIELD_PATHS.IMAGE_URLS, category: '图片媒体' },
      { key: 'VIDEO_URLS', path: STANDARD_FIELD_PATHS.VIDEO_URLS, category: '图片媒体' },
      { key: 'DOCUMENT_URLS', path: STANDARD_FIELD_PATHS.DOCUMENT_URLS, category: '图片媒体' },
      { key: 'CERTIFICATION_URLS', path: STANDARD_FIELD_PATHS.CERTIFICATION_URLS, category: '图片媒体' },
    );

    // 产品尺寸（4个字段）
    fields.push(
      { key: 'PRODUCT_LENGTH', path: STANDARD_FIELD_PATHS.PRODUCT_LENGTH, category: '产品尺寸' },
      { key: 'PRODUCT_WIDTH', path: STANDARD_FIELD_PATHS.PRODUCT_WIDTH, category: '产品尺寸' },
      { key: 'PRODUCT_HEIGHT', path: STANDARD_FIELD_PATHS.PRODUCT_HEIGHT, category: '产品尺寸' },
      { key: 'PRODUCT_WEIGHT', path: STANDARD_FIELD_PATHS.PRODUCT_WEIGHT, category: '产品尺寸' },
    );

    // 包装尺寸（5个字段）
    fields.push(
      { key: 'PACKAGE_LENGTH', path: STANDARD_FIELD_PATHS.PACKAGE_LENGTH, category: '包装尺寸' },
      { key: 'PACKAGE_WIDTH', path: STANDARD_FIELD_PATHS.PACKAGE_WIDTH, category: '包装尺寸' },
      { key: 'PACKAGE_HEIGHT', path: STANDARD_FIELD_PATHS.PACKAGE_HEIGHT, category: '包装尺寸' },
      { key: 'PACKAGE_WEIGHT', path: STANDARD_FIELD_PATHS.PACKAGE_WEIGHT, category: '包装尺寸' },
      { key: 'PACKAGES', path: STANDARD_FIELD_PATHS.PACKAGES, category: '包装尺寸' },
    );

    // 其他核心字段（3个）
    fields.push(
      { key: 'PLACE_OF_ORIGIN', path: STANDARD_FIELD_PATHS.PLACE_OF_ORIGIN, category: '其他信息' },
      { key: 'PRODUCT_TYPE', path: STANDARD_FIELD_PATHS.PRODUCT_TYPE, category: '其他信息' },
      { key: 'SUPPLIER', path: STANDARD_FIELD_PATHS.SUPPLIER, category: '其他信息' },
    );

    // 渠道属性（扩展字段）
    fields.push(
      { key: 'CUSTOM_ATTRIBUTES', path: STANDARD_FIELD_PATHS.CUSTOM_ATTRIBUTES, category: '渠道属性' },
    );

    // 兼容字段（从 customAttributes 或 channelAttributes 中取值）
    // 这些字段不在 STANDARD_FIELD_PATHS 中，但可以通过 channelAttributes 直接访问
    fields.push(
      { key: 'brand', path: 'brand', category: '兼容字段（渠道属性）' },
      { key: 'mpn', path: 'mpn', category: '兼容字段（渠道属性）' },
      { key: 'upc', path: 'upc', category: '兼容字段（渠道属性）' },
      { key: 'ean', path: 'ean', category: '兼容字段（渠道属性）' },
      { key: 'gtin', path: 'gtin', category: '兼容字段（渠道属性）' },
      { key: 'asin', path: 'asin', category: '兼容字段（渠道属性）' },
      { key: 'model', path: 'model', category: '兼容字段（渠道属性）' },
      { key: 'manufacturer', path: 'manufacturer', category: '兼容字段（渠道属性）' },
      { key: 'categoryCode', path: 'categoryCode', category: '兼容字段（渠道属性）' },
      { key: 'categoryName', path: 'category', category: '兼容字段（渠道属性）' },
      { key: 'categoryPath', path: 'categoryPath', category: '兼容字段（渠道属性）' },
      { key: 'characteristics', path: 'characteristics', category: '兼容字段（渠道属性）' },
      { key: 'weight', path: 'weight', category: '兼容字段（渠道属性）' },
      { key: 'length', path: 'length', category: '兼容字段（渠道属性）' },
      { key: 'width', path: 'width', category: '兼容字段（渠道属性）' },
      { key: 'height', path: 'height', category: '兼容字段（渠道属性）' },
      { key: 'assembledWeight', path: 'assembledWeight', category: '兼容字段（渠道属性）' },
      { key: 'assembledLength', path: 'assembledLength', category: '兼容字段（渠道属性）' },
      { key: 'assembledWidth', path: 'assembledWidth', category: '兼容字段（渠道属性）' },
      { key: 'assembledHeight', path: 'assembledHeight', category: '兼容字段（渠道属性）' },
    );

    return fields;
  }

  /**
   * 获取自动生成规则列表
   * GET /attribute-mapping/auto-generate-rules
   */
  @Get('auto-generate-rules')
  getAutoGenerateRules() {
    return [
      {
        ruleType: 'sku_prefix',
        name: 'SKU前缀拼接',
        description: '在SKU前添加指定前缀',
        hasParam: true,
        paramLabel: '前缀字符串',
        example: 'WM-ABC123',
      },
      {
        ruleType: 'sku_suffix',
        name: 'SKU后缀拼接',
        description: '在SKU后添加指定后缀',
        hasParam: true,
        paramLabel: '后缀字符串',
        example: 'ABC123-US',
      },
      {
        ruleType: 'brand_title',
        name: '品牌+标题组合',
        description: '将品牌和标题组合成一个字符串',
        hasParam: false,
        example: 'ACME Product Name',
      },
      {
        ruleType: 'first_characteristic',
        name: '取第一个特点',
        description: '从商品特点列表中取第一个',
        hasParam: false,
        example: 'characteristics[0]',
      },
      {
        ruleType: 'first_bullet_point',
        name: '取第一条五点描述',
        description: '从五点描述列表中取第一条',
        hasParam: false,
        example: 'bulletPoints[0]',
      },
      {
        ruleType: 'current_date',
        name: '当前日期',
        description: '生成当前日期',
        hasParam: true,
        paramLabel: '日期格式',
        example: '2024-01-15',
      },
      {
        ruleType: 'uuid',
        name: '生成UUID',
        description: '生成唯一标识符',
        hasParam: false,
        example: '550e8400-e29b-41d4-a716-446655440000',
      },
      {
        ruleType: 'color_extract',
        name: '智能提取颜色',
        description: '优先从商品颜色字段取值，如果没有则从标题和描述中智能提取',
        hasParam: false,
        example: 'Black / White / Natural',
      },
      {
        ruleType: 'material_extract',
        name: '智能提取材质',
        description: '优先从商品材质字段取值，如果没有则从标题和描述中智能提取',
        hasParam: false,
        example: 'Wood / Metal / Fabric',
      },
      {
        ruleType: 'field_with_fallback',
        name: '多字段回退取值',
        description: '按顺序尝试多个字段，返回第一个非空值',
        hasParam: true,
        paramLabel: '字段列表(逗号分隔)',
        example: 'color,colorFamily,attributes.color',
      },
      {
        ruleType: 'location_extract',
        name: '智能提取使用场景',
        description: '从标题/描述判断 Indoor 或 Outdoor，默认 Indoor',
        hasParam: true,
        paramLabel: '可选枚举值(逗号分隔)',
        example: 'Indoor / Outdoor',
      },
      {
        ruleType: 'piece_count_extract',
        name: '智能提取产品数量',
        description: '从标题/描述中提取产品件数，剔除尺寸等无关数字，默认返回1',
        hasParam: true,
        paramLabel: '默认值',
        example: '1 / 5 / 105',
      },
    ];
  }

  /**
   * 测试 SKU 的属性映射
   * GET /attribute-mapping/test?sku=xxx&categoryId=xxx&country=US
   */
  @Get('test')
  async testMapping(
    @Query('sku') sku: string,
    @Query('categoryId') categoryId?: string,
    @Query('country') country: string = 'US',
  ) {
    if (!sku) {
      return { error: 'SKU is required' };
    }

    // 1. 获取商品数据
    const product = await this.prisma.listingProduct.findFirst({
      where: { sku },
      include: {
        shop: {
          include: { platform: true },
        },
      },
    });

    if (!product) {
      return { error: `Product not found: ${sku}` };
    }

    const channelAttrs = product.channelAttributes as Record<string, any>;
    if (!channelAttrs) {
      return { error: 'Product has no channelAttributes' };
    }

    // 2. 获取类目映射配置
    const platform = await this.prisma.platform.findFirst({
      where: { code: 'walmart' },
    });

    if (!platform) {
      return { error: 'Walmart platform not found' };
    }

    const targetCategoryId = categoryId || product.platformCategoryId || 'Living Room Furniture Sets';
    
    const categoryMapping = await this.prisma.categoryAttributeMapping.findFirst({
      where: {
        platformId: platform.id,
        country,
        categoryId: targetCategoryId,
      },
    });

    if (!categoryMapping) {
      // 列出可用的类目
      const availableCategories = await this.prisma.categoryAttributeMapping.findMany({
        where: { platformId: platform.id, country },
        select: { categoryId: true },
      });
      return {
        error: `Category mapping not found: ${targetCategoryId}`,
        availableCategories: availableCategories.map(c => c.categoryId),
      };
    }

    const mappingRules = categoryMapping.mappingRules as unknown as MappingRulesConfig;

    // 3. 调用属性解析服务
    const result = await this.attributeResolver.resolveAttributes(
      mappingRules,
      channelAttrs,
      {},
    );

    // 4. 返回详细结果
    return {
      product: {
        id: product.id,
        sku: product.sku,
        title: product.title,
        platformCategoryId: product.platformCategoryId,
      },
      categoryMapping: {
        categoryId: categoryMapping.categoryId,
        country: categoryMapping.country,
        rulesCount: mappingRules?.rules?.length || 0,
      },
      resolveResult: result,
      channelAttributes: channelAttrs,
      mappingRules: mappingRules?.rules?.map((r: any) => ({
        attributeId: r.attributeId,
        attributeName: r.attributeName,
        mappingType: r.mappingType,
        value: r.value,
      })),
    };
  }
}
