import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { PlatformAdapterFactory } from '@/adapters/platforms';

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
  constructor(private prisma: PrismaService) {}

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
   * 获取类目属性
   * @param platformId 平台ID
   * @param categoryId 类目ID（平台类目ID，非数据库ID）
   * @param country 国家代码（默认 US）
   */
  async getCategoryAttributes(platformId: string, categoryId: string, country: string = 'US') {
    // 先查找本地缓存
    const category = await this.prisma.platformCategory.findUnique({
      where: { platformId_country_categoryId: { platformId, country, categoryId } },
      include: { attributes: true },
    });

    if (category && category.attributes.length > 0) {
      return category.attributes;
    }

    // 如果本地没有，从平台获取
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

    // 保存到本地
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
    return this.prisma.categoryAttributeMapping.findUnique({
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

    return this.prisma.categoryAttributeMapping.upsert({
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
    return this.prisma.categoryAttributeMapping.delete({
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

    return this.prisma.categoryAttributeMapping.findMany({
      where,
      orderBy: { updatedAt: 'desc' },
    });
  }

  /**
   * 根据映射规则生成商品的平台属性
   * @param mappingRules 映射规则
   * @param channelAttributes 渠道商品属性
   * @param channelRawData 渠道原始数据
   */
  generatePlatformAttributes(
    mappingRules: any,
    channelAttributes: Record<string, any> = {},
    channelRawData: Record<string, any> = {},
  ): Record<string, any> {
    const result: Record<string, any> = {};
    const rules = mappingRules?.rules || [];

    for (const rule of rules) {
      const { attributeId, mappingType, value } = rule;
      
      switch (mappingType) {
        case 'default_value':
          // 使用固定默认值
          if (value !== undefined && value !== '') {
            result[attributeId] = value;
          }
          break;

        case 'channel_data':
          // 从渠道数据提取
          if (value) {
            const extractedValue = this.extractValueFromPath(value, channelAttributes, channelRawData);
            if (extractedValue !== undefined && extractedValue !== '') {
              result[attributeId] = extractedValue;
            }
          }
          break;

        case 'enum_select':
          // 枚举值选择（直接使用配置的值）
          if (value !== undefined && value !== '') {
            result[attributeId] = value;
          }
          break;

        case 'auto_generate':
          // 自动生成（根据属性ID使用特定逻辑）
          const generated = this.autoGenerateValue(attributeId, channelAttributes, channelRawData);
          if (generated !== undefined && generated !== '') {
            result[attributeId] = generated;
          }
          break;
      }
    }

    return result;
  }

  /**
   * 从路径提取值
   * 支持格式：channelAttributes.brand, channelRawData.detail.mpn
   */
  private extractValueFromPath(
    path: string,
    channelAttributes: Record<string, any>,
    channelRawData: Record<string, any>,
  ): any {
    const parts = path.split('.');
    let source: any;

    if (parts[0] === 'channelAttributes') {
      source = channelAttributes;
      parts.shift();
    } else if (parts[0] === 'channelRawData') {
      source = channelRawData;
      parts.shift();
    } else {
      // 默认从 channelAttributes 提取
      source = channelAttributes;
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
    // 根据属性ID实现特定的自动生成逻辑
    switch (attributeId.toLowerCase()) {
      case 'brand':
        return channelAttributes.brand || 'Unbranded';
      case 'productname':
        return channelRawData.title || channelAttributes.title;
      case 'shortdescription':
        return channelRawData.description?.substring(0, 500);
      case 'keyfeatures':
        // 从特点列表生成
        if (channelAttributes.characteristics?.length > 0) {
          return channelAttributes.characteristics.slice(0, 5);
        }
        return undefined;
      default:
        return undefined;
    }
  }
}
