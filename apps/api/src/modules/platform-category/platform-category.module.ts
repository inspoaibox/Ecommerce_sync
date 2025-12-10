import { Module } from '@nestjs/common';
import { PlatformCategoryController } from './platform-category.controller';
import { PlatformCategoryService } from './platform-category.service';

@Module({
  controllers: [PlatformCategoryController],
  providers: [PlatformCategoryService],
  exports: [PlatformCategoryService],
})
export class PlatformCategoryModule {}
