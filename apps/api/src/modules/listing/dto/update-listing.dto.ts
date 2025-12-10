import { IsOptional, IsString, IsArray, IsBoolean, IsNumber } from 'class-validator';

export class UpdateListingDto {
  @IsOptional()
  @IsString()
  title?: string;

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

  @IsOptional()
  @IsNumber()
  price?: number;

  @IsOptional()
  @IsNumber()
  stock?: number;

  @IsOptional()
  @IsString()
  platformCategoryId?: string;

  @IsOptional()
  platformAttributes?: any;

  @IsOptional()
  platformSpecificData?: any;

  @IsOptional()
  attributeMapping?: any;

  @IsOptional()
  aiOptimizedData?: any;

  @IsOptional()
  @IsBoolean()
  useAiOptimized?: boolean;
}
