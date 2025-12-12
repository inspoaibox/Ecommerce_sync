/**
 * 测试脚本：完整测试属性映射流程
 * 
 * 使用 SKU: SJ000149AAK 测试当前配置的属性映射规则
 * 
 * 运行方式: 
 *   cd apps/api
 *   npx ts-node -r tsconfig-paths/register scripts/test-attribute-mapping.ts
 */

import { PrismaClient } from '@prisma/client';
import { getNestedValue, getCustomAttributeValue } from '../src/adapters/channels/standard-product.utils';

const prisma = new PrismaClient();

// 测试的 SKU
const TEST_SKU = 'SJ000149AAK';

/**
 * 模拟 AttributeResolverService 的核心逻辑
 */
class MockAttributeResolver {
  
  /**
   * 解析渠道数据映射
   */
  resolveChannelData(channelAttributes: Record<string, any>, fieldPath: string): any {
    if (!fieldPath) return undefined;
    
    // 支持 customAttributes.xxx 格式
    if (fieldPath.startsWith('customAttributes.')) {
      const attrName = fieldPath.substring('customAttributes.'.length);
      return getCustomAttributeValue(channelAttributes, attrName);
    }
    
    return getNestedValue(channelAttributes, fieldPath);
  }

  /**
   * 提取颜色
   */
  extractColor(channelAttributes: Record<string, any>): string | undefined {
    // 优先从 color 字段取值
    const color = getNestedValue(channelAttributes, 'color');
    if (color) return color;
    
    // 从 customAttributes 取值
    const customColor = getCustomAttributeValue(channelAttributes, 'color');
    if (customColor) return customColor;
    
    // 从标题/描述提取
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();
    
    const colors = ['black', 'white', 'brown', 'gray', 'grey', 'beige', 'natural', 'walnut', 'oak'];
    for (const c of colors) {
      if (text.includes(c)) {
        return c.charAt(0).toUpperCase() + c.slice(1);
      }
    }
    return undefined;
  }

  /**
   * 提取材质
   */
  extractMaterial(channelAttributes: Record<string, any>): string | undefined {
    const material = getNestedValue(channelAttributes, 'material');
    if (material) return material;
    
    const customMaterial = getCustomAttributeValue(channelAttributes, 'material');
    if (customMaterial) return customMaterial;
    
    const title = getNestedValue(channelAttributes, 'title') || '';
    const description = getNestedValue(channelAttributes, 'description') || '';
    const text = `${title} ${description}`.toLowerCase();
    
    const materials = ['wood', 'metal', 'fabric', 'leather', 'velvet', 'linen', 'mdf', 'particleboard'];
    for (const m of materials) {
      if (text.includes(m)) {
        return m.charAt(0).toUpperCase() + m.slice(1);
      }
    }
    return undefined;
  }

  /**
   * 提取产品数量
   */
  extractPieceCount(channelAttributes: Record<string, any>, defaultValue: string = '1'): string {
    const title = getNestedValue(channelAttributes, 'title') || '';
    const text = title.toLowerCase();
    
    // 匹配 "set of X", "X-piece", "X piece" 等
    const patterns = [
      /set\s+of\s+(\d+)/i,
      /(\d+)\s*[-]?\s*pieces?\s+set/i,
      /(\d+)\s*[-]?\s*pc\s+set/i,
    ];
    
    for (const pattern of patterns) {
      const match = text.match(pattern);
      if (match) return match[1];
    }
    
    return defaultValue;
  }

  /**
   * 提取包含物品
   */
  extractItemsIncluded(channelAttributes: Record<string, any>): string[] | undefined {
    const title = getNestedValue(channelAttributes, 'title') || '';
    
    // 匹配 "X and Y Set of N" 模式
    const setPattern = /^(.+?)\s+(?:and|&)\s+(.+?)\s+set\s+of\s+\d+/i;
    const setMatch = title.match(setPattern);
    
    if (setMatch) {
      const item1 = this.extractMainItemName(setMatch[1]);
      const item2 = this.extractMainItemName(setMatch[2]);
      
      if (item1 && item2) {
        const normalized1 = this.normalizeItemName(item1);
        const normalized2 = this.normalizeItemName(item2);
        
        if (normalized1 === normalized2) {
          return [this.capitalizeItemName(item1)];
        }
        return [this.capitalizeItemName(item1), this.capitalizeItemName(item2)];
      }
    }
    
    return undefined;
  }

  private extractMainItemName(segment: string): string | null {
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

    for (const item of furnitureItems) {
      if (lowerSegment.endsWith(item) || lowerSegment.includes(item)) {
        return item;
      }
    }

    const words = lowerSegment.split(/\s+/);
    if (words.length >= 2) {
      return words.slice(-2).join(' ');
    }

    return lowerSegment || null;
  }

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

  private capitalizeItemName(name: string): string {
    return name
      .toLowerCase()
      .split(' ')
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  }

  /**
   * 计算售价
   */
  calculatePrice(channelAttributes: Record<string, any>, config: any): number | undefined {
    const basePrice = getNestedValue(channelAttributes, 'price');
    if (!basePrice) return undefined;
    
    const price = parseFloat(basePrice);
    if (isNaN(price)) return undefined;
    
    const multiplier = config?.multiplier || 1;
    const addition = config?.addition || 0;
    
    return Math.round((price * multiplier + addition) * 100) / 100;
  }

  /**
   * 提取运输重量
   */
  extractShippingWeight(channelAttributes: Record<string, any>): number | undefined {
    // 优先从 packageWeight 取值
    const packageWeight = getNestedValue(channelAttributes, 'packageWeight');
    if (packageWeight) {
      const weight = parseFloat(packageWeight);
      if (!isNaN(weight)) return weight;
    }
    
    // 从 packages 数组计算总重量
    const packages = getNestedValue(channelAttributes, 'packages');
    if (Array.isArray(packages) && packages.length > 0) {
      let totalWeight = 0;
      for (const pkg of packages) {
        const w = parseFloat(pkg.weight || pkg.packageWeight || 0);
        if (!isNaN(w)) totalWeight += w;
      }
      if (totalWeight > 0) return totalWeight;
    }
    
    return undefined;
  }
}

async function main() {
  console.log('='.repeat(80));
  console.log(`测试属性映射 - SKU: ${TEST_SKU}`);
  console.log('='.repeat(80));

  const resolver = new MockAttributeResolver();

  // 1. 获取商品数据
  console.log('\n【1】获取商品数据\n');
  
  const product = await prisma.listingProduct.findFirst({
    where: { sku: TEST_SKU },
    include: {
      shop: {
        include: { platform: true },
      },
    },
  });

  if (!product) {
    console.log(`❌ 未找到商品 ${TEST_SKU}`);
    return;
  }

  console.log(`✅ 找到商品`);
  console.log(`   - ID: ${product.id}`);
  console.log(`   - SKU: ${product.sku}`);
  console.log(`   - 标题: ${product.title?.substring(0, 80)}...`);
  console.log(`   - 平台类目ID: ${product.platformCategoryId}`);

  const channelAttrs = product.channelAttributes as Record<string, any>;
  
  if (!channelAttrs) {
    console.log('❌ channelAttributes 为空');
    return;
  }

  // 2. 获取类目映射配置
  console.log('\n【2】获取类目映射配置\n');
  
  const platform = await prisma.platform.findFirst({
    where: { code: 'walmart' },
  });

  if (!platform) {
    console.log('❌ 未找到 Walmart 平台');
    return;
  }

  // 使用商品的 platformCategoryId 查找映射
  const categoryMapping = await prisma.categoryAttributeMapping.findFirst({
    where: {
      platformId: platform.id,
      country: 'US',
      categoryId: product.platformCategoryId || 'Living Room Furniture Sets',
    },
  });

  if (!categoryMapping) {
    console.log(`❌ 未找到类目 "${product.platformCategoryId}" 的映射配置`);
    return;
  }

  console.log(`✅ 找到类目映射配置`);
  console.log(`   - 类目ID: ${categoryMapping.categoryId}`);

  const mappingRules = categoryMapping.mappingRules as any;
  const rules = mappingRules?.rules || [];
  console.log(`   - 规则数量: ${rules.length}`);

  // 3. 测试每个映射规则
  console.log('\n【3】测试映射规则解析\n');
  console.log('-'.repeat(80));
  
  const results: Array<{
    attributeId: string;
    attributeName: string;
    mappingType: string;
    configValue: any;
    resolvedValue: any;
    status: string;
  }> = [];

  for (const rule of rules) {
    let resolvedValue: any = undefined;
    let status = '❌';

    try {
      switch (rule.mappingType) {
        case 'default_value':
          resolvedValue = rule.value;
          break;

        case 'channel_data':
          resolvedValue = resolver.resolveChannelData(channelAttrs, rule.value as string);
          break;

        case 'enum_select':
          resolvedValue = rule.value;
          break;

        case 'auto_generate':
          const config = rule.value as { ruleType: string; param?: any };
          switch (config?.ruleType) {
            case 'color_extract':
              resolvedValue = resolver.extractColor(channelAttrs);
              break;
            case 'material_extract':
              resolvedValue = resolver.extractMaterial(channelAttrs);
              break;
            case 'piece_count_extract':
              resolvedValue = resolver.extractPieceCount(channelAttrs, config.param);
              break;
            case 'items_included_extract':
              resolvedValue = resolver.extractItemsIncluded(channelAttrs);
              break;
            case 'calculate_price':
              resolvedValue = resolver.calculatePrice(channelAttrs, config.param);
              break;
            case 'shipping_weight_extract':
              resolvedValue = resolver.extractShippingWeight(channelAttrs);
              break;
            case 'date_offset':
              const days = parseInt(config.param) || 0;
              const date = new Date();
              date.setDate(date.getDate() + days);
              resolvedValue = date.toISOString().split('T')[0];
              break;
            case 'sku_to_mpn':
              resolvedValue = channelAttrs.sku;
              break;
            default:
              resolvedValue = `[未实现: ${config?.ruleType}]`;
          }
          break;

        case 'upc_pool':
          resolvedValue = '[从UPC池获取]';
          break;
      }

      if (resolvedValue !== undefined && resolvedValue !== null && resolvedValue !== '') {
        status = '✅';
      }
    } catch (error: any) {
      resolvedValue = `[错误: ${error.message}]`;
      status = '⚠️';
    }

    results.push({
      attributeId: rule.attributeId,
      attributeName: rule.attributeName,
      mappingType: rule.mappingType,
      configValue: rule.value,
      resolvedValue,
      status,
    });
  }

  // 输出结果
  for (const r of results) {
    const displayValue = typeof r.resolvedValue === 'object' 
      ? JSON.stringify(r.resolvedValue)
      : String(r.resolvedValue ?? '');
    const truncatedValue = displayValue.length > 60 
      ? displayValue.substring(0, 60) + '...' 
      : displayValue;
    
    console.log(`${r.status} ${r.attributeId}`);
    console.log(`   名称: ${r.attributeName}`);
    console.log(`   类型: ${r.mappingType}`);
    console.log(`   配置: ${JSON.stringify(r.configValue)}`);
    console.log(`   结果: ${truncatedValue}`);
    console.log('');
  }

  // 4. 统计
  console.log('-'.repeat(80));
  console.log('\n【4】统计\n');
  
  const successCount = results.filter(r => r.status === '✅').length;
  const failCount = results.filter(r => r.status === '❌').length;
  const warnCount = results.filter(r => r.status === '⚠️').length;
  
  console.log(`✅ 成功: ${successCount}`);
  console.log(`❌ 失败/空值: ${failCount}`);
  console.log(`⚠️ 警告: ${warnCount}`);
  console.log(`📊 总计: ${results.length}`);

  // 5. 显示原始渠道数据（用于调试）
  console.log('\n【5】原始渠道数据 (channelAttributes)\n');
  console.log(JSON.stringify(channelAttrs, null, 2).substring(0, 3000));
  if (JSON.stringify(channelAttrs).length > 3000) {
    console.log('... (数据过长，已截断)');
  }

  console.log('\n' + '='.repeat(80));
  console.log('测试完成');
  console.log('='.repeat(80));
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
