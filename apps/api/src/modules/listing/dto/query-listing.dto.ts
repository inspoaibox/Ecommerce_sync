import { IsOptional, IsString, IsArray, IsEnum } from 'class-validator';
import { PaginationDto } from '@/common/dto/pagination.dto';

export class QueryFromChannelDto {
  @IsString()
  channelId: string;

  @IsArray()
  @IsString({ each: true })
  skus: string[];
}

export class ListingQueryDto extends PaginationDto {
  @IsOptional()
  @IsString()
  shopId?: string;

  @IsOptional()
  @IsString()
  channelId?: string;

  @IsOptional()
  @IsString()
  keyword?: string;

  @IsOptional()
  @IsString()
  sku?: string;

  @IsOptional()
  @IsEnum(['draft', 'pending', 'submitting', 'listed', 'failed', 'updating'])
  listingStatus?: string;

  @IsOptional()
  @IsString()
  platformCategoryId?: string;
}
