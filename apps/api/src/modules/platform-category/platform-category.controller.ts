import { Controller, Get, Post, Delete, Param, Query, Body } from '@nestjs/common';
import { PlatformCategoryService } from './platform-category.service';

@Controller('platform-categories')
export class PlatformCategoryController {
  constructor(private readonly categoryService: PlatformCategoryService) {}

  /**
   * 同步平台类目
   * @param platformId 平台ID
   * @param country 国家代码（可选，默认从店铺获取）
   * @param shopId 指定店铺ID（可选，用于获取API凭证）
   */
  @Post('sync/:platformId')
  async syncCategories(
    @Param('platformId') platformId: string,
    @Query('country') country?: string,
    @Query('shopId') shopId?: string,
  ) {
    return this.categoryService.syncCategories(platformId, country, shopId);
  }

  /**
   * 获取类目列表（分页）
   */
  @Get()
  async getCategories(
    @Query() query: {
      platformId?: string;
      country?: string;
      parentId?: string;
      isLeaf?: string;
      keyword?: string;
      page?: number;
      pageSize?: number;
    },
  ) {
    return this.categoryService.getCategories({
      ...query,
      isLeaf: query.isLeaf === 'true' ? true : query.isLeaf === 'false' ? false : undefined,
    });
  }

  /**
   * 获取类目树
   * @param platformId 平台ID
   * @param country 国家代码（默认 US）
   * @param parentId 父类目ID（可选）
   */
  @Get('tree/:platformId')
  async getCategoryTree(
    @Param('platformId') platformId: string,
    @Query('country') country?: string,
    @Query('parentId') parentId?: string,
  ) {
    return this.categoryService.getCategoryTree(platformId, country || 'US', parentId);
  }

  /**
   * 搜索类目
   * @param platformId 平台ID
   * @param keyword 搜索关键词
   * @param country 国家代码（默认 US）
   * @param limit 返回数量限制
   */
  @Get('search/:platformId')
  async searchCategories(
    @Param('platformId') platformId: string,
    @Query('keyword') keyword: string,
    @Query('country') country?: string,
    @Query('limit') limit?: string,
  ) {
    const limitNum = limit ? parseInt(limit, 10) : 50;
    return this.categoryService.searchCategories(platformId, keyword, country || 'US', limitNum);
  }

  /**
   * 获取平台支持的国家列表
   */
  @Get('countries/:platformId')
  async getCountries(@Param('platformId') platformId: string) {
    return this.categoryService.getCountries(platformId);
  }

  /**
   * 获取类目详情
   */
  @Get(':id')
  async getCategory(@Param('id') id: string) {
    return this.categoryService.getCategory(id);
  }

  /**
   * 获取类目属性
   * @param platformId 平台ID
   * @param categoryId 类目ID（平台类目ID）
   * @param country 国家代码（默认 US）
   */
  @Get(':platformId/attributes/:categoryId')
  async getCategoryAttributes(
    @Param('platformId') platformId: string,
    @Param('categoryId') categoryId: string,
    @Query('country') country?: string,
    @Query('forceRefresh') forceRefresh?: string,
  ) {
    return this.categoryService.getCategoryAttributes(
      platformId,
      categoryId,
      country || 'US',
      forceRefresh === 'true',
    );
  }

  /**
   * 获取类目属性原始响应（用于调试）
   * 返回平台 API 的原始 JSON Schema 响应
   */
  @Get(':platformId/attributes-raw/:categoryId')
  async getCategoryAttributesRaw(
    @Param('platformId') platformId: string,
    @Param('categoryId') categoryId: string,
    @Query('country') country?: string,
  ) {
    return this.categoryService.getCategoryAttributesRaw(platformId, categoryId, country || 'US');
  }

  // ==================== 类目属性映射配置 ====================

  /**
   * 获取类目属性映射配置
   */
  @Get(':platformId/mapping/:categoryId')
  async getCategoryAttributeMapping(
    @Param('platformId') platformId: string,
    @Param('categoryId') categoryId: string,
    @Query('country') country?: string,
  ) {
    return this.categoryService.getCategoryAttributeMapping(platformId, categoryId, country || 'US');
  }

  /**
   * 保存类目属性映射配置
   */
  @Post(':platformId/mapping/:categoryId')
  async saveCategoryAttributeMapping(
    @Param('platformId') platformId: string,
    @Param('categoryId') categoryId: string,
    @Body() body: { mappingRules: any },
    @Query('country') country?: string,
  ) {
    return this.categoryService.saveCategoryAttributeMapping({
      platformId,
      categoryId,
      country: country || 'US',
      mappingRules: body.mappingRules,
    });
  }

  /**
   * 删除类目属性映射配置
   */
  @Delete(':platformId/mapping/:categoryId')
  async deleteCategoryAttributeMapping(
    @Param('platformId') platformId: string,
    @Param('categoryId') categoryId: string,
    @Query('country') country?: string,
  ) {
    return this.categoryService.deleteCategoryAttributeMapping(platformId, categoryId, country || 'US');
  }

  /**
   * 获取平台所有类目的映射配置列表
   */
  @Get(':platformId/mappings')
  async getCategoryAttributeMappings(
    @Param('platformId') platformId: string,
    @Query('country') country?: string,
  ) {
    return this.categoryService.getCategoryAttributeMappings(platformId, country);
  }

  /**
   * 获取常用类目（已配置映射的类目）
   */
  @Get(':platformId/frequent')
  async getFrequentCategories(
    @Param('platformId') platformId: string,
    @Query('country') country?: string,
    @Query('limit') limit?: string,
  ) {
    const limitNum = limit ? parseInt(limit, 10) : 10;
    return this.categoryService.getFrequentCategories(platformId, country, limitNum);
  }

  /**
   * 获取可用的映射配置列表（用于加载配置）
   * 返回所有已保存映射配置的类目信息，供用户选择加载
   */
  @Get(':platformId/available-mappings')
  async getAvailableMappings(
    @Param('platformId') platformId: string,
    @Query('country') country?: string,
  ) {
    return this.categoryService.getAvailableMappings(platformId, country);
  }

  // ==================== 默认属性映射配置 ====================

  /**
   * 获取默认属性映射配置
   * 使用特殊的 categoryId = '__default__' 存储全局默认配置
   */
  @Get(':platformId/default-mapping')
  async getDefaultAttributeMapping(
    @Param('platformId') platformId: string,
    @Query('country') country?: string,
  ) {
    return this.categoryService.getDefaultAttributeMapping(platformId, country || 'US');
  }

  /**
   * 保存默认属性映射配置
   */
  @Post(':platformId/default-mapping')
  async saveDefaultAttributeMapping(
    @Param('platformId') platformId: string,
    @Body() body: { mappingRules: any },
    @Query('country') country?: string,
  ) {
    return this.categoryService.saveDefaultAttributeMapping({
      platformId,
      country: country || 'US',
      mappingRules: body.mappingRules,
    });
  }

  /**
   * 删除默认属性映射配置
   */
  @Delete(':platformId/default-mapping')
  async deleteDefaultAttributeMapping(
    @Param('platformId') platformId: string,
    @Query('country') country?: string,
  ) {
    return this.categoryService.deleteDefaultAttributeMapping(platformId, country || 'US');
  }

  /**
   * 应用默认配置到属性列表
   * 根据 attributeId 匹配默认配置中的规则
   */
  @Post(':platformId/apply-default-mapping')
  async applyDefaultMappingToAttributes(
    @Param('platformId') platformId: string,
    @Body() body: { attributes: Array<{ attributeId: string; name: string; isRequired: boolean; dataType: string; enumValues?: string[] }> },
    @Query('country') country?: string,
  ) {
    return this.categoryService.applyDefaultMappingToAttributes(
      platformId,
      country || 'US',
      body.attributes,
    );
  }
}
