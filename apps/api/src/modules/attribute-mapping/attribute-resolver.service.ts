import { Injectable, Logger } from '@nestjs/common';
import { UpcService } from '@/modules/upc/upc.service';
import { PrismaService } from '@/common/prisma/prisma.service';
import { getNestedValue, getCustomAttributeValue } from '@/adapters/channels/standard-product.utils';
import {
  MappingRule,
  MappingRulesConfig,
  ResolveContext,
  ResolveResult,
  ResolveError,
  AutoGenerateConfig,
} from './interfaces/mapping-rule.interface';

/**
 * 属性解析器服务
 *
 * 根据映射规则将渠道数据转换为平台属性
 */
@Injectable()
export class AttributeResolverService {
  private readonly logger = new Logger(AttributeResolverService.name);

  constructor(
    private upcService: UpcService,
    private prisma: PrismaService,
  ) {}

  /**
   * 解析映射规则，生成平台属性
   * 
   * 重要：只处理在类目属性映射配置中有明确值的规则
   * - default_value: 必须有非空的 value
   * - channel_data: 必须有非空的 value（字段路径）且渠道数据中有对应值
   * - enum_select: 必须有非空的 value
   * - upc_pool: 特殊处理，从 UPC 池获取
   * - auto_generate: 不再自动生成，跳过
   */
  async resolveAttributes(
    mappingRules: MappingRulesConfig,
    channelAttributes: Record<string, any>,
    context: ResolveContext = {},
  ): Promise<ResolveResult> {
    const result: ResolveResult = {
      success: true,
      attributes: {},
      errors: [],
      warnings: [],
    };

    const rules = mappingRules?.rules || [];

    for (const rule of rules) {
      try {
        // 跳过没有配置值的规则（auto_generate 类型除外，它有特殊的 value 结构）
        if (!this.hasConfiguredValue(rule)) {
          continue;
        }

        let value = await this.resolveRule(rule, channelAttributes, context);

        // 根据 dataType 进行格式转换
        if (value !== undefined && value !== null && value !== '') {
          value = this.convertValueByDataType(value, rule.dataType);
          result.attributes[rule.attributeId] = value;
        } else if (rule.isRequired) {
          result.warnings.push(
            `Required attribute "${rule.attributeName}" (${rule.attributeId}) resolved to empty value`,
          );
        }
      } catch (error: any) {
        result.errors.push({
          attributeId: rule.attributeId,
          attributeName: rule.attributeName,
          errorType: error.name || 'ResolveError',
          message: error.message,
        });
        result.success = false;
      }
    }

    return result;
  }

  /**
   * 检查规则是否有配置值
   * 只有在类目属性映射页面明确配置了值的规则才会被处理
   * 
   * 数据来源只有：
   * 1. default_value - 用户明确设置的固定值
   * 2. channel_data - 从渠道数据映射的字段
   * 3. enum_select - 用户选择的枚举值
   * 4. upc_pool - 从 UPC 池获取
   * 5. auto_generate - 根据规则自动生成（如提取颜色、计算价格等）
   */
  private hasConfiguredValue(rule: MappingRule): boolean {
    switch (rule.mappingType) {
      case 'default_value':
        // 默认值必须非空
        return rule.value !== undefined && rule.value !== null && rule.value !== '';
      
      case 'channel_data':
        // 渠道数据映射必须有字段路径
        return typeof rule.value === 'string' && rule.value.trim() !== '';
      
      case 'enum_select':
        // 枚举选择必须有选中的值
        return rule.value !== undefined && rule.value !== null && rule.value !== '';
      
      case 'upc_pool':
        // UPC 池总是有效的
        return true;
      
      case 'auto_generate':
        // auto_generate 需要检查是否有有效的规则配置
        if (typeof rule.value === 'object' && rule.value !== null) {
          const config = rule.value as { ruleType?: string };
          return !!config.ruleType;
        }
        return false;
      
      default:
        return false;
    }
  }

  /**
   * 根据数据类型转换值的格式
   * @param value 原始值
   * @param dataType 目标数据类型
   */
  private convertValueByDataType(value: any, dataType?: string): any {
    if (!dataType || value === undefined || value === null) {
      return value;
    }

    switch (dataType) {
      case 'number':
      case 'integer':
        // 转换为数字
        if (typeof value === 'string') {
          const num = parseFloat(value);
          return isNaN(num) ? value : (dataType === 'integer' ? Math.floor(num) : num);
        }
        return value;

      case 'boolean':
        // 转换为布尔值
        if (typeof value === 'string') {
          return value.toLowerCase() === 'true' || value === '1' || value.toLowerCase() === 'yes';
        }
        return Boolean(value);

      case 'array':
        // 转换为数组
        if (Array.isArray(value)) {
          return value;
        }
        if (typeof value === 'string') {
          // 尝试解析 JSON 数组
          if (value.startsWith('[')) {
            try {
              return JSON.parse(value);
            } catch {
              // 解析失败，按分隔符拆分
            }
          }
          // 按分号或逗号拆分
          return value.split(/[;,]/).map(s => s.trim()).filter(s => s);
        }
        return [value];

      case 'enum':
        // 枚举值保持原样
        return value;

      case 'measurement':
        // measurement 类型需要 { measure: number, unit: string } 格式
        // 如果已经是对象格式，直接返回
        if (typeof value === 'object' && value.measure !== undefined) {
          return value;
        }
        // 如果是字符串，尝试解析 "数字 单位" 格式
        if (typeof value === 'string') {
          const match = value.match(/^([\d.]+)\s*(.*)$/);
          if (match) {
            return {
              measure: parseFloat(match[1]),
              unit: match[2].trim() || 'each',
            };
          }
        }
        return value;

      case 'object':
        // 对象类型，保持原样
        return value;

      case 'string':
      default:
        // 字符串类型
        // 如果是对象或数组，保持原样（避免转成 "[object Object]"）
        if (typeof value === 'object') {
          return value;
        }
        if (typeof value !== 'string') {
          return String(value);
        }
        return value;
    }
  }

  /**
   * 解析单条规则
   */
  private async resolveRule(
    rule: MappingRule,
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): Promise<any> {
    switch (rule.mappingType) {
      case 'default_value':
        return this.resolveDefaultValue(rule.value);

      case 'channel_data':
        return this.resolveChannelData(rule.value, channelAttributes);

      case 'enum_select':
        return this.resolveEnumSelect(rule.value);

      case 'auto_generate':
        return this.resolveAutoGenerate(rule.value, channelAttributes, context);

      case 'upc_pool':
        return this.resolveUpcPool(context);

      default:
        this.logger.warn(`Unknown mapping type: ${(rule as any).mappingType}`);
        return undefined;
    }
  }

  /**
   * 解析默认值规则
   */
  private resolveDefaultValue(value: string | number | boolean): any {
    return value;
  }

  /**
   * 解析渠道数据规则
   * 
   * 支持两种路径格式：
   * 1. 标准字段路径：如 'title', 'price', 'bulletPoints' 等
   * 2. customAttributes 属性：如 'customAttributes.brand', 'customAttributes.mpn' 等
   *    会自动从 customAttributes 数组中按 name 查找对应的值
   */
  private resolveChannelData(
    path: string,
    channelAttributes: Record<string, any>,
  ): any {
    // 检查是否是 customAttributes 中的属性
    if (path.startsWith('customAttributes.')) {
      const attrName = path.substring('customAttributes.'.length);
      return getCustomAttributeValue(channelAttributes, attrName);
    }
    
    // 标准字段路径
    return getNestedValue(channelAttributes, path);
  }

  /**
   * 解析枚举选择规则
   */
  private resolveEnumSelect(value: string): any {
    return value;
  }

  /**
   * 解析自动生成规则
   */
  private resolveAutoGenerate(
    config: AutoGenerateConfig,
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): any {
    const { ruleType, param } = config;

    switch (ruleType) {
      case 'sku_prefix':
        return `${param || ''}${context.productSku || ''}`;

      case 'sku_suffix':
        return `${context.productSku || ''}${param || ''}`;

      case 'brand_title':
        // brand 现在在 customAttributes 中
        const brand = getCustomAttributeValue(channelAttributes, 'brand') || '';
        const title = getNestedValue(channelAttributes, 'title') || '';
        return `${brand} ${title}`.trim();

      case 'first_characteristic':
        // characteristics 现在在 customAttributes 中
        const characteristics = getCustomAttributeValue(channelAttributes, 'characteristics');
        return Array.isArray(characteristics) ? characteristics[0] : undefined;

      case 'first_bullet_point':
        const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints');
        return Array.isArray(bulletPoints) ? bulletPoints[0] : undefined;

      case 'current_date':
        return this.formatDate(new Date(), param || 'YYYY-MM-DD');

      case 'uuid':
        return this.generateUUID();

      case 'color_extract':
        return this.extractColor(channelAttributes);

      case 'material_extract':
        return this.extractMaterial(channelAttributes);

      case 'field_with_fallback':
        return this.resolveFieldWithFallback(param, channelAttributes);

      case 'location_extract':
        return this.extractLocation(param, channelAttributes);

      case 'piece_count_extract':
        return this.extractPieceCount(param, channelAttributes);

      case 'seating_capacity_extract':
        return this.extractSeatingCapacity(param, channelAttributes);

      case 'price_calculate':
        return this.calculatePrice(channelAttributes, context);

      case 'shipping_weight_extract':
        return this.extractShippingWeight(param, channelAttributes);

      case 'collection_extract':
        return this.extractCollection(channelAttributes);

      case 'color_category_extract':
        return this.extractColorCategory(param, channelAttributes);

      case 'home_decor_style_extract':
        return this.extractHomeDecorStyle(param, channelAttributes);

      case 'items_included_extract':
        return this.extractItemsIncluded(channelAttributes);

      case 'leg_color_extract':
        return this.extractLegColor(channelAttributes);

      case 'leg_finish_extract':
        return this.extractLegFinish(channelAttributes);

      case 'leg_material_extract':
        return this.extractLegMaterial(channelAttributes);

      case 'mpn_from_sku':
        return this.generateMpnFromSku(channelAttributes);

      case 'living_room_set_type_extract':
        return this.extractLivingRoomSetType(param, channelAttributes);

      case 'max_load_weight_extract':
        return this.extractMaxLoadWeight(channelAttributes);

      case 'net_content_statement_extract':
        return this.extractNetContentStatement(channelAttributes);

      case 'pattern_extract':
        return this.extractPattern(channelAttributes);

      case 'product_line_from_category':
        return this.extractProductLineFromCategory(channelAttributes, context);

      case 'seat_back_height_extract':
        return this.extractSeatBackHeight(channelAttributes);

      case 'seat_color_extract':
        return this.extractSeatColor(channelAttributes);

      case 'seat_height_extract':
        return this.extractSeatHeight(channelAttributes);

      case 'seat_material_extract':
        return this.extractSeatMaterial(channelAttributes);

      case 'upholstered_extract':
        return this.extractUpholstered(param, channelAttributes);

      case 'electronics_indicator_extract':
        return this.extractElectronicsIndicator(param, channelAttributes);

      case 'date_offset':
        return this.generateDateWithOffset(param);

      case 'date_offset_years':
        return this.generateDateWithYearOffset(param);

      default:
        this.logger.warn(`Unknown auto_generate rule type: ${ruleType}`);
        return undefined;
    }
  }

  /**
   * 提取产品数量/件数
   * 从标题和描述中提取数量信息，剔除无关数字（如尺寸、重量等）
   * @param param 默认值，如果提取不到则返回此值
   */
  private extractPieceCount(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): number {
    const defaultValue = parseInt(param || '1', 10) || 1;
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 数量相关的关键词模式（优先匹配）
    const countPatterns = [
      // "X Pieces" 或 "X-Pieces" 或 "X pieces" (如 "6 Pieces", "3-Pieces")
      /(\d+)\s*[-]?\s*pieces?/i,
      // "X-Pieces Sets" 或 "X Pieces Set" (如 "3-Pieces Sets")
      /(\d+)\s*[-]?\s*pieces?\s+sets?/i,
      // "X pc(s)" 或 "X-pc"
      /(\d+)\s*[-]?\s*pcs?/i,
      // "set of X" (如 "Set of 2")
      /set\s+of\s+(\d+)/i,
      // "X set" 或 "X-set" (如 "2 set", "3-set")
      /(\d+)\s*[-]?\s*sets?(?!\s+of)/i,
      // "X pack" 或 "X-pack"
      /(\d+)\s*[-]?\s*pack/i,
      // "pack of X"
      /pack\s+of\s+(\d+)/i,
      // "X count"
      /(\d+)\s*[-]?\s*count/i,
      // "X items"
      /(\d+)\s*[-]?\s*items?/i,
      // "X units"
      /(\d+)\s*[-]?\s*units?/i,
      // "includes X"
      /includes?\s+(\d+)/i,
      // "contains X"
      /contains?\s+(\d+)/i,
      // "X-in-1" 或 "X in 1" (如 "3-in-1", "5 in 1")
      /(\d+)\s*[-]?\s*in\s*[-]?\s*1/i,
    ];

    // 尝试匹配数量模式
    for (const pattern of countPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const count = parseInt(match[1], 10);
        // 合理范围检查：1-10000
        if (count >= 1 && count <= 10000) {
          return count;
        }
      }
    }

    // 如果没有匹配到明确的数量模式，返回默认值
    return defaultValue;
  }

  /**
   * 提取座位容量
   * 从标题和描述中提取座位数量，剔除无关数字（如尺寸、重量等）
   * @param param 默认值，如果提取不到则返回此值（默认为1）
   */
  private extractSeatingCapacity(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): number {
    const defaultValue = parseInt(param || '1', 10) || 1;
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 座位容量相关的关键词模式（按优先级排序）
    const seatingPatterns = [
      // 明确的座位数表达
      // "X seater" 或 "X-seater" (如 "3 seater", "5-seater", "3-seater sofa")
      /(\d+)\s*[-]?\s*seaters?/i,
      // "seats X" 或 "seat X" (如 "seats 4", "seat 6")
      /seats?\s+(\d+)(?!\s*(?:inch|in|cm|mm|ft|"|'))/i,
      // "X seat" 或 "X-seat" (如 "2 seat", "4-seat sofa")
      /(\d+)\s*[-]?\s*seats?(?!\s*(?:cushion|height|depth|width|inch|in|cm))/i,
      // "seating for X" (如 "seating for 6")
      /seating\s+for\s+(\d+)/i,
      // "seating capacity X" 或 "seating capacity: X" 或 "seating capacity of X"
      /seating\s+capacity[:\s]+(?:of\s+)?(\d+)/i,
      // "capacity X" (如 "capacity 4")
      /capacity[:\s]+(\d+)(?!\s*(?:lb|kg|oz|gal|l\b))/i,
      // "X person" 或 "X-person" (如 "4 person", "6-person table")
      /(\d+)\s*[-]?\s*persons?(?!\s*(?:weight|capacity))/i,
      // "for X people" (如 "for 4 people")
      /for\s+(\d+)\s+people/i,
      // "fits X" (如 "fits 4")
      /fits\s+(\d+)(?!\s*(?:inch|in|cm|mm))/i,
      // "accommodates X" (如 "accommodates 6")
      /accommodates?\s+(\d+)/i,
      // "holds X" (如 "holds 4 people")
      /holds\s+(\d+)(?!\s*(?:lb|kg|oz|gal|l\b))/i,
    ];

    // 尝试匹配座位模式
    for (const pattern of seatingPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const count = parseInt(match[1], 10);
        // 合理范围检查：1-20（座位数通常不会太大）
        if (count >= 1 && count <= 20) {
          return count;
        }
      }
    }

    // 特殊家具类型推断（按优先级）
    // 双人沙发/情侣椅
    if (text.includes('loveseat') || text.includes('love seat') || text.includes('two-seater')) {
      return 2;
    }
    // 大型组合沙发
    if (text.includes('u-shaped') || text.includes('u shaped')) {
      return 7;
    }
    // L型沙发/转角沙发
    if (text.includes('l-shaped') || text.includes('l shaped') || text.includes('corner sofa') || text.includes('corner sectional')) {
      return 5;
    }
    // 组合沙发
    if (text.includes('sectional sofa') || text.includes('sectional couch') || text.includes('modular sofa')) {
      return 5;
    }
    // 普通沙发/长沙发
    if ((text.includes('sofa') || text.includes('couch')) && !text.includes('loveseat') && !text.includes('chair')) {
      return 3;
    }
    // 长椅/凳子
    if (text.includes('bench') && !text.includes('workbench')) {
      return 2;
    }
    // 单人椅
    if (text.includes('armchair') || text.includes('arm chair') || text.includes('accent chair') || 
        text.includes('recliner') || text.includes('lounge chair') || text.includes('club chair') ||
        text.includes('wingback') || text.includes('barrel chair') || text.includes('slipper chair')) {
      return 1;
    }
    // 餐椅套装 - 尝试从 "set of X" 提取
    if (text.includes('dining') && text.includes('chair')) {
      const setMatch = text.match(/set\s+of\s+(\d+)/i);
      if (setMatch && setMatch[1]) {
        const count = parseInt(setMatch[1], 10);
        if (count >= 1 && count <= 12) {
          return count;
        }
      }
    }

    // 如果没有匹配到任何模式，返回默认值
    return defaultValue;
  }

  /**
   * 提取使用场景/位置
   * 从标题和描述中判断是 Indoor 还是 Outdoor，默认返回 Indoor
   * @param param 可选枚举值，逗号分隔，如 "Indoor,Outdoor"
   */
  private extractLocation(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 解析可选枚举值
    const options = param?.split(',').map(s => s.trim()) || ['Indoor', 'Outdoor'];
    const defaultValue = options[0] || 'Indoor';

    // Outdoor 关键词
    const outdoorKeywords = [
      'outdoor',
      'patio',
      'garden',
      'backyard',
      'deck',
      'porch',
      'balcony',
      'terrace',
      'poolside',
      'exterior',
      'outside',
      'lawn',
      'yard',
    ];

    // Indoor 关键词
    const indoorKeywords = [
      'indoor',
      'interior',
      'inside',
      'living room',
      'bedroom',
      'dining room',
      'office',
      'kitchen',
      'bathroom',
    ];

    // 检查是否包含 outdoor 关键词
    const hasOutdoor = outdoorKeywords.some(keyword => text.includes(keyword));
    const hasIndoor = indoorKeywords.some(keyword => text.includes(keyword));

    // 如果同时包含，返回 Indoor（更安全的选择）
    if (hasOutdoor && !hasIndoor) {
      return options.find(o => o.toLowerCase() === 'outdoor') || 'Outdoor';
    }

    return options.find(o => o.toLowerCase() === 'indoor') || defaultValue;
  }

  /**
   * 提取颜色
   * 优先从 color 字段取值，如果没有则从标题和描述中提取
   */
  private extractColor(channelAttributes: Record<string, any>): string | undefined {
    // 1. 优先从标准字段获取
    const color = getNestedValue(channelAttributes, 'color');
    if (color) return color;

    // colorFamily 现在在 customAttributes 中
    const colorFamily = getCustomAttributeValue(channelAttributes, 'colorFamily');
    if (colorFamily) return colorFamily;

    // 2. 从标题和描述中提取颜色
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 常见颜色关键词
    const colorKeywords = [
      // 基础颜色
      'black', 'white', 'red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink', 'brown', 'gray', 'grey',
      // 深浅变体
      'dark', 'light', 'navy', 'beige', 'cream', 'ivory', 'silver', 'gold', 'bronze', 'copper',
      // 木色
      'walnut', 'oak', 'cherry', 'mahogany', 'espresso', 'natural', 'rustic',
      // 其他
      'multicolor', 'multi-color', 'transparent', 'clear',
    ];

    for (const keyword of colorKeywords) {
      if (text.includes(keyword)) {
        // 首字母大写
        return keyword.charAt(0).toUpperCase() + keyword.slice(1);
      }
    }

    return undefined;
  }

  /**
   * 提取材质
   * 优先从 material 字段取值，如果没有则从标题和描述中提取
   */
  private extractMaterial(channelAttributes: Record<string, any>): string | undefined {
    // 1. 优先从标准字段获取
    const material = getNestedValue(channelAttributes, 'material');
    if (material) return material;

    // 2. 从标题和描述中提取材质
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 常见材质关键词
    const materialKeywords = [
      // 木材
      'wood', 'wooden', 'mdf', 'plywood', 'particle board', 'solid wood', 'engineered wood',
      // 金属
      'metal', 'steel', 'iron', 'aluminum', 'aluminium', 'stainless steel', 'brass', 'copper',
      // 织物
      'fabric', 'cotton', 'linen', 'polyester', 'velvet', 'leather', 'faux leather', 'pu leather',
      // 塑料
      'plastic', 'abs', 'pvc', 'acrylic', 'polypropylene',
      // 玻璃
      'glass', 'tempered glass',
      // 其他
      'rattan', 'wicker', 'bamboo', 'marble', 'stone', 'ceramic', 'foam',
    ];

    for (const keyword of materialKeywords) {
      if (text.includes(keyword)) {
        // 首字母大写
        return keyword.charAt(0).toUpperCase() + keyword.slice(1);
      }
    }

    return undefined;
  }

  /**
   * 带回退的字段取值
   * param 格式: "field1,field2,field3" - 按顺序尝试获取，返回第一个非空值
   */
  private resolveFieldWithFallback(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): any {
    if (!param) return undefined;

    const fields = param.split(',').map(f => f.trim());
    for (const field of fields) {
      const value = getNestedValue(channelAttributes, field);
      if (value !== undefined && value !== null && value !== '') {
        return value;
      }
    }

    return undefined;
  }

  /**
   * 解析 UPC 池规则
   * 返回 Walmart productIdentifiers 格式的对象
   */
  private async resolveUpcPool(
    context: ResolveContext,
  ): Promise<{ productIdType: string; productId: string } | null> {
    if (!context.productSku) {
      throw new Error('productSku is required for upc_pool mapping');
    }
    const upc = await this.upcService.autoAssignUpc(context.productSku, context.shopId);
    if (!upc) {
      return null;
    }

    // 根据 UPC 长度确定类型
    // UPC-12 -> UPC, EAN-13 -> EAN, GTIN-14 -> GTIN
    let productIdType = 'UPC';

    if (upc.length === 13) {
      productIdType = 'EAN';
    } else if (upc.length === 14) {
      productIdType = 'GTIN';
    }

    return {
      productIdType,
      productId: upc,
    };
  }

  /**
   * 格式化日期
   */
  private formatDate(date: Date, format: string): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return format
      .replace('YYYY', String(year))
      .replace('MM', month)
      .replace('DD', day);
  }

  /**
   * 生成 UUID
   */
  private generateUUID(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  /**
   * 计算售价
   * 根据店铺同步规则配置的价格倍率计算最终售价
   * 公式：最终价格 = (原价 + 运费) * 倍率 + 增减值
   * 
   * 价格来源：
   * - channel: 使用渠道数据中的 price/salePrice
   * - local: 使用本地数据中的 price/salePrice（总价 = price + shippingFee）
   */
  private async calculatePrice(
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): Promise<number | undefined> {
    // 获取店铺同步配置
    let priceConfig = {
      enabled: true,
      source: 'local' as 'channel' | 'local',  // 默认使用本地价格
      useDiscountedPrice: false,
      tiers: [] as { minPrice: number; maxPrice: number | null; multiplier: number; adjustment: number }[],
      defaultMultiplier: 1.0,
      defaultAdjustment: 0,
    };

    if (context.shopId) {
      try {
        const shop = await this.prisma.shop.findUnique({
          where: { id: context.shopId },
          select: { syncConfig: true },
        });
        if (shop?.syncConfig) {
          const syncConfig = shop.syncConfig as any;
          if (syncConfig.price) {
            priceConfig = {
              enabled: syncConfig.price.enabled ?? true,
              source: syncConfig.price.source || 'local',
              useDiscountedPrice: syncConfig.price.useDiscountedPrice || false,
              tiers: syncConfig.price.tiers || [],
              defaultMultiplier: syncConfig.price.defaultMultiplier ?? 1.0,
              defaultAdjustment: syncConfig.price.defaultAdjustment ?? 0,
            };
          }
        }
      } catch (error) {
        this.logger.warn(`Failed to get shop sync config: ${error}`);
      }
    }

    // 如果价格倍率未启用，返回 undefined（不处理价格）
    if (!priceConfig.enabled) {
      return undefined;
    }

    // 从 channelAttributes 获取价格数据
    // channelAttributes.price = 产品原价（不含运费）
    // channelAttributes.salePrice = 优惠价（不含运费）
    // channelAttributes.shippingFee = 运费
    const channelPrice = Number(getNestedValue(channelAttributes, 'price')) || 0;
    const channelSalePrice = getNestedValue(channelAttributes, 'salePrice');
    const shippingFee = Number(getNestedValue(channelAttributes, 'shippingFee')) || 0;

    // 获取产品原价：优先使用 channelAttributes.price，否则使用 context.productPrice
    // context.productPrice 来自 ListingProduct.price 或 ProductPool.price
    const productPrice = channelPrice || Number(context.productPrice) || 0;
    const salePrice = channelSalePrice ? Number(channelSalePrice) : undefined;

    // 如果没有价格数据，返回 undefined
    if (!productPrice && !salePrice) {
      this.logger.warn(`[calculatePrice] No price data available: channelPrice=${channelPrice}, productPrice=${context.productPrice}`);
      return undefined;
    }

    // 计算基础价格（本地总价）
    // 本地总价 = 产品原价 + 运费
    // 本地优惠总价 = 优惠价 + 运费
    let basePrice: number;
    if (priceConfig.useDiscountedPrice && salePrice) {
      // 使用优惠总价
      basePrice = salePrice + shippingFee;
    } else {
      // 使用总价
      basePrice = productPrice + shippingFee;
    }

    this.logger.debug(`[calculatePrice] productPrice=${productPrice}, salePrice=${salePrice}, shippingFee=${shippingFee}, basePrice=${basePrice}`);

    // 根据价格区间找到对应的倍率和增减值
    let multiplier = priceConfig.defaultMultiplier;
    let adjustment = priceConfig.defaultAdjustment;

    for (const tier of priceConfig.tiers) {
      if (basePrice >= tier.minPrice && (tier.maxPrice === null || basePrice < tier.maxPrice)) {
        multiplier = tier.multiplier;
        adjustment = tier.adjustment;
        this.logger.debug(`[calculatePrice] Matched tier: ${tier.minPrice}-${tier.maxPrice}, multiplier=${multiplier}, adjustment=${adjustment}`);
        break;
      }
    }

    // 计算最终价格
    const finalPrice = basePrice * multiplier + adjustment;

    this.logger.debug(`[calculatePrice] Final: ${basePrice} * ${multiplier} + ${adjustment} = ${finalPrice}`);

    // 保留两位小数
    return Math.round(finalPrice * 100) / 100;
  }

  /**
   * 提取运输重量（转换为磅）
   * 优先从渠道数据获取重量，如果没有则从描述中提取，最后使用默认值
   * 支持 kg、g、lb、oz 等单位的自动转换
   * @param param 默认值（磅），如果提取不到则返回此值
   */
  private extractShippingWeight(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): number {
    const defaultValue = parseFloat(param || '1') || 1;

    // 1. 优先从包装重量字段获取（已转换为 lb）
    const packageWeight = getNestedValue(channelAttributes, 'packageWeight');
    if (packageWeight !== undefined && packageWeight !== null && packageWeight !== '') {
      const numWeight = parseFloat(String(packageWeight));
      if (!isNaN(numWeight) && numWeight > 0) {
        return numWeight; // 已经是 lb 单位
      }
    }

    // 2. 尝试从产品重量字段获取（已转换为 lb）
    const productWeight = getNestedValue(channelAttributes, 'productWeight');
    if (productWeight !== undefined && productWeight !== null && productWeight !== '') {
      const numWeight = parseFloat(String(productWeight));
      if (!isNaN(numWeight) && numWeight > 0) {
        return numWeight; // 已经是 lb 单位
      }
    }

    // 3. 从标题和描述中提取重量
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 重量提取模式
    const weightPatterns = [
      // "X lbs" 或 "X lb" 或 "X pounds"
      /(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?)/i,
      // "X kg" 或 "X kilograms"
      /(\d+(?:\.\d+)?)\s*(?:kg|kilograms?)/i,
      // "X g" 或 "X grams" (注意避免匹配其他单位如 "in")
      /(\d+(?:\.\d+)?)\s*(?:g|grams?)(?!\s*(?:al|old|reen|ray))/i,
      // "X oz" 或 "X ounces"
      /(\d+(?:\.\d+)?)\s*(?:oz|ounces?)/i,
      // "weight: X" 或 "weight X"
      /weight[:\s]+(\d+(?:\.\d+)?)\s*(lbs?|kg|g|oz)?/i,
      // "shipping weight: X"
      /shipping\s+weight[:\s]+(\d+(?:\.\d+)?)\s*(lbs?|kg|g|oz)?/i,
    ];

    for (const pattern of weightPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const numWeight = parseFloat(match[1]);
        if (!isNaN(numWeight) && numWeight > 0) {
          // 确定单位
          let unit = 'lb';
          const unitMatch = match[2]?.toLowerCase();
          if (unitMatch) {
            if (unitMatch.startsWith('kg') || unitMatch.startsWith('kilo')) {
              unit = 'kg';
            } else if (unitMatch === 'g' || unitMatch.startsWith('gram')) {
              unit = 'g';
            } else if (unitMatch.startsWith('oz') || unitMatch.startsWith('ounce')) {
              unit = 'oz';
            }
          } else {
            // 根据模式推断单位
            if (pattern.source.includes('kg')) unit = 'kg';
            else if (pattern.source.includes('(?:g|grams?)')) unit = 'g';
            else if (pattern.source.includes('oz')) unit = 'oz';
          }
          
          // 合理范围检查（转换后 0.1-2000 磅）
          const lbsWeight = this.convertWeightToLbs(numWeight, unit);
          if (lbsWeight >= 0.1 && lbsWeight <= 2000) {
            return lbsWeight;
          }
        }
      }
    }

    // 4. 返回默认值
    return defaultValue;
  }

  /**
   * 提取产品系列名称
   * 使用"使用场所 + 产品主体"的格式生成 Collection
   * 例如：Indoor Sofa, Outdoor Dining Table, Living Room Chair
   */
  private extractCollection(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 1. 提取使用场所
    let location = 'Indoor'; // 默认室内
    const outdoorKeywords = ['outdoor', 'patio', 'garden', 'backyard', 'deck', 'balcony', 'terrace', 'poolside'];
    const indoorKeywords = ['indoor', 'living room', 'bedroom', 'dining room', 'kitchen', 'office', 'bathroom'];
    
    for (const keyword of outdoorKeywords) {
      if (text.includes(keyword)) {
        location = 'Outdoor';
        break;
      }
    }
    
    // 更具体的室内场所
    for (const keyword of indoorKeywords) {
      if (text.includes(keyword)) {
        if (keyword === 'living room') location = 'Living Room';
        else if (keyword === 'bedroom') location = 'Bedroom';
        else if (keyword === 'dining room') location = 'Dining Room';
        else if (keyword === 'kitchen') location = 'Kitchen';
        else if (keyword === 'office') location = 'Office';
        else if (keyword === 'bathroom') location = 'Bathroom';
        else location = 'Indoor';
        break;
      }
    }

    // 2. 提取产品主体类型
    const productTypes: Record<string, string> = {
      'sofa': 'Sofa',
      'couch': 'Sofa',
      'sectional': 'Sectional',
      'loveseat': 'Loveseat',
      'chair': 'Chair',
      'armchair': 'Armchair',
      'recliner': 'Recliner',
      'table': 'Table',
      'dining table': 'Dining Table',
      'coffee table': 'Coffee Table',
      'end table': 'End Table',
      'side table': 'Side Table',
      'console table': 'Console Table',
      'desk': 'Desk',
      'bed': 'Bed',
      'bed frame': 'Bed Frame',
      'nightstand': 'Nightstand',
      'dresser': 'Dresser',
      'cabinet': 'Cabinet',
      'bookshelf': 'Bookshelf',
      'shelf': 'Shelf',
      'storage': 'Storage',
      'ottoman': 'Ottoman',
      'bench': 'Bench',
      'stool': 'Stool',
      'bar stool': 'Bar Stool',
      'futon': 'Futon',
      'daybed': 'Daybed',
      'wardrobe': 'Wardrobe',
      'tv stand': 'TV Stand',
      'entertainment center': 'Entertainment Center',
    };

    let productType = 'Furniture'; // 默认
    
    // 优先匹配更长的词组
    const sortedTypes = Object.keys(productTypes).sort((a, b) => b.length - a.length);
    for (const type of sortedTypes) {
      if (text.includes(type)) {
        productType = productTypes[type];
        break;
      }
    }

    // 3. 组合成 Collection 名称
    return `${location} ${productType}`;
  }

  /**
   * 提取颜色分类
   * 从 color 字段匹配最接近的 Walmart 颜色枚举值
   * 返回数组格式（因为 colorCategory 是 array 类型）
   * @param param 默认值，如果提取不到则返回此值
   */
  private extractColorCategory(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string[] {
    const defaultValue = param || 'Multicolor';
    
    // Walmart 支持的颜色枚举值
    const walmartColors = [
      'Blue', 'Brown', 'Gold', 'Gray', 'Purple', 'Clear', 'Yellow',
      'Off-White', 'Multicolor', 'Black', 'Beige', 'Pink', 'Orange',
      'Green', 'White', 'Red', 'Silver', 'Bronze',
    ];

    // 颜色关键词映射到 Walmart 枚举值
    const colorMapping: Record<string, string> = {
      // Blue 系列
      'blue': 'Blue', 'navy': 'Blue', 'teal': 'Blue', 'aqua': 'Blue', 'cyan': 'Blue',
      'turquoise': 'Blue', 'cobalt': 'Blue', 'sapphire': 'Blue', 'azure': 'Blue',
      'indigo': 'Blue', 'royal blue': 'Blue', 'sky blue': 'Blue', 'light blue': 'Blue',
      'dark blue': 'Blue', 'ocean': 'Blue',
      // Brown 系列
      'brown': 'Brown', 'tan': 'Brown', 'chocolate': 'Brown', 'coffee': 'Brown',
      'espresso': 'Brown', 'walnut': 'Brown', 'chestnut': 'Brown', 'mahogany': 'Brown',
      'caramel': 'Brown', 'mocha': 'Brown', 'rustic': 'Brown', 'oak': 'Brown',
      // Gold 系列
      'gold': 'Gold', 'golden': 'Gold', 'brass': 'Gold', 'champagne': 'Gold',
      // Gray 系列
      'gray': 'Gray', 'grey': 'Gray', 'charcoal': 'Gray', 'slate': 'Gray',
      'ash': 'Gray', 'graphite': 'Gray', 'pewter': 'Gray', 'smoke': 'Gray',
      'silver gray': 'Gray', 'dark gray': 'Gray', 'light gray': 'Gray',
      // Purple 系列
      'purple': 'Purple', 'violet': 'Purple', 'lavender': 'Purple', 'plum': 'Purple',
      'mauve': 'Purple', 'lilac': 'Purple', 'magenta': 'Purple', 'eggplant': 'Purple',
      // Clear/透明
      'clear': 'Clear', 'transparent': 'Clear', 'glass': 'Clear',
      // Yellow 系列
      'yellow': 'Yellow', 'lemon': 'Yellow', 'mustard': 'Yellow', 'canary': 'Yellow',
      'sunshine': 'Yellow', 'butter': 'Yellow',
      // Off-White 系列
      'off-white': 'Off-White', 'cream': 'Off-White', 'ivory': 'Off-White',
      'eggshell': 'Off-White', 'pearl': 'Off-White', 'vanilla': 'Off-White',
      'bone': 'Off-White', 'linen': 'Off-White',
      // Black 系列
      'black': 'Black', 'ebony': 'Black', 'jet': 'Black', 'onyx': 'Black',
      'midnight': 'Black', 'noir': 'Black',
      // Beige 系列
      'beige': 'Beige', 'khaki': 'Beige', 'sand': 'Beige', 'taupe': 'Beige',
      'fawn': 'Beige', 'wheat': 'Beige', 'oatmeal': 'Beige', 'natural': 'Beige',
      // Pink 系列
      'pink': 'Pink', 'rose': 'Pink', 'blush': 'Pink', 'coral': 'Pink',
      'salmon': 'Pink', 'fuchsia': 'Pink', 'hot pink': 'Pink',
      // Orange 系列
      'orange': 'Orange', 'tangerine': 'Orange', 'peach': 'Orange', 'apricot': 'Orange',
      'rust': 'Orange', 'terracotta': 'Orange', 'copper': 'Orange', 'amber': 'Orange',
      // Green 系列
      'green': 'Green', 'olive': 'Green', 'sage': 'Green', 'mint': 'Green',
      'emerald': 'Green', 'forest': 'Green', 'lime': 'Green', 'hunter': 'Green',
      'moss': 'Green', 'jade': 'Green', 'seafoam': 'Green', 'avocado': 'Green',
      // White 系列
      'white': 'White', 'snow': 'White', 'pure white': 'White', 'bright white': 'White',
      // Red 系列
      'red': 'Red', 'burgundy': 'Red', 'maroon': 'Red', 'crimson': 'Red',
      'scarlet': 'Red', 'cherry': 'Red', 'wine': 'Red', 'ruby': 'Red', 'brick': 'Red',
      // Silver 系列
      'silver': 'Silver', 'chrome': 'Silver', 'metallic': 'Silver', 'nickel': 'Silver',
      'stainless': 'Silver', 'platinum': 'Silver', 'steel': 'Silver',
      // Bronze 系列
      'bronze': 'Bronze', 'antique bronze': 'Bronze', 'oil rubbed bronze': 'Bronze',
    };

    // 1. 优先从渠道数据的 color 字段获取
    let colorText = getNestedValue(channelAttributes, 'color') || '';
    
    // 2. 如果 color 字段为空，从标题/描述中提取
    if (!colorText) {
      const title = getNestedValue(channelAttributes, 'title') || '';
      const description = getNestedValue(channelAttributes, 'description') || '';
      const text = `${title} ${description}`.toLowerCase();
      
      // 从文本中提取颜色关键词
      const sortedMappings = Object.keys(colorMapping).sort((a, b) => b.length - a.length);
      for (const keyword of sortedMappings) {
        if (text.includes(keyword)) {
          return [colorMapping[keyword]];
        }
      }
      
      // 都没找到，返回默认值
      return [defaultValue];
    }

    const colorLower = colorText.toString().toLowerCase().trim();

    // 3. 直接匹配 Walmart 枚举值（不区分大小写）
    for (const walmartColor of walmartColors) {
      if (colorLower === walmartColor.toLowerCase()) {
        return [walmartColor];
      }
    }

    // 4. 通过映射表匹配
    const sortedMappings = Object.keys(colorMapping).sort((a, b) => b.length - a.length);
    for (const keyword of sortedMappings) {
      if (colorLower.includes(keyword)) {
        return [colorMapping[keyword]];
      }
    }

    // 5. 检查是否包含多种颜色（如 "Black and White", "Blue/Gray"）
    const multiColorPatterns = [' and ', ' & ', '/', ',', ' with '];
    for (const pattern of multiColorPatterns) {
      if (colorLower.includes(pattern)) {
        return ['Multicolor'];
      }
    }

    // 6. 返回默认值
    return [defaultValue];
  }

  /**
   * 提取家居装饰风格
   * 从标题/描述中匹配 Walmart 家居风格枚举值
   * 返回数组格式（因为 homeDecorStyle 是 array 类型）
   * @param param 默认值，如果提取不到则返回此值
   */
  private extractHomeDecorStyle(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string[] {
    const defaultValue = param || 'Minimalist';

    // Walmart 支持的家居风格枚举值
    const walmartStyles = [
      'Mid-Century', 'Tropical', 'Eclectic', 'Glam', 'Cottage', 'Minimalist',
      'Transitional', 'Rustic/Lodge', 'Scandinavian', 'Traditional', 'Victorian',
      'Bohemian', 'Asian Inspired', 'Farmhouse', 'Industrial', 'French Country',
      'Modern', 'Contemporary', 'Shabby Chic', 'Coastal', 'Southwestern', 'Art Deco',
    ];

    // 风格关键词映射
    const styleMapping: Record<string, string> = {
      // Modern 系列
      'modern': 'Modern',
      'contemporary': 'Contemporary',
      'minimalist': 'Minimalist',
      'minimal': 'Minimalist',
      'sleek': 'Modern',
      'clean lines': 'Modern',
      // Mid-Century
      'mid-century': 'Mid-Century',
      'mid century': 'Mid-Century',
      'midcentury': 'Mid-Century',
      'retro': 'Mid-Century',
      '60s': 'Mid-Century',
      '70s': 'Mid-Century',
      // Farmhouse
      'farmhouse': 'Farmhouse',
      'farm house': 'Farmhouse',
      'barn': 'Farmhouse',
      'country': 'French Country',
      'french country': 'French Country',
      // Industrial
      'industrial': 'Industrial',
      'loft': 'Industrial',
      'factory': 'Industrial',
      'metal': 'Industrial',
      'pipe': 'Industrial',
      // Rustic
      'rustic': 'Rustic/Lodge',
      'lodge': 'Rustic/Lodge',
      'cabin': 'Rustic/Lodge',
      'log': 'Rustic/Lodge',
      'reclaimed': 'Rustic/Lodge',
      // Traditional
      'traditional': 'Traditional',
      'classic': 'Traditional',
      'timeless': 'Traditional',
      'elegant': 'Traditional',
      // Scandinavian
      'scandinavian': 'Scandinavian',
      'nordic': 'Scandinavian',
      'swedish': 'Scandinavian',
      'danish': 'Scandinavian',
      'hygge': 'Scandinavian',
      // Bohemian
      'bohemian': 'Bohemian',
      'boho': 'Bohemian',
      'eclectic': 'Eclectic',
      // Coastal
      'coastal': 'Coastal',
      'beach': 'Coastal',
      'nautical': 'Coastal',
      'seaside': 'Coastal',
      'ocean': 'Coastal',
      // Tropical
      'tropical': 'Tropical',
      'palm': 'Tropical',
      'hawaiian': 'Tropical',
      // Glam
      'glam': 'Glam',
      'glamorous': 'Glam',
      'luxurious': 'Glam',
      'luxury': 'Glam',
      'velvet': 'Glam',
      // Victorian
      'victorian': 'Victorian',
      'antique': 'Victorian',
      'ornate': 'Victorian',
      // Cottage
      'cottage': 'Cottage',
      'shabby chic': 'Shabby Chic',
      'shabby': 'Shabby Chic',
      // Asian
      'asian': 'Asian Inspired',
      'japanese': 'Asian Inspired',
      'zen': 'Asian Inspired',
      'oriental': 'Asian Inspired',
      // Southwestern
      'southwestern': 'Southwestern',
      'southwest': 'Southwestern',
      'aztec': 'Southwestern',
      'navajo': 'Southwestern',
      // Art Deco
      'art deco': 'Art Deco',
      'deco': 'Art Deco',
      'gatsby': 'Art Deco',
      // Transitional
      'transitional': 'Transitional',
    };

    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    // style 现在在 customAttributes 中
    const style = getCustomAttributeValue(channelAttributes, 'style') || '';
    const text = `${title} ${description} ${style}`.toLowerCase();

    // 1. 直接匹配 Walmart 枚举值
    for (const walmartStyle of walmartStyles) {
      if (text.includes(walmartStyle.toLowerCase())) {
        return [walmartStyle];
      }
    }

    // 2. 通过映射表匹配（优先匹配更长的词组）
    const sortedMappings = Object.keys(styleMapping).sort((a, b) => b.length - a.length);
    for (const keyword of sortedMappings) {
      if (text.includes(keyword)) {
        return [styleMapping[keyword]];
      }
    }

    // 3. 返回默认值
    return [defaultValue];
  }

  /**
   * 提取包含物品列表
   * 从标题中提取套装的主体物品名称
   * 核心逻辑：识别 "X and Y Set of N" 模式，直接提取主体名称
   * 如果没有提取到则返回 undefined（不传递此字段）
   */
  private extractItemsIncluded(channelAttributes: Record<string, any>): string[] | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';

    // 方法1: 匹配 "X and Y Set of N" 或 "X & Y Set of N" 模式
    // 例如: "TV Stand and Coffee Table Set of 2" → ["TV Stand", "Coffee Table"]
    const setPattern = /^(.+?)\s+(?:and|&)\s+(.+?)\s+set\s+of\s+\d+/i;
    const setMatch = title.match(setPattern);

    if (setMatch) {
      // 提取主体名称，清理品牌前缀等
      let item1 = this.extractMainItemName(setMatch[1]);
      let item2 = this.extractMainItemName(setMatch[2]);

      if (item1 && item2) {
        // 去重（如 TV Stand 和 TV Console 是同一个东西）
        const normalized1 = this.normalizeItemName(item1);
        const normalized2 = this.normalizeItemName(item2);

        if (normalized1 === normalized2) {
          return [this.capitalizeItemName(item1)];
        }
        return [this.capitalizeItemName(item1), this.capitalizeItemName(item2)];
      }
    }

    // 方法2: 匹配 "N-Piece X Set" 模式
    // 例如: "3-Piece Dining Set" → 需要从后续内容提取具体物品
    const pieceSetPattern = /(\d+)\s*[-]?\s*pieces?\s+(.+?)\s+set/i;
    const pieceMatch = title.match(pieceSetPattern);

    if (pieceMatch) {
      const setType = pieceMatch[2].toLowerCase();
      // 根据套装类型返回典型物品
      const setTypeItems: Record<string, string[]> = {
        'dining': ['Dining Table', 'Dining Chair'],
        'living room': ['Sofa', 'Coffee Table'],
        'bedroom': ['Bed Frame', 'Nightstand', 'Dresser'],
        'patio': ['Patio Table', 'Patio Chair'],
        'outdoor': ['Outdoor Table', 'Outdoor Chair'],
      };

      for (const [type, items] of Object.entries(setTypeItems)) {
        if (setType.includes(type)) {
          return items;
        }
      }
    }

    return undefined;
  }

  /**
   * 从标题片段中提取主要物品名称
   * 去除品牌名、形容词等，保留核心物品名
   */
  private extractMainItemName(segment: string): string | null {
    // 常见家具物品名称（用于识别）
    const furnitureItems = [
      'tv stand', 'tv console', 'entertainment center',
      'coffee table', 'end table', 'side table', 'console table', 'dining table', 'center table',
      'sofa', 'couch', 'loveseat', 'sectional', 'futon',
      'chair', 'recliner', 'armchair', 'accent chair', 'dining chair', 'office chair',
      'bed', 'bed frame', 'headboard',
      'dresser', 'nightstand', 'wardrobe', 'bookshelf', 'bookcase',
      'ottoman', 'bench', 'stool', 'bar stool',
      'desk', 'vanity',
    ];

    const lowerSegment = segment.toLowerCase().trim();

    // 从后往前匹配，因为物品名通常在最后
    for (const item of furnitureItems) {
      if (lowerSegment.endsWith(item) || lowerSegment.includes(item)) {
        return item;
      }
    }

    // 如果没匹配到，返回最后几个词作为物品名
    const words = lowerSegment.split(/\s+/);
    if (words.length >= 2) {
      return words.slice(-2).join(' ');
    }

    return lowerSegment || null;
  }

  /**
   * 标准化物品名称（用于去重比较）
   */
  private normalizeItemName(name: string): string {
    const synonyms: Record<string, string> = {
      'tv stand': 'tv_stand',
      'tv console': 'tv_stand',
      'entertainment center': 'tv_stand',
      'coffee table': 'coffee_table',
      'center table': 'coffee_table',
      'end table': 'end_table',
      'side table': 'end_table',
      'sofa': 'sofa',
      'couch': 'sofa',
    };

    const lower = name.toLowerCase().trim();
    return synonyms[lower] || lower.replace(/\s+/g, '_');
  }

  /**
   * 首字母大写格式化物品名称
   */
  private capitalizeItemName(name: string): string {
    return name
      .toLowerCase()
      .split(' ')
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  }

  /**
   * 提取家具腿颜色
   * 从描述中提取 leg color 相关信息
   * 如果没有找到则返回 undefined（不传递此字段）
   */
  private extractLegColor(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 常见腿部颜色
    const legColors = [
      'black', 'white', 'brown', 'gray', 'grey', 'tan', 'beige',
      'gold', 'silver', 'chrome', 'brass', 'bronze', 'copper',
      'walnut', 'oak', 'espresso', 'natural', 'dark brown', 'light brown',
    ];

    // 腿部颜色模式匹配
    const legColorPatterns = [
      // "black legs", "white leg"
      /(\w+(?:\s+\w+)?)\s+legs?(?!\s+(?:height|length|width))/i,
      // "legs in black", "leg in white"
      /legs?\s+in\s+(\w+(?:\s+\w+)?)/i,
      // "black metal legs", "chrome steel legs"
      /(\w+)\s+(?:metal|steel|wood|wooden)\s+legs?/i,
      // "legs: black", "leg color: white"
      /legs?\s*(?:color)?[:\s]+(\w+)/i,
    ];

    for (const pattern of legColorPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const colorCandidate = match[1].toLowerCase().trim();
        // 检查是否是有效颜色
        for (const color of legColors) {
          if (colorCandidate.includes(color)) {
            // 首字母大写
            return color.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
          }
        }
      }
    }

    return undefined;
  }

  /**
   * 提取家具腿表面处理方式
   * 从描述中提取 leg finish 相关信息
   * 如果没有找到则返回 undefined（不传递此字段）
   */
  private extractLegFinish(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 常见表面处理方式
    const finishTypes: Record<string, string> = {
      'glossy': 'Glossy',
      'gloss': 'Glossy',
      'high gloss': 'Glossy',
      'matte': 'Matte',
      'matt': 'Matte',
      'satin': 'Satin',
      'brushed': 'Brushed',
      'polished': 'Polished',
      'chrome': 'Chrome',
      'stainless steel': 'Stainless Steel',
      'powder coated': 'Powder Coated',
      'powder-coated': 'Powder Coated',
      'lacquered': 'Lacquered',
      'lacquer': 'Lacquered',
      'painted': 'Painted',
      'natural': 'Natural',
      'oiled': 'Oiled',
      'waxed': 'Waxed',
      'distressed': 'Distressed',
      'antiqued': 'Antiqued',
      'antique': 'Antiqued',
    };

    // 腿部表面处理模式匹配
    const legFinishPatterns = [
      // "glossy legs", "matte leg"
      /(\w+(?:[-\s]\w+)?)\s+(?:finish\s+)?legs?/i,
      // "legs with glossy finish"
      /legs?\s+(?:with\s+)?(\w+(?:[-\s]\w+)?)\s+finish/i,
      // "chrome legs", "stainless steel legs"
      /(chrome|stainless\s+steel|brushed|polished)\s+(?:\w+\s+)?legs?/i,
      // "powder coated legs"
      /(powder[-\s]?coated)\s+legs?/i,
    ];

    for (const pattern of legFinishPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const finishCandidate = match[1].toLowerCase().trim();
        // 检查是否是有效的表面处理方式
        for (const [keyword, finishName] of Object.entries(finishTypes)) {
          if (finishCandidate.includes(keyword)) {
            return finishName;
          }
        }
      }
    }

    return undefined;
  }

  /**
   * 提取家具腿材料
   * 从描述中提取 leg material 相关信息
   * 如果没有找到则返回 undefined（不传递此字段）
   */
  private extractLegMaterial(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 常见腿部材料
    const legMaterials: Record<string, string> = {
      // 木材
      'wood': 'Wood',
      'wooden': 'Wood',
      'solid wood': 'Solid Wood',
      'oak': 'Oak',
      'walnut': 'Walnut',
      'pine': 'Pine',
      'birch': 'Birch',
      'bamboo': 'Bamboo',
      'rubber wood': 'Rubber Wood',
      'rubberwood': 'Rubber Wood',
      // 金属
      'metal': 'Metal',
      'steel': 'Steel',
      'stainless steel': 'Stainless Steel',
      'iron': 'Iron',
      'aluminum': 'Aluminum',
      'aluminium': 'Aluminum',
      'chrome': 'Chrome',
      'brass': 'Brass',
      // 塑料
      'plastic': 'Plastic',
      'abs': 'ABS Plastic',
      'acrylic': 'Acrylic',
      // 其他
      'rubber': 'Rubber',
    };

    // 腿部材料模式匹配
    const legMaterialPatterns = [
      // "wood legs", "metal leg"
      /(\w+(?:\s+\w+)?)\s+legs?(?!\s+(?:height|length|width|color))/i,
      // "legs made of wood"
      /legs?\s+(?:made\s+)?(?:of|from)\s+(\w+(?:\s+\w+)?)/i,
      // "solid wood legs", "stainless steel legs"
      /(solid\s+wood|stainless\s+steel|rubber\s+wood)\s+legs?/i,
    ];

    for (const pattern of legMaterialPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const materialCandidate = match[1].toLowerCase().trim();
        // 按长度排序，优先匹配更长的词组
        const sortedMaterials = Object.entries(legMaterials).sort((a, b) => b[0].length - a[0].length);
        for (const [keyword, materialName] of sortedMaterials) {
          if (materialCandidate.includes(keyword)) {
            return materialName;
          }
        }
      }
    }

    return undefined;
  }

  /**
   * 从 SKU 生成制造商零件号（MPN）
   * 使用渠道 SKU，如果包含中文则转换为拼音或编码
   */
  private generateMpnFromSku(channelAttributes: Record<string, any>): string | undefined {
    const sku = getNestedValue(channelAttributes, 'sku');
    if (!sku) {
      return undefined;
    }

    const skuStr = String(sku).trim();
    if (!skuStr) {
      return undefined;
    }

    // 检查是否包含中文字符
    const chineseRegex = /[\u4e00-\u9fa5]/g;
    if (chineseRegex.test(skuStr)) {
      // 将中文字符转换为其 Unicode 编码
      // 例如：沙发123 -> SF6C995F123
      let result = '';
      for (const char of skuStr) {
        if (/[\u4e00-\u9fa5]/.test(char)) {
          // 中文字符转为 Unicode 编码的简短形式（取后4位的十六进制）
          const code = char.charCodeAt(0).toString(16).toUpperCase();
          result += code;
        } else {
          result += char;
        }
      }
      // 限制长度为60字符
      return result.substring(0, 60);
    }

    // 没有中文，直接返回 SKU（限制60字符）
    return skuStr.substring(0, 60);
  }

  /**
   * 提取客厅家具套装类型
   * 从描述中匹配对应的枚举值
   * @param param 默认值
   */
  private extractLivingRoomSetType(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'Living Room Set';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 套装类型映射（按优先级排序，更具体的在前）
    const setTypeMapping: Record<string, string> = {
      'l-shaped sectional': 'L-Shaped Sectional Sofa Set',
      'l shaped sectional': 'L-Shaped Sectional Sofa Set',
      'sectional sofa set': 'Sectional Sofa Living Room Set',
      'sectional living room': 'Sectional Sofa Living Room Set',
      'sectional set': 'Sectional Sofa Living Room Set',
      'reclining living room': 'Reclining Living Room Set',
      'reclining set': 'Reclining Living Room Set',
      'recliner set': 'Reclining Living Room Set',
      'sofa and loveseat': 'Sofa and Loveseat Set',
      'sofa & loveseat': 'Sofa and Loveseat Set',
      'couch and loveseat': 'Couch and Loveseat Set',
      'couch & loveseat': 'Couch and Loveseat Set',
      'sleeper living room': 'Sleeper Living Room Set',
      'sleeper set': 'Sleeper Living Room Set',
      'sleeper sofa set': 'Sleeper Living Room Set',
      'living room set': 'Living Room Set',
    };

    // 按关键词长度排序，优先匹配更长的词组
    const sortedMappings = Object.entries(setTypeMapping).sort((a, b) => b[0].length - a[0].length);
    for (const [keyword, setType] of sortedMappings) {
      if (text.includes(keyword)) {
        return setType;
      }
    }

    return defaultValue;
  }

  /**
   * 提取最大承重
   * 从描述中提取承重数值（磅）
   * 如果没有找到则返回 undefined
   */
  private extractMaxLoadWeight(channelAttributes: Record<string, any>): number | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 承重相关模式
    const loadPatterns = [
      // "max load: 300 lbs", "maximum load 500 lbs"
      /(?:max(?:imum)?|weight)\s*(?:load|capacity)[:\s]+(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?|kg)?/i,
      // "load capacity: 300 lbs"
      /load\s*capacity[:\s]+(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?|kg)?/i,
      // "supports up to 300 lbs"
      /supports?\s+(?:up\s+to\s+)?(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?|kg)/i,
      // "holds up to 300 lbs"
      /holds?\s+(?:up\s+to\s+)?(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?|kg)/i,
      // "300 lbs capacity", "300 lb weight capacity"
      /(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?)\s*(?:weight\s+)?capacity/i,
      // "capacity 300 lbs"
      /capacity[:\s]+(\d+(?:\.\d+)?)\s*(?:lbs?|pounds?|kg)/i,
    ];

    for (const pattern of loadPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const weight = parseFloat(match[1]);
        // 合理范围检查：10-10000 磅
        if (weight >= 10 && weight <= 10000) {
          // 检查是否是 kg，需要转换
          if (match[0].toLowerCase().includes('kg')) {
            return Math.round(weight * 2.20462);
          }
          return weight;
        }
      }
    }

    return undefined;
  }

  /**
   * 提取净含量声明
   * 从描述中提取净含量信息
   * 如果没有找到则返回 undefined
   */
  private extractNetContentStatement(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`;

    // 净含量模式
    const contentPatterns = [
      // "Net Weight: 50 lbs", "Net Wt: 50 lb"
      /net\s*(?:weight|wt\.?)[:\s]+(\d+(?:\.\d+)?)\s*(lbs?|kg|g|oz|pounds?)/i,
      // "Net Content: 1.98 Lb"
      /net\s*content[:\s]+(.+?)(?:\.|,|;|$)/i,
      // "Weight: 50 lbs" (单独的重量声明)
      /(?:^|[,;])\s*weight[:\s]+(\d+(?:\.\d+)?)\s*(lbs?|kg|g|oz|pounds?)/i,
    ];

    for (const pattern of contentPatterns) {
      const match = text.match(pattern);
      if (match) {
        if (match[2]) {
          // 有单位的情况
          const value = match[1];
          const unit = match[2];
          // 格式化单位
          let formattedUnit = unit.toLowerCase();
          if (formattedUnit === 'lbs' || formattedUnit === 'lb' || formattedUnit === 'pounds' || formattedUnit === 'pound') {
            formattedUnit = 'Lb';
          } else if (formattedUnit === 'kg') {
            formattedUnit = 'Kg';
          } else if (formattedUnit === 'g') {
            formattedUnit = 'g';
          } else if (formattedUnit === 'oz') {
            formattedUnit = 'Oz';
          }
          return `${value} ${formattedUnit}`;
        } else if (match[1]) {
          // 直接返回匹配的内容
          return match[1].trim();
        }
      }
    }

    return undefined;
  }

  /**
   * 提取图案
   * 从描述中提取图案，如果没有则使用颜色+主体格式
   */
  private extractPattern(channelAttributes: Record<string, any>): string[] {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 常见图案关键词
    const patterns: Record<string, string> = {
      'herringbone': 'Herringbone',
      'chevron': 'Chevron',
      'striped': 'Striped',
      'stripe': 'Striped',
      'plaid': 'Plaid',
      'checkered': 'Checkered',
      'checked': 'Checkered',
      'floral': 'Floral',
      'geometric': 'Geometric',
      'abstract': 'Abstract',
      'solid': 'Solid',
      'tufted': 'Tufted',
      'quilted': 'Quilted',
      'woven': 'Woven',
      'textured': 'Textured',
      'embossed': 'Embossed',
      'distressed': 'Distressed',
    };

    // 尝试匹配图案
    for (const [keyword, patternName] of Object.entries(patterns)) {
      if (text.includes(keyword)) {
        return [patternName];
      }
    }

    // 没有找到图案，使用颜色+主体格式
    const color = this.extractColor(channelAttributes) || 'Solid';
    const productTypes: Record<string, string> = {
      'sofa': 'Sofa', 'couch': 'Sofa', 'sectional': 'Sectional',
      'chair': 'Chair', 'table': 'Table', 'desk': 'Desk',
      'bed': 'Bed', 'dresser': 'Dresser', 'cabinet': 'Cabinet',
    };

    let productType = '';
    for (const [keyword, typeName] of Object.entries(productTypes)) {
      if (text.includes(keyword)) {
        productType = typeName;
        break;
      }
    }

    if (productType) {
      return [`${color} ${productType}`];
    }

    return ['Solid'];
  }

  /**
   * 从分类名称生成产品线
   */
  private extractProductLineFromCategory(
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): string[] {
    // 优先使用 context 中的分类名称
    if (context.categoryName) {
      return [context.categoryName];
    }

    // 从 customAttributes 中获取分类
    const category = getCustomAttributeValue(channelAttributes, 'categoryName') || 
                     getCustomAttributeValue(channelAttributes, 'category') || '';
    
    if (category) {
      return [String(category)];
    }

    // 默认返回 Furniture
    return ['Furniture'];
  }

  /**
   * 提取座椅靠背高度（英寸）
   */
  private extractSeatBackHeight(channelAttributes: Record<string, any>): number | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 座椅靠背高度模式
    const patterns = [
      // "seat back height: 12"", "seat back height 12 inches"
      /seat\s*back\s*height[:\s]+(\d+(?:\.\d+)?)\s*(?:"|in(?:ch(?:es)?)?)?/i,
      // "back height: 12"", "back height 12 inches"
      /back\s*height[:\s]+(\d+(?:\.\d+)?)\s*(?:"|in(?:ch(?:es)?)?)?/i,
      // "12" seat back", "12 inch seat back"
      /(\d+(?:\.\d+)?)\s*(?:"|in(?:ch(?:es)?)?)\s*seat\s*back/i,
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const height = parseFloat(match[1]);
        // 合理范围：5-50英寸
        if (height >= 5 && height <= 50) {
          return height;
        }
      }
    }

    return undefined;
  }

  /**
   * 提取座椅颜色
   */
  private extractSeatColor(channelAttributes: Record<string, any>): string[] | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 常见颜色
    const colors = [
      'black', 'white', 'brown', 'gray', 'grey', 'beige', 'tan',
      'blue', 'red', 'green', 'cream', 'ivory', 'charcoal',
    ];

    // 座椅颜色模式
    const seatColorPatterns = [
      // "black seat", "gray seat cushion"
      /(\w+)\s+seat(?:\s+cushion)?/i,
      // "seat color: black", "seat in black"
      /seat\s+(?:color[:\s]+|in\s+)(\w+)/i,
    ];

    for (const pattern of seatColorPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const colorCandidate = match[1].toLowerCase();
        for (const color of colors) {
          if (colorCandidate === color) {
            return [color.charAt(0).toUpperCase() + color.slice(1)];
          }
        }
      }
    }

    return undefined;
  }

  /**
   * 提取座椅高度（英寸）
   */
  private extractSeatHeight(channelAttributes: Record<string, any>): number | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 座椅高度模式
    const patterns = [
      // "seat height: 18"", "seat height 18 inches"
      /seat\s*height[:\s]+(\d+(?:\.\d+)?)\s*(?:"|in(?:ch(?:es)?)?)?/i,
      // "18" seat height"
      /(\d+(?:\.\d+)?)\s*(?:"|in(?:ch(?:es)?)?)\s*seat\s*height/i,
      // "height from floor to seat: 18""
      /(?:height\s+)?from\s+floor\s+to\s+seat[:\s]+(\d+(?:\.\d+)?)/i,
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const height = parseFloat(match[1]);
        // 合理范围：10-40英寸
        if (height >= 10 && height <= 40) {
          return height;
        }
      }
    }

    return undefined;
  }

  /**
   * 提取座椅材料
   */
  private extractSeatMaterial(channelAttributes: Record<string, any>): string[] | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 座椅材料关键词
    const seatMaterials: Record<string, string> = {
      'leather': 'Leather',
      'genuine leather': 'Leather',
      'faux leather': 'Faux Leather',
      'pu leather': 'Faux Leather',
      'bonded leather': 'Faux Leather',
      'fabric': 'Fabric',
      'velvet': 'Velvet',
      'linen': 'Linen',
      'cotton': 'Cotton',
      'polyester': 'Polyester',
      'microfiber': 'Microfiber',
      'memory foam': 'Memory Foam',
      'foam': 'Foam',
      'wood': 'Wood',
      'wooden': 'Wood',
      'metal': 'Metal',
      'wicker': 'Wicker',
      'rattan': 'Rattan',
      'mesh': 'Mesh',
    };

    // 座椅材料模式
    const seatMaterialPatterns = [
      // "leather seat", "fabric seat cushion"
      /(\w+(?:\s+\w+)?)\s+seat(?:\s+cushion)?/i,
      // "seat material: leather"
      /seat\s+material[:\s]+(\w+(?:\s+\w+)?)/i,
      // "seat made of leather"
      /seat\s+(?:made\s+)?(?:of|from)\s+(\w+(?:\s+\w+)?)/i,
    ];

    for (const pattern of seatMaterialPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const materialCandidate = match[1].toLowerCase();
        const sortedMaterials = Object.entries(seatMaterials).sort((a, b) => b[0].length - a[0].length);
        for (const [keyword, materialName] of sortedMaterials) {
          if (materialCandidate.includes(keyword)) {
            return [materialName];
          }
        }
      }
    }

    return undefined;
  }

  /**
   * 提取是否软包
   * @param param 默认值
   */
  private extractUpholstered(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'No';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 软包相关关键词
    const upholsteredKeywords = [
      'upholstered', 'upholstery', 'padded', 'cushioned', 'tufted',
      'fabric', 'velvet', 'leather', 'faux leather', 'linen',
      'microfiber', 'polyester', 'cotton',
    ];

    for (const keyword of upholsteredKeywords) {
      if (text.includes(keyword)) {
        return 'Yes';
      }
    }

    return defaultValue;
  }

  /**
   * 提取是否含电子元件
   * @param param 默认值
   */
  private extractElectronicsIndicator(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'No';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();

    // 电子元件相关关键词
    const electronicsKeywords = [
      'electric', 'electronic', 'powered', 'motorized', 'motor',
      'usb', 'led', 'light', 'bluetooth', 'wireless', 'rechargeable',
      'battery', 'power recliner', 'power lift', 'massage',
      'heated', 'heating', 'smart', 'remote control',
    ];

    for (const keyword of electronicsKeywords) {
      if (text.includes(keyword)) {
        return 'Yes';
      }
    }

    return defaultValue;
  }

  /**
   * 生成日期（基于当前日期偏移指定天数）
   * @param param 偏移天数，负数为往前，正数为往后
   * @returns 格式：yyyy-mm-dd
   */
  private generateDateWithOffset(param: string | undefined): string {
    const offsetDays = parseInt(param || '0', 10) || 0;
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);
    return this.formatDateToYYYYMMDD(date);
  }

  /**
   * 生成日期（基于当前日期偏移指定年数）
   * @param param 偏移年数，正数为往后
   * @returns 格式：yyyy-mm-dd
   */
  private generateDateWithYearOffset(param: string | undefined): string {
    const offsetYears = parseInt(param || '0', 10) || 0;
    const date = new Date();
    date.setFullYear(date.getFullYear() + offsetYears);
    return this.formatDateToYYYYMMDD(date);
  }

  /**
   * 格式化日期为 yyyy-mm-dd 格式
   */
  private formatDateToYYYYMMDD(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  /**
   * 将重量转换为磅（lbs）
   * @param weight 重量数值
   * @param unit 单位（lb, kg, g, oz）
   */
  private convertWeightToLbs(weight: number, unit: string): number {
    const unitLower = unit.toLowerCase();
    let lbs: number;

    if (unitLower === 'kg' || unitLower === 'kilogram' || unitLower === 'kilograms') {
      // 1 kg = 2.20462 lbs
      lbs = weight * 2.20462;
    } else if (unitLower === 'g' || unitLower === 'gram' || unitLower === 'grams') {
      // 1 g = 0.00220462 lbs
      lbs = weight * 0.00220462;
    } else if (unitLower === 'oz' || unitLower === 'ounce' || unitLower === 'ounces') {
      // 1 oz = 0.0625 lbs
      lbs = weight * 0.0625;
    } else {
      // 默认为磅
      lbs = weight;
    }

    // 保留三位小数（符合 API 要求的 multipleOf: 0.001）
    return Math.round(lbs * 1000) / 1000;
  }
}
