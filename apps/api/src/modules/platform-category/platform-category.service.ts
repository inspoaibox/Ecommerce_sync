import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { PlatformAdapterFactory } from '@/adapters/platforms';
import { AttributeResolverService } from '@/modules/attribute-mapping/attribute-resolver.service';
import { ResolveContext } from '@/modules/attribute-mapping/interfaces/mapping-rule.interface';
import { getDefaultMappingRules } from './default-mapping-rules';

export interface CategoryNode {
  id: string;
  categoryId: string;
  name: string;
  categoryPath: string;
  level: number;
  isLeaf: boolean;
  country?: string;
  children?: CategoryNode[];
}

export interface SyncResult {
  total: number;
  created: number;
  updated: number;
  country: string;
}

@Injectable()
export class PlatformCategoryService {
  constructor(
    private prisma: PrismaService,
    private attributeResolver: AttributeResolverService,
  ) {}

  /**
   * 同步平台类目
   * @param platformId 平台ID
   * @param country 国家代码（可选，默认从店铺获取）
   * @param shopId 指定店铺ID（可选，用于获取API凭证）
   */
  async syncCategories(platformId: string, country?: string, shopId?: string): Promise<SyncResult> {
    const platform = await this.prisma.platform.findUnique({
      where: { id: platformId },
    });
    if (!platform) {
      throw new NotFoundException('平台不存在');
    }

    // 获取店铺（用于API凭证）
    let shop;
    if (shopId) {
      shop = await this.prisma.shop.findUnique({ where: { id: shopId } });
      if (!shop || shop.platformId !== platformId) {
        throw new BadRequestException('店铺不存在或不属于该平台');
      }
    } else {
      // 如果指定了国家，优先找该国家的店铺
      const whereClause: any = { platformId, status: 'active' };
      if (country) {
        whereClause.region = country;
      }
      const shops = await this.prisma.shop.findMany({
        where: whereClause,
        take: 1,
      });
      if (shops.length === 0) {
        throw new BadRequestException('该平台没有可用的店铺，无法同步类目');
      }
      shop = shops[0];
    }

    // 确定国家代码
    const targetCountry = country || shop.region || 'US';

    const adapter = PlatformAdapterFactory.create(
      platform.code,
      shop.apiCredentials as Record<string, any>,
    );
    if (!('getCategories' in adapter)) {
      throw new BadRequestException('该平台不支持类目同步');
    }

    const categories = await (adapter as any).getCategories();
    
    let created = 0;
    let updated = 0;

    for (const cat of categories) {
      const existing = await this.prisma.platformCategory.findUnique({
        where: { 
          platformId_country_categoryId: { 
            platformId, 
            country: targetCountry, 
            categoryId: cat.categoryId 
          } 
        },
      });

      // 构建类目数据（包含 Walmart 特有字段）
      const categoryData = {
        name: cat.name,
        categoryPath: cat.categoryPath,
        parentId: cat.parentId,
        level: cat.level,
        isLeaf: cat.isLeaf,
        // Walmart 特有字段
        productTypeGroupId: cat.productTypeGroupId || null,
        productTypeGroupName: cat.productTypeGroupName || null,
        productTypeId: cat.productTypeId || null,
        productTypeName: cat.productTypeName || null,
      };

      if (existing) {
        await this.prisma.platformCategory.update({
          where: { id: existing.id },
          data: categoryData,
        });
        updated++;
      } else {
        await this.prisma.platformCategory.create({
          data: {
            platformId,
            country: targetCountry,
            categoryId: cat.categoryId,
            ...categoryData,
            parentId: cat.parentId,
            level: cat.level,
            isLeaf: cat.isLeaf,
          },
        });
        created++;
      }
    }

    return { total: categories.length, created, updated, country: targetCountry };
  }

  /**
   * 获取类目树
   * @param platformId 平台ID
   * @param country 国家代码（默认 US）
   * @param parentId 父类目ID（可选）
   */
  async getCategoryTree(platformId: string, country: string = 'US', parentId?: string): Promise<CategoryNode[]> {
    const where: any = { platformId, country };
    if (parentId) {
      where.parentId = parentId;
    } else {
      where.parentId = null;
    }

    const categories = await this.prisma.platformCategory.findMany({
      where,
      orderBy: { name: 'asc' },
    });

    const nodes: CategoryNode[] = [];
    for (const cat of categories) {
      const node: CategoryNode = {
        id: cat.id,
        categoryId: cat.categoryId,
        name: cat.name,
        categoryPath: cat.categoryPath,
        level: cat.level,
        isLeaf: cat.isLeaf,
        country: cat.country,
      };

      if (!cat.isLeaf) {
        node.children = await this.getCategoryTree(platformId, country, cat.categoryId);
      }

      nodes.push(node);
    }

    return nodes;
  }

  /**
   * 搜索类目
   * @param platformId 平台ID
   * @param keyword 搜索关键词
   * @param country 国家代码（默认 US）
   * @param limit 返回数量限制
   */
  async searchCategories(platformId: string, keyword: string, country: string = 'US', limit = 50) {
    return this.prisma.platformCategory.findMany({
      where: {
        platformId,
        country,
        name: { contains: keyword, mode: 'insensitive' },
      },
      take: limit,
      orderBy: { name: 'asc' },
    });
  }

  /**
   * 获取类目详情
   */
  async getCategory(id: string) {
    const category = await this.prisma.platformCategory.findUnique({
      where: { id },
      include: { attributes: true },
    });
    if (!category) {
      throw new NotFoundException('类目不存在');
    }
    return category;
  }

  /**
   * 获取类目属性原始响应（用于调试）
   * 返回平台 API 的原始响应数据
   */
  async getCategoryAttributesRaw(platformId: string, categoryId: string, country: string = 'US') {
    const platform = await this.prisma.platform.findUnique({
      where: { id: platformId },
    });
    if (!platform) {
      throw new NotFoundException('平台不存在');
    }

    // 获取店铺
    const shops = await this.prisma.shop.findMany({
      where: { platformId, status: 'active', region: country },
      take: 1,
    });
    const shop = shops[0] || (await this.prisma.shop.findFirst({ where: { platformId, status: 'active' } }));
    if (!shop) {
      throw new BadRequestException('该平台没有可用的店铺');
    }

    const adapter = PlatformAdapterFactory.create(
      platform.code,
      shop.apiCredentials as Record<string, any>,
    );
    if (!('getCategoryAttributesRaw' in adapter)) {
      throw new BadRequestException('该平台不支持获取原始属性数据');
    }

    return (adapter as any).getCategoryAttributesRaw(categoryId);
  }

  /**
   * 获取类目属性
   * @param platformId 平台ID
   * @param categoryId 类目ID（平台类目ID，非数据库ID）
   * @param country 国家代码（默认 US）
   */
  async getCategoryAttributes(
    platformId: string,
    categoryId: string,
    country: string = 'US',
    forceRefresh: boolean = false,
  ) {
    // 先查找本地缓存
    const category = await this.prisma.platformCategory.findUnique({
      where: { platformId_country_categoryId: { platformId, country, categoryId } },
      include: { attributes: true },
    });

    // 如果不是强制刷新且有缓存，直接返回
    if (!forceRefresh && category && category.attributes.length > 0) {
      return category.attributes;
    }

    // 强制刷新时，先删除旧的属性缓存
    if (forceRefresh && category) {
      await this.prisma.platformAttribute.deleteMany({
        where: { categoryId: category.id },
      });
    }

    // 从平台获取最新属性
    const platform = await this.prisma.platform.findUnique({
      where: { id: platformId },
    });
    if (!platform) {
      throw new NotFoundException('平台不存在');
    }

    // 优先找该国家的店铺
    const shops = await this.prisma.shop.findMany({
      where: { platformId, status: 'active', region: country },
      take: 1,
    });
    if (shops.length === 0) {
      // 如果没有该国家的店铺，尝试任意店铺
      const anyShops = await this.prisma.shop.findMany({
        where: { platformId, status: 'active' },
        take: 1,
      });
      if (anyShops.length === 0) {
        throw new BadRequestException('该平台没有可用的店铺');
      }
    }

    const shop = shops[0] || (await this.prisma.shop.findFirst({ where: { platformId, status: 'active' } }));
    if (!shop) {
      throw new BadRequestException('该平台没有可用的店铺');
    }

    const adapter = PlatformAdapterFactory.create(
      platform.code,
      shop.apiCredentials as Record<string, any>,
    );
    if (!('getCategoryAttributes' in adapter)) {
      return [];
    }

    const attributes = await (adapter as any).getCategoryAttributes(categoryId);

    // 保存到本地（不保存 conditionalRequired，因为数据库没有这个字段，直接返回给前端）
    if (category && attributes.length > 0) {
      for (const attr of attributes) {
        await this.prisma.platformAttribute.upsert({
          where: {
            categoryId_attributeId: { categoryId: category.id, attributeId: attr.attributeId },
          },
          create: {
            categoryId: category.id,
            country,
            attributeId: attr.attributeId,
            name: attr.name,
            description: attr.description,
            dataType: attr.dataType,
            isRequired: attr.isRequired,
            isMultiSelect: attr.isMultiSelect,
            maxLength: attr.maxLength,
            enumValues: attr.enumValues,
          },
          update: {
            name: attr.name,
            description: attr.description,
            dataType: attr.dataType,
            isRequired: attr.isRequired,
            isMultiSelect: attr.isMultiSelect,
            maxLength: attr.maxLength,
            enumValues: attr.enumValues,
          },
        });
      }
    }

    // 返回完整属性（包含 conditionalRequired）
    return attributes;
  }

  /**
   * 获取平台类目列表（分页）
   */
  async getCategories(query: {
    platformId?: string;
    country?: string;
    parentId?: string;
    isLeaf?: boolean;
    keyword?: string;
    page?: number;
    pageSize?: number;
  }) {
    const { platformId, country, parentId, isLeaf, keyword } = query;
    const page = Number(query.page) || 1;
    const pageSize = Number(query.pageSize) || 50;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (platformId) where.platformId = platformId;
    if (country) where.country = country;
    if (parentId !== undefined) where.parentId = parentId || null;
    if (isLeaf !== undefined) where.isLeaf = isLeaf;
    if (keyword) {
      where.OR = [
        { name: { contains: keyword, mode: 'insensitive' } },
        { categoryId: { contains: keyword, mode: 'insensitive' } },
      ];
    }

    const [data, total] = await Promise.all([
      this.prisma.platformCategory.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { name: 'asc' },
        include: { platform: { select: { id: true, name: true } } },
      }),
      this.prisma.platformCategory.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  /**
   * 获取平台支持的国家列表
   */
  async getCountries(platformId: string): Promise<string[]> {
    const result = await this.prisma.platformCategory.groupBy({
      by: ['country'],
      where: { platformId },
      orderBy: { country: 'asc' },
    });
    return result.map(r => r.country);
  }

  // ==================== 类目属性映射配置 ====================

  /**
   * 获取类目属性映射配置
   */
  async getCategoryAttributeMapping(platformId: string, categoryId: string, country: string = 'US') {
    return (this.prisma as any).categoryAttributeMapping.findUnique({
      where: {
        platformId_country_categoryId: { platformId, country, categoryId },
      },
    });
  }

  /**
   * 保存类目属性映射配置
   */
  async saveCategoryAttributeMapping(data: {
    platformId: string;
    categoryId: string;
    country?: string;
    mappingRules: any;
  }) {
    const { platformId, categoryId, country = 'US', mappingRules } = data;

    // 验证平台存在
    const platform = await this.prisma.platform.findUnique({ where: { id: platformId } });
    if (!platform) {
      throw new NotFoundException('平台不存在');
    }

    // 验证类目存在
    const category = await this.prisma.platformCategory.findUnique({
      where: { platformId_country_categoryId: { platformId, country, categoryId } },
    });
    if (!category) {
      throw new NotFoundException('类目不存在');
    }

    return (this.prisma as any).categoryAttributeMapping.upsert({
      where: {
        platformId_country_categoryId: { platformId, country, categoryId },
      },
      create: {
        platformId,
        categoryId,
        country,
        mappingRules,
      },
      update: {
        mappingRules,
      },
    });
  }

  /**
   * 删除类目属性映射配置
   */
  async deleteCategoryAttributeMapping(platformId: string, categoryId: string, country: string = 'US') {
    return (this.prisma as any).categoryAttributeMapping.delete({
      where: {
        platformId_country_categoryId: { platformId, country, categoryId },
      },
    });
  }

  /**
   * 获取平台所有类目的映射配置列表
   */
  async getCategoryAttributeMappings(platformId: string, country?: string) {
    const where: any = { platformId };
    if (country) where.country = country;

    return (this.prisma as any).categoryAttributeMapping.findMany({
      where,
      orderBy: { updatedAt: 'desc' },
    });
  }

  /**
   * 获取常用类目（已配置映射的类目）
   */
  async getFrequentCategories(platformId: string, country?: string, limit = 10) {
    const where: Record<string, any> = { platformId };
    if (country) where.country = country;

    // 获取已配置映射的类目
    const mappings = await (this.prisma as any).categoryAttributeMapping.findMany({
      where,
      orderBy: { updatedAt: 'desc' },
      take: limit,
    });

    if (mappings.length === 0) {
      return [];
    }

    // 获取类目详情
    const categoryIds = mappings.map((m: any) => m.categoryId);
    const categories = await this.prisma.platformCategory.findMany({
      where: {
        platformId,
        country: country || 'US',
        categoryId: { in: categoryIds },
      },
    });

    // 合并映射信息和类目信息
    return mappings.map((mapping: any) => {
      const category = categories.find(c => c.categoryId === mapping.categoryId);
      const rulesCount = (mapping.mappingRules as any)?.rules?.length || 0;
      return {
        ...category,
        mappingId: mapping.id,
        rulesCount,
        lastUpdated: mapping.updatedAt,
      };
    }).filter((item: any) => item.id); // 过滤掉找不到类目的
  }

  /**
   * 根据映射规则生成商品的平台属性
   * @param mappingRules 映射规则
   * @param channelAttributes 渠道商品属性
   * @param context 解析上下文
   */
  async generatePlatformAttributes(
    mappingRules: any,
    channelAttributes: Record<string, any> = {},
    context: ResolveContext = {},
  ): Promise<Record<string, any>> {
    const result = await this.attributeResolver.resolveAttributes(
      mappingRules,
      channelAttributes,
      context,
    );
    return result.attributes;
  }

  /**
   * 获取可用的映射配置列表（用于加载配置）
   * 返回所有已保存映射配置的类目信息，供用户选择加载
   */
  async getAvailableMappings(platformId: string, country?: string) {
    const where: Record<string, any> = { platformId };
    if (country) where.country = country;

    // 获取所有已配置映射的记录（排除默认配置）
    const mappings = await (this.prisma as any).categoryAttributeMapping.findMany({
      where: {
        ...where,
        categoryId: { not: '__default__' },
      },
      orderBy: { updatedAt: 'desc' },
    });

    if (mappings.length === 0) {
      return [];
    }

    // 获取对应的类目信息
    const categoryIds = mappings.map((m: any) => m.categoryId);
    const categories = await this.prisma.platformCategory.findMany({
      where: {
        platformId,
        categoryId: { in: categoryIds },
      },
    });

    // 构建类目ID到类目信息的映射
    const categoryMap = new Map(categories.map(c => [c.categoryId, c]));

    // 合并映射信息和类目信息
    return mappings.map((mapping: any) => {
      const category = categoryMap.get(mapping.categoryId);
      const rules = (mapping.mappingRules as any)?.rules || [];
      const requiredCount = rules.filter((r: any) => r.isRequired).length;
      const configuredCount = rules.filter((r: any) => r.value).length;

      return {
        id: mapping.id,
        categoryId: mapping.categoryId,
        country: mapping.country,
        categoryName: category?.name || mapping.categoryId,
        categoryPath: category?.categoryPath || '',
        isLeaf: category?.isLeaf || false,
        rulesCount: rules.length,
        requiredCount,
        configuredCount,
        mappingRules: mapping.mappingRules,
        updatedAt: mapping.updatedAt,
      };
    });
  }

  /**
   * 获取默认属性映射配置
   * 直接返回预置的默认规则（属性字段库）
   */
  async getDefaultAttributeMapping(platformId: string, country: string = 'US') {
    const platform = await this.prisma.platform.findUnique({ where: { id: platformId } });
    if (!platform) {
      console.log('[getDefaultAttributeMapping] Platform not found:', platformId);
      return null;
    }

    console.log('[getDefaultAttributeMapping] Platform code:', platform.code);
    const defaultRules = getDefaultMappingRules(platform.code);
    console.log('[getDefaultAttributeMapping] Default rules count:', defaultRules.length);
    
    if (defaultRules.length === 0) {
      return null;
    }

    return {
      id: '__preset__',
      platformId,
      categoryId: '__default__',
      country,
      mappingRules: { rules: defaultRules },
      createdAt: new Date(),
      updatedAt: new Date(),
    };
  }

  /**
   * 保存默认属性映射配置
   */
  async saveDefaultAttributeMapping(data: {
    platformId: string;
    country?: string;
    mappingRules: any;
  }) {
    const { platformId, country = 'US', mappingRules } = data;

    // 验证平台存在
    const platform = await this.prisma.platform.findUnique({ where: { id: platformId } });
    if (!platform) {
      throw new NotFoundException('平台不存在');
    }

    return (this.prisma as any).categoryAttributeMapping.upsert({
      where: {
        platformId_country_categoryId: { platformId, country, categoryId: '__default__' },
      },
      create: {
        platformId,
        categoryId: '__default__',
        country,
        mappingRules,
      },
      update: {
        mappingRules,
      },
    });
  }

  /**
   * 删除默认属性映射配置
   */
  async deleteDefaultAttributeMapping(platformId: string, country: string = 'US') {
    return (this.prisma as any).categoryAttributeMapping.delete({
      where: {
        platformId_country_categoryId: { platformId, country, categoryId: '__default__' },
      },
    }).catch(() => null);
  }

  /**
   * 应用默认配置到属性列表
   * 根据 attributeId 匹配默认配置中的规则，返回合并后的规则列表
   */
  async applyDefaultMappingToAttributes(
    platformId: string,
    country: string,
    attributes: Array<{ attributeId: string; name: string; isRequired: boolean; dataType: string; enumValues?: string[] }>,
  ) {
    // 获取默认配置
    const defaultMapping = await this.getDefaultAttributeMapping(platformId, country);
    const defaultRules = (defaultMapping?.mappingRules as any)?.rules || [];

    // 构建 attributeId 到默认规则的映射
    const defaultRuleMap = new Map<string, any>(defaultRules.map((r: any) => [r.attributeId.toLowerCase(), r]));

    // 为每个属性应用默认配置
    return attributes.map(attr => {
      const defaultRule = defaultRuleMap.get(attr.attributeId.toLowerCase()) as any;
      
      if (defaultRule) {
        // 找到匹配的默认配置，使用默认配置的映射类型和值
        return {
          attributeId: attr.attributeId,
          attributeName: attr.name,
          mappingType: defaultRule.mappingType as string,
          value: defaultRule.value as any,
          isRequired: attr.isRequired,
          dataType: attr.dataType,
          enumValues: attr.enumValues,
        };
      }
      
      // 没有默认配置，返回空配置
      return {
        attributeId: attr.attributeId,
        attributeName: attr.name,
        mappingType: 'default_value' as const,
        value: '',
        isRequired: attr.isRequired,
        dataType: attr.dataType,
        enumValues: attr.enumValues,
      };
    });
  }
}
