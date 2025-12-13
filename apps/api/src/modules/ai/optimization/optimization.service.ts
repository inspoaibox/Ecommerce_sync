import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '../../../common/prisma/prisma.service';
import { AiModelService } from '../ai-model/ai-model.service';
import { PromptTemplateService, PromptType } from '../prompt-template/prompt-template.service';

export type OptimizeField = 'title' | 'description' | 'bulletPoints' | 'keywords';

interface OptimizeParams {
  productId: string;
  productType: 'pool' | 'listing';
  fields: OptimizeField[];
  modelId?: string;
  templateIds?: Partial<Record<OptimizeField, string>>;
}

export interface OptimizeResult {
  field: OptimizeField;
  original: string | string[] | null;
  optimized: string | string[];
  tokenUsage: number;
  logId: string;
}

@Injectable()
export class OptimizationService {
  constructor(
    private prisma: PrismaService,
    private aiModelService: AiModelService,
    private promptTemplateService: PromptTemplateService,
  ) {}

  /**
   * 获取商品数据
   */
  private async getProduct(productId: string, productType: 'pool' | 'listing') {
    if (productType === 'pool') {
      const product = await this.prisma.productPool.findUnique({ where: { id: productId } });
      if (!product) throw new NotFoundException('商品池商品不存在');
      return product;
    } else {
      const product = await this.prisma.listingProduct.findUnique({ where: { id: productId } });
      if (!product) throw new NotFoundException('刊登商品不存在');
      return product;
    }
  }

  /**
   * 字段类型映射到 Prompt 类型
   */
  private fieldToPromptType(field: OptimizeField): PromptType {
    const mapping: Record<OptimizeField, PromptType> = {
      title: 'title',
      description: 'description',
      bulletPoints: 'bullet_points',
      keywords: 'keywords',
    };
    return mapping[field];
  }

  /**
   * 格式化数组为字符串
   */
  private formatArray(arr: any[]): string {
    if (!arr || !Array.isArray(arr) || arr.length === 0) return '';
    return arr.join(', ');
  }

  /**
   * 从商品数据中提取所有文字信息，汇总发送给 AI
   * AI 需要全面了解商品信息才能生成高质量的优化内容
   */
  private extractVariables(product: any): Record<string, any> {
    const attrs = product.channelAttributes || {};
    const customAttrs = attrs.customAttributes || [];
    
    // 产品尺寸字符串
    const productDimensions = attrs.productLength 
      ? `${attrs.productLength || '-'} x ${attrs.productWidth || '-'} x ${attrs.productHeight || '-'} in, ${attrs.productWeight || '-'} lb`
      : '';
    
    // 包装尺寸字符串
    const packageDimensions = attrs.packageLength 
      ? `${attrs.packageLength || '-'} x ${attrs.packageWidth || '-'} x ${attrs.packageHeight || '-'} in, ${attrs.packageWeight || '-'} lb`
      : '';

    // 汇总所有渠道属性的文字信息（动态提取，不硬编码属性名）
    const allCustomAttributes = customAttrs
      .filter((a: any) => a.value && (typeof a.value === 'string' || Array.isArray(a.value)))
      .map((a: any) => {
        const val = Array.isArray(a.value) ? a.value.join(', ') : a.value;
        return `${a.label || a.name}: ${val}`;
      })
      .join('\n');

    // 构建完整的商品信息摘要（供 AI 全面了解商品，不含价格信息）
    const productSummary = [
      `Title: ${product.title || attrs.title || ''}`,
      `SKU: ${product.sku || attrs.sku || ''}`,
      `Color: ${attrs.color || ''}`,
      `Material: ${attrs.material || ''}`,
      `Place of Origin: ${attrs.placeOfOrigin || ''}`,
      `Supplier: ${attrs.supplier || ''}`,
      `Product Type: ${attrs.productType || ''}`,
      `Product Dimensions: ${productDimensions}`,
      `Package Dimensions: ${packageDimensions}`,
      `Description: ${product.description || attrs.description || ''}`,
      `Bullet Points: ${this.formatArray(attrs.bulletPoints)}`,
      `Keywords: ${this.formatArray(attrs.keywords)}`,
      // 附加所有渠道属性
      allCustomAttributes ? `\nCustom Attributes:\n${allCustomAttributes}` : '',
    ].filter(line => line && !line.endsWith(': ')).join('\n');

    return {
      // 基础信息
      title: product.title || attrs.title || '',
      sku: product.sku || attrs.sku || '',
      color: attrs.color || '',
      material: attrs.material || '',
      description: product.description || attrs.description || '',
      bulletPoints: this.formatArray(attrs.bulletPoints),
      keywords: this.formatArray(attrs.keywords),
      
      // 尺寸
      productDimensions,
      packageDimensions,
      
      // 汇总信息（推荐使用，AI可获取完整商品信息）
      allCustomAttributes,
      productSummary,
    };
  }

  /**
   * 获取字段原始值（使用新版标准字段结构）
   */
  private getFieldValue(product: any, field: OptimizeField): string | string[] | null {
    const attrs = product.channelAttributes || {};
    switch (field) {
      case 'title':
        return product.title || attrs.title;
      case 'description':
        return product.description || attrs.description;
      case 'bulletPoints':
        return attrs.bulletPoints || [];
      case 'keywords':
        return attrs.keywords || [];
      default:
        return null;
    }
  }

  /**
   * 优化单个字段
   */
  async optimizeField(
    product: any,
    productType: 'pool' | 'listing',
    field: OptimizeField,
    modelId?: string,
    templateId?: string,
  ): Promise<OptimizeResult> {
    const startTime = Date.now();
    
    // 获取 AI 模型
    const { adapter, model } = await this.aiModelService.getAdapter(modelId);
    
    // 获取 Prompt 模板
    const promptType = this.fieldToPromptType(field);
    let template;
    if (templateId) {
      template = await this.promptTemplateService.findById(templateId);
    } else {
      template = await this.promptTemplateService.getDefaultTemplate(promptType);
    }
    
    if (!template) {
      throw new BadRequestException(`没有可用的 ${field} 优化模板`);
    }

    // 提取变量并渲染 Prompt
    const variables = this.extractVariables(product);
    const prompt = this.promptTemplateService.renderPrompt(template.content, variables);
    const originalValue = this.getFieldValue(product, field);

    // 创建日志记录
    const log = await this.prisma.aiOptimizationLog.create({
      data: {
        productType,
        productId: product.id,
        productSku: product.sku,
        modelId: model.id,
        templateId: template.id,
        field,
        originalContent: Array.isArray(originalValue) ? JSON.stringify(originalValue) : originalValue,
        promptUsed: prompt,
        status: 'processing',
      },
    });

    try {
      // 调用 AI 生成
      const result = await adapter.generate(prompt);
      const duration = Date.now() - startTime;

      // 解析结果
      let optimizedValue: string | string[] = result.content.trim();
      
      // 如果是 bulletPoints 或 keywords，尝试解析为数组
      if (field === 'bulletPoints' || field === 'keywords') {
        let content = result.content.trim();
        
        // 移除 markdown 代码块标记
        content = content.replace(/^```(?:json)?\s*\n?/i, '').replace(/\n?```\s*$/i, '').trim();
        
        try {
          const parsed = JSON.parse(content);
          if (Array.isArray(parsed)) {
            optimizedValue = parsed;
          }
        } catch {
          // 尝试提取 JSON 数组
          const jsonMatch = content.match(/\[[\s\S]*\]/);
          if (jsonMatch) {
            try {
              const parsed = JSON.parse(jsonMatch[0]);
              if (Array.isArray(parsed)) {
                optimizedValue = parsed;
              }
            } catch {
              // 继续使用行分割
            }
          }
          
          // 如果还是解析失败，按行分割
          if (!Array.isArray(optimizedValue)) {
            optimizedValue = content
              .split('\n')
              .map(line => line.replace(/^[-•*\d.]\s*/, '').replace(/^["']|["']$/g, '').trim())
              .filter(line => line.length > 0 && !line.startsWith('[') && !line.startsWith(']'));
          }
        }
      }

      // 更新日志
      await this.prisma.aiOptimizationLog.update({
        where: { id: log.id },
        data: {
          optimizedContent: Array.isArray(optimizedValue) ? JSON.stringify(optimizedValue) : optimizedValue,
          promptTokens: result.usage.promptTokens,
          completionTokens: result.usage.completionTokens,
          totalTokens: result.usage.totalTokens,
          duration,
          status: 'completed',
        },
      });

      return {
        field,
        original: originalValue,
        optimized: optimizedValue,
        tokenUsage: result.usage.totalTokens,
        logId: log.id,
      };
    } catch (error) {
      // 更新日志为失败
      await this.prisma.aiOptimizationLog.update({
        where: { id: log.id },
        data: {
          status: 'failed',
          errorMessage: error.message,
          duration: Date.now() - startTime,
        },
      });
      throw error;
    }
  }

  /**
   * 优化商品
   */
  async optimizeProduct(params: OptimizeParams) {
    const product = await this.getProduct(params.productId, params.productType);
    const results: OptimizeResult[] = [];
    let totalTokens = 0;

    for (const field of params.fields) {
      const templateId = params.templateIds?.[field];
      const result = await this.optimizeField(
        product,
        params.productType,
        field,
        params.modelId,
        templateId,
      );
      results.push(result);
      totalTokens += result.tokenUsage;
    }

    return {
      productId: params.productId,
      productType: params.productType,
      results,
      totalTokens,
    };
  }

  /**
   * 批量优化
   */
  async batchOptimize(params: {
    products: Array<{ id: string; type: 'pool' | 'listing' }>;
    fields: OptimizeField[];
    modelId?: string;
    templateIds?: Partial<Record<OptimizeField, string>>;
  }) {
    const results = [];
    
    for (const product of params.products) {
      try {
        const result = await this.optimizeProduct({
          productId: product.id,
          productType: product.type,
          fields: params.fields,
          modelId: params.modelId,
          templateIds: params.templateIds,
        });
        results.push({ ...result, status: 'success' });
      } catch (error) {
        results.push({
          productId: product.id,
          productType: product.type,
          status: 'failed',
          error: error.message,
        });
      }
      
      // 添加延迟避免 API 限流
      await new Promise(resolve => setTimeout(resolve, 1000));
    }

    return {
      total: params.products.length,
      success: results.filter(r => r.status === 'success').length,
      failed: results.filter(r => r.status === 'failed').length,
      results,
    };
  }

  /**
   * 应用优化结果
   */
  async applyOptimization(logIds: string[]) {
    const logs = await this.prisma.aiOptimizationLog.findMany({
      where: { id: { in: logIds }, status: 'completed', isApplied: false },
    });

    if (logs.length === 0) {
      throw new BadRequestException('没有可应用的优化结果');
    }

    // 按商品分组
    const productUpdates = new Map<string, Record<string, any>>();
    
    for (const log of logs) {
      const key = `${log.productType}:${log.productId}`;
      if (!productUpdates.has(key)) {
        productUpdates.set(key, { type: log.productType, id: log.productId, data: {} });
      }
      
      const update = productUpdates.get(key)!;
      let value = log.optimizedContent;
      
      // 尝试解析 JSON
      try {
        value = JSON.parse(log.optimizedContent || '');
      } catch {}

      // 根据字段类型设置更新数据
      if (log.field === 'title') {
        update.data.title = value;
      } else if (log.field === 'description') {
        update.data.description = value;
      } else {
        // bulletPoints, keywords 等存入 aiOptimizedData
        if (!update.data.aiOptimizedData) {
          update.data.aiOptimizedData = {};
        }
        update.data.aiOptimizedData[log.field] = value;
      }
    }

    // 执行更新（使用新版标准字段结构）
    for (const [, update] of productUpdates) {
      if (update.type === 'pool') {
        const existingProduct = await this.prisma.productPool.findUnique({ where: { id: update.id } });
        const existingAttrs = (existingProduct?.channelAttributes as any) || {};
        
        // 构建更新数据
        const updateData: any = {};
        if (update.data.title) updateData.title = update.data.title;
        if (update.data.description) updateData.description = update.data.description;
        
        // 如果有 AI 优化的字段，同步到 channelAttributes
        const hasAiFields = update.data.description || update.data.aiOptimizedData?.bulletPoints || update.data.aiOptimizedData?.keywords;
        if (hasAiFields) {
          updateData.channelAttributes = {
            ...existingAttrs,
          };
          // description 同步到 channelAttributes.description（用于映射规则取值）
          if (update.data.description) {
            updateData.channelAttributes.description = update.data.description;
          }
          if (update.data.aiOptimizedData?.bulletPoints) {
            // 使用标准字段名 bulletPoints
            updateData.channelAttributes.bulletPoints = update.data.aiOptimizedData.bulletPoints;
          }
          if (update.data.aiOptimizedData?.keywords) {
            updateData.channelAttributes.keywords = update.data.aiOptimizedData.keywords;
          }
        }
        
        await this.prisma.productPool.update({
          where: { id: update.id },
          data: updateData,
        });
      } else {
        const existingProduct = await this.prisma.listingProduct.findUnique({ where: { id: update.id } });
        const existingAttrs = (existingProduct?.channelAttributes as any) || {};
        
        const updateData: any = {
          useAiOptimized: true,
        };
        if (update.data.title) updateData.title = update.data.title;
        if (update.data.description) updateData.description = update.data.description;
        
        // 如果有 AI 优化的字段，同步到 channelAttributes
        const hasAiFields = update.data.description || update.data.aiOptimizedData?.bulletPoints || update.data.aiOptimizedData?.keywords;
        if (hasAiFields) {
          updateData.channelAttributes = {
            ...existingAttrs,
          };
          // description 同步到 channelAttributes.description（用于映射规则取值）
          if (update.data.description) {
            updateData.channelAttributes.description = update.data.description;
          }
          if (update.data.aiOptimizedData?.bulletPoints) {
            updateData.channelAttributes.bulletPoints = update.data.aiOptimizedData.bulletPoints;
          }
          if (update.data.aiOptimizedData?.keywords) {
            updateData.channelAttributes.keywords = update.data.aiOptimizedData.keywords;
          }
        }
        
        // 同时保存到 aiOptimizedData 用于记录
        if (update.data.aiOptimizedData) {
          updateData.aiOptimizedData = update.data.aiOptimizedData;
        }
        
        await this.prisma.listingProduct.update({
          where: { id: update.id },
          data: updateData,
        });
      }
    }

    // 标记日志为已应用
    await this.prisma.aiOptimizationLog.updateMany({
      where: { id: { in: logIds } },
      data: { isApplied: true, appliedAt: new Date() },
    });

    return { applied: logs.length };
  }

  /**
   * 获取优化日志列表
   */
  async listLogs(query: {
    page?: number;
    pageSize?: number;
    productSku?: string;
    field?: string;
    status?: string;
    startDate?: string;
    endDate?: string;
  }) {
    const { page = 1, pageSize = 20, productSku, field, status, startDate, endDate } = query;
    
    const where: any = {};
    if (productSku) where.productSku = { contains: productSku };
    if (field) where.field = field;
    if (status) where.status = status;
    if (startDate || endDate) {
      where.createdAt = {};
      if (startDate) where.createdAt.gte = new Date(startDate);
      if (endDate) where.createdAt.lte = new Date(endDate);
    }

    const [data, total] = await Promise.all([
      this.prisma.aiOptimizationLog.findMany({
        where,
        include: { model: { select: { name: true } }, template: { select: { name: true } } },
        orderBy: { createdAt: 'desc' },
        skip: (page - 1) * pageSize,
        take: pageSize,
      }),
      this.prisma.aiOptimizationLog.count({ where }),
    ]);

    return { data, total, page, pageSize };
  }

  /**
   * 获取日志详情
   */
  async getLogDetail(id: string) {
    const log = await this.prisma.aiOptimizationLog.findUnique({
      where: { id },
      include: { model: true, template: true },
    });
    if (!log) throw new NotFoundException('日志不存在');
    return log;
  }
}
