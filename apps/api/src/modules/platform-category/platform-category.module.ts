import { Module } from '@nestjs/common';
import { PlatformCategoryController } from './platform-category.controller';
import { PlatformCategoryService } from './platform-category.service';
import { AttributeMappingModule } from '@/modules/attribute-mapping/attribute-mapping.module';

@Module({
  imports: [AttributeMappingModule],
  controllers: [PlatformCategoryController],
  providers: [PlatformCategoryService],
  exports: [PlatformCategoryService],
})
export class PlatformCategoryModule {}
