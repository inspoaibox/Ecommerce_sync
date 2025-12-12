import { Module } from '@nestjs/common';
import { ImageService } from './image.service';
import { ImageTaskService } from './image-task.service';
import { ImageController } from './image.controller';
import { PrismaModule } from '@/common/prisma/prisma.module';

@Module({
  imports: [PrismaModule],
  controllers: [ImageController],
  providers: [ImageService, ImageTaskService],
  exports: [ImageService, ImageTaskService],
})
export class ImageModule {}
