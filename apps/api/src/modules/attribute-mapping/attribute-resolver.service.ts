import { Injectable, Logger } from '@nestjs/common';
import { UpcService } from '@/modules/upc/upc.service';
import { PrismaService } from '@/common/prisma/prisma.service';
import { getNestedValue, getCustomAttributeValue } from '@/adapters/channels/standard-product.utils';
import {
  MappingRule,
  MappingRulesConfig,
  ResolveContext,
  ResolveResult,
  AutoGenerateConfig,
} from './interfaces/mapping-rule.interface';
import * as compromise from 'compromise';
const nlp = (compromise as any).default || compromise;

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
   * - auto_generate: 根据规则自动生成（LLM 批量提取 + 本地规则兜底）
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
        // 跳过没有配置值的规则
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
   * 使用本地规则提取属性
   */
  private resolveAutoGenerate(
    config: AutoGenerateConfig,
    channelAttributes: Record<string, any>,
    context: ResolveContext,
  ): any {
    const { ruleType, param } = config;

    switch (ruleType) {
      // 提取类规则
      case 'color_extract':
        return this.extractColor(channelAttributes);
      case 'material_extract':
        return this.extractMaterial(channelAttributes);
      case 'location_extract':
        return this.extractLocation(param, channelAttributes);
      case 'piece_count_extract':
        return this.extractPieceCount(param, channelAttributes);
      case 'seating_capacity_extract':
        return this.extractSeatingCapacity(param, channelAttributes);
      case 'collection_extract':
        return this.extractCollection(channelAttributes);
      case 'color_category_extract':
        return this.extractColorCategory(param, channelAttributes);
      case 'home_decor_style_extract':
        return this.extractHomeDecorStyle(param, channelAttributes);
      case 'items_included_extract':
        return this.extractItemsIncluded(channelAttributes);
      case 'features_extract':
        return this.extractFeatures(channelAttributes);
      case 'pattern_extract':
        return this.extractPattern(channelAttributes);
      case 'country_of_origin_extract':
        return this.extractCountryOfOrigin(channelAttributes);
      case 'country_of_origin_textiles_extract':
        return this.extractCountryOfOriginTextiles(channelAttributes);
      case 'max_load_weight_extract':
        return this.extractMaxLoadWeight(channelAttributes);
      case 'leg_color_extract':
        return this.extractLegColor(channelAttributes);
      case 'leg_material_extract':
        return this.extractLegMaterial(channelAttributes);
      case 'seat_material_extract':
        return this.extractSeatMaterial(channelAttributes);
      case 'upholstered_extract':
        return this.extractUpholstered(param, channelAttributes);
      case 'electronics_indicator_extract':
        return this.extractElectronicsIndicator(param, channelAttributes);
      case 'finish_extract':
        return this.extractFinish(param, channelAttributes);
      case 'is_temperature_sensitive_extract':
        return this.extractIsTemperatureSensitive(param, channelAttributes);
      case 'size_extract':
        return this.extractSize(channelAttributes);
      case 'bed_size_extract':
        return this.extractBedSize(channelAttributes);
      case 'number_of_drawers_extract':
        return this.extractNumberOfDrawers(param, channelAttributes);
      case 'number_of_shelves_extract':
        return this.extractNumberOfShelves(param, channelAttributes);
      case 'theme_extract':
        return this.extractTheme(param, channelAttributes);
      case 'shape_extract':
        return this.extractShape(param, channelAttributes);
      case 'diameter_extract':
        return this.extractDiameter(channelAttributes);
      case 'bed_style_extract':
        return this.extractBedStyle(param, channelAttributes);
      case 'mount_type_extract':
        return this.extractMountType(param, channelAttributes);
      case 'fabric_content_extract':
        return this.extractFabricContent(channelAttributes);
      case 'configuration_extract':
        return this.extractConfiguration(channelAttributes);
      case 'fabric_color_extract':
        return this.extractFabricColor(channelAttributes);
      case 'accent_color_extract':
        return this.extractAccentColor(channelAttributes);
      case 'cushion_color_extract':
        return this.extractCushionColor(channelAttributes);
      case 'number_of_panels_extract':
        return this.extractNumberOfPanels(channelAttributes);
      case 'seat_back_style_extract':
        return this.extractSeatBackStyle(channelAttributes);
      case 'power_type_extract':
        return this.extractPowerType(channelAttributes);
      case 'is_powered_extract':
        return this.extractIsPowered(channelAttributes);
      case 'recommended_uses_extract':
        return this.extractRecommendedUses(channelAttributes);
      case 'recommended_rooms_extract':
        return this.extractRecommendedRooms(channelAttributes);
      case 'mattress_firmness_extract':
        return this.extractMattressFirmness(channelAttributes);
      case 'mattress_thickness_extract':
        return this.extractMattressThickness(channelAttributes);
      case 'pump_included_extract':
        return this.extractPumpIncluded(channelAttributes);
      case 'fill_material_extract':
        return this.extractFillMaterial(channelAttributes);
      case 'frame_material_extract':
        return this.extractFrameMaterial(channelAttributes);
      case 'seat_material_extract':
        return this.extractSeatMaterialAuto(channelAttributes);
      case 'table_height_extract':
        return this.extractTableHeight(channelAttributes);
      case 'top_material_extract':
        return this.extractTopMaterial(channelAttributes);
      case 'top_dimensions_extract':
        return this.extractTopDimensions(channelAttributes);
      case 'top_finish_extract':
        return this.extractTopFinish(channelAttributes);
      case 'hardware_finish_extract':
        return this.extractHardwareFinish(channelAttributes);
      case 'base_material_extract':
        return this.extractBaseMaterial(channelAttributes);
      case 'base_color_extract':
        return this.extractBaseColor(channelAttributes);
      case 'base_finish_extract':
        return this.extractBaseFinish(channelAttributes);
      case 'door_opening_style_extract':
        return this.extractDoorOpeningStyle(channelAttributes);
      case 'door_style_extract':
        return this.extractDoorStyle(channelAttributes);
      case 'slat_width_extract':
        return this.extractSlatWidth(channelAttributes);
      case 'number_of_hooks_extract':
        return this.extractNumberOfHooks(channelAttributes);
      case 'headboard_style_extract':
        return this.extractHeadboardStyle(channelAttributes);
      case 'frame_color_extract':
        return this.extractFrameColor(channelAttributes);
      case 'is_smart_extract':
        return this.extractIsSmart(channelAttributes);
      case 'is_antique_extract':
        return this.extractIsAntique(channelAttributes);
      case 'is_foldable_extract':
        return this.extractIsFoldable(channelAttributes);
      case 'is_inflatable_extract':
        return this.extractIsInflatable(channelAttributes);
      case 'is_wheeled_extract':
        return this.extractIsWheeled(channelAttributes);
      case 'is_industrial_extract':
        return this.extractIsIndustrial(channelAttributes);
      case 'assembled_product_length_extract':
        return this.extractAssembledProductLength(channelAttributes);
      case 'assembled_product_width_extract':
        return this.extractAssembledProductWidth(channelAttributes);
      case 'assembled_product_height_extract':
        return this.extractAssembledProductHeight(channelAttributes);
      case 'assembled_product_weight_extract':
        return this.extractAssembledProductWeight(channelAttributes);

      // 其他规则
      case 'sku_prefix':
        return `${param || ''}${context.productSku || ''}`;

      case 'sku_suffix':
        return `${context.productSku || ''}${param || ''}`;

      case 'brand_title':
        const brand = getCustomAttributeValue(channelAttributes, 'brand') || '';
        const title = getNestedValue(channelAttributes, 'title') || '';
        return `${brand} ${title}`.trim();

      case 'first_characteristic':
        const characteristics = getCustomAttributeValue(channelAttributes, 'characteristics');
        return Array.isArray(characteristics) ? characteristics[0] : undefined;

      case 'first_bullet_point':
        const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints');
        return Array.isArray(bulletPoints) ? bulletPoints[0] : undefined;

      case 'current_date':
        return this.formatDate(new Date(), param || 'YYYY-MM-DD');

      case 'uuid':
        return this.generateUUID();

      case 'field_with_fallback':
        return this.resolveFieldWithFallback(param, channelAttributes);

      case 'price_calculate':
        return this.calculatePrice(channelAttributes, context);

      case 'shipping_weight_extract':
        return this.extractShippingWeight(param, channelAttributes);

      case 'leg_finish_extract':
        return this.extractLegFinish(channelAttributes);

      case 'mpn_from_sku':
        return this.generateMpnFromSku(channelAttributes);

      case 'living_room_set_type_extract':
        return this.extractLivingRoomSetType(param, channelAttributes);

      case 'net_content_statement_extract':
        return this.extractNetContentStatement(channelAttributes);

      case 'product_line_from_category':
        return this.extractProductLineFromCategory(channelAttributes, context);

      case 'seat_back_height_extract':
        return this.extractSeatBackHeight(channelAttributes);

      case 'seat_color_extract':
        return this.extractSeatColor(channelAttributes);

      case 'seat_height_extract':
        return this.extractSeatHeight(channelAttributes);

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
   * 提取附加功能/特色
   * 使用 NLP 从描述中智能提取产品的功能特性，返回数组格式
   */
  private extractFeatures(channelAttributes: Record<string, any>): string[] {
    const features: Set<string> = new Set();
    const title = getNestedValue(channelAttributes, 'title') || '';
    const rawDescription = getNestedValue(channelAttributes, 'description') || '';
    
    // 清理 HTML 标签和 CSS 样式
    const description = this.stripHtmlTags(rawDescription);
    const text = `${title} ${description}`;

    if (!text.trim()) {
      return [];
    }

    const doc = nlp(text);

    // 1. 提取形容词+名词短语（如 "adjustable height", "ergonomic design"）
    const adjNouns = doc.match('#Adjective #Noun').out('array') as string[];
    adjNouns.forEach((phrase: string) => {
      const cleaned = phrase.trim();
      if (cleaned.length > 3 && cleaned.length < 50) {
        // 首字母大写
        features.add(cleaned.charAt(0).toUpperCase() + cleaned.slice(1));
      }
    });

    // 2. 提取带有功能性动词的短语（如 "folds flat", "reclines back"）
    const verbPhrases = doc.match('(#Verb|#Gerund) (#Adverb|#Adjective|#Noun)?').out('array') as string[];
    const functionalVerbs = ['fold', 'recline', 'adjust', 'rotate', 'swivel', 'tilt', 'extend', 'expand', 'convert', 'store'];
    verbPhrases.forEach((phrase: string) => {
      const lower = phrase.toLowerCase();
      if (functionalVerbs.some(v => lower.includes(v))) {
        features.add(phrase.charAt(0).toUpperCase() + phrase.slice(1).trim());
      }
    });

    // 3. 提取复合名词（如 "cup holder", "USB port", "lumbar support"）
    const compoundNouns = doc.match('#Noun #Noun').out('array') as string[];
    compoundNouns.forEach((phrase: string) => {
      const cleaned = phrase.trim();
      if (cleaned.length > 5 && cleaned.length < 40) {
        features.add(cleaned.charAt(0).toUpperCase() + cleaned.slice(1));
      }
    });

    // 4. 关键功能词直接匹配（作为补充）
    const keyFeatures = [
      'waterproof', 'water-resistant', 'stain-resistant', 'scratch-resistant',
      'rust-resistant', 'anti-slip', 'non-slip', 'ergonomic', 'portable',
      'lightweight', 'foldable', 'collapsible', 'adjustable', 'removable',
      'reversible', 'convertible', 'modular', 'stackable', 'breathable',
    ];
    const lowerText = text.toLowerCase();
    keyFeatures.forEach(kw => {
      if (lowerText.includes(kw)) {
        features.add(kw.charAt(0).toUpperCase() + kw.slice(1).replace(/-/g, ' '));
      }
    });

    // 返回数组，最多10个特性，过滤掉太短或无意义的
    return Array.from(features)
      .filter(f => f.length > 3 && !/^\d+$/.test(f))
      .slice(0, 10);
  }

  /**
   * 提取原产国
   * 优先从 placeOfOrigin 字段匹配，默认返回 "CN - China"
   * 返回格式：Walmart 枚举格式 "XX - Country Name"
   */
  private extractCountryOfOrigin(channelAttributes: Record<string, any>): string {
    const defaultValue = 'CN - China';
    
    // 从 placeOfOrigin 字段获取产地信息
    const placeOfOrigin = getNestedValue(channelAttributes, 'placeOfOrigin');
    
    if (!placeOfOrigin) {
      return defaultValue;
    }
    
    const origin = String(placeOfOrigin).toLowerCase().trim();
    
    // 常见国家/地区名称到 Walmart 枚举格式的映射
    const countryMapping: Record<string, string> = {
      // 中国
      'china': 'CN - China',
      'cn': 'CN - China',
      'chinese': 'CN - China',
      '中国': 'CN - China',
      'mainland china': 'CN - China',
      'prc': 'CN - China',
      
      // 美国
      'usa': 'US - United States',
      'us': 'US - United States',
      'united states': 'US - United States',
      'america': 'US - United States',
      '美国': 'US - United States',
      
      // 加拿大
      'canada': 'CA - Canada',
      'ca': 'CA - Canada',
      '加拿大': 'CA - Canada',
      
      // 墨西哥
      'mexico': 'MX - Mexico',
      'mx': 'MX - Mexico',
      '墨西哥': 'MX - Mexico',
      
      // 日本
      'japan': 'JP - Japan',
      'jp': 'JP - Japan',
      '日本': 'JP - Japan',
      
      // 韩国
      'korea': 'KR - Korea, Republic of',
      'south korea': 'KR - Korea, Republic of',
      'kr': 'KR - Korea, Republic of',
      '韩国': 'KR - Korea, Republic of',
      
      // 台湾
      'taiwan': 'TW - Taiwan',
      'tw': 'TW - Taiwan',
      '台湾': 'TW - Taiwan',
      
      // 香港
      'hong kong': 'HK - Hong Kong',
      'hk': 'HK - Hong Kong',
      '香港': 'HK - Hong Kong',
      
      // 越南
      'vietnam': 'VN - Vietnam',
      'vn': 'VN - Vietnam',
      '越南': 'VN - Vietnam',
      
      // 印度
      'india': 'IN - India',
      'in': 'IN - India',
      '印度': 'IN - India',
      
      // 印度尼西亚
      'indonesia': 'ID - Indonesia',
      'id': 'ID - Indonesia',
      '印度尼西亚': 'ID - Indonesia',
      '印尼': 'ID - Indonesia',
      
      // 马来西亚
      'malaysia': 'MY - Malaysia',
      'my': 'MY - Malaysia',
      '马来西亚': 'MY - Malaysia',
      
      // 泰国
      'thailand': 'TH - Thailand',
      'th': 'TH - Thailand',
      '泰国': 'TH - Thailand',
      
      // 菲律宾
      'philippines': 'PH - Philippines',
      'ph': 'PH - Philippines',
      '菲律宾': 'PH - Philippines',
      
      // 德国
      'germany': 'DE - Germany',
      'de': 'DE - Germany',
      '德国': 'DE - Germany',
      
      // 英国
      'uk': 'GB - United Kingdom',
      'united kingdom': 'GB - United Kingdom',
      'britain': 'GB - United Kingdom',
      'england': 'GB - United Kingdom',
      'gb': 'GB - United Kingdom',
      '英国': 'GB - United Kingdom',
      
      // 法国
      'france': 'FR - France',
      'fr': 'FR - France',
      '法国': 'FR - France',
      
      // 意大利
      'italy': 'IT - Italy',
      'it': 'IT - Italy',
      '意大利': 'IT - Italy',
      
      // 西班牙
      'spain': 'ES - Spain',
      'es': 'ES - Spain',
      '西班牙': 'ES - Spain',
      
      // 荷兰
      'netherlands': 'NL - Netherlands',
      'nl': 'NL - Netherlands',
      'holland': 'NL - Netherlands',
      '荷兰': 'NL - Netherlands',
      
      // 波兰
      'poland': 'PL - Poland',
      'pl': 'PL - Poland',
      '波兰': 'PL - Poland',
      
      // 土耳其
      'turkey': 'TR - Turkey',
      'tr': 'TR - Turkey',
      '土耳其': 'TR - Turkey',
      
      // 巴西
      'brazil': 'BR - Brazil',
      'br': 'BR - Brazil',
      '巴西': 'BR - Brazil',
      
      // 澳大利亚
      'australia': 'AU - Australia',
      'au': 'AU - Australia',
      '澳大利亚': 'AU - Australia',
      '澳洲': 'AU - Australia',
      
      // 新西兰
      'new zealand': 'NZ - New Zealand',
      'nz': 'NZ - New Zealand',
      '新西兰': 'NZ - New Zealand',
      
      // 俄罗斯
      'russia': 'RU - Russian Federation',
      'ru': 'RU - Russian Federation',
      '俄罗斯': 'RU - Russian Federation',
      
      // 孟加拉国
      'bangladesh': 'BD - Bangladesh',
      'bd': 'BD - Bangladesh',
      '孟加拉': 'BD - Bangladesh',
      
      // 巴基斯坦
      'pakistan': 'PK - Pakistan',
      'pk': 'PK - Pakistan',
      '巴基斯坦': 'PK - Pakistan',
      
      // 斯里兰卡
      'sri lanka': 'LK - Sri Lanka',
      'lk': 'LK - Sri Lanka',
      '斯里兰卡': 'LK - Sri Lanka',
      
      // 柬埔寨
      'cambodia': 'KH - Cambodia',
      'kh': 'KH - Cambodia',
      '柬埔寨': 'KH - Cambodia',
      
      // 缅甸
      'myanmar': 'MM - Myanmar',
      'burma': 'MM - Myanmar',
      'mm': 'MM - Myanmar',
      '缅甸': 'MM - Myanmar',
    };
    
    // 精确匹配
    if (countryMapping[origin]) {
      return countryMapping[origin];
    }
    
    // 模糊匹配：检查是否包含关键词
    for (const [keyword, value] of Object.entries(countryMapping)) {
      if (origin.includes(keyword) || keyword.includes(origin)) {
        return value;
      }
    }
    
    // 如果输入已经是 "XX - Country" 格式，直接返回
    if (/^[A-Z]{2}\s*-\s*.+/.test(placeOfOrigin)) {
      return placeOfOrigin;
    }
    
    // 无法匹配，返回默认值
    return defaultValue;
  }

  /**
   * 提取纺织品原产国
   * 优先从 placeOfOrigin 字段匹配，默认返回 "Imported"
   * 枚举值：["USA and Imported", "Imported", "USA", "USA or Imported"]
   */
  private extractCountryOfOriginTextiles(channelAttributes: Record<string, any>): string {
    const defaultValue = 'Imported';
    
    // 从 placeOfOrigin 字段获取产地信息
    const placeOfOrigin = getNestedValue(channelAttributes, 'placeOfOrigin');
    
    if (!placeOfOrigin) {
      return defaultValue;
    }
    
    const origin = String(placeOfOrigin).toLowerCase().trim();
    
    // 美国相关关键词
    const usaKeywords = ['usa', 'us', 'united states', 'america', '美国'];
    
    // 检查是否是美国制造
    const isUSA = usaKeywords.some(keyword => origin.includes(keyword));
    
    if (isUSA) {
      // 检查是否同时包含进口成分
      const importedKeywords = ['imported', 'import', 'china', 'vietnam', 'india', 'mexico', 'foreign'];
      const hasImported = importedKeywords.some(keyword => origin.includes(keyword));
      
      if (hasImported) {
        // 同时包含美国和进口
        if (origin.includes(' or ')) {
          return 'USA or Imported';
        }
        return 'USA and Imported';
      }
      
      // 纯美国制造
      return 'USA';
    }
    
    // 非美国制造，返回 Imported
    return defaultValue;
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

    // 电子元件相关关键词（更精确的匹配）
    // 注意：避免误匹配 "light luxury"（轻奢）等非电子含义的词
    const electronicsKeywords = [
      'electric', 'electronic', 'powered', 'motorized', 'motor',
      'usb port', 'usb charging', 'usb outlet',
      'led light', 'led strip', 'led lamp', 'with led', 'built-in led',
      'bluetooth', 'wireless charging', 'rechargeable',
      'battery powered', 'battery operated', 'batteries included',
      'power recliner', 'power lift', 'power outlet',
      'massage chair', 'massage function', 'heated seat', 'heating pad',
      'smart home', 'smart furniture', 'remote control', 'with remote',
      'speaker', 'speakers', 'sound system',
    ];

    for (const keyword of electronicsKeywords) {
      if (text.includes(keyword)) {
        return 'Yes';
      }
    }

    return defaultValue;
  }

  /**
   * 提取表面处理/涂层（Finish）
   * 从描述和渠道属性中提取产品的表面处理方式
   * @param param 默认值，如果提取不到则返回此值（默认 New）
   */
  private extractFinish(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'New';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const material = getNestedValue(channelAttributes, 'material') || '';
    const color = getNestedValue(channelAttributes, 'color') || '';
    
    // 清理 HTML
    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc} ${material} ${color}`.toLowerCase();

    // 表面处理关键词映射（按优先级排序）
    const finishKeywords: Record<string, string> = {
      // 光泽度相关
      'high gloss': 'High Gloss',
      'glossy': 'Glossy',
      'gloss': 'Glossy',
      'matte': 'Matte',
      'matt': 'Matte',
      'satin': 'Satin',
      'semi-gloss': 'Semi-Gloss',
      'semi gloss': 'Semi-Gloss',
      
      // 木材处理
      'lacquered': 'Lacquered',
      'lacquer': 'Lacquered',
      'varnished': 'Varnished',
      'varnish': 'Varnished',
      'stained': 'Stained',
      'painted': 'Painted',
      'oiled': 'Oiled',
      'waxed': 'Waxed',
      'distressed': 'Distressed',
      'weathered': 'Weathered',
      'rustic': 'Rustic',
      'natural': 'Natural',
      'unfinished': 'Unfinished',
      
      // 金属处理
      'polished': 'Polished',
      'brushed': 'Brushed',
      'chrome': 'Chrome',
      'nickel': 'Nickel',
      'brass': 'Brass',
      'bronze': 'Bronze',
      'powder coated': 'Powder Coated',
      'powder-coated': 'Powder Coated',
      'anodized': 'Anodized',
      'galvanized': 'Galvanized',
      'oxidized': 'Oxidized',
      'antique': 'Antique',
      
      // 纹理相关
      'textured': 'Textured',
      'smooth': 'Smooth',
      'embossed': 'Embossed',
      'hammered': 'Hammered',
      
      // 其他
      'laminated': 'Laminated',
      'veneer': 'Veneer',
      'melamine': 'Melamine',
    };

    // 按关键词长度降序排列，优先匹配更长的词组
    const sortedKeywords = Object.keys(finishKeywords).sort((a, b) => b.length - a.length);
    
    for (const keyword of sortedKeywords) {
      if (text.includes(keyword)) {
        return finishKeywords[keyword];
      }
    }

    return defaultValue;
  }

  /**
   * 提取是否温度敏感（Is Temperature-Sensitive）
   * 从描述中检测是否需要特殊温度存储
   * @param param 默认值（默认 No）
   */
  private extractIsTemperatureSensitive(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'No';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
    
    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 温度敏感关键词
    const temperatureSensitiveKeywords = [
      'refrigerat',      // refrigerate, refrigerated, refrigeration
      'frozen',
      'freeze',
      'cold storage',
      'keep cold',
      'keep cool',
      'temperature sensitive',
      'temperature-sensitive',
      'perishable',
      'room temperature',
      'do not freeze',
      'store at',
      'keep at',
      'below',
      'above',
      '°c',
      '°f',
      'degrees',
    ];

    for (const keyword of temperatureSensitiveKeywords) {
      if (text.includes(keyword)) {
        return 'Yes';
      }
    }

    return defaultValue;
  }

  /**
   * 提取产品尺寸（Size）
   * 从商品信息中提取整体尺寸
   * 如果没有找到则返回 undefined（不传递此字段）
   */
  private extractSize(channelAttributes: Record<string, any>): string | undefined {
    // 优先从渠道属性中获取
    const sizeAttr = getCustomAttributeValue(channelAttributes, 'size');
    if (sizeAttr) return String(sizeAttr);

    // 尝试从 dimensions 提取
    const dimensions = getCustomAttributeValue(channelAttributes, 'dimensions');
    if (dimensions) {
      if (typeof dimensions === 'string') return dimensions;
      if (typeof dimensions === 'object') {
        const { length, width, height, unit } = dimensions;
        if (length && width && height) {
          const unitStr = unit || 'in';
          return `${length} x ${width} x ${height} ${unitStr}`;
        }
      }
    }

    // 尝试从 overallDimensions 提取
    const overallDimensions = getCustomAttributeValue(channelAttributes, 'overallDimensions');
    if (overallDimensions) return String(overallDimensions);

    // 尝试从描述中提取尺寸模式
    const description = getNestedValue(channelAttributes, 'description') || '';
    const cleanDesc = this.stripHtmlTags(description);
    
    // 匹配常见尺寸格式：L x W x H 或 Length x Width x Height
    const sizePattern = /(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(inch|in|cm|mm|"|\')?/i;
    const match = cleanDesc.match(sizePattern);
    if (match) {
      const unit = match[4] || 'in';
      return `${match[1]} x ${match[2]} x ${match[3]} ${unit}`;
    }

    return undefined; // 不传递此字段
  }

  /**
   * 提取床尺寸（Bed Size）
   * 从标题和描述中提取加拿大标准床尺寸
   * 如果没有找到则返回 undefined（不传递此字段）
   * 
   * 注意：只在床相关产品中匹配，避免误匹配
   */
  private extractBedSize(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc}`.toLowerCase();

    // 首先检查是否是床相关产品（必须包含床相关关键词才匹配尺寸）
    const bedRelatedKeywords = ['bed', 'mattress', 'bedding', 'bedframe', 'headboard', 'footboard', 'bed frame', 'bedroom'];
    const isBedRelated = bedRelatedKeywords.some(k => text.includes(k));
    
    // 如果不是床相关产品，直接返回 undefined
    if (!isBedRelated) {
      return undefined;
    }

    // 加拿大/北美标准床尺寸（精确匹配）
    const bedSizes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['california king', 'cal king', 'cal-king'], value: 'California King' },
      { keywords: ['king size', 'king-size', 'king bed', 'king mattress'], value: 'King' },
      { keywords: ['queen size', 'queen-size', 'queen bed', 'queen mattress'], value: 'Queen' },
      { keywords: ['full size', 'full-size', 'full bed', 'full mattress', 'double bed', 'double size', 'double mattress'], value: 'Full' },
      { keywords: ['twin xl', 'twin-xl', 'twin extra long'], value: 'Twin XL' },
      { keywords: ['twin size', 'twin-size', 'twin bed', 'twin mattress', 'single bed', 'single size', 'single mattress'], value: 'Twin' },
    ];

    // 匹配精确的尺寸短语
    for (const size of bedSizes) {
      for (const keyword of size.keywords) {
        if (text.includes(keyword)) {
          return size.value;
        }
      }
    }

    return undefined; // 不传递此字段
  }

  /**
   * 提取抽屉数量（Number of Drawers）
   * 从描述中提取家具的抽屉数量
   * @param param 默认值（默认 0）
   */
  private extractNumberOfDrawers(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): number {
    const defaultValue = parseInt(param || '0', 10) || 0;
    
    // 优先从渠道属性获取
    const drawersAttr = getCustomAttributeValue(channelAttributes, 'numberOfDrawers');
    if (drawersAttr !== undefined && drawersAttr !== null) {
      const num = parseInt(String(drawersAttr), 10);
      if (!isNaN(num)) return num;
    }

    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
    
    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 匹配数字 + drawer(s) 模式
    const patterns = [
      /(\d+)\s*(?:storage\s+)?drawers?/i,
      /(\d+)\s*(?:pull[- ]?out\s+)?drawers?/i,
      /drawers?[:\s]+(\d+)/i,
      /with\s+(\d+)\s+drawers?/i,
      /includes?\s+(\d+)\s+drawers?/i,
      /features?\s+(\d+)\s+drawers?/i,
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match) {
        const num = parseInt(match[1], 10);
        if (!isNaN(num) && num > 0 && num <= 20) {
          return num;
        }
      }
    }

    // 匹配文字数字
    const wordNumbers: Record<string, number> = {
      'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5,
      'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10,
    };

    for (const [word, num] of Object.entries(wordNumbers)) {
      if (text.includes(`${word} drawer`)) {
        return num;
      }
    }

    return defaultValue;
  }

  /**
   * 提取搁板数量（Number of Shelves）
   * 从描述中提取家具的搁板/层板数量
   * @param param 默认值（默认 0）
   */
  private extractNumberOfShelves(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): number {
    const defaultValue = parseInt(param || '0', 10) || 0;
    
    // 优先从渠道属性获取
    const shelvesAttr = getCustomAttributeValue(channelAttributes, 'numberOfShelves');
    if (shelvesAttr !== undefined && shelvesAttr !== null) {
      const num = parseInt(String(shelvesAttr), 10);
      if (!isNaN(num)) return num;
    }

    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];
    
    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 匹配数字 + shelf/shelves 模式
    const patterns = [
      /(\d+)\s*(?:adjustable\s+)?shelv(?:es|ing)/i,
      /(\d+)\s*(?:open\s+)?shelv(?:es|ing)/i,
      /(\d+)\s*(?:storage\s+)?shelv(?:es|ing)/i,
      /(\d+)\s*(?:tier|level)s?/i,
      /shelv(?:es|ing)[:\s]+(\d+)/i,
      /with\s+(\d+)\s+shelv(?:es|ing)/i,
      /includes?\s+(\d+)\s+shelv(?:es|ing)/i,
      /features?\s+(\d+)\s+shelv(?:es|ing)/i,
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match) {
        const num = parseInt(match[1], 10);
        if (!isNaN(num) && num > 0 && num <= 20) {
          return num;
        }
      }
    }

    // 匹配文字数字
    const wordNumbers: Record<string, number> = {
      'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5,
      'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10,
    };

    for (const [word, num] of Object.entries(wordNumbers)) {
      if (text.includes(`${word} shelf`) || text.includes(`${word} shelves`) || text.includes(`${word}-tier`)) {
        return num;
      }
    }

    return defaultValue;
  }

  /**
   * 提取主题（Theme）
   * 从描述中提取产品的主题/风格设定
   * @param param 默认值（默认 Casual Home Comfort Setting）
   */
  private extractTheme(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'Casual Home Comfort Setting';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 主题关键词映射（按优先级排序）
    const themes: Array<{ keywords: string[]; value: string }> = [
      // 节日主题
      { keywords: ['christmas', 'xmas', 'holiday season'], value: 'Christmas' },
      { keywords: ['halloween', 'spooky'], value: 'Halloween' },
      { keywords: ['easter'], value: 'Easter' },
      { keywords: ['thanksgiving'], value: 'Thanksgiving' },
      { keywords: ['valentine'], value: 'Valentine\'s Day' },
      // 风格主题
      { keywords: ['modern luxury', 'light luxury'], value: 'Modern Luxury' },
      { keywords: ['minimalist', 'minimal'], value: 'Minimalist' },
      { keywords: ['scandinavian', 'nordic'], value: 'Scandinavian' },
      { keywords: ['industrial'], value: 'Industrial' },
      { keywords: ['farmhouse', 'country'], value: 'Farmhouse' },
      { keywords: ['bohemian', 'boho'], value: 'Bohemian' },
      { keywords: ['coastal', 'beach', 'nautical'], value: 'Coastal' },
      { keywords: ['rustic'], value: 'Rustic' },
      { keywords: ['vintage', 'retro'], value: 'Vintage' },
      { keywords: ['mid-century', 'mid century'], value: 'Mid-Century Modern' },
      { keywords: ['contemporary'], value: 'Contemporary' },
      { keywords: ['traditional', 'classic'], value: 'Traditional' },
      // 场景主题
      { keywords: ['outdoor', 'patio', 'garden'], value: 'Outdoor Living' },
      { keywords: ['kids', 'children', 'nursery'], value: 'Kids Room' },
      { keywords: ['office', 'workspace'], value: 'Home Office' },
      { keywords: ['living room'], value: 'Living Room' },
      { keywords: ['bedroom'], value: 'Bedroom' },
      { keywords: ['dining'], value: 'Dining Room' },
    ];

    for (const theme of themes) {
      for (const keyword of theme.keywords) {
        if (text.includes(keyword)) {
          return theme.value;
        }
      }
    }

    return defaultValue;
  }

  /**
   * 提取形状（Shape）
   * 从描述中提取产品的物理形状
   * @param param 默认值（默认 Rectangular）
   */
  private extractShape(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'Rectangular';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';

    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc}`.toLowerCase();

    // 形状关键词映射（按优先级排序，更具体的在前）
    const shapes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['semi-circular', 'semicircular', 'half circle', 'half-circle'], value: 'Semi-Circular' },
      { keywords: ['l-shaped', 'l shaped', 'l-shape'], value: 'L-Shaped' },
      { keywords: ['u-shaped', 'u shaped', 'u-shape'], value: 'U-Shaped' },
      { keywords: ['kidney'], value: 'Kidney' },
      { keywords: ['hexagon', 'hexagonal'], value: 'Hexagonal' },
      { keywords: ['octagon', 'octagonal'], value: 'Octagonal' },
      { keywords: ['triangle', 'triangular'], value: 'Triangular' },
      { keywords: ['heart'], value: 'Heart' },
      { keywords: ['irregular'], value: 'Irregular' },
      { keywords: ['circular', 'circle', 'round'], value: 'Round' },
      { keywords: ['oval', 'ellipse', 'elliptical'], value: 'Oval' },
      { keywords: ['square'], value: 'Square' },
      { keywords: ['rectangular', 'rectangle', 'oblong'], value: 'Rectangular' },
    ];

    for (const shape of shapes) {
      for (const keyword of shape.keywords) {
        if (text.includes(keyword)) {
          return shape.value;
        }
      }
    }

    return defaultValue;
  }

  /**
   * 提取直径（Diameter）
   * 从描述中提取产品的直径尺寸
   * 返回对象格式 { unit: string, measure: number } 或 undefined
   */
  private extractDiameter(
    channelAttributes: Record<string, any>,
  ): { unit: string; measure: number } | undefined {
    // 优先从渠道属性获取
    const diameterAttr = getCustomAttributeValue(channelAttributes, 'diameter');
    if (diameterAttr) {
      if (typeof diameterAttr === 'object' && diameterAttr.measure) {
        return diameterAttr;
      }
      const num = parseFloat(String(diameterAttr));
      if (!isNaN(num)) {
        return { unit: 'in', measure: num };
      }
    }

    const description = getNestedValue(channelAttributes, 'description') || '';
    const cleanDesc = this.stripHtmlTags(description);
    const text = cleanDesc.toLowerCase();

    // 匹配直径模式
    const patterns = [
      /diameter[:\s]+(\d+(?:\.\d+)?)\s*(inch|in|cm|mm|"|\')?/i,
      /(\d+(?:\.\d+)?)\s*(inch|in|cm|mm|"|\')\s*diameter/i,
      /(\d+(?:\.\d+)?)\s*(inch|in|cm|mm|"|\')\s*(?:dia|d)\b/i,
      /dia[:\s]+(\d+(?:\.\d+)?)\s*(inch|in|cm|mm|"|\')?/i,
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match) {
        const measure = parseFloat(match[1]);
        let unit = (match[2] || 'in').toLowerCase();
        // 标准化单位
        if (unit === '"' || unit === 'inch') unit = 'in';
        if (unit === "'" || unit === 'ft') unit = 'ft';
        
        if (!isNaN(measure) && measure > 0) {
          return { unit, measure };
        }
      }
    }

    return undefined; // 不传递此字段
  }

  /**
   * 提取床风格（Bed Style）
   * 从描述中提取床的设计风格
   * @param param 默认值（默认 Platform Bed Style）
   * 
   * 注意：只在床相关产品中匹配
   */
  private extractBedStyle(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string | undefined {
    const defaultValue = param || 'Platform Bed Style';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';

    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc}`.toLowerCase();

    // 首先检查是否是床相关产品
    const bedRelatedKeywords = ['bed', 'mattress', 'bedframe', 'headboard', 'footboard', 'bed frame', 'bedroom'];
    const isBedRelated = bedRelatedKeywords.some(k => text.includes(k));

    // 如果不是床相关产品，返回 undefined
    if (!isBedRelated) {
      return undefined;
    }

    // 床风格关键词映射（按优先级排序）
    const bedStyles: Array<{ keywords: string[]; value: string }> = [
      // 软包/布艺类
      { keywords: ['tufted', 'button tufted'], value: 'Tufted Bed' },
      { keywords: ['upholstered', 'fabric bed', 'padded'], value: 'Upholstered Bed' },
      { keywords: ['fabric platform'], value: 'Fabric Platform Bed' },
      // 木质类
      { keywords: ['solid wood', 'hardwood'], value: 'Solid Wood Bed' },
      { keywords: ['wood panel', 'panel bed'], value: 'Wood Panel Bed' },
      { keywords: ['wood platform'], value: 'Wood Platform Bed' },
      // 金属类
      { keywords: ['metal platform'], value: 'Metal Platform Bed' },
      { keywords: ['steel bed', 'steel frame'], value: 'Steel Bed Frame Style' },
      { keywords: ['iron bed', 'wrought iron'], value: 'Iron Bed' },
      { keywords: ['industrial metal', 'industrial bed'], value: 'Industrial Metal Bed' },
      // 特殊结构
      { keywords: ['storage bed', 'lift-up', 'lift up'], value: 'Storage Bed Style' },
      { keywords: ['drawer storage', 'drawers bed'], value: 'Drawer Storage Bed' },
      { keywords: ['canopy bed', 'four poster', '4 poster'], value: 'Canopy Bed' },
      { keywords: ['sleigh bed', 'sleigh style'], value: 'Sleigh Bed' },
      { keywords: ['bunk bed'], value: 'Bunk Bed' },
      { keywords: ['loft bed'], value: 'Loft Bed' },
      { keywords: ['daybed', 'day bed'], value: 'Daybed' },
      { keywords: ['murphy bed', 'wall bed'], value: 'Murphy Bed' },
      // 风格类
      { keywords: ['modern bed', 'modern style'], value: 'Modern Bed Style' },
      { keywords: ['contemporary bed'], value: 'Contemporary Bed Style' },
      { keywords: ['minimalist bed'], value: 'Minimalist Bed Style' },
      { keywords: ['scandinavian bed', 'nordic bed'], value: 'Scandinavian Bed Style' },
      { keywords: ['traditional bed', 'classic bed'], value: 'Classic Traditional Bed Style' },
      { keywords: ['vintage bed', 'retro bed'], value: 'Vintage Bed Style' },
      // 通用
      { keywords: ['platform bed', 'platform style'], value: 'Platform Bed Style' },
    ];

    for (const style of bedStyles) {
      for (const keyword of style.keywords) {
        if (text.includes(keyword)) {
          return style.value;
        }
      }
    }

    return defaultValue;
  }

  /**
   * 提取安装类型（Mount Type）
   * 从描述中提取产品的安装方式
   * @param param 默认值（默认 Freestanding）
   */
  private extractMountType(
    param: string | undefined,
    channelAttributes: Record<string, any>,
  ): string {
    const defaultValue = param || 'Freestanding';
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 安装类型关键词映射（按优先级排序，更具体的在前）
    const mountTypes: Array<{ keywords: string[]; value: string }> = [
      // 墙面安装
      {
        keywords: ['wall-mounted', 'wall mount', 'mounted on wall', 'hang on wall', 'hanging on wall', 'wall installation'],
        value: 'Wall Mount',
      },
      // 天花板安装
      {
        keywords: ['ceiling-mounted', 'ceiling mount', 'hanging from ceiling', 'suspended', 'suspension', 'pendant'],
        value: 'Ceiling Mount',
      },
      // 橱柜下安装
      {
        keywords: ['under cabinet', 'cabinet bottom', 'mounted under shelf', 'under-cabinet'],
        value: 'Under Cabinet Mount',
      },
      // 床头板安装
      {
        keywords: ['headboard mount', 'bed frame mount', 'headboard-mounted'],
        value: 'Headboard Mount',
      },
      // 支架安装
      {
        keywords: ['bracket mount', 'bracket mounting', 'support bracket', 'brackets'],
        value: 'Bracket Mount',
      },
      // 螺丝安装
      {
        keywords: ['screw mount', 'screwed', 'bolt mount', 'bolts', 'drill', 'drilled', 'mounting hardware', 'fasten', 'fastened'],
        value: 'Screw Mount',
      },
      // 粘贴安装
      {
        keywords: ['adhesive', 'sticky', 'self-adhesive', 'peel and stick', 'glue', '3m tape', 'tape mount'],
        value: 'Adhesive Mount',
      },
      // 磁吸安装
      {
        keywords: ['magnetic', 'magnet', 'magnetically', 'magnetic strip', 'magnetic mount'],
        value: 'Magnetic Mount',
      },
      // 嵌入式安装
      {
        keywords: ['flush mount', 'flush-mounted', 'flat-mounted', 'recessed'],
        value: 'Flush Mount',
      },
      // 表面安装
      {
        keywords: ['surface mount', 'surface-mounted', 'on surface', 'surface installation'],
        value: 'Surface Mount',
      },
      // 龙骨安装
      {
        keywords: ['stud mount', 'stud-mounted', 'mounted to studs', 'attached to studs'],
        value: 'Stud Mount',
      },
      // 独立式（无需安装）
      {
        keywords: ['freestanding', 'standalone', 'stand alone', 'no installation required', 'place anywhere', 'floor standing'],
        value: 'Freestanding',
      },
    ];

    for (const mountType of mountTypes) {
      for (const keyword of mountType.keywords) {
        if (text.includes(keyword)) {
          return mountType.value;
        }
      }
    }

    // 检查是否有 "wall" 单独出现（优先级较低）
    if (text.includes('wall')) {
      return 'Wall Mount';
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

  /**
   * 提取家具配置信息（Configuration）
   * 用于区分同一款式下因结构、功能或组合差异而产生的不同SKU
   * 返回格式化的配置字符串，最大400字符
   * 如果无法识别明确配置差异则返回 undefined
   */
  private extractConfiguration(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const configParts: string[] = [];

    // 1. 结构形态
    const structureTypes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['sectional sofa', 'sectional couch', 'sectional'], value: 'Sectional' },
      { keywords: ['loveseat', 'love seat'], value: 'Loveseat' },
      { keywords: ['sofa bed', 'sleeper sofa', 'pull-out sofa'], value: 'Sofa Bed' },
      { keywords: ['sofa', 'couch'], value: 'Sofa' },
      { keywords: ['armchair', 'arm chair', 'accent chair', 'lounge chair'], value: 'Chair' },
      { keywords: ['ottoman'], value: 'Ottoman' },
      { keywords: ['bunk bed'], value: 'Bunk Bed' },
      { keywords: ['daybed', 'day bed'], value: 'Daybed' },
      { keywords: ['bed frame', 'platform bed'], value: 'Bed Frame' },
      { keywords: ['headboard'], value: 'Headboard' },
      { keywords: ['tv stand', 'tv console', 'media console'], value: 'TV Stand' },
      { keywords: ['coffee table'], value: 'Coffee Table' },
      { keywords: ['dining table'], value: 'Dining Table' },
      { keywords: ['desk'], value: 'Desk' },
      { keywords: ['bookshelf', 'bookcase'], value: 'Bookshelf' },
      { keywords: ['dresser'], value: 'Dresser' },
      { keywords: ['nightstand', 'night stand', 'bedside table'], value: 'Nightstand' },
    ];

    for (const st of structureTypes) {
      if (st.keywords.some(kw => text.includes(kw))) {
        configParts.push(st.value);
        break;
      }
    }

    // 2. 组合形式/朝向
    const combinationTypes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['u-shaped', 'u shaped', 'u-shape'], value: 'U-Shape' },
      { keywords: ['l-shaped', 'l shaped', 'l-shape'], value: 'L-Shape' },
      { keywords: ['reversible chaise', 'reversible'], value: 'Reversible' },
      { keywords: ['left facing', 'left-facing', 'left chaise'], value: 'Left Facing' },
      { keywords: ['right facing', 'right-facing', 'right chaise'], value: 'Right Facing' },
      { keywords: ['with ottoman', 'includes ottoman', 'ottoman included'], value: 'With Ottoman' },
      { keywords: ['without ottoman', 'no ottoman'], value: 'Without Ottoman' },
      { keywords: ['with storage', 'storage included', 'built-in storage'], value: 'With Storage' },
    ];

    for (const ct of combinationTypes) {
      if (ct.keywords.some(kw => text.includes(kw))) {
        configParts.push(ct.value);
        break;
      }
    }

    // 3. 套装数量
    const setPatterns = [
      /(\d+)\s*[-]?\s*piece\s+set/i,
      /set\s+of\s+(\d+)/i,
      /(\d+)\s*[-]?\s*pc\s+set/i,
    ];
    for (const pattern of setPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const count = parseInt(match[1], 10);
        if (count >= 2 && count <= 20) {
          configParts.push(`${count}-Piece Set`);
          break;
        }
      }
    }

    // 4. 尺寸级别（床/床垫）
    const sizeTypes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['california king', 'cal king'], value: 'California King' },
      { keywords: ['king size', 'king bed', 'king'], value: 'King' },
      { keywords: ['queen size', 'queen bed', 'queen'], value: 'Queen' },
      { keywords: ['full size', 'full bed', 'double bed', 'full'], value: 'Full' },
      { keywords: ['twin xl', 'twin extra long'], value: 'Twin XL' },
      { keywords: ['twin size', 'twin bed', 'single bed', 'twin'], value: 'Twin' },
    ];

    // 只有床相关产品才提取尺寸
    const isBedRelated = ['bed', 'mattress', 'headboard', 'bedframe'].some(kw => text.includes(kw));
    if (isBedRelated) {
      for (const st of sizeTypes) {
        if (st.keywords.some(kw => text.includes(kw))) {
          configParts.push(st.value);
          break;
        }
      }
    }

    // 5. 功能属性
    const functionTypes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['power reclining', 'electric reclining', 'power recliner'], value: 'Power Reclining' },
      { keywords: ['manual reclining', 'manual recliner'], value: 'Manual Reclining' },
      { keywords: ['reclining', 'recliner'], value: 'Reclining' },
      { keywords: ['non-reclining', 'non reclining', 'stationary'], value: 'Non-Reclining' },
      { keywords: ['foldable', 'folding', 'collapsible'], value: 'Foldable' },
      { keywords: ['extendable', 'expandable', 'extension'], value: 'Extendable' },
      { keywords: ['adjustable height', 'height adjustable'], value: 'Adjustable Height' },
      { keywords: ['adjustable'], value: 'Adjustable' },
      { keywords: ['convertible'], value: 'Convertible' },
    ];

    for (const ft of functionTypes) {
      if (ft.keywords.some(kw => text.includes(kw))) {
        configParts.push(ft.value);
        break;
      }
    }

    // 6. 座位容量
    const seaterPatterns = [
      /(\d+)\s*[-]?\s*seater/i,
      /(\d+)\s*[-]?\s*seat\s+(?:sofa|couch|sectional)/i,
    ];
    for (const pattern of seaterPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const count = parseInt(match[1], 10);
        if (count >= 1 && count <= 10) {
          configParts.push(`${count}-Seater`);
          break;
        }
      }
    }

    // 7. 安装方式
    if (text.includes('wall-mounted') || text.includes('wall mount')) {
      configParts.push('Wall-Mounted');
    } else if (text.includes('freestanding') || text.includes('floor standing')) {
      configParts.push('Freestanding');
    }

    // 如果没有提取到任何配置信息，返回 undefined
    if (configParts.length === 0) {
      return undefined;
    }

    // 去重并拼接，限制400字符
    const uniqueParts = [...new Set(configParts)];
    const result = uniqueParts.join(', ');
    return result.length > 400 ? result.substring(0, 397) + '...' : result;
  }

  /**
   * 标准颜色词典（用于颜色提取的归一化）
   */
  private readonly standardColors: Record<string, string> = {
    // 灰色系
    grey: 'Gray', gray: 'Gray', charcoal: 'Charcoal Gray', slate: 'Slate Gray',
    silver: 'Silver', ash: 'Ash Gray',
    // 蓝色系
    blue: 'Blue', navy: 'Navy Blue', teal: 'Teal', turquoise: 'Turquoise',
    aqua: 'Aqua', cobalt: 'Cobalt Blue', royal: 'Royal Blue', sky: 'Sky Blue',
    // 绿色系
    green: 'Green', olive: 'Olive Green', sage: 'Sage Green', emerald: 'Emerald Green',
    mint: 'Mint Green', forest: 'Forest Green', lime: 'Lime Green',
    // 棕色系
    brown: 'Brown', tan: 'Tan', taupe: 'Taupe', chocolate: 'Chocolate Brown',
    espresso: 'Espresso', mocha: 'Mocha', caramel: 'Caramel', cognac: 'Cognac',
    walnut: 'Walnut', oak: 'Oak', chestnut: 'Chestnut',
    // 米色/奶油色系
    beige: 'Beige', cream: 'Cream', ivory: 'Ivory', 'off-white': 'Off-White',
    oatmeal: 'Oatmeal', sand: 'Sand', linen: 'Linen',
    // 红色系
    red: 'Red', burgundy: 'Burgundy', wine: 'Wine', maroon: 'Maroon',
    coral: 'Coral', rust: 'Rust', terracotta: 'Terracotta',
    // 粉色系
    pink: 'Pink', blush: 'Blush Pink', rose: 'Rose', salmon: 'Salmon',
    // 紫色系
    purple: 'Purple', violet: 'Violet', lavender: 'Lavender', plum: 'Plum',
    // 橙色/黄色系
    orange: 'Orange', yellow: 'Yellow', gold: 'Gold', mustard: 'Mustard',
    // 黑白
    black: 'Black', white: 'White',
    // 金属色
    brass: 'Brass', bronze: 'Bronze', copper: 'Copper', chrome: 'Chrome',
    nickel: 'Nickel', pewter: 'Pewter',
    // 木色
    natural: 'Natural', maple: 'Maple', cherry: 'Cherry', mahogany: 'Mahogany',
  };

  /**
   * 布艺相关关键词
   */
  private readonly fabricKeywords = [
    'fabric', 'upholstered', 'upholstery', 'linen', 'velvet', 'polyester',
    'cotton', 'chenille', 'boucle', 'microfiber', 'suede', 'tweed',
    'woven', 'textured', 'tufted', 'cushioned',
  ];

  /**
   * 归一化颜色名称
   */
  private normalizeColor(color: string): string | undefined {
    const lower = color.toLowerCase().trim();
    // 直接匹配
    if (this.standardColors[lower]) {
      return this.standardColors[lower];
    }
    // 检查是否包含标准颜色词
    for (const [key, value] of Object.entries(this.standardColors)) {
      if (lower.includes(key)) {
        return value;
      }
    }
    return undefined;
  }

  /**
   * 提取布艺颜色（Fabric Color）
   * 仅提取明确与布艺/织物相关的颜色
   * 最大400字符
   */
  private extractFabricColor(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`;

    const fabricColors: string[] = [];

    // 匹配模式：颜色词 + 布艺关键词 或 布艺关键词 + 颜色词
    // 例如: "Gray Fabric", "Beige Linen Upholstery", "Blue Velvet"
    const colorPattern = /\b(light\s+|dark\s+|charcoal\s+|navy\s+)?(grey|gray|blue|green|brown|beige|cream|ivory|black|white|red|pink|purple|orange|yellow|gold|tan|taupe|silver|teal|navy|olive|burgundy|wine|coral|rust|blush|rose|velvet|cognac|espresso|mocha|caramel|walnut|oatmeal|sand|linen)\b/gi;

    // 查找布艺相关的颜色描述
    for (const fabricKw of this.fabricKeywords) {
      // 模式1: "颜色 布艺词" (如 "Gray Fabric", "Blue Velvet")
      const pattern1 = new RegExp(`(\\w+(?:\\s+\\w+)?)\\s+${fabricKw}`, 'gi');
      let match;
      while ((match = pattern1.exec(text)) !== null) {
        const colorPart = match[1];
        const normalized = this.normalizeColor(colorPart);
        if (normalized && !fabricColors.includes(normalized)) {
          fabricColors.push(normalized);
        }
      }

      // 模式2: "布艺词 颜色" (如 "Fabric in Gray")
      const pattern2 = new RegExp(`${fabricKw}\\s+(?:in\\s+)?(\\w+(?:\\s+\\w+)?)`, 'gi');
      while ((match = pattern2.exec(text)) !== null) {
        const colorPart = match[1];
        const normalized = this.normalizeColor(colorPart);
        if (normalized && !fabricColors.includes(normalized)) {
          fabricColors.push(normalized);
        }
      }
    }

    if (fabricColors.length === 0) {
      return undefined;
    }

    const result = fabricColors.join(' and ');
    return result.length > 400 ? result.substring(0, 397) + '...' : result;
  }

  /**
   * 提取装饰色/次要颜色（Accent Color）
   * 仅提取明确标注为装饰性的次要颜色
   * 最大200字符
   */
  private extractAccentColor(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`;

    const accentColors: string[] = [];

    // 装饰性部件关键词
    const accentKeywords = [
      'accent', 'accents', 'trim', 'detail', 'details', 'legs', 'leg',
      'frame', 'base', 'handle', 'handles', 'stitching', 'piping',
      'hardware', 'knobs', 'pulls', 'feet', 'foot',
    ];

    for (const accentKw of accentKeywords) {
      // 模式1: "颜色 部件词" (如 "Black Legs", "Gold Accents")
      const pattern1 = new RegExp(`(\\w+(?:\\s+\\w+)?)\\s+(?:metal\\s+)?${accentKw}`, 'gi');
      let match;
      while ((match = pattern1.exec(text)) !== null) {
        const colorPart = match[1];
        const normalized = this.normalizeColor(colorPart);
        if (normalized && !accentColors.includes(normalized)) {
          accentColors.push(normalized);
        }
      }

      // 模式2: "部件词 in/with 颜色" (如 "Legs in Black")
      const pattern2 = new RegExp(`${accentKw}\\s+(?:in|with)\\s+(\\w+(?:\\s+\\w+)?)`, 'gi');
      while ((match = pattern2.exec(text)) !== null) {
        const colorPart = match[1];
        const normalized = this.normalizeColor(colorPart);
        if (normalized && !accentColors.includes(normalized)) {
          accentColors.push(normalized);
        }
      }
    }

    // 特殊模式: "with xxx accents" (如 "with Gold Metal Accents")
    const withAccentsPattern = /with\s+(\w+(?:\s+\w+)?)\s+(?:metal\s+)?accents?/gi;
    let match;
    while ((match = withAccentsPattern.exec(text)) !== null) {
      const colorPart = match[1];
      const normalized = this.normalizeColor(colorPart);
      if (normalized && !accentColors.includes(normalized)) {
        accentColors.push(normalized);
      }
    }

    if (accentColors.length === 0) {
      return undefined;
    }

    const result = accentColors.join(' and ');
    return result.length > 200 ? result.substring(0, 197) + '...' : result;
  }

  /**
   * 提取坐垫颜色（Cushion Color）
   * 仅提取明确与坐垫/靠垫相关的颜色
   * 最大200字符
   */
  private extractCushionColor(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`;

    const cushionColors: string[] = [];

    // 坐垫相关关键词
    const cushionKeywords = [
      'cushion', 'cushions', 'seat cushion', 'back cushion',
      'throw pillow', 'throw pillows', 'pillow', 'pillows',
      'pad', 'padded', 'padding', 'seat pad',
    ];

    for (const cushionKw of cushionKeywords) {
      // 模式1: "颜色 坐垫词" (如 "Gray Cushions", "Blue Throw Pillows")
      const pattern1 = new RegExp(`(\\w+(?:\\s+\\w+)?)\\s+${cushionKw}`, 'gi');
      let match;
      while ((match = pattern1.exec(text)) !== null) {
        const colorPart = match[1];
        const normalized = this.normalizeColor(colorPart);
        if (normalized && !cushionColors.includes(normalized)) {
          cushionColors.push(normalized);
        }
      }

      // 模式2: "坐垫词 in/with 颜色" (如 "Cushions in Gray")
      const pattern2 = new RegExp(`${cushionKw}\\s+(?:in|with)\\s+(\\w+(?:\\s+\\w+)?)`, 'gi');
      while ((match = pattern2.exec(text)) !== null) {
        const colorPart = match[1];
        const normalized = this.normalizeColor(colorPart);
        if (normalized && !cushionColors.includes(normalized)) {
          cushionColors.push(normalized);
        }
      }
    }

    if (cushionColors.length === 0) {
      return undefined;
    }

    const result = cushionColors.join(' and ');
    return result.length > 200 ? result.substring(0, 197) + '...' : result;
  }

  /**
   * 提取面料成分（Fabric Content）
   * 从描述中提取软包/布艺家具的面料成分信息
   * 返回 Walmart CA 要求的格式：[{ materialName: { en: string }, materialPercentage?: number }]
   * 如果无法识别则返回 undefined（不传递此字段）
   */
  private extractFabricContent(
    channelAttributes: Record<string, any>,
  ): Array<{ materialName: { en: string }; materialPercentage?: number }> | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const material = getNestedValue(channelAttributes, 'material') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets} ${material}`.toLowerCase();

    // 面料材质关键词映射
    const fabricMaterials: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['polyester', 'polyester fabric', 'polyester fiber', 'polyester upholstery'], value: 'Polyester' },
      { keywords: ['cotton', 'cotton fabric', 'cotton blend'], value: 'Cotton' },
      { keywords: ['linen', 'linen blend', 'linen-like'], value: 'Linen' },
      { keywords: ['velvet', 'velour'], value: 'Velvet' },
      { keywords: ['chenille'], value: 'Chenille' },
      { keywords: ['boucle', 'teddy fabric', 'sherpa'], value: 'Boucle' },
      { keywords: ['faux leather', 'pu leather', 'synthetic leather', 'leatherette', 'vegan leather'], value: 'Faux Leather' },
      { keywords: ['genuine leather', 'top grain leather', 'full grain leather', 'real leather'], value: 'Leather' },
      { keywords: ['microfiber', 'micro fiber', 'microsuede'], value: 'Microfiber' },
      { keywords: ['suede', 'faux suede'], value: 'Suede' },
      { keywords: ['knit', 'woven fabric', 'textured fabric'], value: 'Fabric' },
      { keywords: ['nylon'], value: 'Nylon' },
      { keywords: ['acrylic'], value: 'Acrylic' },
      { keywords: ['rayon', 'viscose'], value: 'Rayon' },
      { keywords: ['silk'], value: 'Silk' },
      { keywords: ['wool'], value: 'Wool' },
      { keywords: ['spandex', 'elastane', 'lycra'], value: 'Spandex' },
    ];

    // 临时结果存储
    const tempResults: Array<{ material: string; percentage?: number }> = [];

    // 1. 尝试匹配百分比表达式（如 "70% polyester / 30% cotton" 或 "70% polyester, 30% cotton"）
    const percentagePattern = /(\d+)\s*%\s*([a-zA-Z\s]+)/g;
    let match;
    const percentageMatches: Array<{ percentage: number; rawMaterial: string }> = [];

    while ((match = percentagePattern.exec(text)) !== null) {
      const percentage = parseInt(match[1], 10);
      const rawMaterial = match[2].trim().toLowerCase();
      if (percentage > 0 && percentage <= 100) {
        percentageMatches.push({ percentage, rawMaterial });
      }
    }

    // 如果找到百分比表达式，解析并匹配材质
    if (percentageMatches.length > 0) {
      for (const pm of percentageMatches) {
        for (const fabric of fabricMaterials) {
          if (fabric.keywords.some(kw => pm.rawMaterial.includes(kw) || kw.includes(pm.rawMaterial))) {
            // 检查是否已添加该材质
            if (!tempResults.some(r => r.material === fabric.value)) {
              tempResults.push({ material: fabric.value, percentage: pm.percentage });
            }
            break;
          }
        }
      }
    }

    // 2. 如果没有百分比匹配结果，尝试直接匹配材质关键词
    if (tempResults.length === 0) {
      for (const fabric of fabricMaterials) {
        for (const keyword of fabric.keywords) {
          if (text.includes(keyword)) {
            // 检查是否已添加该材质
            if (!tempResults.some(r => r.material === fabric.value)) {
              tempResults.push({ material: fabric.value });
            }
            break;
          }
        }
      }
    }

    // 3. 没有检测到任何面料材质，返回 undefined（不传递此字段）
    if (tempResults.length === 0) {
      return undefined;
    }

    // 4. 转换为 Walmart CA 要求的格式
    // 格式: [{ materialName: { en: "Polyester" }, materialPercentage: 70 }]
    return tempResults.map(item => {
      const result: { materialName: { en: string }; materialPercentage?: number } = {
        materialName: { en: item.material },
      };
      if (item.percentage !== undefined) {
        result.materialPercentage = item.percentage;
      }
      return result;
    });
  }

  /**
   * 提取面板数量（Number of Panels）
   * 仅适用于屏风、折叠屏、壁炉屏等有面板结构的商品
   */
  private extractNumberOfPanels(channelAttributes: Record<string, any>): number | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是屏风类产品
    const panelProductKeywords = [
      'room divider', 'divider screen', 'folding screen', 'panel screen',
      'fireplace screen', 'privacy screen', 'partition', 'shoji screen',
    ];
    if (!panelProductKeywords.some(kw => text.includes(kw))) {
      return undefined;
    }

    // 英文数字词映射
    const numberWords: Record<string, number> = {
      one: 1, two: 2, three: 3, four: 4, five: 5,
      six: 6, seven: 7, eight: 8, nine: 9, ten: 10,
    };

    // 匹配数字 + panel/fold/section
    const patterns = [
      /(\d+)\s*[-]?\s*panels?/i,
      /(\d+)\s*[-]?\s*folds?/i,
      /(\d+)\s*[-]?\s*sections?/i,
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const num = parseInt(match[1], 10);
        if (num >= 1 && num <= 20) return num;
      }
    }

    // 匹配英文数字词
    for (const [word, num] of Object.entries(numberWords)) {
      if (new RegExp(`${word}\\s*[-]?\\s*panels?`, 'i').test(text)) return num;
    }

    return undefined;
  }

  /**
   * 提取座椅靠背样式（Seat Back Style）
   * 最大300字符
   */
  private extractSeatBackStyle(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const backStyles: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['button-tufted', 'button tufted'], value: 'Button-Tufted' },
      { keywords: ['channel-tufted', 'channel tufted'], value: 'Channel Tufted' },
      { keywords: ['diamond-tufted', 'diamond tufted'], value: 'Diamond Tufted' },
      { keywords: ['tufted back', 'tufted'], value: 'Tufted' },
      { keywords: ['ladder-back', 'ladder back'], value: 'Ladder Back' },
      { keywords: ['slat-back', 'slat back'], value: 'Slat Back' },
      { keywords: ['cross-back', 'crossback', 'x-back'], value: 'Cross Back' },
      { keywords: ['wingback', 'wing back'], value: 'Wingback' },
      { keywords: ['rolled back'], value: 'Rolled Back' },
      { keywords: ['curved back'], value: 'Curved Back' },
      { keywords: ['straight back'], value: 'Straight Back' },
      { keywords: ['solid back'], value: 'Solid Back' },
      { keywords: ['open back'], value: 'Open Back' },
      { keywords: ['low back', 'low-back'], value: 'Low Back' },
      { keywords: ['high back', 'high-back'], value: 'High Back' },
      { keywords: ['mid back', 'mid-back'], value: 'Mid Back' },
      { keywords: ['camelback'], value: 'Camelback' },
      { keywords: ['pillow back'], value: 'Pillow Back' },
      { keywords: ['tight back'], value: 'Tight Back' },
      { keywords: ['loose back'], value: 'Loose Back' },
    ];

    const foundStyles: string[] = [];
    for (const style of backStyles) {
      if (style.keywords.some(kw => text.includes(kw)) && !foundStyles.includes(style.value)) {
        foundStyles.push(style.value);
      }
    }

    if (foundStyles.length === 0) return undefined;
    const result = foundStyles.join(', ');
    return result.length > 300 ? result.substring(0, 297) + '...' : result;
  }

  /**
   * 提取供电类型（Power Type）
   * 最大300字符
   */
  private extractPowerType(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const powerTypes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['rechargeable battery', 'rechargeable'], value: 'Rechargeable Battery' },
      { keywords: ['battery powered', 'battery-powered'], value: 'Battery Powered' },
      { keywords: ['usb powered', 'usb-powered'], value: 'USB Powered' },
      { keywords: ['ac powered', 'ac power'], value: 'AC Powered' },
      { keywords: ['dc powered', 'dc power'], value: 'DC Powered' },
      { keywords: ['plug-in', 'corded', 'power cord'], value: 'Corded Electric' },
      { keywords: ['cordless'], value: 'Cordless' },
      { keywords: ['electric', 'electrical', 'motorized'], value: 'Electric' },
      { keywords: ['manual', 'non-powered', 'hand-operated'], value: 'Manual' },
    ];

    for (const pt of powerTypes) {
      if (pt.keywords.some(kw => text.includes(kw))) return pt.value;
    }
    return undefined;
  }

  /**
   * 提取是否需要供电（Is Powered）
   * 返回 "Yes" 或 "No"
   */
  private extractIsPowered(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const poweredKeywords = [
      'electric', 'electrical', 'powered', 'power recliner', 'power lift',
      'motorized', 'usb powered', 'battery powered', 'rechargeable',
      'plug-in', 'ac powered', 'dc powered', 'electric fireplace',
      'led light', 'heated', 'massage', 'vibration',
    ];

    const nonPoweredKeywords = ['manual', 'non-powered', 'no power', 'hand-operated'];

    if (nonPoweredKeywords.some(kw => text.includes(kw))) return 'No';
    if (poweredKeywords.some(kw => text.includes(kw))) return 'Yes';
    return 'No';
  }

  /**
   * 提取推荐使用场景（Recommended Uses）
   * 返回数组格式
   */
  private extractRecommendedUses(channelAttributes: Record<string, any>): string[] | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const usageScenes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['living room', 'living area', 'family room'], value: 'Living Room' },
      { keywords: ['bedroom', 'master bedroom'], value: 'Bedroom' },
      { keywords: ['dining room', 'dining area'], value: 'Dining Room' },
      { keywords: ['home office', 'study room', 'study'], value: 'Home Office' },
      { keywords: ['kitchen'], value: 'Kitchen' },
      { keywords: ['bathroom'], value: 'Bathroom' },
      { keywords: ['entryway', 'foyer', 'hallway'], value: 'Entryway' },
      { keywords: ['kids room', 'nursery', 'playroom'], value: 'Kids Room' },
      { keywords: ['guest room'], value: 'Guest Room' },
      { keywords: ['outdoor', 'outside'], value: 'Outdoor' },
      { keywords: ['patio'], value: 'Patio' },
      { keywords: ['garden', 'backyard'], value: 'Garden' },
      { keywords: ['balcony', 'terrace'], value: 'Balcony' },
      { keywords: ['porch', 'deck'], value: 'Porch' },
      { keywords: ['commercial', 'office'], value: 'Commercial' },
      { keywords: ['indoor'], value: 'Indoor' },
      { keywords: ['seating'], value: 'Seating' },
      { keywords: ['sleeping'], value: 'Sleeping' },
      { keywords: ['storage'], value: 'Storage' },
      { keywords: ['room divider', 'partition'], value: 'Room Divider' },
      { keywords: ['relaxation', 'lounging'], value: 'Relaxation' },
    ];

    const foundUses: string[] = [];
    for (const scene of usageScenes) {
      if (scene.keywords.some(kw => text.includes(kw)) && !foundUses.includes(scene.value)) {
        foundUses.push(scene.value);
      }
    }

    return foundUses.length > 0 ? foundUses : undefined;
  }

  /**
   * 提取推荐房间（Recommended Rooms）
   * 三层兜底策略确保必有值：
   * 1. 显式房间提取 - 从标题/描述中提取明确房间名称
   * 2. 行业默认映射 - 根据商品类型映射推荐房间
   * 3. 全兜底默认值 - 通用多场景组合
   * 返回数组格式，最大500字符
   */
  private extractRecommendedRooms(channelAttributes: Record<string, any>): string[] {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 房间词典（同义词归一）
    const roomDictionary: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['living room', 'living area', 'family room', 'lounge'], value: 'Living Room' },
      { keywords: ['bedroom', 'master bedroom', 'guest bedroom'], value: 'Bedroom' },
      { keywords: ['dining room', 'dining area'], value: 'Dining Room' },
      { keywords: ['home office', 'office', 'study room', 'study', 'workspace'], value: 'Home Office' },
      { keywords: ['kitchen'], value: 'Kitchen' },
      { keywords: ['bathroom', 'bath'], value: 'Bathroom' },
      { keywords: ['entryway', 'foyer', 'hallway', 'entrance', 'mudroom'], value: 'Entryway' },
      { keywords: ['kids room', 'children room', 'nursery', 'playroom'], value: 'Kids Room' },
      { keywords: ['guest room'], value: 'Guest Room' },
      { keywords: ['patio', 'outdoor patio', 'deck', 'porch', 'balcony', 'terrace'], value: 'Patio' },
      { keywords: ['garden', 'backyard', 'yard'], value: 'Garden' },
      { keywords: ['den'], value: 'Den' },
      { keywords: ['basement'], value: 'Basement' },
      { keywords: ['garage'], value: 'Garage' },
    ];

    // 商品类型到房间的默认映射
    const productTypeMapping: Array<{ keywords: string[]; rooms: string[] }> = [
      { keywords: ['bed', 'bed frame', 'mattress', 'headboard', 'nightstand', 'dresser', 'wardrobe', 'armoire'], rooms: ['Bedroom'] },
      { keywords: ['sofa', 'sectional', 'loveseat', 'couch', 'tv stand', 'coffee table', 'end table', 'accent chair', 'recliner'], rooms: ['Living Room'] },
      { keywords: ['dining table', 'dining chair', 'bar stool', 'buffet', 'sideboard', 'china cabinet'], rooms: ['Dining Room'] },
      { keywords: ['desk', 'office chair', 'bookcase', 'bookshelf', 'filing cabinet'], rooms: ['Home Office'] },
      { keywords: ['outdoor', 'patio furniture', 'garden furniture', 'adirondack'], rooms: ['Patio'] },
      { keywords: ['storage cabinet', 'shoe rack', 'coat rack', 'hall tree', 'console table'], rooms: ['Entryway', 'Living Room'] },
      { keywords: ['room divider', 'screen', 'partition', 'folding screen'], rooms: ['Living Room', 'Bedroom'] },
      { keywords: ['kids bed', 'bunk bed', 'loft bed', 'kids desk', 'toy storage'], rooms: ['Kids Room'] },
      { keywords: ['vanity', 'makeup table', 'dressing table'], rooms: ['Bedroom', 'Bathroom'] },
      { keywords: ['kitchen island', 'kitchen cart', 'pantry'], rooms: ['Kitchen'] },
      { keywords: ['bench', 'ottoman', 'pouf'], rooms: ['Living Room', 'Bedroom', 'Entryway'] },
    ];

    // 全兜底默认值
    const fallbackRooms = ['Living Room', 'Bedroom', 'Home Office'];

    const foundRooms: string[] = [];

    // 第一层：显式房间提取
    for (const room of roomDictionary) {
      if (room.keywords.some(kw => text.includes(kw)) && !foundRooms.includes(room.value)) {
        foundRooms.push(room.value);
      }
    }

    // 如果第一层有结果，直接返回
    if (foundRooms.length > 0) {
      return this.limitRoomsLength(foundRooms, 500);
    }

    // 第二层：行业默认映射
    for (const mapping of productTypeMapping) {
      if (mapping.keywords.some(kw => text.includes(kw))) {
        for (const room of mapping.rooms) {
          if (!foundRooms.includes(room)) {
            foundRooms.push(room);
          }
        }
      }
    }

    // 如果第二层有结果，返回
    if (foundRooms.length > 0) {
      return this.limitRoomsLength(foundRooms, 500);
    }

    // 第三层：全兜底默认值
    return fallbackRooms;
  }

  /**
   * 限制房间数组总长度
   */
  private limitRoomsLength(rooms: string[], maxLength: number): string[] {
    const result: string[] = [];
    let totalLength = 0;
    for (const room of rooms) {
      if (totalLength + room.length + 2 <= maxLength) {
        result.push(room);
        totalLength += room.length + 2;
      }
    }
    return result;
  }

  /**
   * 提取床垫硬度（Mattress Firmness）
   * 显式优先＋兜底默认策略：
   * 1. 显式提取 - 从标题/描述中提取明确硬度关键词
   * 2. 数值映射 - 将数值型硬度映射为标准等级
   * 3. 默认兜底 - Medium
   * 仅适用于床垫类商品，床架等不适用返回undefined
   */
  private extractMattressFirmness(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是床垫类商品（排除床架等）
    const mattressKeywords = ['mattress', 'memory foam', 'innerspring', 'hybrid mattress', 'latex mattress'];
    const excludeKeywords = ['bed frame', 'platform bed', 'bed base', 'foundation', 'box spring only'];
    
    const isMattress = mattressKeywords.some(kw => text.includes(kw));
    const isExcluded = excludeKeywords.some(kw => text.includes(kw)) && !isMattress;
    
    if (isExcluded || !isMattress) {
      return undefined;
    }

    // 硬度词典（按优先级排序）
    const firmnessDictionary: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['extra firm', 'ultra firm', 'very firm'], value: 'Extra Firm' },
      { keywords: ['medium-firm', 'medium firm'], value: 'Medium Firm' },
      { keywords: ['firm'], value: 'Firm' },
      { keywords: ['medium-soft', 'medium soft'], value: 'Medium Soft' },
      { keywords: ['medium'], value: 'Medium' },
      { keywords: ['plush', 'soft', 'ultra plush', 'pillow top'], value: 'Plush' },
    ];

    // 第一层：显式提取
    for (const firmness of firmnessDictionary) {
      if (firmness.keywords.some(kw => text.includes(kw))) {
        return firmness.value;
      }
    }

    // 第二层：数值映射（如 firmness level 5, 6/10 等）
    const numericPatterns = [
      /firmness\s*(?:level|rating)?\s*[:\s]*(\d+)\s*(?:\/\s*10|out of 10)?/i,
      /(\d+)\s*(?:\/\s*10|out of 10)\s*firmness/i,
    ];

    for (const pattern of numericPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const level = parseInt(match[1], 10);
        if (level >= 1 && level <= 10) {
          if (level <= 3) return 'Plush';
          if (level <= 4) return 'Medium Soft';
          if (level <= 6) return 'Medium';
          if (level <= 7) return 'Medium Firm';
          if (level <= 9) return 'Firm';
          return 'Extra Firm';
        }
      }
    }

    // 第三层：默认兜底
    return 'Medium';
  }

  /**
   * 提取床垫厚度（Mattress Thickness）
   * 返回 { unit: string, measure: number } 格式
   * 仅适用于床垫类商品
   */
  private extractMattressThickness(
    channelAttributes: Record<string, any>,
  ): { unit: string; measure: number } | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是床垫类商品
    const mattressKeywords = ['mattress', 'memory foam', 'innerspring', 'hybrid mattress'];
    const excludeKeywords = ['bed frame', 'platform bed', 'foundation', 'box spring only'];

    const isMattress = mattressKeywords.some(kw => text.includes(kw));
    const isExcluded = excludeKeywords.some(kw => text.includes(kw)) && !isMattress;

    if (isExcluded || !isMattress) {
      return undefined;
    }

    // 第一层：显式数值提取（英寸）
    const inchPatterns = [
      /(\d+(?:\.\d+)?)\s*(?:inch|inches|in|″)\s*(?:thick|thickness|mattress|profile)?/i,
      /(?:thick|thickness|height|profile)[:\s]*(\d+(?:\.\d+)?)\s*(?:inch|inches|in|″)?/i,
      /(\d+(?:\.\d+)?)\s*(?:inch|in)\s+mattress/i,
    ];

    for (const pattern of inchPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const measure = parseFloat(match[1]);
        if (measure >= 4 && measure <= 20) {
          return { unit: 'in', measure };
        }
      }
    }

    // 厘米转英寸
    const cmPatterns = [
      /(\d+(?:\.\d+)?)\s*(?:cm|centimeter|centimeters)\s*(?:thick|thickness)?/i,
      /(?:thick|thickness)[:\s]*(\d+(?:\.\d+)?)\s*(?:cm|centimeter)/i,
    ];

    for (const pattern of cmPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const cm = parseFloat(match[1]);
        if (cm >= 10 && cm <= 50) {
          const inches = Math.round((cm / 2.54) * 10) / 10;
          return { unit: 'in', measure: inches };
        }
      }
    }

    // 第二层：Profile描述映射（仅当有明确描述时）
    if (text.includes('low profile')) {
      return { unit: 'in', measure: 6 };
    }
    if (text.includes('high profile') || text.includes('extra thick')) {
      return { unit: 'in', measure: 14 };
    }
    if (text.includes('standard profile')) {
      return { unit: 'in', measure: 10 };
    }

    // 没有明确厚度信息时，返回 undefined，不传递此字段
    return undefined;
  }

  /**
   * 提取是否包含气泵（Pump Included）
   * 仅适用于充气床垫类商品
   * 返回 "Yes" 或 "No"
   */
  private extractPumpIncluded(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是充气床垫
    const airMattressKeywords = ['air mattress', 'air bed', 'inflatable mattress', 'inflatable bed', 'blow up mattress'];
    const isAirMattress = airMattressKeywords.some(kw => text.includes(kw));

    // 非充气床垫直接返回 No
    if (!isAirMattress) {
      return 'No';
    }

    // 包含气泵的关键词
    const pumpIncludedKeywords = [
      'pump included', 'with pump', 'built-in pump', 'built in pump',
      'integrated pump', 'electric pump included', 'manual pump included',
      'includes pump', 'comes with pump',
    ];

    // 不包含气泵的关键词
    const pumpNotIncludedKeywords = [
      'pump not included', 'no pump', 'without pump', 'pump sold separately',
      'pump required', 'pump not included',
    ];

    // 检查是否明确不包含
    if (pumpNotIncludedKeywords.some(kw => text.includes(kw))) {
      return 'No';
    }

    // 检查是否明确包含
    if (pumpIncludedKeywords.some(kw => text.includes(kw))) {
      return 'Yes';
    }

    // 充气床垫默认不包含气泵
    return 'No';
  }

  /**
   * 提取填充材料（Fill Material）
   * 用于坐垫、靠垫、床垫填充层等内部填充材料
   * 返回数组格式，最大200字符
   */
  private extractFillMaterial(channelAttributes: Record<string, any>): string[] | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是需要填充的商品类型
    const filledProductKeywords = [
      'sofa', 'couch', 'sectional', 'loveseat', 'chair', 'recliner',
      'mattress', 'cushion', 'pillow', 'ottoman', 'pouf', 'bench',
      'headboard', 'upholstered',
    ];
    const noFillKeywords = ['table', 'desk', 'shelf', 'cabinet', 'bed frame', 'platform bed'];

    const needsFill = filledProductKeywords.some(kw => text.includes(kw));
    const noFill = noFillKeywords.some(kw => text.includes(kw)) && !needsFill;

    if (noFill) {
      return undefined;
    }

    // 填充材料词典
    const fillMaterials: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['memory foam', 'memory-foam'], value: 'Memory Foam' },
      { keywords: ['high-density foam', 'high density foam', 'hd foam'], value: 'High-Density Foam' },
      { keywords: ['gel foam', 'gel-infused foam', 'gel memory foam'], value: 'Gel Foam' },
      { keywords: ['polyurethane foam', 'pu foam'], value: 'Polyurethane Foam' },
      { keywords: ['foam'], value: 'Foam' },
      { keywords: ['polyester fiber', 'poly fiber', 'polyester fill'], value: 'Polyester Fiber' },
      { keywords: ['fiber', 'fiberfill'], value: 'Fiber' },
      { keywords: ['down alternative', 'down-alternative'], value: 'Down Alternative' },
      { keywords: ['down', 'goose down', 'duck down'], value: 'Down' },
      { keywords: ['feather', 'feathers'], value: 'Feather' },
      { keywords: ['cotton', 'cotton fill'], value: 'Cotton' },
      { keywords: ['latex'], value: 'Latex' },
      { keywords: ['sponge'], value: 'Sponge' },
      { keywords: ['spring', 'innerspring', 'pocket spring', 'coil'], value: 'Spring' },
    ];

    const foundMaterials: string[] = [];

    // 显式提取
    for (const material of fillMaterials) {
      if (material.keywords.some(kw => text.includes(kw)) && !foundMaterials.includes(material.value)) {
        foundMaterials.push(material.value);
      }
    }

    if (foundMaterials.length > 0) {
      return foundMaterials;
    }

    // 需要填充的商品但未明确说明，使用默认值
    if (needsFill) {
      if (text.includes('mattress')) {
        return ['Foam'];
      }
      return ['Polyester Fiber'];
    }

    return undefined;
  }

  /**
   * 提取框架材料（Frame Material）
   * 用于描述商品承重或支撑结构的框架材料
   * 返回数组格式，最大400字符
   */
  private extractFrameMaterial(channelAttributes: Record<string, any>): string[] | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是有框架的商品类型
    const framedProductKeywords = [
      'sofa', 'couch', 'sectional', 'loveseat', 'chair', 'recliner',
      'bed', 'table', 'desk', 'cabinet', 'shelf', 'bookcase', 'bench',
    ];

    const hasFrame = framedProductKeywords.some(kw => text.includes(kw));

    // 框架材料词典
    const frameMaterials: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['solid wood', 'hardwood', 'real wood'], value: 'Solid Wood' },
      { keywords: ['engineered wood', 'particle board', 'particleboard'], value: 'Engineered Wood' },
      { keywords: ['plywood'], value: 'Plywood' },
      { keywords: ['mdf', 'medium density fiberboard'], value: 'MDF' },
      { keywords: ['rubberwood'], value: 'Rubberwood' },
      { keywords: ['pine', 'pine wood'], value: 'Pine' },
      { keywords: ['oak', 'oak wood'], value: 'Oak' },
      { keywords: ['birch'], value: 'Birch' },
      { keywords: ['walnut'], value: 'Walnut' },
      { keywords: ['stainless steel'], value: 'Stainless Steel' },
      { keywords: ['steel', 'steel frame'], value: 'Steel' },
      { keywords: ['iron', 'wrought iron'], value: 'Iron' },
      { keywords: ['aluminum', 'aluminium'], value: 'Aluminum' },
      { keywords: ['metal', 'metal frame'], value: 'Metal' },
      { keywords: ['wood'], value: 'Wood' },
    ];

    // 框架指向词
    const frameIndicators = ['frame', 'base', 'structure', 'support', 'legs', 'construction'];

    const foundMaterials: string[] = [];

    // 检查是否有框架指向词 + 材料组合
    for (const material of frameMaterials) {
      for (const keyword of material.keywords) {
        // 检查材料是否与框架指向词关联
        const hasFrameContext = frameIndicators.some(indicator => {
          const pattern1 = new RegExp(`${keyword}\\s+${indicator}`, 'i');
          const pattern2 = new RegExp(`${indicator}[:\\s]+.*${keyword}`, 'i');
          const pattern3 = new RegExp(`${keyword}.*${indicator}`, 'i');
          return pattern1.test(text) || pattern2.test(text) || pattern3.test(text);
        });

        // 或者直接出现材料关键词
        if ((hasFrameContext || text.includes(keyword)) && !foundMaterials.includes(material.value)) {
          foundMaterials.push(material.value);
          break;
        }
      }
    }

    if (foundMaterials.length > 0) {
      return foundMaterials;
    }

    // 有框架的商品但未明确说明，使用默认值
    if (hasFrame) {
      return ['Conventional materials'];
    }

    return undefined;
  }

  /**
   * 提取座面材质（Seat Material）- 自动生成版本
   * 用于描述坐面/承坐区域的直接接触材质
   * 返回单值字符串，最大4000字符
   */
  private extractSeatMaterialAuto(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是有座面的商品类型
    const seatedProductKeywords = ['chair', 'stool', 'sofa', 'couch', 'bench', 'loveseat', 'recliner', 'sectional', 'ottoman'];
    const noSeatKeywords = ['table', 'desk', 'bed frame', 'cabinet', 'shelf'];

    const hasSeat = seatedProductKeywords.some(kw => text.includes(kw));
    const noSeat = noSeatKeywords.some(kw => text.includes(kw)) && !hasSeat;

    if (noSeat) {
      return undefined;
    }

    // 座面材质词典
    const seatMaterials: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['genuine leather', 'real leather', 'top grain leather'], value: 'Genuine Leather' },
      { keywords: ['faux leather', 'pu leather', 'leatherette', 'synthetic leather', 'vegan leather'], value: 'Faux Leather' },
      { keywords: ['velvet'], value: 'Velvet' },
      { keywords: ['linen'], value: 'Linen' },
      { keywords: ['polyester'], value: 'Polyester' },
      { keywords: ['mesh'], value: 'Mesh' },
      { keywords: ['fabric', 'upholstered'], value: 'Fabric' },
      { keywords: ['wood', 'wooden seat'], value: 'Wood' },
      { keywords: ['plastic'], value: 'Plastic' },
      { keywords: ['rattan', 'wicker'], value: 'Rattan' },
    ];

    // 座面指向词
    const seatIndicators = ['seat', 'seating', 'seat surface', 'seat cushion', 'upholstered seat'];

    // 检查是否有座面指向词 + 材料组合
    for (const material of seatMaterials) {
      for (const keyword of material.keywords) {
        const hasSeatContext = seatIndicators.some(indicator => {
          return text.includes(`${keyword} ${indicator}`) || 
                 text.includes(`${indicator} ${keyword}`) ||
                 text.includes(keyword);
        });
        if (hasSeatContext && text.includes(keyword)) {
          return material.value;
        }
      }
    }

    // 有座面的商品但未明确说明，使用默认值
    if (hasSeat) {
      return 'Conventional materials';
    }

    return undefined;
  }

  /**
   * 提取桌子高度（Table Height）
   * 返回 { unit: string, measure: number } 格式
   * 仅适用于桌类商品，无明确信息则留空
   */
  private extractTableHeight(
    channelAttributes: Record<string, any>,
  ): { unit: string; measure: number } | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是桌类商品
    const tableKeywords = ['table', 'desk', 'console', 'nightstand', 'end table', 'side table', 'coffee table'];
    const isTable = tableKeywords.some(kw => text.includes(kw));

    if (!isTable) {
      return undefined;
    }

    // 高度提取模式（英寸）
    const heightPatterns = [
      /(?:table\s+)?height[:\s]*(\d+(?:\.\d+)?)\s*(?:inch|inches|in|″)/i,
      /(\d+(?:\.\d+)?)\s*(?:inch|inches|in|″)\s*(?:high|tall|height)/i,
      /(?:overall\s+)?height[:\s]*(\d+(?:\.\d+)?)\s*(?:inch|in)?/i,
      /h[:\s]*(\d+(?:\.\d+)?)\s*(?:inch|in|″)/i,
    ];

    for (const pattern of heightPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const measure = parseFloat(match[1]);
        if (measure >= 10 && measure <= 50) {
          return { unit: 'in', measure };
        }
      }
    }

    // 厘米转英寸
    const cmPatterns = [
      /(?:table\s+)?height[:\s]*(\d+(?:\.\d+)?)\s*(?:cm|centimeter)/i,
      /(\d+(?:\.\d+)?)\s*(?:cm|centimeter)\s*(?:high|tall|height)/i,
    ];

    for (const pattern of cmPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const cm = parseFloat(match[1]);
        if (cm >= 25 && cm <= 130) {
          const inches = Math.round((cm / 2.54) * 10) / 10;
          return { unit: 'in', measure: inches };
        }
      }
    }

    // 无明确信息则留空
    return undefined;
  }

  /**
   * 提取顶部材质（Top Material）
   * 用于描述桌面、台面等顶部部件的材料
   * 返回单值字符串，最大200字符
   */
  private extractTopMaterial(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是有顶部的商品类型
    const topProductKeywords = ['table', 'desk', 'console', 'nightstand', 'dresser', 'cabinet', 'shelf', 'counter'];
    const noTopKeywords = ['chair', 'sofa', 'bed'];

    const hasTop = topProductKeywords.some(kw => text.includes(kw));
    const noTop = noTopKeywords.some(kw => text.includes(kw)) && !hasTop;

    if (noTop) {
      return undefined;
    }

    // 顶部材质词典
    const topMaterials: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['tempered glass', 'safety glass'], value: 'Tempered Glass' },
      { keywords: ['glass top', 'glass'], value: 'Glass' },
      { keywords: ['marble', 'faux marble', 'marble top'], value: 'Marble' },
      { keywords: ['granite'], value: 'Granite' },
      { keywords: ['stone', 'sintered stone'], value: 'Stone' },
      { keywords: ['ceramic'], value: 'Ceramic' },
      { keywords: ['solid wood', 'hardwood'], value: 'Solid Wood' },
      { keywords: ['engineered wood', 'particle board'], value: 'Engineered Wood' },
      { keywords: ['mdf'], value: 'MDF' },
      { keywords: ['wood top', 'wooden top', 'wood'], value: 'Wood' },
      { keywords: ['metal top', 'stainless steel top'], value: 'Metal' },
      { keywords: ['laminate'], value: 'Laminate' },
    ];

    // 顶部指向词
    const topIndicators = ['top', 'tabletop', 'table top', 'surface', 'countertop'];

    // 检查是否有顶部指向词 + 材料组合
    for (const material of topMaterials) {
      for (const keyword of material.keywords) {
        const hasTopContext = topIndicators.some(indicator => {
          return text.includes(`${keyword} ${indicator}`) || 
                 text.includes(`${indicator}`) && text.includes(keyword);
        });
        if (hasTopContext || text.includes(keyword)) {
          return material.value;
        }
      }
    }

    // 有顶部的商品但未明确说明，使用默认值
    if (hasTop) {
      return 'Conventional materials';
    }

    return undefined;
  }

  /**
   * 提取顶部尺寸（Top Dimensions）
   * 用于描述桌面、台面的尺寸规格（长×宽）
   * 返回字符串格式，最大40字符
   */
  private extractTopDimensions(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`;

    // 检查是否是有顶部的商品类型
    const topProductKeywords = ['table', 'desk', 'console', 'nightstand', 'dresser', 'counter'];
    const hasTop = topProductKeywords.some(kw => text.toLowerCase().includes(kw));

    if (!hasTop) {
      return undefined;
    }

    // 顶部尺寸提取模式
    const dimensionPatterns = [
      // "Tabletop: 55 x 31 in" 或 "Top: 55x31 in"
      /(?:tabletop|top|top surface|table top)[:\s]*(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(?:inch|inches|in|″)/i,
      // "Top Dimensions 140×80 cm"
      /(?:top\s+)?dimensions?[:\s]*(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(?:cm|centimeter)/i,
      // "Glass Top Size 40"x24""
      /(?:top\s+)?size[:\s]*(\d+(?:\.\d+)?)\s*[""″]?\s*[x×]\s*(\d+(?:\.\d+)?)\s*[""″]?/i,
    ];

    for (const pattern of dimensionPatterns) {
      const match = text.match(pattern);
      if (match && match[1] && match[2]) {
        const dim1 = parseFloat(match[1]);
        const dim2 = parseFloat(match[2]);
        // 检查是否是厘米（数值较大）
        if (dim1 > 50 || dim2 > 50) {
          // 转换为英寸
          const inch1 = Math.round((dim1 / 2.54) * 10) / 10;
          const inch2 = Math.round((dim2 / 2.54) * 10) / 10;
          return `${inch1} x ${inch2} in`;
        }
        return `${dim1} x ${dim2} in`;
      }
    }

    // 有顶部但无明确尺寸，使用默认值
    if (hasTop) {
      return 'Standard Top Size';
    }

    return undefined;
  }

  /**
   * 提取顶部表面处理（Top Finish）
   * 用于描述桌面或顶部组件的表面处理方式
   * 返回字符串格式，最大100字符
   */
  private extractTopFinish(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是有顶部的商品类型
    const topProductKeywords = ['table', 'desk', 'console', 'nightstand', 'dresser', 'counter'];
    const noTopKeywords = ['chair', 'sofa', 'bed'];

    const hasTop = topProductKeywords.some(kw => text.includes(kw));
    const noTop = noTopKeywords.some(kw => text.includes(kw)) && !hasTop;

    if (noTop) {
      return undefined;
    }

    // 表面处理词典
    const finishTypes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['high gloss', 'high-gloss', 'glossy'], value: 'Glossy' },
      { keywords: ['matte', 'matt'], value: 'Matte' },
      { keywords: ['satin'], value: 'Satin' },
      { keywords: ['distressed', 'weathered', 'rustic'], value: 'Distressed' },
      { keywords: ['brushed'], value: 'Brushed' },
      { keywords: ['polished'], value: 'Polished' },
      { keywords: ['lacquered', 'lacquer'], value: 'Lacquered' },
      { keywords: ['varnished', 'varnish'], value: 'Varnished' },
      { keywords: ['oiled', 'oil finish'], value: 'Oiled' },
      { keywords: ['natural finish', 'clear finish', 'natural'], value: 'Natural Finish' },
      { keywords: ['painted'], value: 'Painted' },
    ];

    // 顶部指向词
    const topIndicators = ['top', 'tabletop', 'table top', 'surface'];

    // 检查是否有顶部指向词 + finish组合
    for (const finish of finishTypes) {
      for (const keyword of finish.keywords) {
        const hasTopContext = topIndicators.some(indicator => {
          return text.includes(`${keyword} ${indicator}`) ||
                 text.includes(`${indicator}`) && text.includes(keyword);
        });
        if (hasTopContext || text.includes(keyword)) {
          return finish.value;
        }
      }
    }

    // 有顶部但未明确说明，使用默认值
    if (hasTop) {
      return 'Natural Finish';
    }

    return undefined;
  }

  /**
   * 提取五金表面处理（Hardware Finish）
   * 用于描述五金部件的表面处理方式
   * 返回字符串格式，最大4000字符
   */
  private extractHardwareFinish(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否是有五金的商品类型
    const hardwareProductKeywords = ['dresser', 'cabinet', 'drawer', 'nightstand', 'wardrobe', 'armoire', 'buffet', 'sideboard'];
    const noHardwareKeywords = ['sofa', 'mattress', 'rug'];

    const hasHardware = hardwareProductKeywords.some(kw => text.includes(kw));
    const noHardware = noHardwareKeywords.some(kw => text.includes(kw)) && !hasHardware;

    if (noHardware) {
      return undefined;
    }

    // 五金表面处理词典
    const hardwareFinishes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['brushed nickel', 'satin nickel'], value: 'Brushed Nickel' },
      { keywords: ['polished nickel'], value: 'Polished Nickel' },
      { keywords: ['brushed brass'], value: 'Brushed Brass' },
      { keywords: ['polished brass'], value: 'Polished Brass' },
      { keywords: ['antique brass'], value: 'Antique Brass' },
      { keywords: ['oil-rubbed bronze', 'oil rubbed bronze'], value: 'Oil-Rubbed Bronze' },
      { keywords: ['bronze'], value: 'Bronze' },
      { keywords: ['chrome', 'polished chrome'], value: 'Chrome' },
      { keywords: ['matte black', 'black'], value: 'Matte Black' },
      { keywords: ['gold', 'golden'], value: 'Gold' },
      { keywords: ['copper'], value: 'Copper' },
      { keywords: ['pewter'], value: 'Pewter' },
      { keywords: ['brushed'], value: 'Brushed' },
      { keywords: ['polished'], value: 'Polished' },
      { keywords: ['satin'], value: 'Satin' },
      { keywords: ['antique'], value: 'Antique' },
    ];

    // 五金指向词
    const hardwareIndicators = ['hardware', 'handle', 'handles', 'pull', 'pulls', 'knob', 'knobs', 'hinge', 'metal accents'];

    // 检查是否有五金指向词 + finish组合
    for (const finish of hardwareFinishes) {
      for (const keyword of finish.keywords) {
        const hasHardwareContext = hardwareIndicators.some(indicator => {
          return text.includes(`${keyword} ${indicator}`) ||
                 text.includes(`${indicator}`) && text.includes(keyword);
        });
        if (hasHardwareContext || (hasHardware && text.includes(keyword))) {
          return finish.value;
        }
      }
    }

    // 有五金但未明确说明，使用默认值
    if (hasHardware) {
      return 'Standard Finish';
    }

    return undefined;
  }

  /**
   * 提取底座材质（Base Material）
   * 返回字符串格式，最大300字符
   */
  private extractBaseMaterial(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否有底座的商品
    const baseProductKeywords = ['table', 'desk', 'pedestal', 'stand', 'lamp', 'tv stand'];
    const hasBase = baseProductKeywords.some(kw => text.includes(kw));

    if (!hasBase) return undefined;

    // 底座材质词典
    const baseMaterials: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['solid wood', 'hardwood'], value: 'Solid Wood' },
      { keywords: ['engineered wood', 'manufactured wood', 'particle board'], value: 'Engineered Wood' },
      { keywords: ['mdf'], value: 'MDF' },
      { keywords: ['stainless steel'], value: 'Stainless Steel' },
      { keywords: ['steel'], value: 'Steel' },
      { keywords: ['iron', 'wrought iron'], value: 'Iron' },
      { keywords: ['aluminum'], value: 'Aluminum' },
      { keywords: ['metal'], value: 'Metal' },
      { keywords: ['marble'], value: 'Marble' },
      { keywords: ['stone'], value: 'Stone' },
      { keywords: ['wood'], value: 'Wood' },
    ];

    const baseIndicators = ['base', 'pedestal', 'stand', 'bottom', 'support base'];

    for (const material of baseMaterials) {
      for (const keyword of material.keywords) {
        const hasBaseContext = baseIndicators.some(ind => 
          text.includes(`${keyword} ${ind}`) || text.includes(`${ind}`) && text.includes(keyword)
        );
        if (hasBaseContext || text.includes(keyword)) {
          return material.value;
        }
      }
    }

    if (hasBase) return 'Standard Material';
    return undefined;
  }

  /**
   * 提取底座颜色（Base Color）
   * 返回字符串格式，最大200字符
   */
  private extractBaseColor(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const baseProductKeywords = ['table', 'desk', 'pedestal', 'stand', 'lamp'];
    const hasBase = baseProductKeywords.some(kw => text.includes(kw));

    if (!hasBase) return undefined;

    const baseIndicators = ['base', 'pedestal', 'stand', 'bottom'];

    for (const [colorKey, colorValue] of Object.entries(this.standardColors)) {
      const hasBaseContext = baseIndicators.some(ind => 
        text.includes(`${colorKey} ${ind}`) || text.includes(`${ind}`) && text.includes(colorKey)
      );
      if (hasBaseContext) {
        return colorValue;
      }
    }

    if (hasBase) return 'Standard Color';
    return undefined;
  }

  /**
   * 提取底座表面处理（Base Finish）
   * 返回字符串格式，最大400字符
   */
  private extractBaseFinish(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const baseProductKeywords = ['table', 'desk', 'pedestal', 'stand'];
    const hasBase = baseProductKeywords.some(kw => text.includes(kw));

    if (!hasBase) return undefined;

    const finishTypes: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['matte', 'matt'], value: 'Matte' },
      { keywords: ['glossy', 'high gloss'], value: 'Glossy' },
      { keywords: ['satin'], value: 'Satin' },
      { keywords: ['brushed'], value: 'Brushed' },
      { keywords: ['polished'], value: 'Polished' },
      { keywords: ['distressed'], value: 'Distressed' },
      { keywords: ['natural'], value: 'Natural' },
    ];

    const baseIndicators = ['base', 'pedestal', 'stand', 'bottom'];

    for (const finish of finishTypes) {
      for (const keyword of finish.keywords) {
        const hasBaseContext = baseIndicators.some(ind => 
          text.includes(`${keyword} ${ind}`) || text.includes(`${ind}`) && text.includes(keyword)
        );
        if (hasBaseContext || text.includes(keyword)) {
          return finish.value;
        }
      }
    }

    if (hasBase) return 'Standard Finish';
    return undefined;
  }

  /**
   * 提取门开启方式（Door Opening Style）
   * 返回字符串格式，最大100字符
   */
  private extractDoorOpeningStyle(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否有门的商品
    const doorProductKeywords = ['cabinet', 'wardrobe', 'armoire', 'cupboard', 'storage cabinet', 'buffet', 'sideboard'];
    const noDoorKeywords = ['open shelf', 'bookshelf', 'drawer only'];

    const hasDoor = doorProductKeywords.some(kw => text.includes(kw));
    const noDoor = noDoorKeywords.some(kw => text.includes(kw)) && !hasDoor;

    if (noDoor) return undefined;

    // 开门方式词典
    const openingStyles: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['sliding door', 'sliding'], value: 'Sliding' },
      { keywords: ['bi-fold', 'bifold', 'bi fold'], value: 'Bi-Fold' },
      { keywords: ['folding door', 'folding'], value: 'Folding' },
      { keywords: ['flip-up', 'flip up', 'lift-up'], value: 'Flip-Up' },
      { keywords: ['drop-down', 'drop down'], value: 'Drop-Down' },
      { keywords: ['swing', 'hinged', 'hinge'], value: 'Swing' },
      { keywords: ['bypass'], value: 'Bypass' },
      { keywords: ['accordion'], value: 'Accordion' },
    ];

    for (const style of openingStyles) {
      if (style.keywords.some(kw => text.includes(kw))) {
        return style.value;
      }
    }

    if (hasDoor) return 'Standard';
    return undefined;
  }

  /**
   * 提取门板样式（Door Style）
   * 返回字符串格式，最大100字符
   */
  private extractDoorStyle(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    const doorProductKeywords = ['cabinet', 'wardrobe', 'armoire', 'cupboard', 'storage cabinet', 'buffet'];
    const noDoorKeywords = ['open shelf', 'bookshelf', 'drawer only'];

    const hasDoor = doorProductKeywords.some(kw => text.includes(kw));
    const noDoor = noDoorKeywords.some(kw => text.includes(kw)) && !hasDoor;

    if (noDoor) return undefined;

    // 门板样式词典
    const doorStyles: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['shaker', 'shaker style'], value: 'Shaker' },
      { keywords: ['raised panel'], value: 'Raised Panel' },
      { keywords: ['recessed panel', 'inset panel'], value: 'Recessed Panel' },
      { keywords: ['flat panel', 'slab'], value: 'Slab' },
      { keywords: ['glass door', 'glass panel'], value: 'Glass' },
      { keywords: ['framed'], value: 'Framed' },
      { keywords: ['beadboard'], value: 'Beadboard' },
      { keywords: ['louvered', 'louver'], value: 'Louvered' },
    ];

    for (const style of doorStyles) {
      if (style.keywords.some(kw => text.includes(kw))) {
        return style.value;
      }
    }

    if (hasDoor) return 'Standard Door Style';
    return undefined;
  }

  /**
   * 提取板条宽度（Slat Width）
   * 返回 { unit: string, measure: number } 格式
   * 仅适用于有板条结构的商品
   */
  private extractSlatWidth(
    channelAttributes: Record<string, any>,
  ): { unit: string; measure: number } | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否有板条结构
    const slatKeywords = ['slat', 'slats', 'slatted', 'bed slat'];
    const hasSlat = slatKeywords.some(kw => text.includes(kw));

    if (!hasSlat) return undefined;

    // 板条宽度提取模式（英寸）
    const widthPatterns = [
      /slat\s*width[:\s]*(\d+(?:\.\d+)?)\s*(?:inch|inches|in|″)/i,
      /(\d+(?:\.\d+)?)\s*(?:inch|in|″)\s*(?:wide\s+)?slats?/i,
      /slats?\s*(?:are\s+)?(\d+(?:\.\d+)?)\s*(?:inch|in)\s*wide/i,
    ];

    for (const pattern of widthPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const measure = parseFloat(match[1]);
        if (measure >= 1 && measure <= 10) {
          return { unit: 'in', measure };
        }
      }
    }

    // 厘米转英寸
    const cmPatterns = [
      /slat\s*width[:\s]*(\d+(?:\.\d+)?)\s*(?:cm|centimeter)/i,
      /(\d+(?:\.\d+)?)\s*(?:cm)\s*(?:wide\s+)?slats?/i,
    ];

    for (const pattern of cmPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const cm = parseFloat(match[1]);
        if (cm >= 2 && cm <= 25) {
          const inches = Math.round((cm / 2.54) * 10) / 10;
          return { unit: 'in', measure: inches };
        }
      }
    }

    // 有板条但无明确宽度，返回undefined（不使用默认值）
    return undefined;
  }

  /**
   * 提取挂钩数量（Number of Hooks）
   * 返回整数
   */
  private extractNumberOfHooks(channelAttributes: Record<string, any>): number | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否有挂钩结构
    const hookKeywords = ['hook', 'hooks', 'coat rack', 'hat rack', 'wall hook'];
    const hasHook = hookKeywords.some(kw => text.includes(kw));

    if (!hasHook) return undefined;

    // 英文数字词映射
    const numberWords: Record<string, number> = {
      one: 1, two: 2, three: 3, four: 4, five: 5,
      six: 6, seven: 7, eight: 8, nine: 9, ten: 10,
      twelve: 12, fifteen: 15, twenty: 20,
    };

    // 数字 + hook 模式
    const patterns = [
      /(\d+)\s*[-]?\s*hooks?/i,
      /hooks?[:\s]*(\d+)/i,
      /includes?\s+(\d+)\s+hooks?/i,
    ];

    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        const num = parseInt(match[1], 10);
        if (num >= 1 && num <= 50) return num;
      }
    }

    // 英文数字词 + hook
    for (const [word, num] of Object.entries(numberWords)) {
      if (new RegExp(`${word}\\s*hooks?`, 'i').test(text)) return num;
    }

    // 有挂钩但无明确数量，默认1
    if (hasHook) return 1;
    return undefined;
  }

  /**
   * 提取床头板样式（Headboard Style）
   * 返回字符串格式，最大100字符
   */
  private extractHeadboardStyle(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否有床头板
    const headboardKeywords = ['headboard', 'bed head', 'with headboard'];
    const noHeadboardKeywords = ['headboard not included', 'no headboard', 'without headboard'];

    const hasHeadboard = headboardKeywords.some(kw => text.includes(kw));
    const noHeadboard = noHeadboardKeywords.some(kw => text.includes(kw));

    if (noHeadboard || !hasHeadboard) return undefined;

    // 床头板样式词典
    const headboardStyles: Array<{ keywords: string[]; value: string }> = [
      { keywords: ['button-tufted', 'button tufted'], value: 'Button-Tufted' },
      { keywords: ['channel-tufted', 'channel tufted'], value: 'Channel Tufted' },
      { keywords: ['tufted'], value: 'Tufted' },
      { keywords: ['upholstered'], value: 'Upholstered' },
      { keywords: ['slatted', 'slat'], value: 'Slatted' },
      { keywords: ['panel', 'panel headboard'], value: 'Panel' },
      { keywords: ['bookcase', 'storage headboard'], value: 'Bookcase' },
      { keywords: ['curved'], value: 'Curved' },
      { keywords: ['straight'], value: 'Straight' },
      { keywords: ['low profile'], value: 'Low Profile' },
      { keywords: ['tall', 'high'], value: 'Tall' },
      { keywords: ['wingback'], value: 'Wingback' },
    ];

    for (const style of headboardStyles) {
      if (style.keywords.some(kw => text.includes(kw))) {
        return style.value;
      }
    }

    if (hasHeadboard) return 'Standard Headboard';
    return undefined;
  }

  /**
   * 提取框架颜色（Frame Color）
   * 返回字符串格式，最大200字符
   */
  private extractFrameColor(channelAttributes: Record<string, any>): string | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 检查是否有框架
    const frameKeywords = ['frame', 'metal frame', 'wood frame', 'steel frame'];
    const hasFrame = frameKeywords.some(kw => text.includes(kw));

    if (!hasFrame) return undefined;

    const frameIndicators = ['frame', 'structure', 'base frame', 'support'];

    for (const [colorKey, colorValue] of Object.entries(this.standardColors)) {
      const hasFrameContext = frameIndicators.some(ind =>
        text.includes(`${colorKey} ${ind}`) || text.includes(`${ind}`) && text.includes(colorKey)
      );
      if (hasFrameContext) {
        return colorValue;
      }
    }

    if (hasFrame) return 'Standard Color';
    return undefined;
  }

  /**
   * 提取是否智能（Is Smart）
   * 强默认No，仅明确智能特征时返回Yes
   */
  private extractIsSmart(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 智能特征关键词
    const smartKeywords = [
      'smart', 'smart furniture', 'wi-fi enabled', 'wifi enabled',
      'bluetooth enabled', 'app controlled', 'app control',
      'voice control', 'alexa compatible', 'google assistant',
      'smart home integration', 'iot', 'connected',
    ];

    // 非智能关键词
    const nonSmartKeywords = ['non-smart', 'manual'];

    if (nonSmartKeywords.some(kw => text.includes(kw))) return 'No';
    if (smartKeywords.some(kw => text.includes(kw))) return 'Yes';
    return 'No';
  }

  /**
   * 提取是否古董（Is Antique）
   * 强默认No，仅真正古董时返回Yes
   */
  private extractIsAntique(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 仿古/风格关键词（排除）
    const styleKeywords = [
      'antique style', 'antique finish', 'antique look', 'vintage style',
      'retro', 'reproduction', 'antique-inspired', 'distressed',
    ];

    // 真正古董关键词
    const antiqueKeywords = [
      'authentic antique', 'genuine antique', '18th century', '19th century',
      'circa 1900', 'circa 1800', 'victorian era', 'antique collectible',
    ];

    // 排除仿古风格
    if (styleKeywords.some(kw => text.includes(kw))) return 'No';
    // 真正古董
    if (antiqueKeywords.some(kw => text.includes(kw))) return 'Yes';
    return 'No';
  }

  /**
   * 提取是否可折叠（Is Foldable）
   * 强默认No，仅明确可折叠时返回Yes
   */
  private extractIsFoldable(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 可折叠关键词
    const foldableKeywords = [
      'foldable', 'folding', 'folds flat', 'collapsible',
      'portable folding', 'flip-and-fold', 'fold up', 'fold down',
    ];

    // 非折叠关键词
    const nonFoldableKeywords = ['non-foldable', 'fixed', 'stationary'];

    if (nonFoldableKeywords.some(kw => text.includes(kw))) return 'No';
    if (foldableKeywords.some(kw => text.includes(kw))) return 'Yes';
    return 'No';
  }

  /**
   * 提取是否充气（Is Inflatable）
   * 强默认No，仅充气结构时返回Yes
   */
  private extractIsInflatable(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 充气关键词
    const inflatableKeywords = [
      'inflatable', 'air mattress', 'air bed', 'blow up',
      'inflates', 'inflation valve', 'air pump', 'inflate',
    ];

    // 非充气关键词
    const nonInflatableKeywords = ['non-inflatable', 'not inflatable'];

    if (nonInflatableKeywords.some(kw => text.includes(kw))) return 'No';
    if (inflatableKeywords.some(kw => text.includes(kw))) return 'Yes';
    return 'No';
  }

  /**
   * 提取是否带轮（Is Wheeled）
   * 强默认No，仅明确带轮时返回Yes
   */
  private extractIsWheeled(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 带轮关键词
    const wheeledKeywords = [
      'wheels', 'wheeled', 'with wheels', 'casters', 'rolling',
      'mobile on wheels', 'on wheels', 'roller', 'swivel casters',
    ];

    // 非带轮关键词
    const nonWheeledKeywords = ['no wheels', 'non-wheeled', 'stationary', 'without wheels'];

    if (nonWheeledKeywords.some(kw => text.includes(kw))) return 'No';
    if (wheeledKeywords.some(kw => text.includes(kw))) return 'Yes';
    return 'No';
  }

  /**
   * 提取是否工业用途（Is Industrial）
   * 强默认No，仅明确工业/商业用途时返回Yes
   * 排除纯工业风格描述
   */
  private extractIsIndustrial(channelAttributes: Record<string, any>): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const bulletPoints = getNestedValue(channelAttributes, 'bulletPoints') || [];

    const cleanDesc = this.stripHtmlTags(description);
    const bullets = Array.isArray(bulletPoints) ? bulletPoints.join(' ') : '';
    const text = `${title} ${cleanDesc} ${bullets}`.toLowerCase();

    // 排除纯风格描述
    const styleOnlyPatterns = [
      'industrial style', 'industrial-style', 'industrial look', 'industrial design',
      'industrial chic', 'industrial aesthetic', 'loft style', 'factory style',
      'warehouse style', 'rustic industrial',
    ];

    // 否定性描述
    const negativeKeywords = ['not industrial', 'residential use only', 'home use only', 'not for commercial'];

    if (negativeKeywords.some(kw => text.includes(kw))) return 'No';

    // 真正工业/商业用途关键词
    const industrialUseKeywords = [
      'industrial use', 'industrial grade', 'industrial application',
      'commercial use', 'commercial grade', 'professional grade',
      'workshop use', 'garage use', 'factory use', 'warehouse use',
      'heavy-duty', 'heavy duty', 'for industrial', 'for commercial',
    ];

    // 检查是否仅为风格描述
    const hasStyleOnly = styleOnlyPatterns.some(kw => text.includes(kw));
    const hasIndustrialUse = industrialUseKeywords.some(kw => text.includes(kw));

    // 如果有工业用途关键词，返回Yes
    if (hasIndustrialUse) return 'Yes';
    // 如果只有风格描述，返回No
    if (hasStyleOnly) return 'No';
    return 'No';
  }

  /**
   * 提取组装后产品长度（assembledProductLength）
   * 优先从渠道数据productLength获取，其次从文本提取，兜底1 in
   */
  private extractAssembledProductLength(channelAttributes: Record<string, any>): { unit: string; measure: number } {
    // 优先级1：渠道结构化数据
    const productLength = getNestedValue(channelAttributes, 'productLength');
    if (productLength !== undefined && productLength !== null) {
      if (typeof productLength === 'object' && productLength.measure !== undefined) {
        return { unit: productLength.unit || 'in', measure: Number(productLength.measure) };
      }
      if (typeof productLength === 'number' && productLength > 0) {
        return { unit: 'in', measure: productLength };
      }
      if (typeof productLength === 'string') {
        const num = parseFloat(productLength);
        if (!isNaN(num) && num > 0) return { unit: 'in', measure: num };
      }
    }

    // 优先级2：从文本提取
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc}`.toLowerCase();

    const lengthPatterns = [
      /(?:assembled|overall|finished|total)\s*length[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
      /length[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
      /(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?\s*(?:l|long|length)/i,
    ];

    for (const pattern of lengthPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        let measure = parseFloat(match[1]);
        const unit = (match[2] || 'in').toLowerCase();
        // 转换为英寸
        if (unit === 'cm') measure = Math.round((measure / 2.54) * 10) / 10;
        else if (unit === 'mm') measure = Math.round((measure / 25.4) * 10) / 10;
        if (measure > 0 && measure < 1000) return { unit: 'in', measure };
      }
    }

    // 兜底默认值
    return { unit: 'in', measure: 1 };
  }

  /**
   * 提取组装后产品宽度（assembledProductWidth）
   * 优先从渠道数据productWidth获取，其次从文本提取，兜底1 in
   */
  private extractAssembledProductWidth(channelAttributes: Record<string, any>): { unit: string; measure: number } {
    // 优先级1：渠道结构化数据
    const productWidth = getNestedValue(channelAttributes, 'productWidth');
    if (productWidth !== undefined && productWidth !== null) {
      if (typeof productWidth === 'object' && productWidth.measure !== undefined) {
        return { unit: productWidth.unit || 'in', measure: Number(productWidth.measure) };
      }
      if (typeof productWidth === 'number' && productWidth > 0) {
        return { unit: 'in', measure: productWidth };
      }
      if (typeof productWidth === 'string') {
        const num = parseFloat(productWidth);
        if (!isNaN(num) && num > 0) return { unit: 'in', measure: num };
      }
    }

    // 优先级2：从文本提取
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc}`.toLowerCase();

    const widthPatterns = [
      /(?:assembled|overall|finished|total)\s*width[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
      /width[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
      /(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?\s*(?:w|wide|width)/i,
    ];

    for (const pattern of widthPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        let measure = parseFloat(match[1]);
        const unit = (match[2] || 'in').toLowerCase();
        if (unit === 'cm') measure = Math.round((measure / 2.54) * 10) / 10;
        else if (unit === 'mm') measure = Math.round((measure / 25.4) * 10) / 10;
        if (measure > 0 && measure < 1000) return { unit: 'in', measure };
      }
    }

    return { unit: 'in', measure: 1 };
  }

  /**
   * 提取组装后产品高度（assembledProductHeight）
   * 优先从渠道数据productHeight获取，其次从文本提取，兜底1 in
   */
  private extractAssembledProductHeight(channelAttributes: Record<string, any>): { unit: string; measure: number } {
    // 优先级1：渠道结构化数据
    const productHeight = getNestedValue(channelAttributes, 'productHeight');
    if (productHeight !== undefined && productHeight !== null) {
      if (typeof productHeight === 'object' && productHeight.measure !== undefined) {
        return { unit: productHeight.unit || 'in', measure: Number(productHeight.measure) };
      }
      if (typeof productHeight === 'number' && productHeight > 0) {
        return { unit: 'in', measure: productHeight };
      }
      if (typeof productHeight === 'string') {
        const num = parseFloat(productHeight);
        if (!isNaN(num) && num > 0) return { unit: 'in', measure: num };
      }
    }

    // 优先级2：从文本提取
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc}`.toLowerCase();

    const heightPatterns = [
      /(?:assembled|overall|finished|total)\s*height[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
      /height[:\s]*(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?/i,
      /(\d+(?:\.\d+)?)\s*(in|inch|inches|cm|mm)?\s*(?:h|high|tall|height)/i,
    ];

    for (const pattern of heightPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        let measure = parseFloat(match[1]);
        const unit = (match[2] || 'in').toLowerCase();
        if (unit === 'cm') measure = Math.round((measure / 2.54) * 10) / 10;
        else if (unit === 'mm') measure = Math.round((measure / 25.4) * 10) / 10;
        if (measure > 0 && measure < 1000) return { unit: 'in', measure };
      }
    }

    return { unit: 'in', measure: 1 };
  }

  /**
   * 提取组装后产品重量（assembledProductWeight）
   * 优先从渠道数据productWeight获取，其次从文本提取，兜底1 lb
   */
  private extractAssembledProductWeight(channelAttributes: Record<string, any>): { unit: string; measure: number } {
    // 优先级1：渠道结构化数据
    const productWeight = getNestedValue(channelAttributes, 'productWeight');
    if (productWeight !== undefined && productWeight !== null) {
      if (typeof productWeight === 'object' && productWeight.measure !== undefined) {
        let measure = Number(productWeight.measure);
        const unit = (productWeight.unit || 'lb').toLowerCase();
        // 转换为磅
        if (unit === 'kg') measure = Math.round(measure * 2.20462 * 10) / 10;
        else if (unit === 'g') measure = Math.round((measure / 453.592) * 10) / 10;
        else if (unit === 'oz') measure = Math.round((measure / 16) * 10) / 10;
        return { unit: 'lb', measure };
      }
      if (typeof productWeight === 'number' && productWeight > 0) {
        return { unit: 'lb', measure: productWeight };
      }
      if (typeof productWeight === 'string') {
        const num = parseFloat(productWeight);
        if (!isNaN(num) && num > 0) return { unit: 'lb', measure: num };
      }
    }

    // 优先级2：从文本提取（排除shipping/package weight）
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const cleanDesc = this.stripHtmlTags(description);
    const text = `${title} ${cleanDesc}`.toLowerCase();

    // 排除包装/运输重量
    const excludePatterns = ['shipping weight', 'package weight', 'gross weight', 'boxed weight'];

    const weightPatterns = [
      /(?:assembled|overall|net|item|product)\s*weight[:\s]*(\d+(?:\.\d+)?)\s*(lb|lbs|pound|pounds|kg|g|oz)?/i,
      /weight[:\s]*(\d+(?:\.\d+)?)\s*(lb|lbs|pound|pounds|kg|g|oz)?/i,
      /(\d+(?:\.\d+)?)\s*(lb|lbs|pound|pounds|kg)\s*(?:weight)?/i,
    ];

    for (const pattern of weightPatterns) {
      const match = text.match(pattern);
      if (match && match[1]) {
        // 检查是否是排除的重量类型
        const matchIndex = match.index || 0;
        const contextBefore = text.substring(Math.max(0, matchIndex - 20), matchIndex);
        if (excludePatterns.some(ep => contextBefore.includes(ep))) continue;

        let measure = parseFloat(match[1]);
        const unit = (match[2] || 'lb').toLowerCase();
        // 转换为磅
        if (unit === 'kg') measure = Math.round(measure * 2.20462 * 10) / 10;
        else if (unit === 'g') measure = Math.round((measure / 453.592) * 10) / 10;
        else if (unit === 'oz') measure = Math.round((measure / 16) * 10) / 10;
        if (measure > 0 && measure < 10000) return { unit: 'lb', measure };
      }
    }

    return { unit: 'lb', measure: 1 };
  }

  /**
   * 清理 HTML 标签和 CSS 样式
   * 用于从描述中提取纯文本内容
   */
  private stripHtmlTags(html: string): string {
    if (!html) return '';
    
    return html
      // 移除 style 标签及其内容
      .replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
      // 移除 script 标签及其内容
      .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
      // 移除内联 style 属性
      .replace(/\s*style\s*=\s*["'][^"']*["']/gi, '')
      // 移除所有 HTML 标签
      .replace(/<[^>]+>/g, ' ')
      // 解码 HTML 实体
      .replace(/&nbsp;/g, ' ')
      .replace(/&amp;/g, '&')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"')
      .replace(/&#39;/g, "'")
      // 移除多余空白
      .replace(/\s+/g, ' ')
      .trim();
  }
}
