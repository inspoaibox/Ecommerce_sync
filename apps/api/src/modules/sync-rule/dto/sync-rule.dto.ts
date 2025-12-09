import { IsString, IsOptional, IsEnum, IsUUID, IsNumber, IsObject, Min } from 'class-validator';
import { ApiProperty, ApiPropertyOptional, PartialType } from '@nestjs/swagger';
import { SyncType, SyncRuleStatus } from '@prisma/client';
import { Type } from 'class-transformer';

export class CreateSyncRuleDto {
  @ApiProperty({ description: '规则名称' })
  @IsString()
  name: string;

  @ApiProperty({ description: '渠道ID' })
  @IsUUID()
  channelId: string;

  @ApiProperty({ description: '店铺ID' })
  @IsUUID()
  shopId: string;

  @ApiPropertyOptional({ enum: SyncType, default: 'incremental' })
  @IsOptional()
  @IsEnum(SyncType)
  syncType?: SyncType;

  @ApiPropertyOptional({ description: '同步间隔(天)', default: 1 })
  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(1)
  intervalDays?: number;

  @ApiPropertyOptional({ description: '价格倍率', default: 1.0 })
  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  priceMultiplier?: number;

  @ApiPropertyOptional({ description: '价格增减', default: 0 })
  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  priceAdjustment?: number;

  @ApiPropertyOptional({ description: '库存倍率', default: 1.0 })
  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  stockMultiplier?: number;

  @ApiPropertyOptional({ description: '库存增减', default: 0 })
  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  stockAdjustment?: number;

  @ApiPropertyOptional({ description: '字段映射' })
  @IsOptional()
  @IsObject()
  fieldMapping?: Record<string, any>;
}

export class UpdateSyncRuleDto extends PartialType(CreateSyncRuleDto) {
  @ApiPropertyOptional({ enum: SyncRuleStatus })
  @IsOptional()
  @IsEnum(SyncRuleStatus)
  status?: SyncRuleStatus;
}
