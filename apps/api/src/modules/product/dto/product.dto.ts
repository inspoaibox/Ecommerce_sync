import { IsString, IsOptional, IsEnum, IsUUID, IsNumber, IsObject } from 'class-validator';
import { ApiProperty, ApiPropertyOptional, PartialType } from '@nestjs/swagger';
import { SyncStatus } from '@prisma/client';
import { Type } from 'class-transformer';
import { PaginationDto } from '@/common/dto/pagination.dto';

export class CreateProductDto {
  @ApiProperty()
  @IsUUID()
  syncRuleId: string;

  @ApiProperty()
  @IsUUID()
  shopId: string;

  @ApiProperty()
  @IsString()
  channelProductId: string;

  @ApiProperty()
  @IsString()
  sku: string;

  @ApiProperty()
  @IsString()
  title: string;

  @ApiProperty()
  @Type(() => Number)
  @IsNumber()
  originalPrice: number;

  @ApiProperty()
  @Type(() => Number)
  @IsNumber()
  finalPrice: number;

  @ApiProperty()
  @Type(() => Number)
  @IsNumber()
  originalStock: number;

  @ApiProperty()
  @Type(() => Number)
  @IsNumber()
  finalStock: number;

  @ApiPropertyOptional()
  @IsOptional()
  @IsString()
  currency?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsObject()
  extraFields?: Record<string, any>;
}

export class UpdateProductDto extends PartialType(CreateProductDto) {
  @ApiPropertyOptional({ enum: SyncStatus })
  @IsOptional()
  @IsEnum(SyncStatus)
  syncStatus?: SyncStatus;
}

export class ProductQueryDto extends PaginationDto {
  @ApiPropertyOptional()
  @IsOptional()
  @IsUUID()
  shopId?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsUUID()
  syncRuleId?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsString()
  sku?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsString()
  keyword?: string;

  @ApiPropertyOptional({ enum: SyncStatus })
  @IsOptional()
  @IsEnum(SyncStatus)
  syncStatus?: SyncStatus;
}

export class BatchDeleteDto {
  @ApiProperty({ type: [String] })
  @IsString({ each: true })
  ids: string[];
}

export class AssignShopDto {
  @ApiProperty({ type: [String] })
  @IsString({ each: true })
  ids: string[];

  @ApiProperty()
  @IsUUID()
  shopId: string;
}

export class SyncFromChannelDto {
  @ApiProperty()
  @IsUUID()
  channelId: string;

  @ApiProperty({ type: [Object] })
  products: any[];

  @ApiPropertyOptional()
  @IsOptional()
  @IsUUID()
  shopId?: string;
}
