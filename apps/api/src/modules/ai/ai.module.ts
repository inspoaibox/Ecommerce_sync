import { Module, OnModuleInit } from '@nestjs/common';
import { PrismaModule } from '../../common/prisma/prisma.module';
import { PrismaService } from '../../common/prisma/prisma.service';
import { AiModelService } from './ai-model/ai-model.service';
import { AiModelController } from './ai-model/ai-model.controller';
import { PromptTemplateService } from './prompt-template/prompt-template.service';
import { PromptTemplateController } from './prompt-template/prompt-template.controller';
import { OptimizationService } from './optimization/optimization.service';
import { OptimizationController } from './optimization/optimization.controller';
import { initDefaultTemplates } from './prompt-template/default-templates';

@Module({
  imports: [PrismaModule],
  controllers: [
    AiModelController,
    PromptTemplateController,
    OptimizationController,
  ],
  providers: [
    AiModelService,
    PromptTemplateService,
    OptimizationService,
  ],
  exports: [
    AiModelService,
    PromptTemplateService,
    OptimizationService,
  ],
})
export class AiModule implements OnModuleInit {
  constructor(private prisma: PrismaService) {}

  async onModuleInit() {
    // 初始化预设模板
    await initDefaultTemplates(this.prisma);
  }
}
