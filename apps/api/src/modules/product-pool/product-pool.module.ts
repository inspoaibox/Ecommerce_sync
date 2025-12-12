import { Module } from '@nestjs/common';
import { ProductPoolController } from './product-pool.controller';
import { ProductPoolService } from './product-pool.service';
import { AttributeMappingModule } from '@/modules/attribute-mapping/attribute-mapping.module';

@Module({
  imports: [AttributeMappingModule],
  controllers: [ProductPoolController],
  providers: [ProductPoolService],
  exports: [ProductPoolService],
})
export class ProductPoolModule {}
