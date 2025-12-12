import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { PrismaService } from '../../../common/prisma/prisma.service';

export type PromptType = 'title' | 'description' | 'bullet_points' | 'keywords' | 'general';

@Injectable()
export class PromptTemplateService {
  constructor(private prisma: PrismaService) {}

  /**
   * 获取模板列表
   */
  async list(type?: PromptType) {
    return this.prisma.promptTemplate.findMany({
      where: type ? { type } : undefined,
      orderBy: [{ isDefault: 'desc' }, { isSystem: 'desc' }, { createdAt: 'desc' }],
    });
  }

  /**
   * 获取模板详情
   */
  async findById(id: string) {
    const template = await this.prisma.promptTemplate.findUnique({ where: { id } });
    if (!template) {
      throw new NotFoundException('Prompt 模板不存在');
    }
    return template;
  }

  /**
   * 获取默认模板
   */
  async getDefaultTemplate(type: PromptType) {
    const template = await this.prisma.promptTemplate.findFirst({
      where: { type, isDefault: true, status: 'active' },
    });
    if (!template) {
      // 如果没有默认模板，返回第一个系统模板或普通模板
      return this.prisma.promptTemplate.findFirst({
        where: { type, status: 'active' },
        orderBy: [{ isSystem: 'desc' }, { createdAt: 'asc' }],
      });
    }
    return template;
  }

  /**
   * 创建模板
   */
  async create(data: {
    name: string;
    type: PromptType;
    content: string;
    description?: string;
    isDefault?: boolean;
  }) {
    if (!data.content?.trim()) {
      throw new BadRequestException('模板内容不能为空');
    }

    // 如果设置为默认，先取消同类型的其他默认
    if (data.isDefault) {
      await this.prisma.promptTemplate.updateMany({
        where: { type: data.type, isDefault: true },
        data: { isDefault: false },
      });
    }

    return this.prisma.promptTemplate.create({
      data: {
        name: data.name,
        type: data.type,
        content: data.content,
        description: data.description,
        isDefault: data.isDefault || false,
        isSystem: false,
      },
    });
  }

  /**
   * 更新模板
   */
  async update(
    id: string,
    data: {
      name?: string;
      content?: string;
      description?: string;
      status?: 'active' | 'inactive';
    },
  ) {
    await this.findById(id);

    return this.prisma.promptTemplate.update({
      where: { id },
      data,
    });
  }

  /**
   * 删除模板
   */
  async delete(id: string) {
    await this.findById(id);

    // 检查是否有关联的优化日志
    const logsCount = await this.prisma.aiOptimizationLog.count({
      where: { templateId: id },
    });

    if (logsCount > 0) {
      throw new BadRequestException(`This template has ${logsCount} optimization records and cannot be deleted`);
    }

    return this.prisma.promptTemplate.delete({ where: { id } });
  }

  /**
   * 复制模板
   */
  async duplicate(id: string) {
    const template = await this.findById(id);
    
    return this.prisma.promptTemplate.create({
      data: {
        name: `${template.name} (副本)`,
        type: template.type,
        content: template.content,
        description: template.description,
        isDefault: false,
        isSystem: false,
      },
    });
  }

  /**
   * 设置为默认模板
   */
  async setDefault(id: string) {
    const template = await this.findById(id);

    // 取消同类型的其他默认
    await this.prisma.promptTemplate.updateMany({
      where: { type: template.type, isDefault: true },
      data: { isDefault: false },
    });

    // 设置当前为默认
    return this.prisma.promptTemplate.update({
      where: { id },
      data: { isDefault: true },
    });
  }

  /**
   * 提取模板中的变量
   */
  extractVariables(content: string): string[] {
    const regex = /\{\{(\w+)\}\}/g;
    const variables: string[] = [];
    let match;
    while ((match = regex.exec(content)) !== null) {
      if (!variables.includes(match[1])) {
        variables.push(match[1]);
      }
    }
    return variables;
  }

  /**
   * 渲染模板
   */
  renderPrompt(template: string, variables: Record<string, any>): string {
    return template.replace(/\{\{(\w+)\}\}/g, (match, key) => {
      const value = variables[key];
      if (value === undefined || value === null) return '';
      if (Array.isArray(value)) return value.join('\n- ');
      return String(value);
    });
  }

  /**
   * 预览渲染结果
   */
  async preview(templateId: string, variables: Record<string, any>) {
    const template = await this.findById(templateId);
    const rendered = this.renderPrompt(template.content, variables);
    const extractedVars = this.extractVariables(template.content);
    
    return {
      template: template.content,
      variables: extractedVars,
      rendered,
    };
  }
}
