import { IsString, IsArray, IsOptional, IsEnum, ValidateNested } from 'class-validator';
import { Type } from 'class-transformer';

export class ImportProductItemDto {
  @IsString()
  sku: string;

  @IsString()
  title: string;

  @IsOptional()
  @IsString()
  description?: string;

  @IsOptional()
  @IsString()
  mainImageUrl?: string;

  @IsOptional()
  @IsArray()
  imageUrls?: string[];

  @IsOptional()
  @IsArray()
  videoUrls?: string[];

  price: number;
  stock: number;

  @IsOptional()
  @IsString()
  currency?: string;

  @IsOptional()
  channelRawData?: any;

  @IsOptional()
  channelAttributes?: any;

  @IsOptional()
  @IsString()
  platformCategoryId?: string;
}

export class ImportListingDto {
  @IsString()
  shopId: string;

  @IsString()
  channelId: string;

  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => ImportProductItemDto)
  products: ImportProductItemDto[];

  @IsOptional()
  @IsEnum(['skip', 'update'])
  duplicateAction?: 'skip' | 'update';
}

export class ImportResultDto {
  total: number;
  success: number;
  failed: number;
  skipped: number;
  errors?: { sku: string; error: string }[];
}
