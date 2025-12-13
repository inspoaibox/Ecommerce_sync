import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '@/common/prisma/prisma.service';
import { AttributeResolverService } from '@/modules/attribute-mapping/attribute-resolver.service';
import * as XLSX from 'xlsx';

@Injectable()
export class ProductPoolService {
  constructor(
    private prisma: PrismaService,
    private attributeResolver: AttributeResolverService,
  ) {}

  /**
   * 获取商品池统计信息
   */
  async getStats(channelId?: string) {
    const where: any = {};
    if (channelId) where.channelId = channelId;

    const total = await this.prisma.productPool.count({ where });
    return { total };
  }

  /**
   * 获取商品池列表（分页）
   */
  async list(params: {
    page?: number;
    pageSize?: number;
    channelId?: string;
    keyword?: string;
    sku?: string;
    platformCategoryId?: string;
  }) {
    const { page = 1, pageSize = 20, channelId, keyword, sku, platformCategoryId } = params;
    const skip = (page - 1) * pageSize;

    const where: any = {};
    if (channelId) where.channelId = channelId;
    if (sku) where.sku = { contains: sku, mode: 'insensitive' };
    if (platformCategoryId) where.platformCategoryId = platformCategoryId;
    if (keyword) {
      where.OR = [
        { sku: { contains: keyword, mode: 'insensitive' } },
        { title: { contains: keyword, mode: 'insensitive' } },
      ];
    }

    const [data, total] = await Promise.all([
      this.prisma.productPool.findMany({
        where,
        skip,
        take: pageSize,
        orderBy: { createdAt: 'desc' },
        include: {
          channel: { select: { id: true, name: true } },
        },
      }),
      this.prisma.productPool.count({ where }),
    ]);

    return { data, total, page, pageSize, totalPages: Math.ceil(total / pageSize) };
  }

  /**
   * 获取单个商品详情
   */
  async get(id: string) {
    const product = await this.prisma.productPool.findUnique({
      where: { id },
      include: {
        channel: { select: { id: true, name: true } },
        listingProducts: {
          include: {
            shop: { select: { id: true, name: true } },
          },
        },
      },
    });
    if (!product) throw new NotFoundException('商品不存在');
    return product;
  }


  /**
   * 导入商品到商品池
   */
  async importProducts(data: {
    channelId: string;
    products: Array<{
      sku: string;
      title: string;
      description?: string;
      mainImageUrl?: string;
      imageUrls?: string[];
      videoUrls?: string[];
      price: number;
      stock?: number;
      currency?: string;
      channelRawData?: any;
      channelAttributes?: any;
      platformCategoryId?: string;
    }>;
    duplicateAction?: 'skip' | 'update';
    platformCategoryId?: string; // 统一设置的平台类目ID
  }) {
    const { channelId, products, duplicateAction = 'skip', platformCategoryId: globalCategoryId } = data;

    // 验证渠道
    const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) throw new NotFoundException('渠道不存在');

    const result = {
      total: products.length,
      success: 0,
      failed: 0,
      skipped: 0,
      errors: [] as Array<{ sku: string; error: string }>,
    };

    for (const product of products) {
      try {
        const existing = await this.prisma.productPool.findUnique({
          where: { channelId_sku: { channelId, sku: product.sku } },
        });

        if (existing) {
          if (duplicateAction === 'skip') {
            result.skipped++;
            continue;
          }
          // 更新
          await this.prisma.productPool.update({
            where: { id: existing.id },
            data: {
              title: product.title || '',
              description: product.description || null,
              mainImageUrl: product.mainImageUrl || null,
              imageUrls: product.imageUrls || [],
              videoUrls: product.videoUrls || [],
              price: product.price ?? 0,
              stock: product.stock ?? 0,
              currency: product.currency || 'USD',
              channelRawData: product.channelRawData || null,
              channelAttributes: product.channelAttributes || null,
              // 优先使用统一设置的类目，其次使用商品自带的，最后保留原有的
              platformCategoryId: globalCategoryId || product.platformCategoryId || existing.platformCategoryId,
            },
          });
        } else {
          // 创建
          await this.prisma.productPool.create({
            data: {
              channelId,
              sku: product.sku,
              title: product.title || '',
              description: product.description || null,
              mainImageUrl: product.mainImageUrl || null,
              imageUrls: product.imageUrls || [],
              videoUrls: product.videoUrls || [],
              price: product.price ?? 0,
              stock: product.stock ?? 0,
              currency: product.currency || 'USD',
              channelRawData: product.channelRawData || null,
              channelAttributes: product.channelAttributes || null,
              // 优先使用统一设置的类目，其次使用商品自带的
              platformCategoryId: globalCategoryId || product.platformCategoryId || null,
            },
          });
        }
        result.success++;
      } catch (error: any) {
        result.failed++;
        result.errors.push({ sku: product.sku, error: error.message });
      }
    }

    return result;
  }

  /**
   * 从商品池刊登到店铺（直接提交到平台）
   */
  async publishToShop(data: {
    productPoolIds: string[];
    shopId: string;
    platformCategoryId?: string;
  }) {
    const { productPoolIds, shopId, platformCategoryId } = data;

    // 验证店铺（包含平台信息）
    const shop = await this.prisma.shop.findUnique({
      where: { id: shopId },
      include: { platform: true },
    });
    if (!shop) throw new NotFoundException('店铺不存在');

    // 获取商品池商品
    const poolProducts = await this.prisma.productPool.findMany({
      where: { id: { in: productPoolIds } },
    });

    if (poolProducts.length === 0) {
      throw new BadRequestException('未找到商品');
    }

    // 获取类目属性映射配置（如果有）
    let categoryMapping: any = null;
    if (platformCategoryId && shop.platformId) {
      categoryMapping = await this.prisma.categoryAttributeMapping.findUnique({
        where: {
          platformId_country_categoryId: {
            platformId: shop.platformId,
            country: shop.region || 'US',
            categoryId: platformCategoryId,
          },
        },
      });
      console.log(`[publishToShop] Category mapping found: ${!!categoryMapping}`);
    }

    const result = {
      total: poolProducts.length,
      success: 0,
      failed: 0,
      skipped: 0,
      errors: [] as Array<{ sku: string; error: string }>,
      listingProductIds: [] as string[],
    };

    // 第一步：创建或更新 ListingProduct 记录
    for (const poolProduct of poolProducts) {
      try {
        // 根据映射配置生成平台属性
        let platformAttributes: Record<string, any> | null = null;
        if (categoryMapping?.mappingRules) {
          const channelAttrs = (poolProduct.channelAttributes as any) || {};
          const resolveResult = await this.attributeResolver.resolveAttributes(
            categoryMapping.mappingRules,
            channelAttrs,
            { 
              productSku: poolProduct.sku, 
              shopId,
              // 传递商品原价，用于价格计算
              productPrice: Number(poolProduct.price) || 0,
            },
          );
          platformAttributes = resolveResult.attributes;
          console.log(`[publishToShop] Resolved ${Object.keys(platformAttributes).length} platform attributes for ${poolProduct.sku}`);
        }

        // 检查是否已存在
        let listingProduct = await this.prisma.listingProduct.findUnique({
          where: { shopId_sku: { shopId, sku: poolProduct.sku } },
        });

        if (listingProduct) {
          // 更新已存在的记录
          listingProduct = await this.prisma.listingProduct.update({
            where: { id: listingProduct.id },
            data: {
              title: poolProduct.title,
              description: poolProduct.description,
              mainImageUrl: poolProduct.mainImageUrl,
              imageUrls: poolProduct.imageUrls as any,
              videoUrls: poolProduct.videoUrls as any,
              price: poolProduct.price,
              stock: poolProduct.stock,
              currency: poolProduct.currency,
              channelRawData: poolProduct.channelRawData as any,
              channelAttributes: poolProduct.channelAttributes as any,
              platformCategoryId: platformCategoryId || poolProduct.platformCategoryId,
              platformAttributes: platformAttributes ?? (listingProduct.platformAttributes as any) ?? undefined,
              listingStatus: 'pending',
            },
          });
        } else {
          // 创建刊登商品
          listingProduct = await this.prisma.listingProduct.create({
            data: {
              shopId,
              channelId: poolProduct.channelId,
              productPoolId: poolProduct.id,
              sku: poolProduct.sku,
              title: poolProduct.title,
              description: poolProduct.description,
              mainImageUrl: poolProduct.mainImageUrl,
              imageUrls: poolProduct.imageUrls as any,
              videoUrls: poolProduct.videoUrls as any,
              price: poolProduct.price,
              stock: poolProduct.stock,
              currency: poolProduct.currency,
              channelRawData: poolProduct.channelRawData as any,
              channelAttributes: poolProduct.channelAttributes as any,
              platformCategoryId: platformCategoryId || poolProduct.platformCategoryId,
              platformAttributes: platformAttributes ?? undefined,
              listingStatus: 'pending',
            },
          });
        }
        result.listingProductIds.push(listingProduct.id);
        result.success++;
      } catch (error: any) {
        result.failed++;
        result.errors.push({ sku: poolProduct.sku, error: error.message });
      }
    }

    return result;
  }

  /**
   * 更新商品池商品
   */
  async update(id: string, data: any) {
    const product = await this.prisma.productPool.findUnique({ where: { id } });
    if (!product) throw new NotFoundException('商品不存在');

    return this.prisma.productPool.update({
      where: { id },
      data,
    });
  }

  /**
   * 删除商品池商品
   */
  async delete(ids: string[]) {
    await this.prisma.productPool.deleteMany({
      where: { id: { in: ids } },
    });
    return { success: true, deleted: ids.length };
  }

  /**
   * 获取商品池商品关联的店铺刊登情况
   */
  async getListingStatus(productPoolId: string) {
    const listings = await this.prisma.listingProduct.findMany({
      where: { productPoolId },
      include: {
        shop: { select: { id: true, name: true } },
      },
    });
    return listings;
  }

  /**
   * 生成导入模板 Excel
   * 使用标准商品格式
   */
  async generateImportTemplate(): Promise<Buffer> {
    // 定义模板列（基于标准商品格式）
    const columns = [
      // 基础信息（必填）
      { header: 'SKU *', key: 'sku', width: 15, required: true, description: '商品唯一标识，必填' },
      { header: '商品标题 *', key: 'title', width: 40, required: true, description: '商品名称，必填' },
      
      // 基础信息（选填）
      { header: '颜色', key: 'color', width: 12, description: '商品颜色' },
      { header: '材质', key: 'material', width: 15, description: '商品材质' },
      { header: '商品描述', key: 'description', width: 50, description: '支持HTML格式' },
      { header: '五点描述', key: 'bulletPoints', width: 50, description: '多条用 | 分隔' },
      { header: '搜索关键词', key: 'keywords', width: 30, description: '多个用 | 分隔' },
      
      // 价格信息
      { header: '价格 *', key: 'price', width: 10, required: true, description: '商品价格，必填' },
      { header: '优惠价', key: 'salePrice', width: 10, description: '促销价格' },
      { header: '运费', key: 'shippingFee', width: 10, description: '运费价格' },
      { header: '平台价', key: 'platformPrice', width: 10, description: '刊登售价' },
      { header: '货币', key: 'currency', width: 8, description: '默认 USD' },
      
      // 库存
      { header: '库存 *', key: 'stock', width: 10, required: true, description: '库存数量，必填' },
      
      // 图片
      { header: '主图URL', key: 'mainImageUrl', width: 50, description: '主图链接' },
      { header: '附图URLs', key: 'imageUrls', width: 60, description: '多个用 | 分隔' },
      { header: '视频URLs', key: 'videoUrls', width: 40, description: '多个用 | 分隔' },
      
      // 产品尺寸
      { header: '产品长(in)', key: 'productLength', width: 12, description: '英寸' },
      { header: '产品宽(in)', key: 'productWidth', width: 12, description: '英寸' },
      { header: '产品高(in)', key: 'productHeight', width: 12, description: '英寸' },
      { header: '产品重(lb)', key: 'productWeight', width: 12, description: '磅' },
      
      // 包装尺寸
      { header: '包装长(in)', key: 'packageLength', width: 12, description: '英寸' },
      { header: '包装宽(in)', key: 'packageWidth', width: 12, description: '英寸' },
      { header: '包装高(in)', key: 'packageHeight', width: 12, description: '英寸' },
      { header: '包装重(lb)', key: 'packageWeight', width: 12, description: '磅' },
      
      // 其他
      { header: '产地', key: 'placeOfOrigin', width: 12, description: '如 China' },
      { header: '商品性质', key: 'productType', width: 12, description: 'oversized/small/normal/multiPackage' },
      { header: '供货商', key: 'supplier', width: 15, description: '供货商名称' },
      
      // 平台类目
      { header: '平台类目ID', key: 'platformCategoryId', width: 25, description: 'Walmart类目ID' },
    ];

    // 创建工作簿
    const wb = XLSX.utils.book_new();

    // 创建数据表
    const headers = columns.map(c => c.header);
    const dataSheet = XLSX.utils.aoa_to_sheet([headers]);
    
    // 设置列宽
    dataSheet['!cols'] = columns.map(c => ({ wch: c.width }));
    
    // 添加示例数据行
    const exampleRow = [
      'SKU001',                    // SKU
      '示例商品标题',              // 标题
      'Black',                     // 颜色
      'Wood',                      // 材质
      '<p>商品描述内容</p>',       // 描述
      '特点1|特点2|特点3',         // 五点描述
      '关键词1|关键词2',           // 关键词
      '99.99',                     // 价格
      '79.99',                     // 优惠价
      '10.00',                     // 运费
      '109.99',                    // 平台价
      'USD',                       // 货币
      '100',                       // 库存
      'https://example.com/main.jpg',  // 主图
      'https://example.com/1.jpg|https://example.com/2.jpg',  // 附图
      '',                          // 视频
      '30',                        // 产品长
      '20',                        // 产品宽
      '15',                        // 产品高
      '10',                        // 产品重
      '35',                        // 包装长
      '25',                        // 包装宽
      '20',                        // 包装高
      '15',                        // 包装重
      'China',                     // 产地
      'normal',                    // 商品性质
      '供货商名称',                // 供货商
      'Living Room Furniture Sets', // 平台类目
    ];
    XLSX.utils.sheet_add_aoa(dataSheet, [exampleRow], { origin: 'A2' });
    
    XLSX.utils.book_append_sheet(wb, dataSheet, '商品数据');

    // 创建说明表
    const instructionData = [
      ['字段说明'],
      [''],
      ['字段名', '是否必填', '说明'],
      ...columns.map(c => [c.header.replace(' *', ''), c.required ? '是' : '否', c.description || '']),
      [''],
      ['注意事项：'],
      ['1. 带 * 的字段为必填项'],
      ['2. 多个值用 | 分隔（如五点描述、图片URLs等）'],
      ['3. 价格、尺寸等数值字段请填写纯数字'],
      ['4. 图片URL请确保可公开访问'],
      ['5. 商品性质可选值：oversized(超大件)、small(轻小件)、normal(普通件)、multiPackage(多包裹)'],
    ];
    const instructionSheet = XLSX.utils.aoa_to_sheet(instructionData);
    instructionSheet['!cols'] = [{ wch: 20 }, { wch: 10 }, { wch: 50 }];
    XLSX.utils.book_append_sheet(wb, instructionSheet, '填写说明');

    // 生成 Buffer
    const buffer = XLSX.write(wb, { type: 'buffer', bookType: 'xlsx' });
    return buffer;
  }

  /**
   * 从 Excel 文件导入商品
   */
  async importFromExcel(data: {
    fileBuffer: Buffer;
    channelId: string;
    duplicateAction?: 'skip' | 'update';
    platformCategoryId?: string;
  }) {
    const { fileBuffer, channelId, duplicateAction = 'skip', platformCategoryId: globalCategoryId } = data;

    // 验证渠道
    const channel = await this.prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) throw new NotFoundException('渠道不存在');

    // 解析 Excel
    const workbook = XLSX.read(fileBuffer, { type: 'buffer' });
    const sheetName = workbook.SheetNames[0];
    const sheet = workbook.Sheets[sheetName];
    const rows = XLSX.utils.sheet_to_json<any>(sheet, { defval: '' });

    if (rows.length === 0) {
      throw new BadRequestException('Excel 文件中没有数据');
    }

    const result = {
      total: rows.length,
      success: 0,
      failed: 0,
      skipped: 0,
      errors: [] as Array<{ row: number; sku: string; error: string }>,
    };

    // 字段映射（Excel 表头 -> 标准字段）
    const fieldMap: Record<string, string> = {
      'SKU *': 'sku',
      'SKU': 'sku',
      '商品标题 *': 'title',
      '商品标题': 'title',
      '颜色': 'color',
      '材质': 'material',
      '商品描述': 'description',
      '五点描述': 'bulletPoints',
      '搜索关键词': 'keywords',
      '价格 *': 'price',
      '价格': 'price',
      '优惠价': 'salePrice',
      '运费': 'shippingFee',
      '平台价': 'platformPrice',
      '货币': 'currency',
      '库存 *': 'stock',
      '库存': 'stock',
      '主图URL': 'mainImageUrl',
      '附图URLs': 'imageUrls',
      '视频URLs': 'videoUrls',
      '产品长(in)': 'productLength',
      '产品宽(in)': 'productWidth',
      '产品高(in)': 'productHeight',
      '产品重(lb)': 'productWeight',
      '包装长(in)': 'packageLength',
      '包装宽(in)': 'packageWidth',
      '包装高(in)': 'packageHeight',
      '包装重(lb)': 'packageWeight',
      '产地': 'placeOfOrigin',
      '商品性质': 'productType',
      '供货商': 'supplier',
      '平台类目ID': 'platformCategoryId',
    };

    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const rowNum = i + 2; // Excel 行号（从2开始，1是表头）

      try {
        // 转换字段名
        const product: Record<string, any> = {};
        for (const [excelKey, value] of Object.entries(row)) {
          const stdKey = fieldMap[excelKey] || excelKey;
          product[stdKey] = value;
        }

        // 验证必填字段
        if (!product.sku) {
          throw new Error('SKU 不能为空');
        }
        if (!product.title) {
          throw new Error('商品标题不能为空');
        }
        if (product.price === '' || product.price === undefined) {
          throw new Error('价格不能为空');
        }

        // 处理数组字段（用 | 分隔）
        const parseArray = (val: any): string[] => {
          if (!val) return [];
          if (Array.isArray(val)) return val;
          return String(val).split('|').map(s => s.trim()).filter(Boolean);
        };

        // 构建 channelAttributes（标准商品格式）
        const channelAttributes: Record<string, any> = {
          sku: String(product.sku).trim(),
          title: String(product.title).trim(),
          color: product.color || undefined,
          material: product.material || undefined,
          description: product.description || undefined,
          bulletPoints: parseArray(product.bulletPoints),
          keywords: parseArray(product.keywords),
          price: parseFloat(product.price) || 0,
          salePrice: product.salePrice ? parseFloat(product.salePrice) : undefined,
          shippingFee: product.shippingFee ? parseFloat(product.shippingFee) : undefined,
          platformPrice: product.platformPrice ? parseFloat(product.platformPrice) : undefined,
          currency: product.currency || 'USD',
          stock: parseInt(product.stock) || 0,
          mainImageUrl: product.mainImageUrl || undefined,
          imageUrls: parseArray(product.imageUrls),
          videoUrls: parseArray(product.videoUrls),
          productLength: product.productLength ? parseFloat(product.productLength) : undefined,
          productWidth: product.productWidth ? parseFloat(product.productWidth) : undefined,
          productHeight: product.productHeight ? parseFloat(product.productHeight) : undefined,
          productWeight: product.productWeight ? parseFloat(product.productWeight) : undefined,
          packageLength: product.packageLength ? parseFloat(product.packageLength) : undefined,
          packageWidth: product.packageWidth ? parseFloat(product.packageWidth) : undefined,
          packageHeight: product.packageHeight ? parseFloat(product.packageHeight) : undefined,
          packageWeight: product.packageWeight ? parseFloat(product.packageWeight) : undefined,
          placeOfOrigin: product.placeOfOrigin || undefined,
          productType: product.productType || undefined,
          supplier: product.supplier || undefined,
        };

        // 移除 undefined 值
        Object.keys(channelAttributes).forEach(key => {
          if (channelAttributes[key] === undefined) {
            delete channelAttributes[key];
          }
        });

        const sku = String(product.sku).trim();
        const categoryId = globalCategoryId || product.platformCategoryId || null;

        // 检查是否已存在
        const existing = await this.prisma.productPool.findUnique({
          where: { channelId_sku: { channelId, sku } },
        });

        if (existing) {
          if (duplicateAction === 'skip') {
            result.skipped++;
            continue;
          }
          // 更新
          await this.prisma.productPool.update({
            where: { id: existing.id },
            data: {
              title: channelAttributes.title,
              description: channelAttributes.description || null,
              mainImageUrl: channelAttributes.mainImageUrl || null,
              imageUrls: channelAttributes.imageUrls || [],
              videoUrls: channelAttributes.videoUrls || [],
              price: channelAttributes.price,
              stock: channelAttributes.stock,
              currency: channelAttributes.currency,
              channelAttributes,
              platformCategoryId: categoryId || existing.platformCategoryId,
            },
          });
        } else {
          // 创建
          await this.prisma.productPool.create({
            data: {
              channelId,
              sku,
              title: channelAttributes.title,
              description: channelAttributes.description || null,
              mainImageUrl: channelAttributes.mainImageUrl || null,
              imageUrls: channelAttributes.imageUrls || [],
              videoUrls: channelAttributes.videoUrls || [],
              price: channelAttributes.price,
              stock: channelAttributes.stock,
              currency: channelAttributes.currency,
              channelAttributes,
              platformCategoryId: categoryId,
            },
          });
        }
        result.success++;
      } catch (error: any) {
        result.failed++;
        result.errors.push({
          row: rowNum,
          sku: row['SKU *'] || row['SKU'] || row.sku || '未知',
          error: error.message,
        });
      }
    }

    return result;
  }
}
