import { Module } from '@nestjs/common';
import { AttributeMappingController } from './attribute-mapping.controller';
import { AttributeResolverService } from './attribute-resolver.service';
import { UpcModule } from '@/modules/upc/upc.module';

@Module({
  imports: [UpcModule],
  controllers: [AttributeMappingController],
  providers: [AttributeResolverService],
  exports: [AttributeResolverService],
})
export class AttributeMappingModule {}
